<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CesAnalyticsSnapshotSynchronizer
{
    public function __construct(private readonly CesAnalyticsSnapshotStore $snapshots) {}

    public function sync(): void
    {
        $campuses = config('ces.campuses', []);
        $connection = DB::connection(config('ces.database_connection'));
        [$stageTables, $terms] = $this->discoverStageTables(array_column($campuses, 'database'));
        [$gradeTables, $gradeTerms] = $this->discoverGradeTables(array_column($campuses, 'database'));
        $incomeCampuses = $this->discoverIncomeCampuses(array_column($campuses, 'database'));
        $graduateTerms = [];
        $availableTerms = [];
        foreach ($terms as $campusTerms) {
            foreach ($campusTerms as $year => $semesters) {
                foreach (array_keys($semesters) as $semester) {
                    $availableTerms[(string) $year][(string) $semester] = true;
                }
            }
        }
        foreach ($campuses as $campus) {
            $database = $campus['database'];
            $graduateTerms[$database] = $this->queryGraduateTerms($database);
            foreach ($graduateTerms[$database] as $term) {
                $availableTerms[$term['year']][$term['semester']] = true;
            }
            foreach ($gradeTerms[$database] ?? [] as $year => $semesters) {
                foreach (array_keys($semesters) as $semester) {
                    $availableTerms[(string) $year][(string) $semester] = true;
                }
            }
        }

        $termList = [];
        foreach ($availableTerms as $year => $semesters) {
            foreach (array_keys($semesters) as $semester) {
                $termList[] = ['year' => (string) $year, 'semester' => (string) $semester];
            }
        }
        usort($termList, fn ($left, $right) => [$right['year'], $right['semester']] <=> [$left['year'], $left['semester']]);
        if ($termList === []) {
            $termList[] = ['year' => null, 'semester' => null];
        }

        $catalog = [];
        $graduates = [];
        $rows = [];
        $campusPayload = [];

        foreach ($campuses as $campusKey => $campus) {
            $database = $campus['database'];
            $catalogRows = $this->queryCatalog($database);
            foreach ($catalogRows as $item) {
                if ($item['college'] !== '' && $item['program'] !== '') {
                    $catalog[] = [
                        'campus' => $campusKey,
                        'college' => $item['college'],
                        'program' => $item['program'],
                    ];
                }
            }

            $hasIncome = isset($incomeCampuses[$database]);
            foreach ($this->queryAnnualGraduateCounts($database) as $graduate) {
                $graduates[] = [
                    'campus' => $campusKey,
                    ...$graduate,
                ];
            }
            $freshmanCounts = [];
            $campusTerms = $terms[$database] ?? [];
            $campusGradeTerms = $gradeTerms[$database] ?? [];
            $campusPayload[$campusKey] = [
                'name' => $campus['name'],
                'status' => 'available',
                'terms' => $campusTerms,
                'grade_terms' => $campusGradeTerms,
                'graduate_terms' => $graduateTerms[$database],
                'income_available' => $hasIncome,
            ];

            foreach ($termList as $term) {
                $year = $term['year'];
                $semester = $term['semester'];
                $gradeTable = $year !== null && $semester !== null
                    ? ($gradeTables[$database][$year][$semester] ?? null)
                    : null;
                $stageTable = $year !== null && $semester !== null
                    ? ($stageTables[$database][$year][$semester] ?? null)
                    : null;
                $gradeCounts = $gradeTable === null
                    ? []
                    : $this->queryGradeCounts($database, $gradeTable, $hasIncome);

                if ($year !== null && ! array_key_exists($year, $freshmanCounts)) {
                    $freshmanCounts[$year] = $this->queryFreshmanCounts($database, $year);
                }

                $termFreshmanCounts = $year === null ? [] : $freshmanCounts[$year];
                $aidCounts = $gradeTable !== null && $year !== null && $semester !== null
                    ? $this->queryFinancialAidCounts($database, $year, $semester)
                    : [];
                $scholarshipCounts = $gradeTable !== null && $year !== null && $semester !== null
                    ? $this->queryScholarshipCounts($database, $year, $semester)
                    : [];
                $graduationCounts = $stageTable === null
                    ? []
                    : $this->queryGraduationCounts($database, $stageTable);

                $groups = [];
                foreach ([$catalogRows, $gradeCounts, $termFreshmanCounts, $aidCounts, $scholarshipCounts, $graduationCounts] as $sourceRows) {
                    foreach ($sourceRows as $groupKey => $item) {
                        $groups[$groupKey] = $item;
                    }
                }

                foreach ($groups as $groupKey => $group) {
                    $grade = $gradeCounts[$groupKey] ?? [];
                    $freshman = $termFreshmanCounts[$groupKey] ?? [];
                    $aid = $aidCounts[$groupKey] ?? [];
                    $scholarships = $scholarshipCounts[$groupKey]['scholarships'] ?? [];
                    $graduation = $graduationCounts[$groupKey] ?? [];
                    $rows[] = [
                        'campus' => $campusKey,
                        'college' => $group['college'],
                        'program' => $group['program'],
                        'year' => $year,
                        'semester' => $semester,
                        'summary' => [
                            'total_enrolled_students' => $gradeTable === null
                                ? null
                                : (int) ($grade['total_enrolled_students'] ?? 0),
                            'graduating_enrollees' => $stageTable === null
                                ? null
                                : (int) ($graduation['graduating_enrollees'] ?? 0),
                            'new_freshman' => (int) ($freshman['new_freshman'] ?? 0),
                        ],
                        'demographics' => [
                            'international' => $gradeTable === null ? null : $this->demographicValues($grade, 'international'),
                            'four_ps' => $gradeTable === null ? null : $this->demographicValues($grade, 'four_ps'),
                            'disability' => $gradeTable === null ? null : $this->demographicValues($grade, 'disability'),
                            'first_generation' => $gradeTable === null ? null : $this->demographicValues($grade, 'first_generation'),
                            'financial_aid' => [
                                'total' => $gradeTable === null
                                    ? null
                                    : (int) ($aid['financial_aid_total'] ?? 0),
                                'scholarships' => $scholarships,
                            ],
                            'household_income' => [
                                'total' => $gradeTable === null || ! $hasIncome
                                    ? null
                                    : (int) ($grade['household_income_total'] ?? 0),
                            ],
                        ],
                    ];
                }
            }
        }

        $catalog = $this->uniqueCatalog($catalog);
        $payload = [
            'synced_at' => now()->toIso8601String(),
            'campuses' => $campusPayload,
            'catalog' => $catalog,
            'graduates' => $graduates,
            'rows' => $rows,
        ];

        $this->snapshots->replace($payload);
    }

    /**
     * @param  list<string>  $databases
     * @return array{0: array<string, array<string, array<string, string>>>, 1: array<string, array<string, array<string, bool>>>}
     */
    private function discoverStageTables(array $databases): array
    {
        $rows = DB::connection(config('ces.database_connection'))
            ->table('information_schema.tables')
            ->whereIn('table_schema', $databases)
            ->where('table_name', 'like', 'stage%')
            ->selectRaw('TABLE_SCHEMA AS table_schema, TABLE_NAME AS table_name')
            ->get();

        $stageTables = [];
        $terms = [];

        foreach ($rows as $row) {
            $table = array_change_key_case((array) $row, CASE_LOWER);
            $schema = $table['table_schema'] ?? null;
            $tableName = $table['table_name'] ?? null;

            if (
                ! is_string($schema)
                || ! is_string($tableName)
                || ! preg_match('/^stage(\d{4})([129])$/D', $tableName, $matches)
            ) {
                continue;
            }

            $year = $matches[1];
            $semester = $matches[2];
            $stageTables[$schema][$year][$semester] = $tableName;
            $terms[$schema][$year][$semester] = true;
        }

        return [$stageTables, $terms];
    }

    /**
     * @param  list<string>  $databases
     * @return array{0: array<string, array<string, array<string, string>>>, 1: array<string, array<string, array<string, bool>>>}
     */
    private function discoverGradeTables(array $databases): array
    {
        $rows = DB::connection(config('ces.database_connection'))
            ->table('information_schema.tables')
            ->whereIn('table_schema', $databases)
            ->where('table_name', 'like', 'grades%')
            ->selectRaw('TABLE_SCHEMA AS table_schema, TABLE_NAME AS table_name')
            ->get();

        $gradeTables = [];
        $terms = [];
        foreach ($rows as $row) {
            $table = array_change_key_case((array) $row, CASE_LOWER);
            $schema = $table['table_schema'] ?? null;
            $tableName = $table['table_name'] ?? null;

            if (
                ! is_string($schema)
                || ! is_string($tableName)
                || ! preg_match('/^grades(\d{4})([129])$/D', $tableName, $matches)
            ) {
                continue;
            }

            $year = $matches[1];
            $semester = $matches[2];
            $gradeTables[$schema][$year][$semester] = $tableName;
            $terms[$schema][$year][$semester] = true;
        }

        return [$gradeTables, $terms];
    }

    /**
     * @param  list<string>  $databases
     * @return array<string, true>
     */
    private function discoverIncomeCampuses(array $databases): array
    {
        $rows = DB::connection(config('ces.database_connection'))
            ->table('information_schema.columns')
            ->whereIn('table_schema', $databases)
            ->where('table_name', 'students')
            ->where('column_name', 'anual_income')
            ->selectRaw('TABLE_SCHEMA AS table_schema')
            ->distinct()
            ->get();

        $campuses = [];
        foreach ($rows as $row) {
            $values = array_change_key_case((array) $row, CASE_LOWER);
            if (is_string($values['table_schema'] ?? null)) {
                $campuses[$values['table_schema']] = true;
            }
        }

        return $campuses;
    }

    /**
     * @return list<array{year: string, semester: string}>
     */
    private function queryGraduateTerms(string $database): array
    {
        $rows = DB::connection(config('ces.database_connection'))
            ->table("{$database}.listgradmain")
            ->selectRaw('DISTINCT SchoolYear AS year, Semester AS semester')
            ->whereNotNull('SchoolYear')
            ->whereNotNull('Semester')
            ->whereIn('Semester', [1, 2, 9])
            ->get();

        $terms = [];
        foreach ($rows as $row) {
            $values = array_change_key_case((array) $row, CASE_LOWER);
            if (isset($values['year'], $values['semester'])) {
                $terms[] = [
                    'year' => (string) $values['year'],
                    'semester' => (string) $values['semester'],
                ];
            }
        }

        return $terms;
    }

    /**
     * @return array<string, array{college: string, program: string}>
     */
    private function queryCatalog(string $database): array
    {
        $rows = DB::connection(config('ces.database_connection'))
            ->table("{$database}.course as c")
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->whereNotNull('c.course_title')
            ->selectRaw($this->collegeExpression().' AS college, TRIM(c.course_title) AS program')
            ->distinct()
            ->get();

        return $this->groupedRows($rows);
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function queryGradeCounts(string $database, string $gradeTable, bool $incomeAvailable): array
    {
        $college = $this->collegeExpression();
        $program = "COALESCE(NULLIF(TRIM(c.course_title), ''), '')";
        $incomeCount = $incomeAvailable
            ? 'COUNT(DISTINCT CASE WHEN s.anual_income IS NOT NULL THEN g.StudentNo END) AS household_income_total,'
            : '';
        $query = DB::connection(config('ces.database_connection'))
            ->table("{$database}.{$gradeTable} as g")
            ->leftJoin("{$database}.students as s", 'g.StudentNo', '=', 's.StudentNo')
            ->leftJoin("{$database}.students2 as s2", 'g.StudentNo', '=', 's2.StudentNo')
            ->leftJoin("{$database}.guaranteedinfo as gi", function ($join) {
                $join->on('g.StudentNo', '=', 'gi.StudentNo')
                    ->whereNull('gi.deleted_at');
            })
            ->leftJoin("{$database}.course as c", 's.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->whereNotNull('g.StudentNo')
            ->whereRaw("TRIM(CAST(g.StudentNo AS CHAR)) <> ''")
            ->selectRaw(<<<SQL
                {$college} AS college,
                {$program} AS program,
                COUNT(DISTINCT g.StudentNo) AS total_enrolled_students,
                {$incomeCount}
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
                    THEN g.StudentNo END) AS disability_female,
                COUNT(DISTINCT CASE WHEN COALESCE(gi.isFirstGeneration, 0) = 1
                    THEN g.StudentNo END) AS first_generation_total,
                COUNT(DISTINCT CASE WHEN COALESCE(gi.isFirstGeneration, 0) = 1
                    AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('m', 'male')
                    THEN g.StudentNo END) AS first_generation_male,
                COUNT(DISTINCT CASE WHEN COALESCE(gi.isFirstGeneration, 0) = 1
                    AND LOWER(TRIM(COALESCE(s.Sex, ''))) IN ('f', 'female')
                    THEN g.StudentNo END) AS first_generation_female
                SQL)
            ->groupByRaw("{$college}, {$program}");

        return $this->groupedRows($query->get());
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function queryFinancialAidCounts(string $database, string $year, string $semester): array
    {
        $college = $this->collegeExpression();
        $program = "COALESCE(NULLIF(TRIM(c.course_title), ''), '')";
        $query = DB::connection(config('ces.database_connection'))
            ->table("{$database}.registration as r")
            ->leftJoin("{$database}.course as c", 'r.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->where('r.finalize', 1)
            ->whereNotNull('r.StudentNo')
            ->whereRaw("TRIM(CAST(r.StudentNo AS CHAR)) <> ''")
            ->where('r.SchoolYear', $year)
            ->where('r.Semester', $semester)
            ->selectRaw(<<<SQL
                {$college} AS college,
                {$program} AS program,
                COUNT(DISTINCT CASE WHEN
                    (
                        NULLIF(TRIM(COALESCE(r.Scholar, '')), '') IS NOT NULL
                        AND LOWER(TRIM(r.Scholar)) NOT IN ('0', 'none', 'no', 'null')
                    )
                    OR COALESCE(r.TES, 0) <> 0
                    OR COALESCE(r.isUnifastPaid, 0) <> 0
                    THEN r.StudentNo END) AS financial_aid_total
                SQL)
            ->groupByRaw("{$college}, {$program}");

        return $this->groupedRows($query->get());
    }

    /**
     * @return array<string, array{college: string, program: string, scholarships: list<array{name: string, total: int}>}>
     */
    private function queryScholarshipCounts(string $database, string $year, string $semester): array
    {
        $college = $this->collegeExpression();
        $program = "COALESCE(NULLIF(TRIM(c.course_title), ''), '')";
        $rows = DB::connection(config('ces.database_connection'))
            ->table("{$database}.registration as r")
            ->leftJoin("{$database}.scholar as sc", function ($join) {
                $join->on(DB::raw('TRIM(r.Scholar)'), '=', DB::raw('TRIM(sc.id)'));
            })
            ->leftJoin("{$database}.course as c", 'r.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->where('r.finalize', 1)
            ->whereNotNull('r.StudentNo')
            ->whereRaw("TRIM(CAST(r.StudentNo AS CHAR)) <> ''")
            ->where('r.SchoolYear', $year)
            ->where('r.Semester', $semester)
            ->whereNotNull('sc.scholar_name')
            ->whereRaw("TRIM(COALESCE(sc.scholar_name, '')) <> ''")
            ->selectRaw(<<<SQL
                {$college} AS college,
                {$program} AS program,
                TRIM(sc.scholar_name) AS scholarship_name,
                COUNT(DISTINCT r.StudentNo) AS scholarship_total
                SQL)
            ->groupByRaw("{$college}, {$program}, TRIM(sc.scholar_name)")
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $values = array_change_key_case((array) $row, CASE_LOWER);
            $collegeName = trim((string) ($values['college'] ?? ''));
            $programName = trim((string) ($values['program'] ?? ''));
            $name = $this->normalizeScholarshipName((string) ($values['scholarship_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $groupKey = $this->groupKey($collegeName, $programName);
            $scholarshipKey = strtolower($name);
            $grouped[$groupKey] ??= [
                'college' => $collegeName,
                'program' => $programName,
                'scholarships' => [],
            ];
            $grouped[$groupKey]['scholarships'][$scholarshipKey] ??= ['name' => $name, 'total' => 0];
            $grouped[$groupKey]['scholarships'][$scholarshipKey]['total'] += (int) ($values['scholarship_total'] ?? 0);
        }

        foreach ($grouped as &$group) {
            $group['scholarships'] = array_values($group['scholarships']);
        }
        unset($group);

        return $grouped;
    }

    private function normalizeScholarshipName(string $name): string
    {
        $name = preg_replace('/\s*\([^)]*(?:\d|%)[^)]*\)\s*/u', ' ', trim($name)) ?? $name;
        $name = preg_replace('/\s*[-–—]?\s*(?:19|20)\d{2}\s*$/u', '', $name) ?? $name;
        $name = preg_replace('/\b(?:full|half)\s+merit\b/iu', 'Merit', $name) ?? $name;
        $name = preg_replace('/\s*[-–—]\s*[\d,]+(?:\.\d+)?\s*\/\s*(?:month|mo)\s*$/iu', '', $name) ?? $name;
        $name = preg_replace('/\s*[-–—]?\s*\d+(?:\.\d+)?\s*%\s*$/u', '', $name) ?? $name;
        $name = preg_replace('/(?:\s*[-–—]\s*|\s+)(?:full|half|partial|one[-\s]?fourth|one[-\s]?quarter|quarter)\s*$/iu', '', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name, " \t\n\r\0\x0B-–—,");

        $canonicalNames = [
            'academic scholar' => 'Academic Scholar',
            'adjust' => 'Adjustment',
            'adjustment' => 'Adjustment',
            'adjustments' => 'Adjustment',
            'cooperating teacher' => 'Cooperating Teacher',
            'cooperating-teacher' => 'Cooperating Teacher',
            'cooperting teacher' => 'Cooperating Teacher',
            'cooperting-teacher' => 'Cooperating Teacher',
            'ched hlaf merit' => 'CHED Merit',
            'chedm merit' => 'CHED Merit',
        ];

        return $canonicalNames[strtolower($name)] ?? $name;
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function queryFreshmanCounts(string $database, string $year): array
    {
        $college = $this->collegeExpression();
        $program = "COALESCE(NULLIF(TRIM(c.course_title), ''), '')";
        $query = DB::connection(config('ces.database_connection'))
            ->table("{$database}.registration as r")
            ->leftJoin("{$database}.course as c", 'r.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->where('r.finalize', 1)
            ->whereNotNull('r.StudentNo')
            ->whereRaw("TRIM(CAST(r.StudentNo AS CHAR)) <> ''")
            ->where('r.SchoolYear', $year)
            ->selectRaw(<<<SQL
                {$college} AS college,
                {$program} AS program,
                COUNT(DISTINCT CASE WHEN LOWER(TRIM(COALESCE(r.StudentStatus, ''))) = 'new'
                    THEN r.StudentNo END) AS new_freshman
                SQL)
            ->groupByRaw("{$college}, {$program}");

        return $this->groupedRows($query->get());
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    private function queryGraduationCounts(string $database, string $stageTable): array
    {
        $college = $this->collegeExpression();
        $program = "COALESCE(NULLIF(TRIM(c.course_title), ''), '')";
        $query = DB::connection(config('ces.database_connection'))
            ->table("{$database}.{$stageTable} as st")
            ->leftJoin("{$database}.students as s", 'st.StudentNo', '=', 's.StudentNo')
            ->leftJoin("{$database}.course as c", 's.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->whereNotNull('st.StudentNo')
            ->whereRaw("TRIM(CAST(st.StudentNo AS CHAR)) <> ''")
            ->whereNull('st.deleted_at')
            ->selectRaw(<<<SQL
                {$college} AS college,
                {$program} AS program,
                COUNT(DISTINCT st.StudentNo) AS graduating_enrollees
                SQL)
            ->groupByRaw("{$college}, {$program}");

        return $this->groupedRows($query->get());
    }

    /**
     * @return list<array{year: string, college: string, program: string, already_graduated: int}>
     */
    private function queryAnnualGraduateCounts(string $database): array
    {
        $college = $this->collegeExpression();
        $program = "COALESCE(NULLIF(TRIM(c.course_title), ''), '')";
        $rows = DB::connection(config('ces.database_connection'))
            ->table("{$database}.listgradsub as gs")
            ->join("{$database}.listgradmain as gm", 'gs.MainID', '=', 'gm.id')
            ->leftJoin("{$database}.students as s", 'gs.StudentNo', '=', 's.StudentNo')
            ->leftJoin("{$database}.course as c", 's.Course', '=', 'c.id')
            ->leftJoin("{$database}.department as d", 'c.Department', '=', 'd.id')
            ->whereNotNull('gm.SchoolYear')
            ->whereIn('gm.Semester', [1, 2, 9])
            ->whereNotNull('gs.StudentNo')
            ->whereRaw("TRIM(CAST(gs.StudentNo AS CHAR)) <> ''")
            ->selectRaw(<<<SQL
                CAST(gm.SchoolYear AS CHAR) AS year,
                {$college} AS college,
                {$program} AS program,
                COUNT(DISTINCT gs.StudentNo) AS already_graduated
                SQL)
            ->groupByRaw("gm.SchoolYear, {$college}, {$program}")
            ->get();

        $graduates = [];
        foreach ($rows as $row) {
            $values = array_change_key_case((array) $row, CASE_LOWER);
            $graduates[] = [
                'year' => (string) $values['year'],
                'college' => trim((string) $values['college']),
                'program' => trim((string) $values['program']),
                'already_graduated' => (int) $values['already_graduated'],
            ];
        }

        return $graduates;
    }

    /**
     * @param  iterable<object>  $rows
     * @return array<string, array<string, int|string>>
     */
    private function groupedRows(iterable $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $values = array_change_key_case((array) $row, CASE_LOWER);
            $college = trim((string) ($values['college'] ?? ''));
            $program = trim((string) ($values['program'] ?? ''));
            $grouped[$this->groupKey($college, $program)] = [
                ...$values,
                'college' => $college,
                'program' => $program,
            ];
        }

        return $grouped;
    }

    /**
     * @param  array<string, int|string>  $row
     * @return array{total: int, male: int, female: int}
     */
    private function demographicValues(array $row, string $prefix): array
    {
        return [
            'total' => (int) ($row["{$prefix}_total"] ?? 0),
            'male' => (int) ($row["{$prefix}_male"] ?? 0),
            'female' => (int) ($row["{$prefix}_female"] ?? 0),
        ];
    }

    /**
     * @param  list<array{campus: string, college: string, program: string}>  $catalog
     * @return list<array{campus: string, college: string, program: string}>
     */
    private function uniqueCatalog(array $catalog): array
    {
        $unique = [];

        foreach ($catalog as $item) {
            $unique[json_encode($item, JSON_THROW_ON_ERROR)] = $item;
        }

        return array_values($unique);
    }

    private function groupKey(string $college, string $program): string
    {
        return json_encode([$college, $program], JSON_THROW_ON_ERROR);
    }

    private function collegeExpression(): string
    {
        return "COALESCE(NULLIF(TRIM(d.CollegeName), ''), NULLIF(TRIM(d.DepartmentName), ''), CAST(d.id AS CHAR), '')";
    }
}
