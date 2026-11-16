<?php

namespace Tests\Unit;

use App\Services\CesSourceApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CesSourceApiClientTest extends TestCase
{
    public function test_manifest_client_ignores_academic_periods_before_2019(): void
    {
        config([
            'source_apis.ces.url' => 'https://ces.example.test/api/source/ces',
            'source_apis.ces.api_key' => 'test-ces-token',
            'ces.minimum_academic_year' => 2019,
        ]);
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'income_available' => true,
                    'course_ids' => [],
                    'grade_tables' => ['1900-1' => 'grades19001', '2019-1' => 'grades20191'],
                    'stage_tables' => ['1899-2' => 'stage18992', '2026-2' => 'stage20262'],
                    'graduation_periods' => ['1900-1', '2026-2'],
                    'has_registration' => true,
                    'has_tes_by' => false,
                    'has_graduation_list' => true,
                ],
            ]),
        ]);

        $manifest = app(CesSourceApiClient::class)->manifest('sogod');

        $this->assertSame(['2019-1' => 'grades20191'], $manifest['grade_tables']);
        $this->assertSame(['2026-2' => 'stage20262'], $manifest['stage_tables']);
        $this->assertSame(['2026-2'], $manifest['graduation_periods']);
    }

    public function test_student_page_client_rejects_tables_before_2019(): void
    {
        config([
            'source_apis.ces.url' => 'https://ces.example.test/api/source/ces',
            'source_apis.ces.api_key' => 'test-ces-token',
            'ces.minimum_academic_year' => 2019,
        ]);
        Http::preventStrayRequests();

        $this->expectException(RuntimeException::class);
        iterator_to_array(
            app(CesSourceApiClient::class)->studentNumberPages('sogod', 'enrollments', 'grades19001')
        );
    }

    public function test_registration_and_graduation_pages_filter_records_before_2019(): void
    {
        config([
            'source_apis.ces.url' => 'https://ces.example.test/api/source/ces',
            'source_apis.ces.api_key' => 'test-ces-token',
            'source_apis.ces.page_size' => 3,
            'ces.minimum_academic_year' => 2019,
        ]);
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['SchoolYear' => '1901'],
                    ['SchoolYear' => '2019'],
                    ['SchoolYear' => '2026-2027'],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 3,
                    'has_more' => false,
                ],
            ]),
        ]);
        $client = app(CesSourceApiClient::class);

        $registrations = iterator_to_array($client->registrationPages('sogod'));
        $graduations = iterator_to_array($client->graduationListPages('sogod'));

        $this->assertSame(['2019', '2026-2027'], array_column($registrations[0], 'SchoolYear'));
        $this->assertSame(['2019', '2026-2027'], array_column($graduations[0], 'SchoolYear'));
    }

    public function test_student_page_client_sends_bearer_key_and_follows_pages(): void
    {
        config([
            'source_apis.ces.url' => 'https://ces.example.test/api/source/ces',
            'source_apis.ces.api_key' => 'test-ces-token',
            'source_apis.ces.page_size' => 1,
        ]);
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 0);

            return Http::response([
                'data' => [['student_no' => "S{$page}"]],
                'meta' => [
                    'current_page' => $page,
                    'per_page' => 1,
                    'has_more' => $page === 1,
                ],
            ]);
        });

        $pages = iterator_to_array(
            app(CesSourceApiClient::class)->studentNumberPages('sogod', 'enrollments', 'grades20261')
        );

        $this->assertSame('S1', $pages[0][0]['student_no']);
        $this->assertSame('S2', $pages[1][0]['student_no']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-ces-token'));
        Http::assertSentCount(2);
    }

    public function test_student_page_client_fails_when_server_pagination_stalls(): void
    {
        config([
            'source_apis.ces.url' => 'https://ces.example.test/api/source/ces',
            'source_apis.ces.api_key' => 'test-ces-token',
        ]);
        Http::fake([
            '*' => Http::response([
                'data' => [],
                'meta' => ['current_page' => 1, 'has_more' => true],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        iterator_to_array(app(CesSourceApiClient::class)->studentNumberPages('sogod', 'enrollments', 'grades20261'));
    }
}
