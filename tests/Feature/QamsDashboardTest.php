<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CesEnrollmentAnalytics;
use App\Services\QamsSystemData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class QamsDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        foreach (array_keys(config('qams.systems', [])) as $system) {
            config([
                "qams.systems.{$system}.endpoint" => null,
                "qams.systems.{$system}.token" => null,
            ]);
        }
    }

    public function test_dashboard_renders_in_the_personal_views_folder_with_sample_data(): void
    {
        $response = $this->actingAs(User::factory()->make())
            ->get(route('dashboard-analytics'));

        $response->assertOk()
            ->assertViewIs('personal.dashboard')
            ->assertSee('Executive Dashboard')
            ->assertSee('Student Information System')
            ->assertSee('Current school year enrolled students')
            ->assertSeeHtml('data-summary-value-for="system-17">—</strong>')
            ->assertSee('Designation Management System')
            ->assertSee(route('qams.system', ['system' => 'student-information-system']))
            ->assertSee('qams-system-card-link')
            ->assertDontSee('qams-system-open')
            ->assertSee('dashboard/systems/designation-management-system')
            ->assertSee('Executive Dashboard')
            ->assertSee('row-cols-xxl-4')
            ->assertSee('qams-system-heading')
            ->assertSee('Systems monitored')
            ->assertSee('University personnel')
            ->assertSee('Academic Services')
            ->assertSee('People & HR')
            ->assertSee('Finance')
            ->assertSee('Health')
            ->assertSee('Research')
            ->assertSee('data-qams-system-search')
            ->assertSeeHtml('data-view-mode="grid"')
            ->assertSee('data-system-sort')
            ->assertDontSee('Systems Overview')
            ->assertDontSee('data-source-badge')
            ->assertSeeHtml('title="Sample data"')
            ->assertDontSee('Active programs')
            ->assertDontSee('Example values for layout preview')
            ->assertDontSee('Sample values · No API connected')
            ->assertSee('Southern_Leyte_State_University logo.png');

        $systemNames = array_column(config('qams.systems'), 'name');
        $this->assertCount(16, $systemNames);
        $categorizedSystemKeys = array_merge(...array_column(config('qams.categories'), 'systems'));
        $this->assertEqualsCanonicalizing(array_keys(config('qams.systems')), $categorizedSystemKeys);
        $this->assertCount(16, config('qams.short_names'));
        $this->assertSame([
            'Comprehensive Enrolment System',
            'Human Resource Management System',
            'Student Information System',
            'Learning Management System',
            'Online Admission System',
            'Online Clearance System',
            'Procurement Management System',
            'Policy Management System',
            'Clinic Management System',
            'Online Payslip Access System',
            'Research, Innovation, and Extension Services Management System',
            'Automated Faculty Evaluation System',
            'Gender and Development System',
            'SLSU Web-based Integrated Payment Engine',
            'Proximity Attendance Scanning System',
            'Designation Management System',
        ], $systemNames);
        $systemMenu = json_decode(
            file_get_contents(resource_path('menu/verticalMenu.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        )['menu'][0]['submenu'];

        $this->assertSame([
            'CES',
            'HRMIS',
            'SIS',
            'LMS',
            'ODS',
            'OCMS',
            'PMIS',
            'PoLMIS',
            'CMIS',
            'OPAS',
            'RIESMIS',
            'AFES',
            'GAD DB',
            'SWIPE',
            'PASS',
            'DMIS',
        ], array_column($systemMenu, 'name'));

        foreach ($systemMenu as $menuItem) {
            $system = config("qams.systems.{$menuItem['systemKey']}");

            $this->assertSame(
                'dashboard/systems/'.Str::slug($system['name']),
                $menuItem['url']
            );
        }
    }

    public function test_dashboard_page_does_not_wait_for_a_configured_system_api(): void
    {
        config(['qams.systems.system-17.endpoint' => 'https://ces.example.test/api/summary']);
        Http::fake();

        $this->actingAs(User::factory()->make())
            ->get(route('dashboard-analytics'))
            ->assertOk()
            ->assertSeeHtml('data-summary-value-for="system-17">—</strong>');

        Http::assertNothingSent();
    }

    public function test_local_ces_summary_uses_the_service_without_a_loopback_http_request(): void
    {
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'qams.systems.system-17.endpoint' => 'http://127.0.0.1:8000/api/ces/enrollment-summary',
        ]);
        $analytics = \Mockery::mock(CesEnrollmentAnalytics::class);
        $analytics->shouldReceive('get')
            ->once()
            ->andReturn([
                'selected' => ['year' => '2026'],
                'partial' => false,
                'updated_at' => '2026-11-12T00:00:00+00:00',
                'summary' => [
                    ['key' => 'total-enrolled-students', 'value' => 12394],
                ],
            ]);
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);
        Http::fake();

        $systems = app(QamsSystemData::class)->all();
        $ces = collect($systems)->firstWhere('key', 'system-17');

        $this->assertSame('live', $ces['source']);
        $this->assertSame([
            ['label' => 'Total Enrolled Students (AY 2026-2027)', 'value' => '12,394'],
        ], $ces['metrics']);
        Http::assertNothingSent();
    }

    public function test_dashboard_refresh_uses_only_the_local_ces_service_when_no_other_api_is_configured(): void
    {
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'qams.systems.system-17.endpoint' => 'http://127.0.0.1:8000/api/ces/enrollment-summary',
        ]);
        $analytics = \Mockery::mock(CesEnrollmentAnalytics::class);
        $analytics->shouldReceive('get')
            ->once()
            ->andReturn([
                'selected' => ['year' => '2026'],
                'partial' => false,
                'updated_at' => '2026-11-12T00:00:00+00:00',
                'summary' => [
                    ['key' => 'total-enrolled-students', 'value' => 12394],
                ],
            ]);
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);
        Http::fake();

        $response = $this->actingAs(User::factory()->make())
            ->getJson(route('qams.systems'));

        $response->assertOk()
            ->assertJsonPath('systems.0.key', 'system-17')
            ->assertJsonPath('systems.0.source', 'live')
            ->assertJsonPath('systems.0.metrics.0.label', 'Total Enrolled Students (AY 2026-2027)')
            ->assertJsonPath('systems.1.source', 'demo');
        Http::assertNothingSent();
    }

    public function test_local_ces_does_not_report_a_partial_campus_total_as_current_enrollment(): void
    {
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'qams.systems.system-17.endpoint' => 'http://127.0.0.1:8000/api/ces/enrollment-summary',
        ]);
        $analytics = \Mockery::mock(CesEnrollmentAnalytics::class);
        $analytics->shouldReceive('get')
            ->once()
            ->andReturn([
                'selected' => ['year' => '2026'],
                'partial' => true,
                'updated_at' => '2026-11-12T00:00:00+00:00',
                'summary' => [
                    ['key' => 'total-enrolled-students', 'value' => 1000],
                ],
            ]);
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);
        Http::fake();

        $ces = collect(app(QamsSystemData::class)->all())->firstWhere('key', 'system-17');

        $this->assertSame('unavailable', $ces['source']);
        $this->assertSame([], $ces['metrics']);
        Http::assertNothingSent();
    }

    public function test_dashboard_enrollment_summary_uses_the_named_enrollment_metric(): void
    {
        config(['qams.systems.system-17.endpoint' => 'https://ces.example.test/api/summary']);
        Cache::put('qams.system.system-17.grades20261', [
            'source' => 'live',
            'source_label' => 'Live data',
            'summary' => 'CES metrics',
            'updated_at' => now()->toIso8601String(),
            'metrics' => [
                ['label' => 'Sogod student records', 'value' => '12,815'],
                ['label' => 'Total Enrolled Students (AY 2026-2027)', 'value' => '12,394'],
            ],
        ], now()->addMinute());

        $this->actingAs(User::factory()->make())
            ->get(route('dashboard-analytics'))
            ->assertOk()
            ->assertSeeHtml('data-summary-value-for="system-17">12,394</strong>');
    }

    public function test_dashboard_uses_last_successful_api_data_after_the_fresh_cache_expires(): void
    {
        config(['qams.systems.system-17.endpoint' => 'https://ces.example.test/api/summary']);
        Cache::put('qams.system.last-known-good.system-17.grades20261', [
            'source' => 'live',
            'source_label' => 'Live data',
            'summary' => 'CES metrics',
            'updated_at' => now()->subMinutes(5)->toIso8601String(),
            'metrics' => [
                ['label' => 'Total Enrolled Students (AY 2026-2027)', 'value' => '12,394'],
            ],
        ], now()->addDay());

        $this->actingAs(User::factory()->make())
            ->get(route('dashboard-analytics'))
            ->assertOk()
            ->assertSeeHtml('data-summary-value-for="system-17">12,394</strong>')
            ->assertSee('Cached data');
    }

    public function test_dashboard_does_not_display_student_record_counts_as_current_year_enrollment(): void
    {
        config(['qams.systems.system-17.endpoint' => 'https://ces.example.test/api/summary']);
        Cache::put('qams.system.system-17.grades20261', [
            'source' => 'live',
            'source_label' => 'Live data',
            'summary' => 'CES metrics',
            'updated_at' => now()->toIso8601String(),
            'metrics' => [
                ['label' => 'Total student records', 'value' => '12,815'],
            ],
        ], now()->addMinute());

        $this->actingAs(User::factory()->make())
            ->get(route('dashboard-analytics'))
            ->assertOk()
            ->assertSeeHtml('data-summary-value-for="system-17">—</strong>')
            ->assertDontSee('12,815')
            ->assertDontSee('Total student records');
    }

    public function test_clicking_a_system_card_opens_its_total_metrics_page(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('qams.system', ['system' => 'student-information-system']))
            ->assertOk()
            ->assertViewIs('personal.system-metrics')
            ->assertSee('Total Metrics')
            ->assertSee('Student records')
            ->assertSee('Active programs')
            ->assertSee('Systems Overview');
    }

    public function test_ces_metrics_page_shows_filtered_enrollment_and_gender_analytics(): void
    {
        $response = $this->actingAs(User::factory()->make())
            ->get(route('qams.system', ['system' => 'comprehensive-enrolment-system']));

        $response->assertOk()
            ->assertViewIs('personal.system-metrics')
            ->assertDontSee('Enrollment Analytics')
            ->assertDontSee('Explore student metrics by campus, college, and program.')
            ->assertDontSee('These preview figures are illustrative and are not live CES records.')
            ->assertSee('Campus')
            ->assertSee('College')
            ->assertSee('Program')
            ->assertSee('Academic Year')
            ->assertSee('Latest Academic Year')
            ->assertDontSee('Loading live CES data')
            ->assertSee('data-ces-analytics-url')
            ->assertSee('data-ces-filter="year"', false)
            ->assertSee('Graduating Enrollees')
            ->assertSee('New Freshman')
            ->assertSee('Total Enrolled Students')
            ->assertSee('International Students')
            ->assertSee('4P&#039;s Beneficiaries', false)
            ->assertSee('Children of OFWs')
            ->assertSee('Persons Deprived of Liberty')
            ->assertSee('With Special Needs')
            ->assertSee('Working Students')
            ->assertSee('Indigenous People')
            ->assertSee('Solo Parents')
            ->assertSee('Persons with Disabilities')
            ->assertSee('First Generation Learners')
            ->assertDontSee('Largest cohort')
            ->assertDontSee('Share of tracked students')
            ->assertDontSee('Illustrative sample data')
            ->assertSee('data-ces-view="cards"', false)
            ->assertSee('data-ces-view="chart"', false)
            ->assertSee('data-ces-demographic-overview', false)
            ->assertSee('data-ces-demographic-chart', false)
            ->assertSee('ri ri-global-line', false)
            ->assertSee('ri ri-hand-heart-line', false)
            ->assertSee('ri ri-plane-line', false)
            ->assertSee('ri ri-wheelchair-line', false)
            ->assertSee('—')
            ->assertDontSee('qams-ces-summary-change', false)
            ->assertDontSee('qams-ces-female-share', false);

        self::assertLessThan(
            strpos($response->getContent(), 'data-ces-summary="graduating-enrollees"'),
            strpos($response->getContent(), 'data-ces-summary="total-enrolled-students"')
        );
    }

    public function test_ces_metrics_page_does_not_fetch_the_redundant_system_summary_api(): void
    {
        config(['qams.systems.system-17.endpoint' => 'https://ces.example.test/api/summary']);
        Http::fake();

        $this->actingAs(User::factory()->make())
            ->get(route('qams.system', ['system' => 'comprehensive-enrolment-system']))
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_unknown_system_metrics_page_returns_not_found(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('qams.system', ['system' => 'unknown']))
            ->assertNotFound();
    }

    public function test_legacy_system_key_url_redirects_to_the_system_name_url(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('qams.system', ['system' => 'system-17']))
            ->assertRedirect(route('qams.system', ['system' => 'comprehensive-enrolment-system']));
    }

    public function test_systems_api_returns_normalized_data_from_a_configured_system(): void
    {
        config([
            'qams.systems.student-information.endpoint' => 'https://sis.example.test/api/summary',
            'qams.systems.student-information.token' => 'test-token',
        ]);
        Http::fake([
            'https://sis.example.test/api/summary' => Http::response([
                'summary' => 'Enrollment data received.',
                'metrics' => [
                    ['label' => 'Enrolled students', 'value' => 1300],
                ],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->make())
            ->getJson(route('qams.systems'));

        $response->assertOk()
            ->assertJsonPath('systems.2.source', 'live')
            ->assertJsonPath('systems.2.metrics.0.label', 'Enrolled students')
            ->assertJsonPath('systems.2.metrics.0.value', '1300');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_systems_api_refreshes_multiple_configured_apis_and_returns_each_result(): void
    {
        config([
            'qams.systems.human-resources.endpoint' => 'https://hr.example.test/api/summary',
            'qams.systems.student-information.endpoint' => 'https://sis.example.test/api/summary',
        ]);
        Http::fake([
            'https://hr.example.test/api/summary' => Http::response([
                'metrics' => [['label' => 'Personnel', 'value' => 1124]],
            ]),
            'https://sis.example.test/api/summary' => Http::response([
                'metrics' => [['label' => 'Students', 'value' => 8420]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->make())
            ->getJson(route('qams.systems'));

        $response->assertOk()
            ->assertJsonPath('systems.1.source', 'live')
            ->assertJsonPath('systems.1.metrics.0.value', '1124')
            ->assertJsonPath('systems.2.source', 'live')
            ->assertJsonPath('systems.2.metrics.0.value', '8420');

        Http::assertSentCount(2);
    }

    public function test_systems_api_marks_a_connection_failure_unavailable_without_failing_the_dashboard(): void
    {
        config(['qams.systems.student-information.endpoint' => 'https://sis.example.test/api/summary']);
        Http::fake([
            'https://sis.example.test/api/summary' => fn () => throw new ConnectionException('Connection refused.'),
        ]);

        $response = $this->actingAs(User::factory()->make())
            ->getJson(route('qams.systems'));

        $response->assertOk()
            ->assertJsonPath('systems.2.source', 'unavailable')
            ->assertJsonPath('systems.2.metrics', []);
    }

    public function test_configured_system_api_failure_is_reported_as_unavailable(): void
    {
        config(['qams.systems.student-information.endpoint' => 'https://sis.example.test/api/summary']);
        Http::fake([
            'https://sis.example.test/api/summary' => Http::response([], 503),
        ]);

        $response = $this->actingAs(User::factory()->make())
            ->getJson(route('qams.systems'));

        $response->assertOk()
            ->assertJsonPath('systems.2.source', 'unavailable')
            ->assertJsonPath('systems.2.metrics', []);
    }

    public function test_invalid_configured_api_url_is_reported_as_unavailable(): void
    {
        config(['qams.systems.student-information.endpoint' => 'not a URL']);

        $response = $this->actingAs(User::factory()->make())
            ->getJson(route('qams.systems'));

        $response->assertOk()
            ->assertJsonPath('systems.2.source', 'unavailable')
            ->assertJsonPath('systems.2.metrics', []);
        Http::assertNothingSent();
    }

    public function test_systems_api_requires_authentication(): void
    {
        $this->getJson(route('qams.systems'))->assertUnauthorized();
    }

    public function test_navbar_shows_national_pillar_by_default(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('dashboard-analytics'))
            ->assertOk()
            ->assertSee('Pillar:')
            ->assertSee('National')
            ->assertSee('International')
            ->assertSee('Institutional')
            ->assertSee('qams-pillar-selector')
            ->assertSeeHtml('id="qams-pillar-select"')
            ->assertSeeHtml('data-confirm-pillar')
            ->assertSeeHtml('data-current-value="National"')
            ->assertSeeHtml('data-confirm-logout')
            ->assertSeeHtml('data-logout-button')
            ->assertSee('qams-navbar-search-input')
            ->assertSee('qams-user-avatar')
            ->assertSee('menu-active-route')
            ->assertSee('menu-item active')
            ->assertDontSee('menu-item active open')
            ->assertSeeHtml('href="javascript:void(0);" class="menu-link menu-toggle menu-active-route"')
            ->assertDontSee('assets/img/avatars/1.png');
    }

    public function test_executive_dashboard_menu_item_navigates_to_dashboard_from_other_pages(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('pages-account-settings-account'))
            ->assertOk()
            ->assertSeeHtml('href="'.route('dashboard-analytics').'" class="menu-link "');
    }

    public function test_executive_dashboard_menu_stays_open_on_a_system_page(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('qams.system', ['system' => 'student-information-system']))
            ->assertOk()
            ->assertSee('menu-item active open')
            ->assertSeeHtml('href="javascript:void(0);" class="menu-link menu-toggle "');
    }

    public function test_pillar_selection_is_saved_in_the_session_and_shown_in_the_navbar(): void
    {
        $this->actingAs(User::factory()->make())
            ->from(route('dashboard-analytics'))
            ->post(route('pillar.update'), ['pillar' => 'International'])
            ->assertRedirect(route('dashboard-analytics'))
            ->assertSessionHas('pillar', 'International');

        $this->get(route('dashboard-analytics'))
            ->assertOk()
            ->assertSee('Pillar:')
            ->assertSee('International')
            ->assertSeeHtml('id="qams-pillar-select"')
            ->assertSeeHtml('value="International" selected');
    }

    public function test_invalid_pillar_selection_is_rejected(): void
    {
        $this->actingAs(User::factory()->make())
            ->from(route('dashboard-analytics'))
            ->post(route('pillar.update'), ['pillar' => 'Regional'])
            ->assertSessionHasErrors('pillar');
    }
}
