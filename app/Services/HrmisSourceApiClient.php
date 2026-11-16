<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HrmisSourceApiClient
{
    /**
     * @return array{
     *     employees: list<array<string, mixed>>,
     *     departments: list<array<string, mixed>>,
     *     positions: list<array<string, mixed>>,
     *     education: list<array<string, mixed>>
     * }
     */
    public function fetch(): array
    {
        $baseUrl = rtrim((string) config('source_apis.hrmis.url'), '/');
        $token = config('source_apis.hrmis.api_key');
        $pageSize = max(1, min(1000, (int) config('source_apis.hrmis.page_size', 1000)));
        if ($baseUrl === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException('HRMIS source API URL and HRMIS_API_KEY must be configured before running the warehouse ETL.');
        }

        $datasets = [];
        foreach (['employees', 'departments', 'positions', 'education'] as $dataset) {
            $page = 1;
            $lastPage = 1;
            $datasets[$dataset] = [];
            do {
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->timeout(120)
                    ->get("{$baseUrl}/{$dataset}", [
                        'page' => $page,
                        'per_page' => $pageSize,
                    ])
                    ->throw()
                    ->json();

                if (
                    ! is_array($response)
                    || ! isset($response['data'], $response['meta']['current_page'], $response['meta']['last_page'])
                    || ! is_array($response['data'])
                ) {
                    throw new RuntimeException("HRMIS source API returned an invalid {$dataset} page.");
                }

                foreach ($response['data'] as $row) {
                    if (! is_array($row)) {
                        throw new RuntimeException("HRMIS source API returned an invalid {$dataset} record.");
                    }
                    $datasets[$dataset][] = $row;
                }
                $currentPage = (int) $response['meta']['current_page'];
                $lastPage = (int) $response['meta']['last_page'];
                if ($currentPage !== $page || $lastPage < 1 || $currentPage > $lastPage) {
                    throw new RuntimeException("HRMIS source API returned invalid pagination metadata for {$dataset}.");
                }
                $page = $currentPage + 1;
            } while ($page <= $lastPage);
        }

        return $datasets;
    }
}
