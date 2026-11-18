<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

class HrmisCampusDirectory
{
    public static function label(string $code): string
    {
        return config("ces.campuses.$code.name", $code).' Campus';
    }

    public function synchronize(Connection $warehouse, array $sourceIds, Carbon $now): void
    {
        $codes = config('hrmis.campus_codes', []);
        $linkedCampuses = $warehouse->table('dim_campus')
            ->whereIn('campus_code', array_values($codes))
            ->pluck('id', 'campus_code');

        foreach ($sourceIds as $sourceId) {
            $code = $codes[$sourceId] ?? null;
            $row = [
                'source_campus_id' => $sourceId,
                'source_campus_label' => $code === null
                    ? ($sourceId === 0 ? 'Unknown HRMIS campus' : "HRMIS campus ID $sourceId")
                    : self::label($code),
                'qams_campus_id' => $code === null ? null : $linkedCampuses->get($code),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $campuses = $warehouse->table('dim_hrmis_source_campus');
            if ($code === null) {
                $campuses->insertOrIgnore($row);
            } else {
                $campuses->upsert([$row], ['source_campus_id'], ['source_campus_label', 'qams_campus_id', 'updated_at']);
            }
        }
    }
}
