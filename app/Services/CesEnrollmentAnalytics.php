<?php

namespace App\Services;

use App\Exceptions\CesWarehouseUnavailable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CesEnrollmentAnalytics
{
    private const SEMESTERS = [
        '1' => '1st Semester',
        '2' => '2nd Semester',
        '9' => 'Summer',
    ];

    private const SUMMARY_METRICS = [
        ['key' => 'total-enrolled-students', 'label' => 'Total Enrolled Students', 'icon' => 'ri ri-graduation-cap-line'],
        ['key' => 'graduating-enrollees', 'label' => 'Graduating Enrollees', 'icon' => 'ri ri-award-line'],
        ['key' => 'already-graduated-students', 'label' => 'Already Graduated Students', 'icon' => 'ri ri-medal-line'],
        ['key' => 'new-freshman', 'label' => 'New Freshman', 'icon' => 'ri ri-user-add-line'],
    ];

    private const SCHOLARSHIP_TYPES = [
        'CHEDMerit',
        'CHEDTD',
        'TES',
        'Subsidy',
        'Financial Assistance',
        'DOST',
        'BFAR Scholarship Grant',
        '4Ps',
        'ANCOP',
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
        ['key' => 'first-generation-learners', 'label' => 'First Generation Learners', 'column' => 'first_generation'],
        [
            'key' => 'financial-aid-recipients',
            'label' => 'Students Receiving Financial Aid',
            'count_key' => 'financial_aid',
            'icon' => 'ri ri-hand-heart-line',
            'note' => 'Finalized registrations with a scholarship, TES, or UniFAST marker.',
        ],
        [
            'key' => 'household-income',
            'label' => 'Students with Reported Annual Income',
            'count_key' => 'household_income',
            'icon' => 'ri ri-money-dollar-circle-line',
        ],
        ['key' => 'international-students-developing-countries', 'label' => 'International Students from Developing Countries'],
    ];

    /**
     * @param  array{campus?: string|null, college?: string|null, program?: string|null, year?: string|null, semester?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters = []): array
    {
        $filters = [
            'campus' => $filters['campus'] ?? null,
            'college' => $filters['college'] ?? null,
            'program' => $filters['program'] ?? null,
            'year' => $filters['year'] ?? null,
            'semester' => $filters['semester'] ?? null,
        ];
        $warehouse = DB::connection(config('ces.warehouse_connection'));
        $syncRun = $warehouse->table('ces_warehouse_sync_runs')
            ->where('status', 'succeeded')
            ->orderByDesc('id')
            ->first();

        if ($syncRun === null) {
            throw new CesWarehouseUnavailable(
                'CES warehouse has not been successfully synchronized. Run php artisan ces:sync-warehouse first.'
            );
        }

        $allCampuses = config('ces.campuses', []);
        $campuses = $filters['campus'] === null
            ? $allCampuses
            : array_intersect_key($allCampuses, [$filters['campus'] => true]);
        $warehouseCampuses = $warehouse->table('dim_campus')->get()->keyBy('campus_code');
        $syncStatuses = $warehouse->table('ces_warehouse_campus_syncs')
            ->where('run_id', $syncRun->id)
            ->get()
            ->keyBy('campus_id');
        $campusIds = [];
        $campusIdByKey = [];

        foreach ($campuses as $key => $_campus) {
            $warehouseCampus = $warehouseCampuses->get($key);
            if ($warehouseCampus === null) {
                continue;
            }
            $campusId = (int) $warehouseCampus->id;
            $campusIdByKey[$key] = $campusId;
            if (($syncStatuses->get($campusId)->status ?? null) === 'succeeded') {
                $campusIds[] = $campusId;
            }
        }
        $campusIds = array_values(array_unique($campusIds));

        $availableTerms = [];
        $periodsByCampus = [];
        $periodIds = [];
        if ($campusIds !== []) {
            $periodRows = $warehouse->table('ces_campus_academic_periods as cap')
                ->join('dim_academic_period as ap', 'cap.academic_period_id', '=', 'ap.id')
                ->whereIn('cap.campus_id', $campusIds)
                ->select([
                    'cap.campus_id',
                    'cap.has_grade_table',
                    'cap.has_stage_table',
                    'cap.has_graduation_period',
                    'ap.id as period_id',
                    'ap.academic_year',
                    'ap.semester',
                ])->get();

            foreach ($periodRows as $period) {
                $year = (string) $period->academic_year;
                $semester = (string) $period->semester;
                $periodKey = "$year-$semester";
                $periodsByCampus[(int) $period->campus_id][$periodKey] = [
                    'has_grade_table' => (bool) $period->has_grade_table,
                    'has_stage_table' => (bool) $period->has_stage_table,
                    'has_graduation_period' => (bool) $period->has_graduation_period,
                ];
                $periodIds[$periodKey] = (int) $period->period_id;
                if ($period->has_grade_table || $period->has_stage_table || $period->has_graduation_period) {
                    $availableTerms[$year][$semester] = true;
                }
            }
        }

        $years = array_map('strval', array_keys($availableTerms));
        rsort($years, SORT_NUMERIC);
        $selectedYear = $filters['year'];
        if ($selectedYear !== null && ! in_array($selectedYear, $years, true)) {
            $selectedYear = null;
        }
        if ($selectedYear === null && $years !== []) {
            $selectedYear = $years[0];
        }

        $semesters = array_map('strval', array_keys($availableTerms[$selectedYear] ?? []));
        sort($semesters, SORT_NUMERIC);
        $selectedSemester = $filters['semester'];
        if ($selectedSemester !== null && ! in_array($selectedSemester, $semesters, true)) {
            $selectedSemester = null;
        }
        if ($selectedSemester === null && $semesters !== []) {
            $selectedSemester = in_array('1', $semesters, true) ? '1' : $semesters[count($semesters) - 1];
        }

        $catalog = $this->queryCatalog($warehouse, $campusIds);
        $collegeOptions = [];
        foreach ($catalog as $item) {
            $college = trim((string) $item->college_name);
            if ($college !== '') {
                $collegeOptions[$college] = ['value' => $college, 'label' => $college];
            }
        }
        uasort($collegeOptions, fn ($left, $right) => strnatcasecmp($left['label'], $right['label']));

        $programOptions = [];
        foreach ($catalog as $item) {
            $college = trim((string) $item->college_name);
            $program = trim((string) $item->course_title);
            if ($program !== '' && ($filters['college'] === null || $college === $filters['college'])) {
                $programOptions[$program] = ['value' => $program, 'label' => $program];
            }
        }
        uasort($programOptions, fn ($left, $right) => strnatcasecmp($left['label'], $right['label']));

        $incomeCampuses = [];
        foreach ($campuses as $key => $campus) {
            $warehouseCampus = $warehouseCampuses->get($key);
            if ($warehouseCampus !== null && (bool) $warehouseCampus->income_available) {
                $incomeCampuses[$key] = $campus;
            }
        }
        $selectedPeriodKey = $selectedYear !== null && $selectedSemester !== null
            ? "$selectedYear-$selectedSemester"
            : null;
        $selectedPeriodId = $selectedPeriodKey === null ? null : ($periodIds[$selectedPeriodKey] ?? null);
        $hasEnrollmentData = false;
        $graduationUnavailable = [];
        $campusStatuses = [];

        foreach ($campuses as $key => $campus) {
            $warehouseCampus = $warehouseCampuses->get($key);
            $campusId = $campusIdByKey[$key] ?? null;
            $status = $campusId !== null
                ? ($syncStatuses->get($campusId)->status ?? 'unavailable')
                : 'unavailable';
            $availability = $campusId !== null && $selectedPeriodKey !== null
                ? ($periodsByCampus[$campusId][$selectedPeriodKey] ?? [])
                : [];
            $hasStage = $status === 'succeeded' && ($availability['has_stage_table'] ?? false);
            $hasEnrollmentData = $hasEnrollmentData
                || ($status === 'succeeded' && ($availability['has_grade_table'] ?? false));

            if (! $hasStage || $status !== 'succeeded') {
                $graduationUnavailable[$key] = true;
            }
            $campusStatuses[] = [
                'key' => $key,
                'name' => $campus['name'],
                'status' => $status === 'succeeded' ? 'available' : 'unavailable',
                'graduation_status' => $hasStage ? 'available' : 'unavailable',
                'income_status' => ($warehouseCampus?->income_available ?? false) ? 'available' : 'unavailable',
            ];
        }

        $summary = array_fill_keys(array_column(self::SUMMARY_METRICS, 'key'), 0);
        $studentGender = null;
        $demographics = $this->emptyDemographics($incomeCampuses);
        $scholarshipTotals = array_fill_keys(self::SCHOLARSHIP_TYPES, 0);
        $aidBreakdown = array_fill_keys([...self::SCHOLARSHIP_TYPES, 'Multiple Scholarships', 'Other or Unspecified Aid'], 0);
        if ($selectedPeriodId !== null && $campusIds !== []) {
            $enrollmentQuery = $this->enrollmentQuery($warehouse, $campusIds, $selectedPeriodId, $filters);
            $enrollmentCounts = (clone $enrollmentQuery)->selectRaw(<<<'SQL'
                COUNT(DISTINCT f.student_id) AS total_enrolled_students,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(COALESCE(s.sex, ''))) IN ('m', 'male')
                    THEN f.student_id END) AS enrolled_male,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(COALESCE(s.sex, ''))) IN ('f', 'female')
                    THEN f.student_id END) AS enrolled_female,
                COUNT(DISTINCT CASE WHEN
                    LOWER(TRIM(COALESCE(s.nationality, ''))) <> ''
                    AND LOWER(TRIM(s.nationality)) NOT LIKE '%filipin%'
                    AND LOWER(TRIM(s.nationality)) <> 'philippines'
                    THEN f.student_id END) AS international_total,
                COUNT(DISTINCT CASE WHEN
                    LOWER(TRIM(COALESCE(s.nationality, ''))) <> ''
                    AND LOWER(TRIM(s.nationality)) NOT LIKE '%filipin%'
                    AND LOWER(TRIM(s.nationality)) <> 'philippines'
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('m', 'male')
                    THEN f.student_id END) AS international_male,
                COUNT(DISTINCT CASE WHEN
                    LOWER(TRIM(COALESCE(s.nationality, ''))) <> ''
                    AND LOWER(TRIM(s.nationality)) NOT LIKE '%filipin%'
                    AND LOWER(TRIM(s.nationality)) <> 'philippines'
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('f', 'female')
                    THEN f.student_id END) AS international_female,
                COUNT(DISTINCT CASE WHEN s.is_four_ps_beneficiary = 1 THEN f.student_id END) AS four_ps_total,
                COUNT(DISTINCT CASE WHEN s.is_four_ps_beneficiary = 1
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('m', 'male')
                    THEN f.student_id END) AS four_ps_male,
                COUNT(DISTINCT CASE WHEN s.is_four_ps_beneficiary = 1
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('f', 'female')
                    THEN f.student_id END) AS four_ps_female,
                COUNT(DISTINCT CASE WHEN
                    LOWER(TRIM(COALESCE(s.disability, ''))) NOT IN ('', 'no', 'none', 'n/a', 'na', '0', 'false', 'nil')
                    THEN f.student_id END) AS disability_total,
                COUNT(DISTINCT CASE WHEN
                    LOWER(TRIM(COALESCE(s.disability, ''))) NOT IN ('', 'no', 'none', 'n/a', 'na', '0', 'false', 'nil')
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('m', 'male')
                    THEN f.student_id END) AS disability_male,
                COUNT(DISTINCT CASE WHEN
                    LOWER(TRIM(COALESCE(s.disability, ''))) NOT IN ('', 'no', 'none', 'n/a', 'na', '0', 'false', 'nil')
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('f', 'female')
                    THEN f.student_id END) AS disability_female,
                COUNT(DISTINCT CASE WHEN s.is_first_generation = 1 THEN f.student_id END) AS first_generation_total,
                COUNT(DISTINCT CASE WHEN s.is_first_generation = 1
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('m', 'male')
                    THEN f.student_id END) AS first_generation_male,
                COUNT(DISTINCT CASE WHEN s.is_first_generation = 1
                    AND LOWER(TRIM(COALESCE(s.sex, ''))) IN ('f', 'female')
                    THEN f.student_id END) AS first_generation_female,
                COUNT(DISTINCT CASE WHEN c.income_available = 1 AND s.annual_income IS NOT NULL
                    THEN f.student_id END) AS household_income_total
                SQL)->first();

            $summary['total-enrolled-students'] = $hasEnrollmentData
                ? (int) ($enrollmentCounts->total_enrolled_students ?? 0)
                : null;
            if ($hasEnrollmentData) {
                $male = (int) $enrollmentCounts->enrolled_male;
                $female = (int) $enrollmentCounts->enrolled_female;
                $studentGender = [
                    'male' => $male,
                    'female' => $female,
                    'unknown_gender' => $summary['total-enrolled-students'] - $male - $female,
                ];
            }
            $this->populateGenderMetric($demographics, 'international-students', $enrollmentCounts, 'international');
            $this->populateGenderMetric($demographics, '4ps-beneficiaries', $enrollmentCounts, 'four_ps');
            $this->populateGenderMetric($demographics, 'persons-with-disabilities', $enrollmentCounts, 'disability');
            $this->populateGenderMetric($demographics, 'first-generation-learners', $enrollmentCounts, 'first_generation');
            if ($incomeCampuses !== []) {
                $demographics['household-income']['total'] = (int) ($enrollmentCounts->household_income_total ?? 0);
            }

            $graduationQuery = $this->graduationQuery($warehouse, $campusIds, $selectedPeriodId, $filters)
                ->where('f.is_graduation_applicant', true);
            $summary['graduating-enrollees'] = (int) (clone $graduationQuery)->distinct()->count('f.student_id');

            $financialAidQuery = $this->registrationQuery($warehouse, $campusIds, $selectedPeriodId, $filters)
                ->where('f.is_finalized', true)
                ->whereRaw(<<<'SQL'
                    (
                        (
                            NULLIF(TRIM(COALESCE(f.source_scholarship_id, '')), '') IS NOT NULL
                            AND LOWER(TRIM(f.source_scholarship_id)) NOT IN ('0', 'none', 'no', 'null')
                        )
                        OR CAST(COALESCE(f.tes_value, '0') AS DECIMAL(14, 2)) <> 0
                        OR CAST(COALESCE(f.unifast_paid_value, '0') AS DECIMAL(14, 2)) <> 0
                    )
                    SQL);
            $demographics['financial-aid-recipients']['total'] = (int) (clone $financialAidQuery)
                ->distinct()
                ->count('f.student_id');

            $scholarshipRows = (clone $this->registrationQuery($warehouse, $campusIds, $selectedPeriodId, $filters))
                ->join('dim_ces_scholarship as scholarship', 'f.scholarship_id', '=', 'scholarship.id')
                ->where('f.is_finalized', true)
                ->whereIn('scholarship.canonical_name', self::SCHOLARSHIP_TYPES)
                ->selectRaw('scholarship.canonical_name, COUNT(DISTINCT f.student_id) AS recipients')
                ->groupBy('scholarship.canonical_name')
                ->get();
            foreach ($scholarshipRows as $row) {
                $scholarshipTotals[$row->canonical_name] = (int) $row->recipients;
            }

            $aidScholarshipsByStudent = (clone $financialAidQuery)
                ->join('dim_ces_scholarship as scholarship', 'f.scholarship_id', '=', 'scholarship.id')
                ->whereIn('scholarship.canonical_name', self::SCHOLARSHIP_TYPES)
                ->selectRaw('MIN(scholarship.canonical_name) AS scholarship_name, COUNT(DISTINCT scholarship.canonical_name) AS scholarship_count')
                ->groupBy('f.student_id');
            $aidScholarshipGroups = $warehouse->query()
                ->fromSub($aidScholarshipsByStudent, 'student_aid')
                ->selectRaw('scholarship_name, scholarship_count, COUNT(*) AS recipients')
                ->groupBy('scholarship_name', 'scholarship_count')
                ->get();
            foreach ($aidScholarshipGroups as $group) {
                $name = (int) $group->scholarship_count > 1 ? 'Multiple Scholarships' : $group->scholarship_name;
                $aidBreakdown[$name] += (int) $group->recipients;
            }
            $aidBreakdown['Other or Unspecified Aid'] = $demographics['financial-aid-recipients']['total'] - array_sum($aidBreakdown);
        } else {
            $summary['total-enrolled-students'] = null;
        }

        if ($selectedYear !== null && $campusIds !== []) {
            $summary['already-graduated-students'] = (int) $this->graduationQuery($warehouse, $campusIds, null, $filters)
                ->where('f.is_graduation_applicant', false)
                ->where('ap.academic_year', $selectedYear)
                ->distinct()
                ->count('f.student_id');
            $summary['new-freshman'] = (int) $this->registrationQuery($warehouse, $campusIds, null, $filters)
                ->where('f.is_finalized', true)
                ->where('f.source_school_year', $selectedYear)
                ->whereRaw("LOWER(TRIM(COALESCE(f.source_student_status, ''))) = 'new'")
                ->distinct()
                ->count('f.student_id');
        }

        $scholarships = array_map(
            fn ($name) => ['name' => $name, 'total' => $scholarshipTotals[$name]],
            self::SCHOLARSHIP_TYPES
        );
        $partial = $graduationUnavailable !== [];
        $completedAt = $syncRun->completed_at === null
            ? null
            : Carbon::parse($syncRun->completed_at)->toIso8601String();

        $trendPeriods = [];
        if ($selectedYear !== null && $selectedSemester !== null) {
            foreach ($years as $year) {
                $term = "$year-$selectedSemester";
                if ((int) $year > (int) $selectedYear || ! isset($periodIds[$term])) {
                    continue;
                }

                $coveredCampuses = count(array_filter(
                    $periodsByCampus, fn ($periods) => $periods[$term]['has_grade_table'] ?? false
                ));
                if ($coveredCampuses > 0) {
                    $trendPeriods[] = [
                        'id' => $periodIds[$term],
                        'year' => $year,
                        'partial' => $coveredCampuses < count($campuses),
                    ];
                }
                if (count($trendPeriods) === 5) {
                    break;
                }
            }
        }

        return [
            'summary' => array_map(
                fn ($metric) => [...$metric, 'value' => $summary[$metric['key']]],
                self::SUMMARY_METRICS
            ),
            'student_gender' => $studentGender,
            'enrollment_trend' => $this->enrollmentTrend($warehouse, $campusIds, array_reverse($trendPeriods), $filters),
            'demographics' => array_values($demographics),
            'scholarships' => $scholarships,
            'financial_aid_breakdown' => array_map(
                fn ($name, $total) => ['name' => $name, 'total' => $total],
                array_keys($aidBreakdown),
                array_values($aidBreakdown)
            ),
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
                'semester' => array_map(
                    fn ($semester) => ['value' => $semester, 'label' => self::SEMESTERS[$semester]],
                    $semesters
                ),
            ],
            'selected' => [
                'campus' => $filters['campus'],
                'college' => $filters['college'],
                'program' => $filters['program'],
                'year' => $selectedYear,
                'semester' => $selectedSemester,
            ],
            'campuses' => $campusStatuses,
            'graduation_unavailable_campuses' => array_values(array_map(
                fn ($key) => $campuses[$key]['name'],
                array_keys($graduationUnavailable)
            )),
            'graduation_discovery_failed' => false,
            'partial' => $partial,
            'updated_at' => $completedAt,
        ];
    }

    /**
     * @param  list<int>  $campusIds
     * @return list<object>
     */
    private function queryCatalog(Connection $warehouse, array $campusIds): array
    {
        if ($campusIds === []) {
            return [];
        }

        return $warehouse->table('dim_ces_program')
            ->whereIn('campus_id', $campusIds)
            ->whereNotNull('college_name')
            ->where('college_name', '<>', '')
            ->whereNotNull('course_title')
            ->where('course_title', '<>', '')
            ->select(['college_name', 'course_title'])
            ->distinct()
            ->get()
            ->all();
    }

    /**
     * @param  list<int>  $campusIds
     * @param  array{campus: string|null, college: string|null, program: string|null, year: string|null, semester: string|null}  $filters
     */
    private function enrollmentQuery(Connection $warehouse, array $campusIds, ?int $periodId, array $filters): Builder
    {
        return $this->applyProgramFilters($warehouse->table('fact_ces_enrollment as f')
            ->join('dim_ces_student as s', 'f.student_id', '=', 's.id')
            ->join('dim_campus as c', 'f.campus_id', '=', 'c.id')
            ->leftJoin('dim_ces_program as p', 'f.program_id', '=', 'p.id')
            ->whereIn('f.campus_id', $campusIds)
            ->when($periodId !== null, fn (Builder $query) => $query->where('f.academic_period_id', $periodId)), $filters);
    }

    private function enrollmentTrend(Connection $warehouse, array $campusIds, array $periods, array $filters): array
    {
        if ($periods === []) {
            return [];
        }

        $counts = $this->enrollmentQuery($warehouse, $campusIds, null, $filters)
            ->join('ces_campus_academic_periods as availability', function ($join) {
                $join->on('availability.campus_id', '=', 'f.campus_id')
                    ->on('availability.academic_period_id', '=', 'f.academic_period_id');
            })
            ->where('availability.has_grade_table', true)
            ->whereIn('f.academic_period_id', array_column($periods, 'id'))
            ->selectRaw('f.academic_period_id, COUNT(DISTINCT f.student_id) AS students')
            ->groupBy('f.academic_period_id')
            ->pluck('students', 'academic_period_id');

        return array_map(fn ($period) => [
            'label' => $period['year'].'–'.((int) $period['year'] + 1),
            'total' => (int) $counts->get($period['id'], 0),
            'partial' => $period['partial'],
        ], $periods);
    }

    /**
     * @param  list<int>  $campusIds
     * @param  array{campus: string|null, college: string|null, program: string|null, year: string|null, semester: string|null}  $filters
     */
    private function registrationQuery(Connection $warehouse, array $campusIds, ?int $periodId, array $filters): Builder
    {
        $query = $warehouse->table('fact_ces_registration as f')
            ->join('dim_ces_student as s', 'f.student_id', '=', 's.id')
            ->leftJoin('dim_academic_period as ap', 'f.academic_period_id', '=', 'ap.id')
            ->leftJoin('dim_ces_program as p', 'f.program_id', '=', 'p.id')
            ->whereIn('f.campus_id', $campusIds);
        if ($periodId !== null) {
            $query->where('f.academic_period_id', $periodId);
        }

        return $this->applyProgramFilters($query, $filters);
    }

    /**
     * @param  list<int>  $campusIds
     * @param  array{campus: string|null, college: string|null, program: string|null, year: string|null, semester: string|null}  $filters
     */
    private function graduationQuery(Connection $warehouse, array $campusIds, ?int $periodId, array $filters): Builder
    {
        $query = $warehouse->table('fact_ces_graduation as f')
            ->join('dim_ces_student as s', 'f.student_id', '=', 's.id')
            ->leftJoin('dim_academic_period as ap', 'f.academic_period_id', '=', 'ap.id')
            ->leftJoin('dim_ces_program as p', 'f.program_id', '=', 'p.id')
            ->whereIn('f.campus_id', $campusIds);
        if ($periodId !== null) {
            $query->where('f.academic_period_id', $periodId);
        }

        return $this->applyProgramFilters($query, $filters);
    }

    /**
     * @param  array{campus: string|null, college: string|null, program: string|null, year: string|null, semester: string|null}  $filters
     */
    private function applyProgramFilters(Builder $query, array $filters): Builder
    {
        if ($filters['college'] !== null) {
            $query->where('p.college_name', $filters['college']);
        }
        if ($filters['program'] !== null) {
            $query->where('p.course_title', $filters['program']);
        }

        return $query;
    }

    /**
     * @param  array<string, array<string, mixed>>  $demographics
     * @return array<string, array<string, mixed>>
     */
    private function emptyDemographics(array $incomeCampuses): array
    {
        $demographics = [];
        foreach (self::DEMOGRAPHIC_METRICS as $metric) {
            $genderBreakdown = isset($metric['column']);
            $countKey = $metric['count_key'] ?? null;
            $incomeCount = $countKey === 'household_income';
            $available = $genderBreakdown || $countKey === 'financial_aid' || ($incomeCount && $incomeCampuses !== []);
            $demographics[$metric['key']] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'available' => $available,
                'display' => $genderBreakdown ? 'gender' : 'count',
                'financial' => $countKey !== null,
                'total' => $available ? 0 : null,
                'male' => $genderBreakdown ? 0 : null,
                'female' => $genderBreakdown ? 0 : null,
                'note' => $incomeCount
                    ? ($incomeCampuses === []
                        ? 'Annual-income data is unavailable for this campus.'
                        : 'Counts include stored default value 1; income data is available only in '.implode(', ', array_column($incomeCampuses, 'name')).'.')
                    : ($metric['note'] ?? null),
            ];
        }

        return $demographics;
    }

    /**
     * @param  array<string, array<string, mixed>>  $demographics
     */
    private function populateGenderMetric(array &$demographics, string $key, object $counts, string $prefix): void
    {
        $demographics[$key]['total'] = (int) ($counts->{$prefix.'_total'} ?? 0);
        $demographics[$key]['male'] = (int) ($counts->{$prefix.'_male'} ?? 0);
        $demographics[$key]['female'] = (int) ($counts->{$prefix.'_female'} ?? 0);
    }
}
