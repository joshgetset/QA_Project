<?php

namespace Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
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

    public function test_summary_endpoint_returns_only_aggregated_campus_counts(): void
    {
        config(['ces.api_key' => 'test-ces-key']);

        $connection = \Mockery::mock(Connection::class);
        DB::shouldReceive('connection')
            ->with('ces_sources')
            ->times(6)
            ->andReturn($connection);

        $expectedCounts = [
            'sogod' => 1,
            'bontoc' => 2,
            'tomasoppus' => 3,
            'hinunangan' => 4,
            'maasin' => 5,
            'sanjuan' => 6,
        ];
        foreach (config('ces.campuses') as $key => $campus) {
            $query = \Mockery::mock(QueryBuilder::class);
            $connection->shouldReceive('table')
                ->once()
                ->with($campus['database'].'.students')
                ->andReturn($query);
            $query->shouldReceive('count')
                ->once()
                ->andReturn($expectedCounts[$key]);
        }

        $this->withToken('test-ces-key')
            ->getJson(route('ces.api.enrollment-summary'))
            ->assertOk()
            ->assertJsonPath('summary', 'Aggregated student-record counts from the six CES campus databases.')
            ->assertJsonPath('campuses.0.key', 'sogod')
            ->assertJsonPath('campuses.0.student_records', 1)
            ->assertJsonPath('metrics.6.label', 'Total student records')
            ->assertJsonPath('metrics.6.value', 21)
            ->assertJsonMissingPath('campuses.0.student_names');
    }
}
