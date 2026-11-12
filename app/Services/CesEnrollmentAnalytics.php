<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;

class CesEnrollmentAnalytics
{
    private const CURRENT_GRADE_TABLE = 'grades20261';

    private const CURRENT_GRADE_YEAR = '2026';

    private const SUMMARY_METRICS = [
        ['key' => 'total-enrolled-students', 'label' => 'Total Enrolled Students', 'icon' => 'ri ri-graduation-cap-line'],
        ['key' => 'graduating-enrollees', 'label' => 'Graduating Enrollees', 'icon' => 'ri ri-award-line'],
        ['key' => 'new-freshman', 'label' => 'New Freshman', 'icon' => 'ri ri-user-add-line'],
    ];

    private const DEMOGRAPHIC_METRICS = [
        ['key' => 'international-students', 'label' => 'International Students', 'column' => 'international'],
        ['key' => '4ps-beneficiaries', 'label' => "4P's Beneficiaries", 'column' => 'four_ps'],
        ['key' => 'children-of-ofws', 'label' => 'Children of OFWs'],
        ['key' => 'persons-deprived-of-liberty', 'label' => 'Persons Deprived of Liberty'],
        ['key' => 'special-needs', 'label' => 'With Special Needs'],
        ['key' => 'working-students', 'label' => 'Working Students'],
        ['key' => 'indigenous-people', 'label' => 'Indigenous People'],
        ['key' => 'solo-parents', 'label' => 'Solo Parents'],
        ['key' => 'persons-with-disabilities', 'label' => 'Persons with Disabilities', 'column' => 'disability'],
        ['key' => 'first-generation-learners', 'label' => 'First Generation Learners'],
    ];

    /**
     * @param  array{campus?: string|null, college?: string|null, program?: string|null, year?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters = []): array
    {
        $filters = [
            'campus' => $filters['campus'] ?? null,
            'college' => $filters['college'] ?? null,
            'program' => $filters['program'] ?? null,
            'year' => $filters['year'] ?? null,
        ];
        $cacheKey = 'ces.analytics.v3.'.sha1(json_encode($filters, JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, now()->addSeconds(30), fn () => $this->load($filters));
    }

    /**
     * @param  array{campus: string|null, college: string|null, program: string|null, year: string|null}  $filters
     * @return array<string, mixed>
     */
    private function load(array $filters): array
    {
        $allCampuses = config('ces.campuses', []);
        $campuses = $filters['campus'] === null
            ? $allCampuses
            : array_intersect_key($allCampuses, [$filters['campus'] => true]);
        $years = [];
        $catalog = [];
        $unavailable = [];

        foreach ($campuses as $key => $campus) {
            $database = $campus['database'];

            try {
                DB::connection(config('ces.database_connection'))
                    ->table("{$database}.".self::CURRENT_GRADE_TABLE)
                    ->limit(1)
                    ->get(['StudentNo']);

                $years[] = self::CURRENT_GRADE_YEAR;

                $catalog = [
                    ...$catalog,
                    ...DB::connection(config('ces.database_connection'))
                        ->table("{$database}.course as c")
                        ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
                        ->whereNotNull('c.course_title')
                        ->select([
                            'c.course_title as program',
                            'd.CollegeName as college_name',
                            'd.DepartmentName as department_name',
                            'd.id as department_id',
                        ])
                        ->distinct()
                        ->get()
                        ->map(fn ($row) => (array) $row)
                        ->all(),
                ];
            } catch (QueryException|PDOException $exception) {
                Log::warning('CES filter options could not be loaded for a campus.', [
                    'campus' => $key,
                    'exception' => $exception::class,
                ]);
                $unavailable[$key] = true;
            }
        }

        $years = array_values(array_unique($years));
        rsort($years, SORT_NUMERIC);

        $selectedYear = $filters['year'];
        if ($selectedYear !== null && ! in_array($selectedYear, $years, true)) {
            $selectedYear = null;
        }
        if ($selectedYear === null && $years !== []) {
            $selectedYear = $years[0];
        }

        $collegeOptions = [];
        foreach ($catalog as $item) {
            $college = $this->collegeValue($item);
            if ($college !== null) {
                $collegeOptions[$college] = ['value' => $college, 'label' => $college];
            }
        }
        uasort($collegeOptions, fn ($left, $right) => strnatcasecmp($left['label'], $right['label']));

        $programOptions = [];
        foreach ($catalog as $item) {
            $college = $this->collegeValue($item);
            if (
                is_string($item['program'] ?? null)
                && trim($item['program']) !== ''
                && ($filters['college'] === null || $college === $filters['college'])
            ) {
                $program = trim($item['program']);
                $programOptions[$program] = ['value' => $program, 'label' => $program];
            }
        }
        uasort($programOptions, fn ($left, $right) => strnatcasecmp($left['label'], $right['label']));

        $summaryAliases = [
            'total-enrolled-students' => 'total_enrolled_students',
            'graduating-enrollees' => 'graduating_enrollees',
            'new-freshman' => 'new_freshman',
        ];
        $summary = array_fill_keys(array_column(self::SUMMARY_METRICS, 'key'), 0);
        $demographics = [];
        foreach (self::DEMOGRAPHIC_METRICS as $metric) {
            $demographics[$metric['key']] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'available' => isset($metric['column']),
                'total' => null,
                'male' => null,
                'female' => null,
            ];
        }

        $campusStatuses = [];
        foreach ($campuses as $key => $campus) {
            if (isset($unavailable[$key])) {
                $campusStatuses[] = ['key' => $key, 'name' => $campus['name'], 'status' => 'unavailable'];

                continue;
            }

            try {
                $counts = $this->queryCampus($campus['database'], $filters, $selectedYear);
                foreach ($summaryAliases as $metric => $column) {
                    $summary[$metric] += (int) ($counts[$column] ?? 0);
                }

                foreach (self::DEMOGRAPHIC_METRICS as $metric) {
                    if (! isset($metric['column'])) {
                        continue;
                    }

                    $prefix = $metric['column'];
                    $demographics[$metric['key']]['total'] += (int) ($counts["{$prefix}_total"] ?? 0);
                    $demographics[$metric['key']]['male'] += (int) ($counts["{$prefix}_male"] ?? 0);
                    $demographics[$metric['key']]['female'] += (int) ($counts["{$prefix}_female"] ?? 0);
                }

                $campusStatuses[] = ['key' => $key, 'name' => $campus['name'], 'status' => 'available'];
            } catch (QueryException|PDOException $exception) {
                Log::warning('CES enrollment analytics could not be loaded for a campus.', [
                    'campus' => $key,
                    'exception' => $exception::class,
                ]);
                $unavailable[$key] = true;
                $campusStatuses[] = ['key' => $key, 'name' => $campus['name'], 'status' => 'unavailable'];
            }
        }

        $summaryMetrics = array_map(
            fn ($metric) => [...$metric, 'value' => $summary[$metric['key']]],
            self::SUMMARY_METRICS
        );
        $partial = $unavailable !== [];

        return [
            'summary' => $summaryMetrics,
            'demographics' => array_values($demographics),
            'filters' => [
                'campus' => array_map(
                    fn ($key, $campus) => ['value' => $key, 'label' => $campus['name'].' Campus'],
                    array_keys($allCampuses),
                    $allCampuses
                ),
                'college' => array_values($collegeOptions),
                'program' => array_values($programOptions),
                'year' => array_map(
                    fn ($year) => ['value' => $year, 'label' => $year.'–'.((int) $year + 1)],
                    $years
                ),
            ],
            'selected' => [
                'campus' => $filters['campus'],
                'college' => $filters['college'],
                'program' => $filters['program'],
                'year' => $selectedYear,
            ],
            'campuses' => $campusStatuses,
            'partial' => $partial,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array{campus: string|null, college: string|null, program: string|null, year: string|null}  $filters
     * @return array<string, int>
     */
    private function queryCampus(string $database, array $filters, ?string $year): array
    {
        $connection = DB::connection(config('ces.database_connection'));

        $registrationQuery = $connection
            ->table("{$database}.registration as r")
            ->leftJoin("{$database}.students as s", 'r.StudentNo', '=', 's.StudentNo')
            ->leftJoin("{$database}.students2 as s2", 'r.StudentNo', '=', 's2.StudentNo')
            ->leftJoin("{$database}.course as c", 'r.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->where('r.finalize', 1)
            ->whereNotNull('r.StudentNo');

        if ($year !== null) {
            $registrationQuery->where('r.SchoolYear', $year);
        }
        if ($filters['college'] !== null) {
            $registrationQuery->whereRaw($this->collegeExpression().' = ?', [$filters['college']]);
        }
        if ($filters['program'] !== null) {
            $registrationQuery->where('c.course_title', $filters['program']);
        }

        $registrationCounts = (array) $registrationQuery->selectRaw(<<<'SQL'
            COUNT(DISTINCT CASE WHEN LOWER(TRIM(COALESCE(r.StudentStatus, ''))) = 'new' THEN r.StudentNo END) AS new_freshman,
            COUNT(DISTINCT CASE WHEN r.isGraduating = 1 THEN r.StudentNo END) AS graduating_enrollees
            SQL)->first();

        $gradeQuery = $connection
            ->table("{$database}.".self::CURRENT_GRADE_TABLE.' as g')
            ->leftJoin("{$database}.students as s", 'g.StudentNo', '=', 's.StudentNo')
            ->leftJoin("{$database}.students2 as s2", 'g.StudentNo', '=', 's2.StudentNo')
            ->leftJoin("{$database}.course as c", 's.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->whereNotNull('g.StudentNo')
            ->whereRaw("TRIM(CAST(g.StudentNo AS CHAR)) <> ''");

        if ($filters['college'] !== null) {
            $gradeQuery->whereRaw($this->collegeExpression().' = ?', [$filters['college']]);
        }
        if ($filters['program'] !== null) {
            $gradeQuery->where('c.course_title', $filters['program']);
        }

        $gradeCounts = (array) $gradeQuery->selectRaw(<<<'SQL'
            COUNT(DISTINCT g.StudentNo) AS total_enrolled_students,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s.nationality, ''))) <> ''
                AND LOWER(TRIM(s.nationality)) NOT LIKE '%filipin%'
                AND LOWER(TRIM(s.nationality)) <> 'philippines'
                THEN g.StudentNo END) AS international_total,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s.nationality, ''))) <> ''
                AND LOWER(TRIM(s.nationality)) NOT LIKE '%filipin%'
                AND LOWER(TRIM(s.nationality)) <> 'philippines'
                AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('m', 'male')
                THEN g.StudentNo END) AS international_male,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s.nationality, ''))) <> ''
                AND LOWER(TRIM(s.nationality)) NOT LIKE '%filipin%'
                AND LOWER(TRIM(s.nationality)) <> 'philippines'
                AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('f', 'female')
                THEN g.StudentNo END) AS international_female,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s2.Family4Ps, ''))) IN ('yes', 'y', 'true', '1', '4ps')
                THEN g.StudentNo END) AS four_ps_total,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s2.Family4Ps, ''))) IN ('yes', 'y', 'true', '1', '4ps')
                AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('m', 'male')
                THEN g.StudentNo END) AS four_ps_male,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s2.Family4Ps, ''))) IN ('yes', 'y', 'true', '1', '4ps')
                AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('f', 'female')
                THEN g.StudentNo END) AS four_ps_female,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s.Disability, ''))) NOT IN ('', 'no', 'none', 'n/a', 'na', '0', 'false', 'nil')
                THEN g.StudentNo END) AS disability_total,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s.Disability, ''))) NOT IN ('', 'no', 'none', 'n/a', 'na', '0', 'false', 'nil')
                AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('m', 'male')
                THEN g.StudentNo END) AS disability_male,
            COUNT(DISTINCT CASE WHEN
                LOWER(TRIM(COALESCE(s.Disability, ''))) NOT IN ('', 'no', 'none', 'n/a', 'na', '0', 'false', 'nil')
                AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('f', 'female')
                THEN g.StudentNo END) AS disability_female
            SQL)->first();

        return [...$registrationCounts, ...$gradeCounts];
    }

    private function collegeValue(array $item): ?string
    {
        foreach (['college_name', 'department_name', 'department_id'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function collegeExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(d.CollegeName), ''), NULLIF(TRIM(d.DepartmentName), ''), CAST(d.id AS CHAR))";
    }
}
