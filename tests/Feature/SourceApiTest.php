<?php

namespace Tests\Feature;

use App\Services\HrmisWarehouseSynchronizer;
use Mockery;
use Tests\TestCase;

class SourceApiTest extends TestCase
{
    public function test_hrmis_source_export_requires_its_own_bearer_key(): void
    {
        config(['source_apis.hrmis.api_key' => 'hrmis-source-test-key']);

        $this->getJson(route('source.api.hrmis.export', ['dataset' => 'employees']))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer');

        $this->withToken('incorrect-key')
            ->getJson(route('source.api.hrmis.export', ['dataset' => 'employees']))
            ->assertUnauthorized();
    }

    public function test_hrmis_source_export_pages_allowlisted_records(): void
    {
        config([
            'source_apis.hrmis.api_key' => 'hrmis-source-test-key',
            'source_apis.hrmis.page_size' => 1,
        ]);

        $synchronizer = Mockery::mock(HrmisWarehouseSynchronizer::class);
        $synchronizer->shouldReceive('exportSourceData')->once()->andReturn([
            'employees' => [
                ['source_employee_id' => 10, 'gender_code' => 'female'],
                ['source_employee_id' => 20, 'gender_code' => 'male'],
            ],
            'departments' => [],
            'positions' => [],
            'education' => [],
        ]);
        $this->app->instance(HrmisWarehouseSynchronizer::class, $synchronizer);

        $this->withToken('hrmis-source-test-key')
            ->getJson(route('source.api.hrmis.export', [
                'dataset' => 'employees',
                'page' => 2,
                'per_page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.source_employee_id', 20)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 2);

        $this->getJson(route('source.api.hrmis.export', ['dataset' => 'private-data']))
            ->assertNotFound();
    }

    public function test_hrmis_source_api_reports_missing_server_key(): void
    {
        config(['source_apis.hrmis.api_key' => null]);

        $this->getJson(route('source.api.hrmis.export', ['dataset' => 'employees']))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Source API is not configured.');
    }
}
