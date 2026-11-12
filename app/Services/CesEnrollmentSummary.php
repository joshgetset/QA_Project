<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CesEnrollmentSummary
{
    /**
     * @return array{
     *   summary: string,
     *   metrics: list<array{label: string, value: int}>,
     *   campuses: list<array{key: string, name: string, student_records: int}>,
     *   updated_at: string
     * }
     */
    public function get(): array
    {
        $campuses = [];
        $metrics = [];
        $totalStudentRecords = 0;

        foreach (config('ces.campuses', []) as $key => $campus) {
            $studentRecords = DB::connection(config('ces.database_connection'))
                ->table($campus['database'].'.students')
                ->count();

            $campuses[] = [
                'key' => $key,
                'name' => $campus['name'],
                'student_records' => $studentRecords,
            ];
            $metrics[] = [
                'label' => $campus['name'].' student records',
                'value' => $studentRecords,
            ];
            $totalStudentRecords += $studentRecords;
        }

        $metrics[] = [
            'label' => 'Total student records',
            'value' => $totalStudentRecords,
        ];

        return [
            'summary' => 'Aggregated student-record counts from the six CES campus databases.',
            'metrics' => $metrics,
            'campuses' => $campuses,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
