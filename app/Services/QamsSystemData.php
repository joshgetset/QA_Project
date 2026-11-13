<?php

namespace App\Services;

use App\Contracts\SystemDataIntegration;
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

    public function __construct(private readonly SystemDataIntegrationResolver $integrations) {}

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

            $integration = $this->integrations->resolve($system);
            $cacheKey = $this->systemCacheKey($key, $integration);
            $cached = Cache::get($cacheKey);

            if ($cached !== null && ($integration === null || $integration->acceptsCachedData($cached))) {
                $systems[$key] = $this->get($key, $system, $cached);

                continue;
            }

            if ($integration !== null && $integration->usesLocalEndpoint($endpoint)) {
                try {
                    $result = $integration->fetchLocalData();
                    if ($result['source'] === 'live') {
                        Cache::put($cacheKey, $result, now()->addSeconds(config('qams.refresh_interval', 30)));
                        $this->storeLastKnownGood($key, $result, $integration);
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
                'integration' => $integration,
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
                    $this->storeLastKnownGood($key, $result, $pending[$key]['integration']);
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

            $integration = $this->integrations->resolve($system);
            $cached = Cache::get($this->systemCacheKey($key, $integration))
                ?? Cache::get($this->lastKnownGoodCacheKey($key, $integration));
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

    private function storeLastKnownGood(
        string $key,
        array $result,
        ?SystemDataIntegration $integration
    ): void
    {
        Cache::put(
            $this->lastKnownGoodCacheKey($key, $integration),
            $result,
            now()->addSeconds(self::LAST_KNOWN_GOOD_TTL_SECONDS)
        );
    }

    private function lastKnownGoodCacheKey(string $key, ?SystemDataIntegration $integration): string
    {
        $version = $integration?->cacheVersion();

        return "qams.system.last-known-good.{$key}".($version === null ? '' : ".{$version}");
    }

    private function systemCacheKey(string $key, ?SystemDataIntegration $integration): string
    {
        $version = $integration?->cacheVersion();

        return "qams.system.{$key}".($version === null ? '' : ".{$version}");
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

        $integration = $this->integrations->resolve($system);
        $cacheKey = $this->systemCacheKey($key, $integration);
        $cached = Cache::get($cacheKey);

        if ($cached !== null && ($integration === null || $integration->acceptsCachedData($cached))) {
            return $cached;
        }

        $scheme = parse_url($endpoint, PHP_URL_SCHEME);

        if (! filter_var($endpoint, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            Log::warning('QAMS system API URL must be a valid HTTP(S) URL.', ['system' => $key]);

            return $this->unavailable($cacheKey);
        }

        try {
            if ($integration !== null && $integration->usesLocalEndpoint($endpoint)) {
                $result = $integration->fetchLocalData();
            } else {
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
            }

            Cache::put($cacheKey, $result, now()->addSeconds(config('qams.refresh_interval', 30)));
            if ($result['source'] === 'live') {
                $this->storeLastKnownGood($key, $result, $integration);
            }

            return $result;
        } catch (ConnectionException|RequestException|QueryException|PDOException|UnexpectedValueException $exception) {
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
