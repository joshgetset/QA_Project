<?php

namespace App\Services;

use App\Exceptions\CesAnalyticsSnapshotUnavailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class CesAnalyticsSnapshotStore
{
    private const SNAPSHOT_KEY = 'ces-enrollment-analytics';

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $row = DB::connection(config('ces.reporting_connection'))
            ->table(config('ces.snapshot_table'))
            ->where('snapshot_key', self::SNAPSHOT_KEY)
            ->first();

        if ($row === null) {
            throw new CesAnalyticsSnapshotUnavailable(
                'CES analytics snapshot is missing. Run php artisan ces:sync-analytics-snapshot after configuring the reporting database.'
            );
        }

        try {
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('CES analytics snapshot contains invalid JSON.', previous: $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('CES analytics snapshot payload must be a JSON object.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function replace(array $payload): void
    {
        $connection = DB::connection(config('ces.reporting_connection'));

        $connection->transaction(function () use ($connection, $payload): void {
            $connection->table(config('ces.snapshot_table'))->updateOrInsert(
                ['snapshot_key' => self::SNAPSHOT_KEY],
                [
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'synced_at' => Carbon::parse($payload['synced_at'])->toDateTimeString(),
                ]
            );
        });
    }
}
