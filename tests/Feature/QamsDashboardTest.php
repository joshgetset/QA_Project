<?php

namespace Tests\Feature;

use App\Models\User;
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
    }

    public function test_dashboard_renders_in_the_personal_views_folder_with_sample_data(): void
    {
        $response = $this->actingAs(User::factory()->make())
            ->get(route('dashboard-analytics'));

        $response->assertOk()
            ->assertViewIs('personal.dashboard')
            ->assertSee('Executive Dashboard')
            ->assertSee('Student Information System')
            ->assertSee('8,420')
            ->assertSeeInOrder(['8,420', 'Enrolled students'])
            ->assertSee('Designation Management System')
            ->assertSee(route('qams.system', ['system' => 'student-information-system']))
            ->assertSee('qams-system-card-link')
            ->assertDontSee('qams-system-open')
            ->assertSee('dashboard/systems/designation-management-system')
            ->assertSee('Executive Dashboard')
            ->assertSee('row-cols-xxl-4')
            ->assertSee('qams-system-heading')
            ->assertSee('Systems monitored')
            ->assertSee('Enrolled students')
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
            ->assertSee('2022–2023')
            ->assertSee('2026–2027')
            ->assertSee('data-ces-filter="year"', false)
            ->assertSee('Graduating Enrollees')
            ->assertSee('New Freshman')
            ->assertSee('Total Enrolled Students')
            ->assertSee('International Students')
            ->assertSee("4P&#039;s Beneficiaries", false)
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
            ->assertDontSee('qams-ces-summary-change', false)
            ->assertDontSee('qams-ces-female-share', false);

        self::assertLessThan(
            strpos($response->getContent(), 'data-ces-summary="graduating-enrollees"'),
            strpos($response->getContent(), 'data-ces-summary="total-enrolled-students"')
        );
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
