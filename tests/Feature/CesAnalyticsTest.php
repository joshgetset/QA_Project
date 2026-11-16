<?php

namespace Tests\Feature;

use App\Exceptions\CesWarehouseUnavailable;
use App\Models\User;
use App\Services\CesEnrollmentAnalytics;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CesAnalyticsTest extends TestCase
{
    public function test_analytics_endpoint_requires_authentication(): void
    {
        $this->getJson(route('qams.ces.analytics'))->assertUnauthorized();
    }

    public function test_analytics_endpoint_passes_filters_to_the_analytics_service(): void
    {
        $payload = [
            'summary' => [],
            'demographics' => [],
            'filters' => [],
            'selected' => [],
            'campuses' => [],
            'partial' => false,
            'updated_at' => '2026-11-12T00:00:00+00:00',
        ];
        $analytics = Mockery::mock(CesEnrollmentAnalytics::class);
        $analytics->shouldReceive('get')
            ->once()
            ->with([
                'campus' => 'sogod',
                'college' => 'Education',
                'program' => 'Bachelor of Education',
                'year' => '2026',
                'semester' => '1',
            ])
            ->andReturn($payload);
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);

        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', [
                'campus' => 'sogod',
                'college' => 'Education',
                'program' => 'Bachelor of Education',
                'year' => '2026',
                'semester' => '1',
            ]))
            ->assertOk()
            ->assertExactJson($payload);
    }

    public function test_analytics_endpoint_rejects_unknown_campuses_and_semesters(): void
    {
        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', ['campus' => 'unknown']))
            ->assertUnprocessable();

        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', ['semester' => '3']))
            ->assertUnprocessable();
    }

    public function test_endpoint_reports_missing_warehouse_as_service_unavailable(): void
    {
        $analytics = Mockery::mock(CesEnrollmentAnalytics::class);
        $analytics->shouldReceive('get')
            ->once()
            ->andThrow(new CesWarehouseUnavailable('Warehouse has not been synced.'));
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);

        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics'))
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Warehouse has not been synced.');
    }

    public function test_analytics_aggregates_enrollment_and_demographics_from_warehouse_facts(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Warehouse analytics fixtures require the SQLite PDO driver.');
        }

        config([
            'database.connections.qams_warehouse' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'ces.warehouse_connection' => 'qams_warehouse',
            'ces.campuses' => [
                'sogod' => ['name' => 'Sogod', 'database' => 'enrolment_sogod'],
                'maasin' => ['name' => 'Maasin', 'database' => 'enrolment_maasin'],
            ],
        ]);
        DB::purge('qams_warehouse');
        $schema = Schema::connection('qams_warehouse');

        $schema->create('ces_warehouse_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
        });
        $schema->create('ces_warehouse_campus_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('campus_id');
            $table->string('status');
        });
        $schema->create('dim_campus', function (Blueprint $table) {
            $table->id();
            $table->string('campus_code');
            $table->string('campus_name');
            $table->string('source_database');
            $table->boolean('income_available')->default(false);
        });
        $schema->create('dim_academic_period', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year');
            $table->string('semester');
        });
        $schema->create('ces_campus_academic_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('academic_period_id');
            $table->boolean('has_grade_table');
            $table->boolean('has_stage_table');
            $table->boolean('has_graduation_period');
        });
        $schema->create('dim_ces_program', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id');
            $table->string('college_name')->nullable();
            $table->string('course_title')->nullable();
        });
        $schema->create('dim_ces_student', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id');
            $table->string('sex')->nullable();
            $table->string('nationality')->nullable();
            $table->string('disability')->nullable();
            $table->boolean('is_four_ps_beneficiary');
            $table->boolean('is_first_generation');
            $table->decimal('annual_income', 14, 2)->nullable();
        });
        $schema->create('dim_ces_scholarship', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name')->nullable();
        });
        $schema->create('fact_ces_enrollment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('academic_period_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('program_id')->nullable();
        });
        $schema->create('fact_ces_registration', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('academic_period_id')->nullable();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedBigInteger('scholarship_id')->nullable();
            $table->string('source_school_year')->nullable();
            $table->string('source_student_status')->nullable();
            $table->string('source_scholarship_id')->nullable();
            $table->boolean('is_finalized');
            $table->string('tes_value')->nullable();
            $table->string('unifast_paid_value')->nullable();
        });
        $schema->create('fact_ces_graduation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id');
            $table->unsignedBigInteger('academic_period_id')->nullable();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('program_id')->nullable();
            $table->boolean('is_graduation_applicant');
        });

        $warehouse = DB::connection('qams_warehouse');
        $warehouse->table('ces_warehouse_sync_runs')->insert([
            'id' => 1,
            'status' => 'succeeded',
            'completed_at' => '2026-11-16 00:00:00',
        ]);
        $warehouse->table('dim_campus')->insert([
            ['id' => 1, 'campus_code' => 'sogod', 'campus_name' => 'Sogod', 'source_database' => 'enrolment_sogod', 'income_available' => true],
            ['id' => 2, 'campus_code' => 'maasin', 'campus_name' => 'Maasin', 'source_database' => 'enrolment_maasin', 'income_available' => false],
        ]);
        $warehouse->table('ces_warehouse_campus_syncs')->insert([
            ['run_id' => 1, 'campus_id' => 1, 'status' => 'succeeded'],
            ['run_id' => 1, 'campus_id' => 2, 'status' => 'succeeded'],
        ]);
        $warehouse->table('dim_academic_period')->insert([
            ['id' => 10, 'academic_year' => '2026', 'semester' => '1'],
            ['id' => 11, 'academic_year' => '2026', 'semester' => '2'],
            ['id' => 12, 'academic_year' => '2025', 'semester' => '2'],
        ]);
        $warehouse->table('ces_campus_academic_periods')->insert([
            ['campus_id' => 1, 'academic_period_id' => 10, 'has_grade_table' => true, 'has_stage_table' => true, 'has_graduation_period' => true],
            ['campus_id' => 1, 'academic_period_id' => 12, 'has_grade_table' => true, 'has_stage_table' => false, 'has_graduation_period' => true],
            ['campus_id' => 2, 'academic_period_id' => 10, 'has_grade_table' => true, 'has_stage_table' => false, 'has_graduation_period' => false],
        ]);
        $warehouse->table('dim_ces_program')->insert([
            ['id' => 1, 'campus_id' => 1, 'college_name' => 'Education', 'course_title' => 'Bachelor of Education'],
            ['id' => 2, 'campus_id' => 2, 'college_name' => 'Education', 'course_title' => 'Bachelor of Education'],
        ]);
        $warehouse->table('dim_ces_student')->insert([
            ['id' => 1, 'campus_id' => 1, 'sex' => 'F', 'nationality' => 'Korean', 'disability' => '', 'is_four_ps_beneficiary' => true, 'is_first_generation' => true, 'annual_income' => 1],
            ['id' => 2, 'campus_id' => 1, 'sex' => 'Male', 'nationality' => 'Filipino', 'disability' => 'Yes', 'is_four_ps_beneficiary' => false, 'is_first_generation' => false, 'annual_income' => null],
            ['id' => 3, 'campus_id' => 2, 'sex' => 'F', 'nationality' => 'Philippines', 'disability' => '', 'is_four_ps_beneficiary' => false, 'is_first_generation' => false, 'annual_income' => null],
        ]);
        $warehouse->table('dim_ces_scholarship')->insert([
            ['id' => 101, 'canonical_name' => 'CHEDMerit'],
            ['id' => 102, 'canonical_name' => 'TES'],
        ]);
        $warehouse->table('fact_ces_enrollment')->insert([
            ['campus_id' => 1, 'academic_period_id' => 10, 'student_id' => 1, 'program_id' => 1],
            ['campus_id' => 1, 'academic_period_id' => 10, 'student_id' => 2, 'program_id' => 1],
            ['campus_id' => 2, 'academic_period_id' => 10, 'student_id' => 3, 'program_id' => 2],
        ]);
        $warehouse->table('fact_ces_registration')->insert([
            ['campus_id' => 1, 'academic_period_id' => 10, 'student_id' => 1, 'program_id' => 1, 'scholarship_id' => 101, 'source_school_year' => '2026', 'source_student_status' => 'New', 'source_scholarship_id' => 'SCH-1', 'is_finalized' => true, 'tes_value' => '0', 'unifast_paid_value' => '0'],
            ['campus_id' => 1, 'academic_period_id' => 10, 'student_id' => 2, 'program_id' => 1, 'scholarship_id' => 102, 'source_school_year' => '2026', 'source_student_status' => 'Continuing', 'source_scholarship_id' => 'SCH-2', 'is_finalized' => true, 'tes_value' => '0', 'unifast_paid_value' => '0'],
        ]);
        $warehouse->table('fact_ces_graduation')->insert([
            ['campus_id' => 1, 'academic_period_id' => 10, 'student_id' => 1, 'program_id' => 1, 'is_graduation_applicant' => true],
            ['campus_id' => 1, 'academic_period_id' => 11, 'student_id' => 1, 'program_id' => 1, 'is_graduation_applicant' => false],
        ]);

        $payload = (new CesEnrollmentAnalytics)->get([
            'campus' => 'sogod',
            'college' => 'Education',
            'program' => 'Bachelor of Education',
            'year' => '2026',
            'semester' => '1',
        ]);
        $summary = collect($payload['summary'])->keyBy('key');
        $demographics = collect($payload['demographics'])->keyBy('key');
        $scholarships = collect($payload['scholarships'])->keyBy('name');

        $this->assertSame(2, $summary['total-enrolled-students']['value']);
        $this->assertSame(1, $summary['graduating-enrollees']['value']);
        $this->assertSame(1, $summary['already-graduated-students']['value']);
        $this->assertSame(1, $summary['new-freshman']['value']);
        $this->assertSame(1, $demographics['international-students']['total']);
        $this->assertSame(1, $demographics['4ps-beneficiaries']['female']);
        $this->assertSame(1, $demographics['persons-with-disabilities']['male']);
        $this->assertSame(1, $demographics['first-generation-learners']['female']);
        $this->assertSame(2, $demographics['financial-aid-recipients']['total']);
        $this->assertSame(1, $demographics['household-income']['total']);
        $this->assertSame(1, $scholarships['CHEDMerit']['total']);
        $this->assertSame(1, $scholarships['TES']['total']);
        $this->assertFalse($payload['partial']);
        $this->assertSame('2026', $payload['selected']['year']);
    }
}
