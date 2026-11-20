<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GadWarehouseAnalytics;
use App\Services\GadWarehouseSynchronizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GadWarehouseTest extends TestCase
{
    public function test_gad_sync_publishes_proposal_facts_and_preserves_the_snapshot_if_the_next_export_fails(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Warehouse fixtures require PDO SQLite.');
        }

        $url = 'https://gad.example.test/api/source/gad/snapshot';
        config([
            'database.connections.qams_warehouse' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
            'source_apis.gad.url' => $url,
            'source_apis.gad.api_key' => 'test-gad-key',
        ]);
        $migration = require database_path('migrations/2026_11_20_000000_create_gad_warehouse_tables.php');
        $migration->up();
        $scoresMigration = require database_path('migrations/2026_11_20_000001_add_hgdg_score_to_gad_proposals.php');
        $scoresMigration->up();
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', 'Asia/Manila'));
        $this->getJson(route('source.api.gad.snapshot'))->assertUnauthorized();

        $proposal = [
            'id' => 1, 'reference' => 'SG-2026-001', 'title' => 'Campus workshop for local communities',
            'proposed_on' => '2026-11-02', 'submitted_at' => '2026-10-01 10:00:00',
            'budget' => '5000.00', 'location' => 'Main Campus Hall', 'attribution' => '2500.00',
            'department_id' => 10, 'department_name' => 'Quality Office', 'department_lookup' => 'Quality Office',
            'campus_id' => 1, 'status' => 'approved', 'progress_status' => 'completed',
            'hgdg_score' => 15,
            'schedule_type' => 'single', 'schedules' => '{"single":{"start_date":"2026-11-02","end_date":"2026-11-03"}}',
        ];
        $approvedReport = [
            'id' => 1, 'proposal_id' => 2, 'status' => 'submitted', 'review_status' => 'approved',
            'submitted_at' => '2026-11-08 11:00:00', 'ended_on' => '2026-11-03', 'location' => 'Main Campus Hall',
            'participants' => 15, 'approved_budget' => '5000.00', 'actual_budget_use' => '4200.00', 'attribution' => '2100.00',
        ];
        Http::fake([$url => Http::sequence()->push([
            'version' => 1,
            'proposals' => [
                $proposal,
                [...$proposal, 'id' => 2, 'reference' => 'SG-2026-002', 'title' => 'Completed initiative', 'hgdg_score' => 20.02],
                [...$proposal, 'id' => 3, 'reference' => 'SG-2026-003', 'status' => 'pending', 'progress_status' => 'in_progress', 'hgdg_score' => 20],
            ],
            'beneficiaries' => [
                ['id' => 1, 'proposal_id' => 1, 'beneficiary_id' => 1, 'name' => 'Employees', 'total' => 10],
                ['id' => 2, 'proposal_id' => 1, 'beneficiary_id' => 2, 'name' => 'Students', 'total' => 25],
            ],
            'accomplishments' => [
                $approvedReport,
                [...$approvedReport, 'id' => 2, 'proposal_id' => 1, 'review_status' => 'for_review', 'location' => 'Pending report venue'],
            ],
            'employee_logins' => [
                ['employee_id' => '101', 'login_date' => '2026-10-07', 'login_count' => 2],
                ['employee_id' => '101', 'login_date' => '2026-10-10', 'login_count' => 1],
            ],
        ])->push(['version' => 1, 'proposals' => []])]);

        $sync = app(GadWarehouseSynchronizer::class);
        $this->assertSame(3, $sync->sync()['proposal_count']);
        $analytics = app(GadWarehouseAnalytics::class);
        $this->assertSame('1', $analytics->overview()['metrics'][0]['value']);
        $cohort = $analytics->get(['year' => 2026, 'month' => 10, 'campus' => 1, 'department' => 10]);
        $this->assertSame(['submitted' => 2, 'approved' => 2, 'ongoing' => 0, 'overdue' => 1, 'completed' => 1], $cohort['summary']);
        $this->assertSame(35, (int) $cohort['beneficiaryTotal']);
        $this->assertSame(1, $cohort['genderResponsiveTotal']);
        $this->assertSame(10, $cohort['proposalRows']->perPage());
        $this->assertSame(3, (int) DB::connection('qams_warehouse')->table('fact_gad_employee_login')->sum('login_count'));
        $this->assertSame(1, $cohort['accomplishments']->total());
        $this->assertSame(10, $cohort['accomplishments']->perPage());
        $this->assertSame(2, $cohort['accomplishmentTotal']);

        $this->withoutVite()->actingAs(User::factory()->make())
            ->get(route('qams.system', ['system' => 'gender-and-development-system', 'month' => 10, 'campus' => 1, 'department' => 10]))
            ->assertOk()
            ->assertSee('Track submissions, approvals, implementation schedules, and completion.')
            ->assertDontSee('Proposal Submission Cohort')
            ->assertSeeHtml('data-gad-monthly-chart="submitted approved"')
            ->assertSeeHtml('data-gad-monthly-chart="ongoing overdue completed"')
            ->assertSee('View monthly chart data')
            ->assertDontSee('Gender and development programs and activities')
            ->assertSeeInOrder(['Total Gender Responsive Proposals', 'Planned proposal beneficiaries', 'Accomplishment reports', 'Submitted', 'Monthly Proposal Cohort'])
            ->assertDontSee('Employees with recorded privileged logins')
            ->assertDontSee('Main Campus Hall')
            ->assertSee('Sogod Campus')
            ->assertSee('SG-2026-001')
            ->assertSeeHtml('class="qams-gad-proposal-title" title="Campus workshop for local communities">Campus workshop for local communities</span>')
            ->assertDontSee('SG-2026-003')
            ->assertSee('Accomplishment Reports')
            ->assertDontSee('Pending report venue');

        try {
            $sync->sync();
            $this->fail('An empty source snapshot must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('empty proposal snapshot', $exception->getMessage());
        }
        $this->assertSame('1', $analytics->overview()['metrics'][0]['value']);
    }
}
