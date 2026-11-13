<?php

namespace Tests\Feature;

use App\Services\Integrations\CesSystemIntegration;
use Mockery;
use Tests\TestCase;

class CesEnrollmentSummaryApiTest extends TestCase
{
    public function test_summary_endpoint_requires_a_bearer_api_key(): void
    {
        config(['ces.api_key' => 'test-ces-key']);

        $this->getJson(route('ces.api.enrollment-summary'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer');

        $this->withToken('wrong-key')
            ->getJson(route('ces.api.enrollment-summary'))
            ->assertUnauthorized();
    }

    public function test_summary_endpoint_reports_when_its_api_key_is_not_configured(): void
    {
        config(['ces.api_key' => null]);

        $this->getJson(route('ces.api.enrollment-summary'))
            ->assertStatus(503)
            ->assertJsonPath('message', 'CES API is not configured.');
    }

    public function test_summary_endpoint_returns_snapshot_enrollment_metrics(): void
    {
        config(['ces.api_key' => 'test-ces-key']);

        $integration = Mockery::mock(CesSystemIntegration::class);
        $integration->shouldReceive('fetchLocalData')
            ->once()
            ->andReturn([
                'source' => 'live',
                'source_label' => 'Synced snapshot',
                'summary' => 'Current school-year enrollment total from the latest synchronized CES snapshot.',
                'updated_at' => '2026-11-13T04:00:00+00:00',
                'metrics' => [[
                    'label' => 'Total Enrolled Students (AY 2026-2027)',
                    'value' => '12,394',
                ]],
            ]);
        $this->app->instance(CesSystemIntegration::class, $integration);

        $this->withToken('test-ces-key')
            ->getJson(route('ces.api.enrollment-summary'))
            ->assertOk()
            ->assertJsonPath('source_label', 'Synced snapshot')
            ->assertJsonPath('metrics.0.label', 'Total Enrolled Students (AY 2026-2027)')
            ->assertJsonPath('metrics.0.value', '12,394')
            ->assertJsonMissingPath('campuses.0.student_records');
    }

    public function test_summary_endpoint_reports_unavailable_snapshot_as_service_unavailable(): void
    {
        config(['ces.api_key' => 'test-ces-key']);

        $integration = Mockery::mock(CesSystemIntegration::class);
        $integration->shouldReceive('fetchLocalData')
            ->once()
            ->andReturn([
                'source' => 'unavailable',
                'source_label' => 'Snapshot unavailable',
                'summary' => 'CES analytics snapshot is missing.',
                'updated_at' => null,
                'metrics' => [],
            ]);
        $this->app->instance(CesSystemIntegration::class, $integration);

        $this->withToken('test-ces-key')
            ->getJson(route('ces.api.enrollment-summary'))
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'CES analytics snapshot is missing.');
    }
}
