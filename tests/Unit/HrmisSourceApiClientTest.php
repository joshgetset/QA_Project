<?php

namespace Tests\Unit;

use App\Services\HrmisSourceApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HrmisSourceApiClientTest extends TestCase
{
    public function test_fetch_retrieves_each_dataset_and_follows_page_metadata(): void
    {
        config([
            'source_apis.hrmis.url' => 'https://hrmis.example.test/api/source/hrmis',
            'source_apis.hrmis.api_key' => 'test-token',
            'source_apis.hrmis.page_size' => 1,
        ]);
        Http::fake(function (Request $request) {
            $dataset = basename(parse_url($request->url(), PHP_URL_PATH));
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 0);
            $lastPage = $dataset === 'employees' ? 2 : 1;

            return Http::response([
                'data' => [['source_employee_id' => $dataset === 'employees' ? $page : 1]],
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                ],
            ]);
        });

        $datasets = app(HrmisSourceApiClient::class)->fetch();

        $this->assertCount(2, $datasets['employees']);
        $this->assertSame(2, $datasets['employees'][1]['source_employee_id']);
        $this->assertCount(1, $datasets['education']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-token'));
        Http::assertSentCount(5);
    }

    public function test_fetch_fails_on_invalid_page_metadata(): void
    {
        config([
            'source_apis.hrmis.url' => 'https://hrmis.example.test/api/source/hrmis',
            'source_apis.hrmis.api_key' => 'test-token',
        ]);
        Http::fake([
            '*' => Http::response([
                'data' => [],
                'meta' => ['current_page' => 2, 'last_page' => 1],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        app(HrmisSourceApiClient::class)->fetch();
    }
}
