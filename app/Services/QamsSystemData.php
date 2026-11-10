<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class QamsSystemData
{
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
        $systems = [];

        foreach (config('qams.systems', []) as $key => $system) {
            $systems[] = $this->get($key, $system);
        }

        return $systems;
    }

    public function find(string $key): ?array
    {
        $system = config("qams.systems.{$key}");

        return is_array($system) ? $this->get($key, $system) : null;
    }

    private function get(string $key, array $system): array
    {
        return [
            'key' => $key,
            'name' => $system['name'],
            'description' => $system['description'],
            'icon' => $system['icon'],
            ...$this->dataFor($key, $system),
        ];
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

        $cacheKey = "qams.system.{$key}";
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
