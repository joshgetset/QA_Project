<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Batch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDOException;
use UnexpectedValueException;

class QamsSystemData
{
    private const LAST_KNOWN_GOOD_TTL_SECONDS = 86400;

    public function __construct(private readonly CesEnrollmentAnalytics $cesEnrollmentAnalytics) {}

    /**
     * @return list<array{
     *   key: string,
     *   name: string,
     *   description: string,
     *   icon: string,
     *   source: string,
     *   source_label: string,
     *   summary: string,
     *   updated_at: string|null,
     *   metrics: list<array{label: string, value: string}>
     * }>
     */
    public function all(): array
    {
        $definitions = config('qams.systems', []);
        $systems = [];
        $pending = [];

        foreach ($definitions as $key => $system) {
            $endpoint = $system['endpoint'] ?? null;

            if (! is_string($endpoint) || trim($endpoint) === '') {
                $systems[$key] = $this->get($key, $system);

                continue;
            }

            $cacheKey = $this->systemCacheKey($key);
            $cached = Cache::get($cacheKey);

            if ($cached !== null && ($key !== 'system-17' || $this->hasCurrentSchoolYearEnrollmentMetric($cached))) {
                $systems[$key] = $this->get($key, $system, $cached);

                continue;
            }

            if ($key === 'system-17' && $this->isLocalCesSummaryEndpoint($endpoint)) {
                try {
                    $result = $this->localCesEnrollmentResult();
                    if ($result['source'] === 'live') {
                        Cache::put($cacheKey, $result, now()->addSeconds(config('qams.refresh_interval', 30)));
                        $this->storeLastKnownGood($key, $result);
                    }
                    $systems[$key] = $this->get($key, $system, $result);
                } catch (QueryException|PDOException $exception) {
                    $this->logRequestFailure($key, $exception::class, $exception->getMessage());
                    $systems[$key] = $this->get($key, $system, $this->unavailable($cacheKey));
                }

                continue;
            }

            $scheme = parse_url($endpoint, PHP_URL_SCHEME);

            if (! filter_var($endpoint, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                Log::warning('QAMS system API URL must be a valid HTTP(S) URL.', ['system' => $key]);
                $systems[$key] = $this->get($key, $system, $this->unavailable($cacheKey));

                continue;
            }

            $pending[$key] = [
                'definition' => $system,
                'endpoint' => $endpoint,
                'cache_key' => $cacheKey,
            ];
        }

        if ($pending !== []) {
            $responses = Http::batch(function (Batch $batch) use ($pending): void {
                foreach ($pending as $key => $request) {
                    $pendingRequest = $batch->as($key)
                        ->acceptJson()
                        ->connectTimeout(3)
                        ->timeout(8);

                    if (is_string($request['definition']['token'] ?? null) && $request['definition']['token'] !== '') {
                        $pendingRequest = $pendingRequest->withToken($request['definition']['token']);
                    }

                    $pendingRequest->get($request['endpoint']);
                }
            })->progress(function (Batch $batch, string $key, Response $response) use (&$systems, $pending): void {
                try {
                    $data = $this->normalizePayload($response->json());
                    $result = [
                        'source' => 'live',
                        'source_label' => 'Live data',
                        'summary' => $data['summary'] ?? 'Data received from the connected system.',
                        'updated_at' => now()->toIso8601String(),
                        'metrics' => $data['metrics'],
                    ];
                    Cache::put($pending[$key]['cache_key'], $result, now()->addSeconds(config('qams.refresh_interval', 30)));
                    $this->storeLastKnownGood($key, $result);
                    $systems[$key] = $this->get($key, $pending[$key]['definition'], $result);
                } catch (RequestException|UnexpectedValueException $exception) {
                    $this->logRequestFailure($key, $exception::class, $exception->getMessage());
                    $systems[$key] = $this->get($key, $pending[$key]['definition'], $this->unavailable($pending[$key]['cache_key']));
                }
            })->catch(function (Batch $batch, string $key, Response|RequestException $failure) use (&$systems, $pending): void {
                $this->logRequestFailure(
                    $key,
                    $failure::class,
                    $failure instanceof Response ? "HTTP {$failure->status()} response." : $failure->getMessage()
                );
                $systems[$key] = $this->get($key, $pending[$key]['definition'], $this->unavailable($pending[$key]['cache_key']));
            })->send();

            foreach ($pending as $key => $request) {
                if (isset($systems[$key])) {
                    continue;
                }

                $failure = $responses[$key] ?? null;
                $this->logRequestFailure(
                    $key,
                    $failure instanceof \Throwable ? $failure::class : 'MissingBatchResponse',
                    $failure instanceof \Throwable ? $failure->getMessage() : 'The API request did not return a response.'
                );
                $systems[$key] = $this->get($key, $request['definition'], $this->unavailable($request['cache_key']));
            }
        }

        return array_values(array_map(
            fn ($key) => $systems[$key],
            array_keys($definitions)
        ));
    }

    /**
     * Return cached API values for page rendering without waiting for remote systems.
     *
     * @return list<array<string, mixed>>
     */
    public function snapshot(): array
    {
        $systems = [];

        foreach (config('qams.systems', []) as $key => $system) {
            $endpoint = $system['endpoint'] ?? null;

            if (! is_string($endpoint) || trim($endpoint) === '') {
                $systems[] = $this->get($key, $system);

                continue;
            }

            $cached = Cache::get($this->systemCacheKey($key))
                ?? Cache::get($this->lastKnownGoodCacheKey($key));
            $data = $cached === null ? [
                'source' => 'unavailable',
                'source_label' => 'Not loaded',
                'summary' => 'No successful API result is cached yet. Use Refresh to load data.',
                'updated_at' => null,
                'metrics' => [],
            ] : [
                ...$cached,
                'source' => 'cached',
                'source_label' => 'Cached data',
            ];
            $systems[] = $this->get($key, $system, $data);
        }

        return $systems;
    }

    public function find(string $key): ?array
    {
        $definition = $this->definition($key);

        if ($definition === null) {
            return null;
        }

        $system = config("qams.systems.{$definition['key']}");

        return $this->get($definition['key'], $system);
    }

    /**
     * @return array{key: string, name: string, description: string, icon: string}|null
     */
    public function definition(string $key): ?array
    {
        foreach (config('qams.systems', []) as $systemKey => $system) {
            if ($systemKey === $key || Str::slug($system['name']) === $key) {
                return [
                    'key' => $systemKey,
                    'name' => $system['name'],
                    'description' => $system['description'],
                    'icon' => $system['icon'],
                ];
            }
        }

        return null;
    }

    private function get(string $key, array $system, ?array $data = null): array
    {
        return [
            'key' => $key,
            'name' => $system['name'],
            'description' => $system['description'],
            'icon' => $system['icon'],
            ...($data ?? $this->dataFor($key, $system)),
        ];
    }

    private function logRequestFailure(string $key, string $exception, string $message): void
    {
        Log::warning('QAMS system data could not be refreshed.', [
            'system' => $key,
            'exception' => $exception,
            'message' => $message,
        ]);
    }

    private function storeLastKnownGood(string $key, array $result): void
    {
        Cache::put(
            $this->lastKnownGoodCacheKey($key),
            $result,
            now()->addSeconds(self::LAST_KNOWN_GOOD_TTL_SECONDS)
        );
    }

    private function lastKnownGoodCacheKey(string $key): string
    {
        $version = $key === 'system-17' ? '.grades20261' : '';

        return "qams.system.last-known-good.{$key}{$version}";
    }

    private function systemCacheKey(string $key): string
    {
        $version = $key === 'system-17' ? '.grades20261' : '';

        return "qams.system.{$key}{$version}";
    }

    private function isLocalCesSummaryEndpoint(string $endpoint): bool
    {
        $endpointParts = parse_url($endpoint);
        $appParts = parse_url(config('app.url'));

        if (! is_array($endpointParts) || ! is_array($appParts)) {
            return false;
        }

        $loopbackHosts = ['127.0.0.1', '::1', 'localhost'];
        $endpointHost = trim(strtolower($endpointParts['host'] ?? ''), '[]');
        $appHost = trim(strtolower($appParts['host'] ?? ''), '[]');

        return in_array($endpointHost, $loopbackHosts, true)
            && in_array($appHost, $loopbackHosts, true)
            && ($endpointParts['scheme'] ?? null) === ($appParts['scheme'] ?? null)
            && ($endpointParts['port'] ?? null) === ($appParts['port'] ?? null)
            && ($endpointParts['path'] ?? '') === '/api/ces/enrollment-summary';
    }

    private function localCesEnrollmentResult(): array
    {
        $analytics = $this->cesEnrollmentAnalytics->get();
        $year = $analytics['selected']['year'] ?? null;

        if ($analytics['partial'] || ! is_string($year) || preg_match('/^\d{4}$/D', $year) !== 1) {
            return [
                'source' => 'unavailable',
                'source_label' => 'Enrollment data unavailable',
                'summary' => 'The current school-year enrollment total is unavailable because the academic year could not be fully verified across CES campuses.',
                'updated_at' => now()->toIso8601String(),
                'metrics' => [],
            ];
        }

        $metric = collect($analytics['summary'] ?? [])
            ->firstWhere('key', 'total-enrolled-students');

        if (! is_array($metric) || ! is_numeric($metric['value'] ?? null)) {
            return [
                'source' => 'unavailable',
                'source_label' => 'Enrollment data unavailable',
                'summary' => 'CES did not return a valid current school-year enrollment total.',
                'updated_at' => now()->toIso8601String(),
                'metrics' => [],
            ];
        }

        return [
            'source' => 'live',
            'source_label' => 'Live data',
            'summary' => 'Current school-year enrollment total from the latest academic year available in CES.',
            'updated_at' => $analytics['updated_at'] ?? now()->toIso8601String(),
            'metrics' => [[
                'label' => sprintf('Total Enrolled Students (AY %s-%d)', $year, (int) $year + 1),
                'value' => number_format((int) $metric['value']),
            ]],
        ];
    }

    private function hasCurrentSchoolYearEnrollmentMetric(array $data): bool
    {
        foreach ($data['metrics'] ?? [] as $metric) {
            if (
                is_array($metric)
                && is_string($metric['label'] ?? null)
                && preg_match('/^Total Enrolled Students \(AY \d{4}-\d{4}\)$/iD', trim($metric['label'])) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function dataFor(string $key, array $system): array
    {
        $endpoint = $system['endpoint'] ?? null;

        if (! is_string($endpoint) || trim($endpoint) === '') {
            return [
                'source' => 'demo',
                'source_label' => 'Sample data',
                'summary' => 'Example values for layout preview; not live university data.',
                'updated_at' => null,
                'metrics' => $system['sample_metrics'],
            ];
        }

        $cacheKey = $this->systemCacheKey($key);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $scheme = parse_url($endpoint, PHP_URL_SCHEME);

        if (! filter_var($endpoint, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            Log::warning('QAMS system API URL must be a valid HTTP(S) URL.', ['system' => $key]);

            return $this->unavailable($cacheKey);
        }

        try {
            $request = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(8);

            if (is_string($system['token'] ?? null) && $system['token'] !== '') {
                $request = $request->withToken($system['token']);
            }

            $payload = $request->get($endpoint)->throw()->json();
            $data = $this->normalizePayload($payload);
            $result = [
                'source' => 'live',
                'source_label' => 'Live data',
                'summary' => $data['summary'] ?? 'Data received from the connected system.',
                'updated_at' => now()->toIso8601String(),
                'metrics' => $data['metrics'],
            ];

            Cache::put($cacheKey, $result, now()->addSeconds(config('qams.refresh_interval', 30)));
            $this->storeLastKnownGood($key, $result);

            return $result;
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            Log::warning('QAMS system data could not be refreshed.', [
                'system' => $key,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->unavailable($cacheKey);
        }
    }

    private function unavailable(string $cacheKey): array
    {
        $result = [
            'source' => 'unavailable',
            'source_label' => 'Unavailable',
            'summary' => 'Live data could not be retrieved. Check the system API configuration.',
            'updated_at' => null,
            'metrics' => [],
        ];
        Cache::put($cacheKey, $result, now()->addSeconds(10));

        return $result;
    }

    private function normalizePayload(mixed $payload): array
    {
        if (! is_array($payload) || ! isset($payload['metrics']) || ! is_array($payload['metrics'])) {
            throw new UnexpectedValueException('Expected an object with a metrics array.');
        }

        $metrics = [];

        foreach ($payload['metrics'] as $metric) {
            if (
                ! is_array($metric)
                || ! is_string($metric['label'] ?? null)
                || ! array_key_exists('value', $metric)
                || (! is_string($metric['value']) && ! is_numeric($metric['value']))
            ) {
                throw new UnexpectedValueException('Each metric must include a string label and scalar value.');
            }

            $metrics[] = [
                'label' => $metric['label'],
                'value' => (string) $metric['value'],
            ];
        }

        return [
            'summary' => is_string($payload['summary'] ?? null) ? $payload['summary'] : null,
            'metrics' => $metrics,
        ];
    }
}
