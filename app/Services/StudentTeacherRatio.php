<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StudentTeacherRatio
{
    public function get(int $snapshotId, int $year): ?array
    {
        $warehouse = DB::connection('qams_warehouse');
        $campusCodes = config('hrmis.campus_codes');
        if (! $warehouse->getSchemaBuilder()->hasTable('ces_warehouse_sync_runs')) {
            return null;
        }

        $cesRunId = $warehouse->table('ces_warehouse_sync_runs')->where('status', 'succeeded')->max('id');
        if ($cesRunId === null) {
            return null;
        }

        $availablePeriods = $warehouse->table('ces_campus_academic_periods as availability')
            ->join('dim_academic_period as period', 'period.id', '=', 'availability.academic_period_id')
            ->join('dim_campus as campus', 'campus.id', '=', 'availability.campus_id')
            ->join('ces_warehouse_campus_syncs as sync', 'sync.campus_id', '=', 'campus.id')
            ->where('sync.run_id', $cesRunId)
            ->where('sync.status', 'succeeded')
            ->where('availability.has_grade_table', true)
            ->where('period.academic_year', (string) $year)
            ->whereIn('campus.campus_code', array_values($campusCodes))
            ->get(['period.id as period_id', 'period.semester', 'campus.id as campus_id', 'campus.campus_code']);
        $semesters = $availablePeriods->pluck('semester')->map(fn ($semester) => (string) $semester);
        $semester = $semesters->containsStrict('1') ? '1' : $semesters->sortDesc(SORT_NUMERIC)->first();
        if ($semester === null) {
            return null;
        }

        $period = $availablePeriods->filter(fn ($row) => (string) $row->semester === $semester)->keyBy('campus_code');
        $enrollment = $warehouse->table('fact_ces_enrollment')
            ->where('academic_period_id', $period->first()->period_id)
            ->whereIn('campus_id', $period->pluck('campus_id'))
            ->selectRaw('campus_id, COUNT(DISTINCT student_id) AS students')
            ->groupBy('campus_id')
            ->pluck('students', 'campus_id');
        $staff = $warehouse->table('fact_hrmis_employee_snapshot as fact')
            ->join('dim_hrmis_source_campus as campus', 'campus.id', '=', 'fact.campus_id')
            ->leftJoin('dim_campus as ces_campus', 'ces_campus.id', '=', 'campus.qams_campus_id')
            ->where('fact.sync_run_id', $snapshotId)
            ->whereIn('campus.source_campus_id', array_keys($campusCodes))
            ->selectRaw('campus.source_campus_id, ces_campus.campus_code, SUM(CASE WHEN fact.is_teaching_personnel = 1 THEN fact.employee_count ELSE 0 END) AS teachers')
            ->groupBy('campus.source_campus_id', 'ces_campus.campus_code')
            ->get()
            ->keyBy('source_campus_id');

        $campuses = collect($campusCodes)->map(function ($code, $sourceId) use ($period, $enrollment, $staff) {
            $campusPeriod = $period->get($code);
            $personnel = $staff->get($sourceId);

            return $this->row(
                HrmisCampusDirectory::label($code),
                $campusPeriod === null ? null : (int) $enrollment->get($campusPeriod->campus_id, 0),
                $personnel?->campus_code === $code ? (int) $personnel->teachers : null
            );
        })->values();
        $university = $this->row(
            'Southern Leyte State University',
            $campuses->containsStrict('students', null) ? null : $campuses->sum('students'),
            $campuses->containsStrict('teachers', null) ? null : $campuses->sum('teachers')
        );
        return [
            'year' => $year,
            'semester' => $semester,
            'campuses' => $campuses->sortByDesc('ratio')->values()->all(),
            'university' => [$university],
        ];
    }

    private function row(string $label, ?int $students, ?int $teachers): array
    {
        return [
            'label' => $label,
            'students' => $students,
            'teachers' => $teachers,
            'ratio' => $students !== null && $teachers > 0 ? round($students / $teachers, 2) : null,
        ];
    }
}
