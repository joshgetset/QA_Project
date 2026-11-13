<?php

namespace App\Services\Integrations;

use App\Contracts\SystemDataIntegration;
use App\Exceptions\CesAnalyticsSnapshotUnavailable;
use App\Services\CesEnrollmentAnalytics;

class CesSystemIntegration implements SystemDataIntegration
{
    public function __construct(private readonly CesEnrollmentAnalytics $analytics) {}

    public function cacheVersion(): ?string
    {
        return 'analytics-snapshot-v1';
    }

    public function acceptsCachedData(array $data): bool
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

    public function usesLocalEndpoint(string $endpoint): bool
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

    public function fetchLocalData(): array
    {
        try {
            $analytics = $this->analytics->get();
        } catch (CesAnalyticsSnapshotUnavailable $exception) {
            return [
                'source' => 'unavailable',
                'source_label' => 'Snapshot unavailable',
                'summary' => $exception->getMessage(),
                'updated_at' => null,
                'metrics' => [],
            ];
        }

        $year = $analytics['selected']['year'] ?? null;

        if ($this->hasUnavailableCampus($analytics) || ! is_string($year) || preg_match('/^\d{4}$/D', $year) !== 1) {
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
            'source_label' => 'Synced snapshot',
            'summary' => 'Current school-year enrollment total from the latest synchronized CES snapshot.',
            'updated_at' => $analytics['updated_at'] ?? now()->toIso8601String(),
            'metrics' => [[
                'label' => sprintf('Total Enrolled Students (AY %s-%d)', $year, (int) $year + 1),
                'value' => number_format((int) $metric['value']),
            ]],
        ];
    }

    private function hasUnavailableCampus(array $analytics): bool
    {
        $campuses = $analytics['campuses'] ?? null;

        if (! is_array($campuses) || $campuses === []) {
            return (bool) ($analytics['partial'] ?? false);
        }

        foreach ($campuses as $campus) {
            if (($campus['status'] ?? null) !== 'available') {
                return true;
            }
        }

        return false;
    }
}
