<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CesSourceExportService
{
    /**
     * @return array<string, mixed>
     */
    public function manifest(string $campusCode): array
    {
        [$source, $database] = $this->source($campusCode);

        return [
            'income_available' => $this->columnExists($source, $database, 'students', 'anual_income'),
            'course_ids' => $source->table("$database.course")
                ->pluck('id')
                ->map(fn ($value): string => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'grade_tables' => $this->discoverTables($source, $database, 'grades'),
            'stage_tables' => $this->discoverTables($source, $database, 'stage'),
            'graduation_periods' => $this->graduationPeriods($source, $database),
            'has_registration' => $this->tableExists($source, $database, 'registration'),
            'has_tes_by' => $this->columnExists($source, $database, 'registration', 'TESBy'),
            'has_graduation_list' => $this->tableExists($source, $database, 'listgradmain')
                && $this->tableExists($source, $database, 'listgradsub'),
        ];
    }

    /**
     * @return list<array{id: string, scholar_name: ?string}>
     */
    public function scholarships(string $campusCode): array
    {
        [$source, $database] = $this->source($campusCode);

        return $source->table("$database.scholar")
            ->select(['id', 'scholar_name'])
            ->orderBy('id')
            ->get()
            ->map(fn ($row): array => [
                'id' => trim((string) $row->id),
                'scholar_name' => $row->scholar_name === null ? null : (string) $row->scholar_name,
            ])
            ->all();
    }

    /**
     * @param  list<string>  $courseIds
     * @return list<array<string, mixed>>
     */
    public function programs(string $campusCode, array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }
        [$source, $database] = $this->source($campusCode);

        return $source->table("$database.course as c")
            ->leftJoin("$database.department as d", 'c.Department', '=', 'd.id')
            ->whereIn('c.id', $courseIds)
            ->select([
                'c.id as source_program_id',
                'c.course_title',
                'd.CollegeName as college_name',
                'd.DepartmentName as department_name',
            ])
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /**
     * @param  list<string>  $studentNumbers
     * @return list<array<string, mixed>>
     */
    public function students(string $campusCode, array $studentNumbers): array
    {
        if ($studentNumbers === []) {
            return [];
        }
        [$source, $database] = $this->source($campusCode);
        $incomeAvailable = $this->columnExists($source, $database, 'students', 'anual_income');
        $select = ['StudentNo', 'Course', 'Sex', 'nationality', 'Disability'];
        if ($incomeAvailable) {
            $select[] = 'anual_income';
        }
        $sourceStudents = $source->table("$database.students")
            ->select($select)
            ->whereIn('StudentNo', $studentNumbers)
            ->get()
            ->keyBy(fn ($row): string => trim((string) $row->StudentNo));

        $fourPsStatuses = [];
        $fourPsBeneficiaries = [];
        foreach ($source->table("$database.students2")
            ->select(['StudentNo', 'Family4Ps'])
            ->whereIn('StudentNo', $studentNumbers)
            ->get() as $row) {
            $studentNumber = trim((string) $row->StudentNo);
            $status = trim((string) ($row->Family4Ps ?? ''));
            if ($status !== '' && ! isset($fourPsStatuses[$studentNumber])) {
                $fourPsStatuses[$studentNumber] = $status;
            }
            if (in_array(strtolower($status), ['yes', 'y', 'true', '1', '4ps'], true)) {
                $fourPsStatuses[$studentNumber] = $status;
                $fourPsBeneficiaries[$studentNumber] = true;
            }
        }

        $firstGeneration = [];
        foreach ($source->table("$database.guaranteedinfo")
            ->select(['StudentNo', 'isFirstGeneration'])
            ->whereIn('StudentNo', $studentNumbers)
            ->whereNull('deleted_at')
            ->get() as $row) {
            if (in_array(strtolower(trim((string) ($row->isFirstGeneration ?? ''))), ['yes', 'y', 'true', '1'], true)) {
                $firstGeneration[trim((string) $row->StudentNo)] = true;
            }
        }

        $result = [];
        foreach ($studentNumbers as $studentNumber) {
            $row = $sourceStudents->get($studentNumber);
            $result[] = [
                'student_no' => $studentNumber,
                'course_id' => $row === null ? null : trim((string) ($row->Course ?? '')),
                'sex' => $row?->Sex,
                'nationality' => $row?->nationality,
                'disability' => $row?->Disability,
                'family_4ps_status' => $fourPsStatuses[$studentNumber] ?? null,
                'is_four_ps_beneficiary' => isset($fourPsBeneficiaries[$studentNumber]),
                'is_first_generation' => isset($firstGeneration[$studentNumber]),
                'annual_income' => $incomeAvailable && $row !== null && is_numeric($row->anual_income ?? null)
                    ? $row->anual_income
                    : null,
            ];
        }

        return $result;
    }

    /**
     * @return array{data: list<array<string, mixed>>, has_more: bool}
     */
    public function studentNumbers(string $campusCode, string $dataset, string $table, int $page, int $perPage): array
    {
        [$source, $database] = $this->source($campusCode);
        $prefix = $dataset === 'enrollments' ? 'grades' : ($dataset === 'graduations' ? 'stage' : null);
        if ($prefix === null || ! in_array($table, array_values($this->discoverTables($source, $database, $prefix)), true)) {
            throw ValidationException::withMessages(['table' => 'The requested source table is not available.']);
        }

        $query = $source->table("$database.$table")
            ->select('StudentNo')
            ->whereNotNull('StudentNo')
            ->whereRaw("TRIM(CAST(`StudentNo` AS CHAR)) <> ''")
            ->distinct()
            ->orderBy('StudentNo');
        if ($dataset === 'graduations') {
            $query->whereNull('deleted_at');
        }

        $rows = $query->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();
        $hasMore = $rows->count() > $perPage;
        $studentNumbers = $rows->take($perPage)
            ->map(fn ($row): string => trim((string) $row->StudentNo))
            ->values()
            ->all();

        return [
            'data' => $this->students($campusCode, $studentNumbers),
            'has_more' => $hasMore,
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, has_more: bool}
     */
    public function registrations(string $campusCode, int $page, int $perPage): array
    {
        [$source, $database] = $this->source($campusCode);
        if (! $this->tableExists($source, $database, 'registration')) {
            return ['data' => [], 'has_more' => false];
        }

        $select = ['id', 'StudentNo', 'Course', 'finalize', 'SchoolYear', 'Semester', 'StudentStatus', 'Scholar', 'TES', 'isUnifastPaid'];
        if ($this->columnExists($source, $database, 'registration', 'TESBy')) {
            $select[] = 'TESBy';
        }
        $rows = $source->table("$database.registration")
            ->select($select)
            ->whereNotNull('StudentNo')
            ->whereRaw("TRIM(CAST(`StudentNo` AS CHAR)) <> ''")
            ->whereRaw(
                'CAST(TRIM(CAST(`SchoolYear` AS CHAR)) AS UNSIGNED) BETWEEN ? AND ?',
                [(int) config('ces.minimum_academic_year', 2020), $this->latestGradeYear($source, $database)]
            )
            ->orderBy('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();
        $records = $rows->take($perPage)->map(fn ($row): array => (array) $row)->all();
        $studentNumbers = array_values(array_unique(array_filter(array_map(
            fn (array $record): string => trim((string) ($record['StudentNo'] ?? '')),
            $records
        ))));
        $studentProfiles = array_column($this->students($campusCode, $studentNumbers), null, 'student_no');
        foreach ($records as &$record) {
            $record['student_profile'] = $studentProfiles[trim((string) ($record['StudentNo'] ?? ''))] ?? null;
        }
        unset($record);

        return [
            'data' => $records,
            'has_more' => $rows->count() > $perPage,
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, has_more: bool}
     */
    public function graduationList(string $campusCode, int $page, int $perPage): array
    {
        [$source, $database] = $this->source($campusCode);
        if (! $this->tableExists($source, $database, 'listgradmain') || ! $this->tableExists($source, $database, 'listgradsub')) {
            return ['data' => [], 'has_more' => false];
        }
        $rows = $source->table("$database.listgradsub as gs")
            ->join("$database.listgradmain as gm", 'gs.MainID', '=', 'gm.id')
            ->select(['gs.id', 'gs.StudentNo', 'gm.SchoolYear', 'gm.Semester'])
            ->whereNotNull('gs.StudentNo')
            ->whereRaw("TRIM(CAST(gs.`StudentNo` AS CHAR)) <> ''")
            ->whereRaw(
                'CAST(TRIM(CAST(gm.`SchoolYear` AS CHAR)) AS UNSIGNED) BETWEEN ? AND ?',
                [(int) config('ces.minimum_academic_year', 2020), $this->latestGradeYear($source, $database)]
            )
            ->orderBy('gs.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();
        $records = $rows->take($perPage)->map(fn ($row): array => (array) $row)->all();
        $studentNumbers = array_values(array_unique(array_filter(array_map(
            fn (array $record): string => trim((string) ($record['StudentNo'] ?? '')),
            $records
        ))));
        $studentProfiles = array_column($this->students($campusCode, $studentNumbers), null, 'student_no');
        foreach ($records as &$record) {
            $record['student_profile'] = $studentProfiles[trim((string) ($record['StudentNo'] ?? ''))] ?? null;
        }
        unset($record);

        return [
            'data' => $records,
            'has_more' => $rows->count() > $perPage,
        ];
    }

    /**
     * @return array{Connection, string}
     */
    private function source(string $campusCode): array
    {
        $campus = config("ces.campuses.{$campusCode}");
        if (! is_array($campus) || ! isset($campus['database'])) {
            throw ValidationException::withMessages(['campus' => 'The requested campus is not configured.']);
        }
        $database = (string) $campus['database'];
        if (! preg_match('/^[A-Za-z0-9_]+$/D', $database)) {
            throw new RuntimeException('CES source database name contains unsupported characters.');
        }

        return [DB::connection(config('ces.database_connection')), $database];
    }

    /**
     * @return array<string, string>
     */
    private function discoverTables(Connection $source, string $database, string $prefix): array
    {
        $pattern = $prefix === 'grades' ? '/^grades(\d{4})([129])$/D' : '/^stage(\d{4})([129])$/D';
        $names = $source->table('information_schema.tables')
            ->where('table_schema', $database)
            ->where('table_name', 'like', $prefix.'%')
            ->selectRaw('TABLE_NAME AS table_name')
            ->get()
            ->pluck('table_name')
            ->all();
        $tables = [];
        foreach ($names as $name) {
            if (
                preg_match($pattern, (string) $name, $matches) === 1
                && (int) $matches[1] >= (int) config('ces.minimum_academic_year', 2020)
                && ($prefix !== 'stage' || (int) $matches[1] <= $this->latestGradeYear($source, $database))
            ) {
                $tables[$matches[1].'-'.$matches[2]] = (string) $name;
            }
        }
        ksort($tables);

        return $tables;
    }

    private function latestGradeYear(Connection $source, string $database): int
    {
        $years = array_map(
            fn (string $table): int => (int) substr($table, 6, 4),
            array_values($this->discoverGradeTables($source, $database))
        );

        return $years === [] ? (int) config('ces.minimum_academic_year', 2020) - 1 : max($years);
    }

    /** @return array<string, string> */
    private function discoverGradeTables(Connection $source, string $database): array
    {
        // Grade discovery is the authoritative upper bound for all CES periods.
        return $this->discoverTables($source, $database, 'grades');
    }

    /**
     * @return list<string>
     */
    private function graduationPeriods(Connection $source, string $database): array
    {
        if (! $this->tableExists($source, $database, 'listgradmain')) {
            return [];
        }

        $periods = [];
        foreach ($source->table("$database.listgradmain")
            ->selectRaw('DISTINCT SchoolYear AS academic_year, Semester AS semester')
            ->whereNotNull('SchoolYear')
            ->whereIn('Semester', [1, 2, 9])
            ->get() as $row) {
            $year = trim((string) $row->academic_year);
            $semester = trim((string) $row->semester);
            if (
                preg_match('/^\d{4}$/D', $year)
                && (int) $year >= (int) config('ces.minimum_academic_year', 2020)
                && (int) $year <= $this->latestGradeYear($source, $database)
                && in_array($semester, ['1', '2', '9'], true)
            ) {
                $periods[] = "{$year}-{$semester}";
            }
        }

        return array_values(array_unique($periods));
    }

    private function tableExists(Connection $source, string $database, string $table): bool
    {
        return $source->table('information_schema.tables')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->exists();
    }

    private function columnExists(Connection $source, string $database, string $table, string $column): bool
    {
        return $source->table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }
}
