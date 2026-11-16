<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CesWarehouseSynchronizer
{
    private array $periodIds = [];

    private array $programIds = [];

    private array $loadedProgramIds = [];

    public function __construct(private readonly CesSourceApiClient $sourceApiClient) {}

    /**
     * @return array<string, array{enrollments: int, registrations: int, graduations: int}>
     */
    public function sync(): array
    {
        $warehouse = DB::connection('qams_warehouse');
        $campuses = config('ces.campuses', []);
        $startedAt = now();
        $runId = $warehouse->table('ces_warehouse_sync_runs')->insertGetId([
            'status' => 'running',
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);
        $campusIds = [];

        foreach ($campuses as $code => $campus) {
            $warehouse->table('dim_campus')->insertOrIgnore([
                'campus_code' => $code,
                'campus_name' => $campus['name'],
                'source_database' => $campus['database'],
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
            $campusId = (int) $warehouse->table('dim_campus')
                ->where('campus_code', $code)
                ->value('id');
            $campusIds[$code] = $campusId;
            $warehouse->table('dim_campus')->where('id', $campusId)->update([
                'campus_name' => $campus['name'],
                'source_database' => $campus['database'],
                'updated_at' => $startedAt,
            ]);
            $warehouse->table('ces_warehouse_campus_syncs')->insert([
                'run_id' => $runId,
                'campus_id' => $campusId,
                'status' => 'running',
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
        }

        $this->periodIds = [];
        $this->programIds = [];
        $this->loadedProgramIds = [];
        $results = [];

        try {
            $warehouse->transaction(function () use ($warehouse, $campuses, $campusIds, $runId, &$results): void {
                $warehouse->table('fact_ces_graduation')->delete();
                $warehouse->table('fact_ces_registration')->delete();
                $warehouse->table('fact_ces_enrollment')->delete();
                $warehouse->table('ces_campus_academic_periods')->delete();

                foreach ($campuses as $code => $campus) {
                    $this->assertSafeSchema($campus['database']);
                    $campusId = $campusIds[$code];
                    $scholarshipIds = $this->loadScholarships($code, $warehouse, $campusId);
                    $results[$code] = $this->syncCampus($code, $warehouse, $campus, $campusId, $scholarshipIds);

                    $warehouse->table('ces_warehouse_campus_syncs')
                        ->where('run_id', $runId)
                        ->where('campus_id', $campusId)
                        ->update([
                            'status' => 'succeeded',
                            'enrollments_loaded' => $results[$code]['enrollments'],
                            'registrations_loaded' => $results[$code]['registrations'],
                            'graduations_loaded' => $results[$code]['graduations'],
                            'updated_at' => now(),
                        ]);
                }
            });

            $warehouse->table('ces_warehouse_sync_runs')->where('id', $runId)->update([
                'status' => 'succeeded',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message = mb_substr($exception->getMessage(), 0, 4000);
            $warehouse->table('ces_warehouse_sync_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $message,
                'updated_at' => now(),
            ]);
            foreach ($campusIds as $campusId) {
                $warehouse->table('ces_warehouse_campus_syncs')
                    ->where('run_id', $runId)
                    ->where('campus_id', $campusId)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $message,
                        'updated_at' => now(),
                    ]);
            }

            throw $exception;
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $campus
     * @param  array<string, int>  $scholarshipIds
     * @return array{enrollments: int, registrations: int, graduations: int}
     */
    private function syncCampus(string $campusCode, Connection $warehouse, array $campus, int $campusId, array $scholarshipIds): array
    {
        $database = $campus['database'];
        $this->assertSafeSchema($database);
        $manifest = $this->sourceApiClient->manifest($campusCode);
        $incomeAvailable = (bool) ($manifest['income_available'] ?? false);
        $warehouse->table('dim_campus')->where('id', $campusId)->update([
            'income_available' => $incomeAvailable,
            'updated_at' => now(),
        ]);
        $courseIds = array_values(array_filter(array_map('strval', $manifest['course_ids'] ?? [])));
        $this->syncPrograms($campusCode, $warehouse, $campusId, $courseIds);
        $stageTables = $manifest['stage_tables'] ?? [];
        $gradeTables = $manifest['grade_tables'] ?? [];
        $availablePeriods = [];
        foreach ($gradeTables as $term => $_table) {
            $availablePeriods[$term]['has_grade_table'] = true;
        }
        foreach ($stageTables as $term => $_table) {
            $availablePeriods[$term]['has_stage_table'] = true;
        }
        foreach ($manifest['graduation_periods'] ?? [] as $term) {
            $availablePeriods[$term]['has_graduation_period'] = true;
        }
        foreach ($availablePeriods as $term => $availability) {
            [$year, $semester] = explode('-', $term);
            $this->syncPeriodAvailability(
                $warehouse,
                $campusId,
                $this->periodId($warehouse, $year, $semester),
                $availability
            );
        }
        $counts = ['enrollments' => 0, 'registrations' => 0, 'graduations' => 0];

        foreach ($gradeTables as $term => $table) {
            [$year, $semester] = explode('-', $term);
            $periodId = $this->periodId($warehouse, $year, $semester);
            foreach ($this->sourceApiClient->studentNumberPages($campusCode, 'enrollments', $table) as $records) {
                $studentNumbers = array_values(array_filter(array_map(
                    fn (array $row): string => trim((string) ($row['student_no'] ?? '')),
                    $records
                )));
                if ($studentNumbers === []) {
                    continue;
                }

                $students = $this->syncStudents($campusCode, $warehouse, $campusId, $studentNumbers, $incomeAvailable, $records);
                $rows = [];
                $now = now();
                foreach ($studentNumbers as $studentNumber) {
                    if (! isset($students[$studentNumber])) {
                        continue;
                    }
                    $rows[] = [
                        'campus_id' => $campusId,
                        'academic_period_id' => $periodId,
                        'student_id' => $students[$studentNumber]['id'],
                        'program_id' => $students[$studentNumber]['program_id'],
                        'source_table' => $table,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                $this->upsertChunks($warehouse, 'fact_ces_enrollment', $rows, ['campus_id', 'academic_period_id', 'student_id'], [
                    'program_id', 'source_table', 'updated_at',
                ]);
                $counts['enrollments'] += count($rows);
            }
        }

        $counts['registrations'] = $this->syncRegistrations(
            $campusCode,
            $warehouse,
            $campusId,
            $incomeAvailable,
            (bool) ($manifest['has_tes_by'] ?? false),
            $scholarshipIds
        );

        foreach ($stageTables as $term => $table) {
            [$year, $semester] = explode('-', $term);
            $periodId = $this->periodId($warehouse, $year, $semester);
            foreach ($this->sourceApiClient->studentNumberPages($campusCode, 'graduations', $table) as $records) {
                $studentNumbers = array_values(array_filter(array_map(
                    fn (array $row): string => trim((string) ($row['student_no'] ?? '')),
                    $records
                )));
                if ($studentNumbers === []) {
                    continue;
                }

                $students = $this->syncStudents($campusCode, $warehouse, $campusId, $studentNumbers, $incomeAvailable, $records);
                $rows = [];
                $now = now();
                foreach ($studentNumbers as $studentNumber) {
                    if (! isset($students[$studentNumber])) {
                        continue;
                    }
                    $rows[] = [
                        'campus_id' => $campusId,
                        'academic_period_id' => $periodId,
                        'student_id' => $students[$studentNumber]['id'],
                        'program_id' => $students[$studentNumber]['program_id'],
                        'source_table' => $table,
                        'source_record_key' => $studentNumber,
                        'is_graduation_applicant' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                $this->upsertChunks($warehouse, 'fact_ces_graduation', $rows, [
                    'campus_id', 'source_table', 'source_record_key',
                ], ['academic_period_id', 'student_id', 'program_id', 'is_graduation_applicant', 'updated_at']);
                $counts['graduations'] += count($rows);
            }
        }

        if ($manifest['has_graduation_list'] ?? false) {
            $counts['graduations'] += $this->syncGraduationList(
                $campusCode,
                $warehouse,
                $campusId,
                $incomeAvailable
            );
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function loadScholarships(string $campusCode, Connection $warehouse, int $campusId): array
    {
        $rows = [];
        $now = now();
        foreach ($this->sourceApiClient->scholarships($campusCode) as $scholarship) {
            $sourceId = trim((string) ($scholarship['id'] ?? ''));
            if ($sourceId === '') {
                continue;
            }
            $sourceName = trim((string) ($scholarship['scholar_name'] ?? ''));
            $rows[] = [
                'campus_id' => $campusId,
                'source_scholarship_id' => $sourceId,
                'source_name' => $sourceName !== '' ? $sourceName : null,
                'canonical_name' => CesScholarshipNameNormalizer::canonicalName($sourceName) ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->upsertChunks($warehouse, 'dim_ces_scholarship', $rows, ['campus_id', 'source_scholarship_id'], [
            'source_name', 'canonical_name', 'updated_at',
        ]);

        return $warehouse->table('dim_ces_scholarship')
            ->where('campus_id', $campusId)
            ->pluck('id', 'source_scholarship_id')
            ->mapWithKeys(fn ($id, $sourceId) => [(string) $sourceId => (int) $id])
            ->all();
    }

    /**
     * @param  list<string>  $studentNumbers
     * @return array<string, array{id: int, program_id: ?int}>
     */
    private function syncStudents(
        string $campusCode,
        Connection $warehouse,
        int $campusId,
        array $studentNumbers,
        bool $incomeAvailable,
        array $profileRows = []
    ): array {
        $sourceStudents = [];
        $profileRows = $profileRows !== [] ? $profileRows : $this->sourceApiClient->students($campusCode, $studentNumbers);
        foreach ($profileRows as $row) {
            $sourceStudents[trim((string) ($row['student_no'] ?? ''))] = $row;
        }
        $courseIds = array_values(array_filter(array_map(
            fn (array $row): string => trim((string) ($row['course_id'] ?? '')),
            array_values($sourceStudents)
        )));
        $courseIds = array_values(array_unique($courseIds));
        $programIds = $this->syncPrograms($campusCode, $warehouse, $campusId, $courseIds);
        $rows = [];
        $now = now();
        foreach ($studentNumbers as $studentNumber) {
            $sourceStudent = $sourceStudents[$studentNumber] ?? null;
            $courseId = trim((string) ($sourceStudent['course_id'] ?? ''));
            $rows[] = [
                'campus_id' => $campusId,
                'source_student_no' => $studentNumber,
                'sex' => $this->nullableString($sourceStudent['sex'] ?? null),
                'nationality' => $this->nullableString($sourceStudent['nationality'] ?? null),
                'disability' => $this->nullableString($sourceStudent['disability'] ?? null),
                'family_4ps_status' => $this->nullableString($sourceStudent['family_4ps_status'] ?? null),
                'is_four_ps_beneficiary' => (bool) ($sourceStudent['is_four_ps_beneficiary'] ?? false),
                'is_first_generation' => (bool) ($sourceStudent['is_first_generation'] ?? false),
                'annual_income' => $incomeAvailable && $sourceStudent !== null && is_numeric($sourceStudent['annual_income'] ?? null)
                    ? $sourceStudent['annual_income']
                    : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->upsertChunks($warehouse, 'dim_ces_student', $rows, ['campus_id', 'source_student_no'], [
            'sex', 'nationality', 'disability', 'family_4ps_status', 'is_four_ps_beneficiary',
            'is_first_generation', 'annual_income', 'updated_at',
        ]);

        $studentIds = $warehouse->table('dim_ces_student')
            ->where('campus_id', $campusId)
            ->whereIn('source_student_no', $studentNumbers)
            ->pluck('id', 'source_student_no');
        $result = [];
        foreach ($studentNumbers as $studentNumber) {
            if ($studentIds->has($studentNumber)) {
                $sourceStudent = $sourceStudents[$studentNumber] ?? null;
                $courseId = trim((string) ($sourceStudent['course_id'] ?? ''));
                $result[$studentNumber] = [
                    'id' => (int) $studentIds->get($studentNumber),
                    'program_id' => $programIds[$courseId] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $courseIds
     * @return array<string, int>
     */
    private function syncPrograms(string $campusCode, Connection $warehouse, int $campusId, array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }
        $courseIds = array_values(array_unique(array_filter(array_map('strval', $courseIds))));
        $loadedIds = $this->loadedProgramIds[$campusCode] ?? [];
        $missingCourseIds = array_values(array_filter(
            $courseIds,
            fn (string $courseId): bool => ! isset($loadedIds[$courseId])
        ));
        $rows = [];
        $now = now();
        foreach (array_chunk($missingCourseIds, 500) as $courseIdChunk) {
            foreach ($this->sourceApiClient->programs($campusCode, $courseIdChunk) as $program) {
                $sourceId = trim((string) ($program['source_program_id'] ?? ''));
                if ($sourceId !== '') {
                    $rows[] = [
                        'campus_id' => $campusId,
                        'source_program_id' => $sourceId,
                        'course_title' => $this->nullableString($program['course_title'] ?? null),
                        'college_name' => $this->nullableString($program['college_name'] ?? null),
                        'department_name' => $this->nullableString($program['department_name'] ?? null),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach ($courseIdChunk as $courseId) {
                $this->loadedProgramIds[$campusCode][$courseId] = true;
            }
        }
        $this->upsertChunks($warehouse, 'dim_ces_program', $rows, ['campus_id', 'source_program_id'], [
            'course_title', 'college_name', 'department_name', 'updated_at',
        ]);

        $mappedProgramIds = $warehouse->table('dim_ces_program')
            ->where('campus_id', $campusId)
            ->whereIn('source_program_id', $courseIds)
            ->pluck('id', 'source_program_id')
            ->mapWithKeys(fn ($id, $sourceId) => [(string) $sourceId => (int) $id])
            ->all();
        // Course IDs are numeric strings. array_merge() renumbers integer keys,
        // which breaks the source-course-ID to warehouse-program-ID lookup.
        $this->programIds[$campusCode] = array_replace($this->programIds[$campusCode] ?? [], $mappedProgramIds);

        return array_intersect_key($this->programIds[$campusCode], array_flip($courseIds));
    }

    private function syncRegistrations(
        string $campusCode,
        Connection $warehouse,
        int $campusId,
        bool $incomeAvailable,
        bool $hasTesBy,
        array $scholarshipIds
    ): int {
        $loaded = 0;
        foreach ($this->sourceApiClient->registrationPages($campusCode) as $records) {
            if ($records === []) {
                continue;
            }
            $studentNumbers = array_values(array_unique(array_filter(array_map(
                fn (array $record): string => trim((string) ($record['StudentNo'] ?? '')),
                $records
            ))));
            $profiles = array_values(array_filter(array_map(
                fn (array $record): ?array => $record['student_profile'] ?? null,
                $records
            )));
            $students = $this->syncStudents($campusCode, $warehouse, $campusId, $studentNumbers, $incomeAvailable, $profiles);
            $courseIds = array_values(array_unique(array_filter(array_map(
                fn (array $record): string => trim((string) ($record['Course'] ?? '')),
                $records
            ))));
            $programIds = $this->syncPrograms($campusCode, $warehouse, $campusId, $courseIds);
            $rows = [];
            $now = now();
            foreach ($records as $record) {
                $studentNumber = trim((string) ($record['StudentNo'] ?? ''));
                if (! isset($students[$studentNumber])) {
                    continue;
                }
                $sourceScholarshipId = $this->nullableString($record['Scholar'] ?? null);
                $parts = [trim((string) ($record['id'] ?? ''))];
                if ($hasTesBy) {
                    $parts[] = trim((string) ($record['TESBy'] ?? ''));
                }
                $schoolYear = $this->nullableString($record['SchoolYear'] ?? null);
                $semester = $this->nullableString($record['Semester'] ?? null);
                $courseId = trim((string) ($record['Course'] ?? ''));
                $rows[] = [
                    'campus_id' => $campusId,
                    'academic_period_id' => $this->periodId($warehouse, $schoolYear, $semester),
                    'student_id' => $students[$studentNumber]['id'],
                    'program_id' => $programIds[$courseId] ?? null,
                    'scholarship_id' => $sourceScholarshipId === null ? null : ($scholarshipIds[$sourceScholarshipId] ?? null),
                    'source_registration_key' => mb_substr(implode(':', $parts), 0, 191),
                    'source_school_year' => $schoolYear,
                    'source_semester' => $semester,
                    'source_student_status' => $this->nullableString($record['StudentStatus'] ?? null),
                    'source_scholarship_id' => $sourceScholarshipId,
                    'is_finalized' => $this->isTruthy($record['finalize'] ?? null),
                    'tes_value' => $this->nullableString($record['TES'] ?? null),
                    'unifast_paid_value' => $this->nullableString($record['isUnifastPaid'] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $this->upsertChunks($warehouse, 'fact_ces_registration', $rows, [
                'campus_id', 'source_registration_key',
            ], [
                'academic_period_id', 'student_id', 'program_id', 'scholarship_id', 'source_school_year',
                'source_semester', 'source_student_status', 'source_scholarship_id', 'is_finalized',
                'tes_value', 'unifast_paid_value', 'updated_at',
            ]);
            $loaded += count($rows);
        }

        return $loaded;
    }

    private function syncGraduationList(
        string $campusCode,
        Connection $warehouse,
        int $campusId,
        bool $incomeAvailable
    ): int {
        $loaded = 0;
        foreach ($this->sourceApiClient->graduationListPages($campusCode) as $records) {
            if ($records === []) {
                continue;
            }
            $studentNumbers = array_values(array_unique(array_filter(array_map(
                fn (array $record): string => trim((string) ($record['StudentNo'] ?? '')),
                $records
            ))));
            $profiles = array_values(array_filter(array_map(
                fn (array $record): ?array => $record['student_profile'] ?? null,
                $records
            )));
            $students = $this->syncStudents($campusCode, $warehouse, $campusId, $studentNumbers, $incomeAvailable, $profiles);
            $rows = [];
            $now = now();
            foreach ($records as $record) {
                $studentNumber = trim((string) ($record['StudentNo'] ?? ''));
                if (! isset($students[$studentNumber])) {
                    continue;
                }
                $schoolYear = $this->nullableString($record['SchoolYear'] ?? null);
                $semester = $this->nullableString($record['Semester'] ?? null);
                $rows[] = [
                    'campus_id' => $campusId,
                    'academic_period_id' => $this->periodId($warehouse, $schoolYear, $semester),
                    'student_id' => $students[$studentNumber]['id'],
                    'program_id' => $students[$studentNumber]['program_id'],
                    'source_table' => 'listgradsub',
                    'source_record_key' => trim((string) ($record['id'] ?? '')),
                    'is_graduation_applicant' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $this->upsertChunks($warehouse, 'fact_ces_graduation', $rows, [
                'campus_id', 'source_table', 'source_record_key',
            ], ['academic_period_id', 'student_id', 'program_id', 'is_graduation_applicant', 'updated_at']);
            $loaded += count($rows);
        }

        return $loaded;
    }

    private function periodId(Connection $warehouse, mixed $year, mixed $semester): ?int
    {
        $year = trim((string) ($year ?? ''));
        $semester = trim((string) ($semester ?? ''));
        if (! preg_match('/^\d{4}$/D', $year) || ! in_array($semester, ['1', '2', '9'], true)) {
            return null;
        }
        $key = "$year-$semester";
        if (isset($this->periodIds[$key])) {
            return $this->periodIds[$key];
        }

        $now = now();
        $warehouse->table('dim_academic_period')->insertOrIgnore([
            'academic_year' => $year,
            'semester' => $semester,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->periodIds[$key] = (int) $warehouse->table('dim_academic_period')
            ->where('academic_year', $year)
            ->where('semester', $semester)
            ->value('id');

        return $this->periodIds[$key];
    }

    /**
     * @param  array<string, bool>  $availability
     */
    private function syncPeriodAvailability(Connection $warehouse, int $campusId, ?int $periodId, array $availability): void
    {
        if ($periodId === null) {
            return;
        }
        $now = now();
        $warehouse->table('ces_campus_academic_periods')->upsert([[
            'campus_id' => $campusId,
            'academic_period_id' => $periodId,
            'has_grade_table' => $availability['has_grade_table'] ?? false,
            'has_stage_table' => $availability['has_stage_table'] ?? false,
            'has_graduation_period' => $availability['has_graduation_period'] ?? false,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['campus_id', 'academic_period_id'], [
            'has_grade_table',
            'has_stage_table',
            'has_graduation_period',
            'updated_at',
        ]);
    }

    private function upsertChunks(Connection $connection, string $table, array $rows, array $uniqueBy, array $update): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $connection->table($table)->upsert($chunk, $uniqueBy, $update);
        }
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) ($value ?? ''))), ['yes', 'y', 'true', '1'], true);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function assertSafeSchema(string $database): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/D', $database)) {
            throw new RuntimeException('CES source database name contains unsupported characters.');
        }
    }
}
