<?php

namespace Tests\Feature;

use App\Services\CesSourceExportService;
use Mockery;
use Tests\TestCase;

class CesSourceApiTest extends TestCase
{
    public function test_ces_source_export_requires_its_own_bearer_key(): void
    {
        config(['source_apis.ces.api_key' => 'ces-source-test-key']);

        $this->getJson(route('source.api.ces.manifest', ['campus' => 'sogod']))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer');

        $this->withToken('incorrect-key')
            ->getJson(route('source.api.ces.manifest', ['campus' => 'sogod']))
            ->assertUnauthorized();
    }

    public function test_ces_source_manifest_returns_allowlisted_source_metadata(): void
    {
        config(['source_apis.ces.api_key' => 'ces-source-test-key']);
        $exporter = Mockery::mock(CesSourceExportService::class);
        $exporter->shouldReceive('manifest')->once()->with('sogod')->andReturn([
            'income_available' => true,
            'course_ids' => ['1'],
            'grade_tables' => ['2026-1' => 'grades20261'],
            'stage_tables' => [],
            'graduation_periods' => [],
            'has_registration' => true,
            'has_tes_by' => false,
            'has_graduation_list' => false,
        ]);
        $this->app->instance(CesSourceExportService::class, $exporter);

        $this->withToken('ces-source-test-key')
            ->getJson(route('source.api.ces.manifest', ['campus' => 'sogod']))
            ->assertOk()
            ->assertJsonPath('data.grade_tables.2026-1', 'grades20261')
            ->assertJsonMissingPath('data.student_records');
    }

    public function test_ces_source_student_lookup_limits_request_batch_size(): void
    {
        config(['source_apis.ces.api_key' => 'ces-source-test-key']);

        $this->withToken('ces-source-test-key')
            ->postJson(route('source.api.ces.students', ['campus' => 'sogod']), [
                'student_numbers' => array_fill(0, 1001, '10001'),
            ])
            ->assertUnprocessable();
    }
}
