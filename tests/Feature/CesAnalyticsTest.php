<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CesEnrollmentAnalytics;
use Mockery;
use Tests\TestCase;

class CesAnalyticsTest extends TestCase
{
    public function test_live_analytics_endpoint_requires_authentication(): void
    {
        $this->getJson(route('qams.ces.analytics'))->assertUnauthorized();
    }

    public function test_live_analytics_endpoint_passes_filters_to_the_analytics_service(): void
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
            ])
            ->andReturn($payload);
        $this->app->instance(CesEnrollmentAnalytics::class, $analytics);

        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', [
                'campus' => 'sogod',
                'college' => 'Education',
                'program' => 'Bachelor of Education',
                'year' => '2026',
            ]))
            ->assertOk()
            ->assertExactJson($payload);
    }

    public function test_live_analytics_endpoint_rejects_unknown_campuses(): void
    {
        $this->actingAs(User::factory()->make())
            ->getJson(route('qams.ces.analytics', ['campus' => 'unknown']))
            ->assertUnprocessable();
    }
}
