<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HrmisWorkforceAnalytics
{
    private const TEACHING_RANKS = [
        'instructor' => 'Instructor',
        'assistant_professor' => 'Assistant Professors',
        'associate_professor' => 'Associate Professors',
        'professor' => 'Professors',
        'university_professor' => 'University Professor',
    ];

    private const NON_TEACHING = [
        'is_non_teaching_full_time' => 'Full-time',
        'is_non_teaching_part_time' => 'Part-time',
        'is_non_teaching_permanent' => 'Permanent',
        'is_non_teaching_casual' => 'Casual',
        'is_non_teaching_contractual' => 'Contractual',
        'is_non_teaching_job_order' => 'Job Order',
        'is_guidance_counselor' => 'Mental Health / Guidance Counselors',
        'is_physical_health' => 'Physical Health / Nurse / Physician',
    ];

    private const SENIOR_LEADERSHIP = [
        'Deputy Vice-Chancellors' => '/\bdeputy vice chancellors?\b/',
        'Vice-Chancellors' => '/\bvice chancellors?\b/',
        'Chancellors' => '/\bchancellors?\b/',
        'Rectors' => '/\brectors?\b/',
        'Presidents' => '/\bpresidents?\b/',
        'Deans' => '/\bdeans?\b/',
        'Chairs' => '/\bchair(?:person|man|woman)?s?\b/',
    ];

    private const HIGHER_EDUCATION = [
        'bachelor' => 'Bachelor',
        'master_units' => 'Master’s Units',
        'masters_degree' => 'Master’s Degree',
        'doctorate_units' => 'Doctorate Units',
        'doctorate_degree' => 'Doctorate Degree',
    ];

    private const NON_TEACHING_EDUCATION = [
        'high_school' => 'High School',
        'tesda' => 'TESDA',
        ...self::HIGHER_EDUCATION,
    ];

    public function get(?int $campusId = null, ?int $year = null): ?array
    {
        $warehouse = DB::connection('qams_warehouse');
        $runs = $warehouse->table('hrmis_warehouse_sync_runs')->where('status', 'succeeded');
        $years = (clone $runs)
            ->selectRaw('DISTINCT SUBSTR(snapshot_date, 1, 4) AS snapshot_year')
            ->orderByDesc('snapshot_year')
            ->pluck('snapshot_year')
            ->map(fn ($snapshotYear) => (int) $snapshotYear);
        $year = $years->containsStrict($year) ? $year : $years->first();
        if ($year === null) {
            return null;
        }

        $snapshot = $runs
            ->whereYear('snapshot_date', $year)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first(['id', 'snapshot_date']);

        if ($snapshot === null) {
            return null;
        }

        $facts = $warehouse->table('fact_hrmis_employee_snapshot as f')
            ->join('dim_hrmis_source_campus as campus', 'f.campus_id', '=', 'campus.id')
            ->where('f.sync_run_id', $snapshot->id)
            ->whereIn('campus.source_campus_id', array_keys(config('hrmis.campus_codes')));
        $campuses = (clone $facts)
            ->distinct()
            ->orderBy('campus.source_campus_label')
            ->get(['campus.id', 'campus.source_campus_label']);
        $campusId = $campuses->contains(fn ($campus) => (int) $campus->id === $campusId) ? $campusId : null;

        $flags = [
            'is_senior_academic_staff', 'is_pwd', 'is_teaching_personnel', 'is_non_teaching',
            'is_part_time_teaching', ...array_keys(self::NON_TEACHING),
        ];
        $groupColumns = [
            'position.position_category', 'position.position_title', 'status.source_status',
            'education.achievement_code', 'gender.gender_code',
            ...array_map(fn ($flag) => 'f.'.$flag, $flags),
        ];
        $groups = $facts
            ->when($campusId !== null, fn ($query) => $query->where('f.campus_id', $campusId))
            ->leftJoin('dim_hrmis_position as position', 'f.position_id', '=', 'position.id')
            ->join('dim_hrmis_employment_status as status', 'f.employment_status_id', '=', 'status.id')
            ->join('dim_hrmis_education as education', 'f.education_id', '=', 'education.id')
            ->join('dim_hrmis_gender as gender', 'f.gender_id', '=', 'gender.id')
            ->select($groupColumns)
            ->selectRaw('SUM(f.employee_count) AS employees')
            ->groupBy($groupColumns)
            ->get();

        $workforce = $groups->filter(fn ($group) => $group->is_teaching_personnel || $group->is_non_teaching);
        $teaching = $groups->where('is_teaching_personnel', true);
        $nonTeaching = $groups->where('is_non_teaching', true);
        $genderMetric = static function ($label, $rows): array {
            $total = (int) $rows->sum('employees');
            $male = (int) $rows->where('gender_code', 'male')->sum('employees');
            $female = (int) $rows->where('gender_code', 'female')->sum('employees');

            return ['label' => $label, 'total' => $total, 'male' => $male, 'female' => $female, 'unknown_gender' => $total - $male - $female];
        };
        $academic = $teaching->filter(fn ($group) =>
            in_array($this->employmentCategory($group->source_status), ['Permanent', 'Temporary'], true)
        );
        $senior = $workforce->map(function ($group) {
            $group->senior_role = $this->seniorLeadershipRole($group->position_title)
                ?? ($group->is_senior_academic_staff
                    ? (self::TEACHING_RANKS[$group->position_category] ?? 'Unspecified Senior Rank')
                    : null);

            return $group;
        })->whereNotNull('senior_role');
        $seniorByRole = $senior->groupBy('senior_role');
        $seniorRoles = array_diff(array_unique([
            'Associate Professors', 'Professors', 'University Professor',
            ...array_keys(self::SENIOR_LEADERSHIP), ...$seniorByRole->keys()->all(),
        ]), ['Deputy Vice-Chancellors', 'Vice-Chancellors', 'Chancellors', 'Rectors']);
        $summary = [];
        foreach ([
            ['Number of Employees', 'ri-team-line', $workforce],
            ['Academic Staff', 'ri-graduation-cap-line', $academic],
            ['Senior Academic Staff', 'ri-award-line', $senior],
            ['Employees With Disability', 'ri-wheelchair-line', $workforce->where('is_pwd', true)],
        ] as [$label, $icon, $rows]) {
            $summary[] = [
                ...$genderMetric($label, $rows),
                'icon' => $icon,
            ];
        }
        $educationMetrics = static function (array $levels, $rows): array {
            $counts = $rows->groupBy('achievement_code')->map(fn ($group) => (int) $group->sum('employees'));

            return array_map(
                fn ($code, $label) => ['label' => $label, 'total' => $counts->get($code, 0)],
                array_keys($levels),
                array_values($levels)
            );
        };

        return [
            'snapshot_id' => (int) $snapshot->id,
            'snapshot_date' => $snapshot->snapshot_date,
            'year' => $year,
            'years' => $years,
            'campus_id' => $campusId,
            'campuses' => $campuses,
            'summary' => $summary,
            'overall_gender' => [
                $genderMetric('Teaching Gender', $teaching),
                $genderMetric('Non-Teaching Gender', $nonTeaching),
            ],
            'senior_roles' => array_values(array_map(
                fn ($role) => $genderMetric($role, $seniorByRole->get($role, collect())),
                $seniorRoles
            )),
            'unclassified_personnel' => (int) ($groups->sum('employees') - $workforce->sum('employees')),
            'sections' => [
                [
                    'title' => 'Teaching Personnel',
                    'gender' => [
                        ...array_map(
                            fn ($rank, $label) => $genderMetric($label, $teaching->where('position_category', $rank)),
                            array_keys(self::TEACHING_RANKS),
                            array_values(self::TEACHING_RANKS)
                        ),
                        $genderMetric('Part-time Teaching Personnel', $teaching->where('is_part_time_teaching', true)),
                    ],
                    'education' => $educationMetrics(self::HIGHER_EDUCATION, $teaching),
                ],
                [
                    'title' => 'Non-Teaching Personnel',
                    'gender' => array_map(
                        fn ($flag, $label) => $genderMetric($label, $nonTeaching->where($flag, true)),
                        array_keys(self::NON_TEACHING),
                        array_values(self::NON_TEACHING)
                    ),
                    'education' => $educationMetrics(self::NON_TEACHING_EDUCATION, $nonTeaching),
                ],
            ],
        ];
    }

    private function employmentCategory(string $status): string
    {
        $status = trim(preg_replace('/[^a-z0-9]+/i', ' ', strtolower($status)) ?? '');

        return match (true) {
            preg_match('/\bco\s?terminous\b/', $status) === 1 => 'Co-terminous',
            preg_match('/\b(?:contract of services?|cos)\b/', $status) === 1 => 'Contract of Service (COS)',
            $status === 'job', preg_match('/\bjob order\b/', $status) === 1 => 'Job Order',
            preg_match('/\bcontractual\b/', $status) === 1 => 'Contractual',
            preg_match('/\bcasual\b/', $status) === 1 => 'Casual',
            preg_match('/\btemporary\b/', $status) === 1 => 'Temporary',
            preg_match('/\bpermanent\b/', $status) === 1 => 'Permanent',
            preg_match('/\b(?:part\s*time(?:r)?|visiting instructor)\b/', $status) === 1 => 'Part-time',
            default => 'Unknown Emp Status',
        };
    }

    private function seniorLeadershipRole(?string $title): ?string
    {
        $title = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $title ?? '') ?? '');
        if (preg_match('/\b(?:assistant to|secretary to|office of)\b/', $title) === 1) {
            return null;
        }

        foreach (self::SENIOR_LEADERSHIP as $role => $pattern) {
            if (preg_match($pattern, $title) === 1) {
                return $role;
            }
        }

        return null;
    }
}
