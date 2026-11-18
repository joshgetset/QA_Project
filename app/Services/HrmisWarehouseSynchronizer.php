<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class HrmisWarehouseSynchronizer
{
    private const EDUCATION_CATEGORIES = [
        ['code' => 'unknown', 'label' => 'Unknown', 'rank' => 0],
        ['code' => 'high_school', 'label' => 'High School', 'rank' => 1],
        ['code' => 'tesda', 'label' => 'TESDA', 'rank' => 2],
        ['code' => 'bachelor', 'label' => 'Bachelor', 'rank' => 3],
        ['code' => 'master_units', 'label' => 'Master Units', 'rank' => 4],
        ['code' => 'masters_degree', 'label' => 'Masters Degree', 'rank' => 5],
        ['code' => 'doctorate_units', 'label' => 'Doctorate Units', 'rank' => 6],
        ['code' => 'doctorate_degree', 'label' => 'Doctorate Degree', 'rank' => 7],
    ];

    public function __construct(
        private readonly HrmisEmployeeClassifier $classifier,
        private readonly HrmisSourceApiClient $sourceApiClient,
        private readonly HrmisCampusDirectory $campusDirectory
    ) {}

    /**
     * @return array{employees_loaded: int, education_records_staged: int, source_campuses: int}
     */
    public function sync(): array
    {
        $warehouse = DB::connection('qams_warehouse');

        $startedAt = now();
        $runId = $warehouse->table('hrmis_warehouse_sync_runs')->insertGetId([
            'status' => 'running',
            'snapshot_date' => $startedAt->toDateString(),
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        try {
            $extracted = $this->sourceApiClient->fetch();
            if ($extracted['employees'] === []) {
                throw new RuntimeException('HRMIS returned no active employees; the current warehouse snapshot was not changed.');
            }

            foreach ($extracted['employees'] as &$employee) {
                $employee['gender_code'] = $this->classifier->gender($employee['gender_code'] ?? null);
            }
            unset($employee);

            $counts = $warehouse->transaction(function () use ($warehouse, $extracted, $startedAt, $runId): array {
                $this->stage($warehouse, $extracted, $startedAt);
                $maps = $this->syncDimensions($warehouse, $extracted, $startedAt);
                $employeesLoaded = $this->loadEmployeeFacts(
                    $warehouse,
                    $extracted,
                    $maps,
                    $startedAt,
                    $runId
                );

                return [
                    'employees_loaded' => $employeesLoaded,
                    'education_records_staged' => count($extracted['education']),
                    'source_campuses' => count($maps['campusMap']),
                ];
            });

            $warehouse->table('hrmis_warehouse_sync_runs')->where('id', $runId)->update([
                'status' => 'succeeded',
                'employees_staged' => count($extracted['employees']),
                'education_records_staged' => count($extracted['education']),
                'employees_loaded' => $counts['employees_loaded'],
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return $counts;
        } catch (Throwable $exception) {
            $warehouse->table('hrmis_warehouse_sync_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{
     *     employees: list<array<string, mixed>>,
     *     departments: list<array<string, mixed>>,
     *     positions: list<array<string, mixed>>,
     *     education: list<array<string, mixed>>
     * }
     */
    public function exportSourceData(): array
    {
        $encryptionKey = config('hrmis.sex_encryption_key');
        if (! is_string($encryptionKey) || trim($encryptionKey) === '') {
            throw new RuntimeException('HRMIS_SEX_ENCRYPTION_KEY must be configured before exporting HRMIS source data.');
        }

        return $this->extract(DB::connection(config('hrmis.source_connection')), $encryptionKey);
    }

    /**
     * @return array{
     *     employees: list<array<string, mixed>>,
     *     departments: list<array<string, mixed>>,
     *     positions: list<array<string, mixed>>,
     *     education: list<array<string, mixed>>
     * }
     */
    private function extract(Connection $source, string $encryptionKey): array
    {
        $source->statement("SET SESSION block_encryption_mode = 'aes-128-cbc'");
        $keyStatement = $source->getPdo()->prepare('SET @qams_hrmis_sex_key = ?');
        $keyStatement->execute([$encryptionKey]);

        try {
            $employeeRows = $source->select(<<<'SQL'
                SELECT
                    e.id AS source_employee_id,
                    COALESCE(e.Campus, 0) AS source_campus_id,
                    e.Department AS source_department_id,
                    item.id AS source_position_id,
                    item.ItemName AS source_position_title,
                    e.EmploymentStatus AS source_employment_status,
                    CASE
                        WHEN e.Sex LIKE '%:%' THEN CAST(
                            AES_DECRYPT(
                                FROM_BASE64(SUBSTRING_INDEX(e.Sex, ':', 1)),
                                @qams_hrmis_sex_key,
                                FROM_BASE64(SUBSTRING_INDEX(e.Sex, ':', -1))
                            ) AS CHAR
                        )
                        ELSE e.Sex
                    END AS source_sex,
                            CASE WHEN e.Sex LIKE '%:%' THEN 1 ELSE 0 END AS sex_is_encrypted,
                    pwd.is_pwd,
                    e.updated_at AS source_updated_at
                FROM employee AS e
                LEFT JOIN cscitemname AS item
                    ON TRIM(e.CurrentItem) REGEXP '^[0-9]+$'
                    AND item.id = CAST(TRIM(e.CurrentItem) AS UNSIGNED)
                    AND item.deleted_at IS NULL
                LEFT JOIN department AS department
                    ON department.id = e.Department
                    AND department.Campus = e.Campus
                    AND department.deleted_at IS NULL
                LEFT JOIN (
                    SELECT
                        empId,
                        MAX(CASE
                            WHEN LOWER(TRIM(COALESCE(ispwd, ''))) IN ('yes', 'y', 'true', '1') THEN 1
                            WHEN LOWER(TRIM(COALESCE(ispwd, ''))) IN ('no', 'n', 'false', '0') THEN 0
                            ELSE NULL
                        END) AS is_pwd
                    FROM stats
                    WHERE deleted_at IS NULL
                    GROUP BY empId
                ) AS pwd ON pwd.empId = e.id
                WHERE e.deleted_at IS NULL
                    AND LOWER(TRIM(COALESCE(e.isActive, ''))) = 'yes'
                ORDER BY e.id
                SQL);
        } finally {
            $source->unprepared('SET @qams_hrmis_sex_key = NULL');
        }

        $employees = [];
        foreach ($employeeRows as $row) {
            if ((int) $row->sex_is_encrypted === 1 && $row->source_sex === null) {
                throw new RuntimeException('Could not decrypt an HRMIS sex value; verify HRMIS_SEX_ENCRYPTION_KEY and the source encoding.');
            }

            $pwd = $row->is_pwd === null ? null : (string) $row->is_pwd;
            $genderCode = $this->classifier->gender($row->source_sex);
            $employees[] = [
                'source_employee_id' => (int) $row->source_employee_id,
                'source_campus_id' => max(0, (int) $row->source_campus_id),
                'source_department_id' => $row->source_department_id === null ? null : (int) $row->source_department_id,
                'source_position_id' => $row->source_position_id === null ? null : (int) $row->source_position_id,
                'source_position_title' => $this->nullableString($row->source_position_title, 100),
                'source_employment_status' => $this->nullableString($row->source_employment_status, 100),
                'gender_code' => $genderCode,
                'is_pwd' => in_array($pwd, ['0', '1'], true) ? (int) $pwd : null,
                'source_updated_at' => $row->source_updated_at,
            ];
        }

        $departments = [];
        foreach ($source->table('department')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'Campus', 'DepartmentName']) as $row) {
            $departments[] = [
                'source_department_id' => (int) $row->id,
                'source_campus_id' => max(0, (int) $row->Campus),
                'department_name' => $this->nullableString($row->DepartmentName, 200),
            ];
        }

        $positions = [];
        foreach ($source->table('cscitemname')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'campus', 'ItemName']) as $row) {
            $positions[] = [
                'source_position_id' => (int) $row->id,
                'source_campus_id' => max(0, (int) $row->campus),
                'position_title' => $this->nullableString($row->ItemName, 100),
            ];
        }

        $education = [];
        foreach ($source->table('educationalbackground AS education')
            ->join('employee AS employee', 'employee.id', '=', 'education.EmpId')
            ->whereNull('education.deleted_at')
            ->whereNull('employee.deleted_at')
            ->whereRaw("LOWER(TRIM(COALESCE(employee.isActive, ''))) = 'yes'")
            ->orderBy('education.EmpId')
            ->orderBy('education.id')
            ->get([
                'education.id',
                'education.EmpId',
                'education.StudentLevel',
                'education.DegreeCourse',
                'education.UnitsEarned',
                'education.YearGraduated',
                'education.OrderBy',
            ]) as $row) {
            $education[] = [
                'source_education_id' => (int) $row->id,
                'source_employee_id' => (int) $row->EmpId,
                'student_level' => $this->nullableString($row->StudentLevel, 200),
                'degree_course' => $this->nullableString($row->DegreeCourse, 300),
                'units_earned' => $this->nullableString($row->UnitsEarned, 100),
                'year_graduated' => $this->nullableString($row->YearGraduated, 100),
                'source_order' => $row->OrderBy === null ? null : max(0, (int) $row->OrderBy),
            ];
        }

        return compact('employees', 'departments', 'positions', 'education');
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function stage(Connection $warehouse, array $extracted, \Illuminate\Support\Carbon $loadedAt): void
    {
        foreach ([
            'hrmis_stg_education_records',
            'hrmis_stg_employees',
            'hrmis_stg_departments',
            'hrmis_stg_positions',
        ] as $table) {
            $warehouse->table($table)->delete();
        }

        $this->insertChunks($warehouse, 'hrmis_stg_employees', array_map(
            fn (array $row): array => [...$row, 'loaded_at' => $loadedAt],
            $extracted['employees']
        ));
        $this->insertChunks($warehouse, 'hrmis_stg_departments', array_map(
            fn (array $row): array => [...$row, 'loaded_at' => $loadedAt],
            $extracted['departments']
        ));
        $this->insertChunks($warehouse, 'hrmis_stg_positions', array_map(
            fn (array $row): array => [...$row, 'loaded_at' => $loadedAt],
            $extracted['positions']
        ));
        $this->insertChunks($warehouse, 'hrmis_stg_education_records', array_map(
            fn (array $row): array => [...$row, 'loaded_at' => $loadedAt],
            $extracted['education']
        ));
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array<string, array<int|string, int>>
     */
    private function syncDimensions(Connection $warehouse, array $extracted, \Illuminate\Support\Carbon $now): array
    {
        $campusIds = [0];
        foreach (['employees', 'departments', 'positions'] as $collection) {
            foreach ($extracted[$collection] as $row) {
                $campusIds[] = (int) $row['source_campus_id'];
            }
        }
        $campusIds = array_values(array_unique($campusIds));
        sort($campusIds);

        $this->campusDirectory->synchronize($warehouse, $campusIds, $now);
        $campusMap = $warehouse->table('dim_hrmis_source_campus')
            ->whereIn('source_campus_id', $campusIds)
            ->pluck('id', 'source_campus_id')
            ->all();

        $departmentRows = [];
        foreach ($extracted['departments'] as $row) {
            $departmentRows[] = [
                'source_department_id' => $row['source_department_id'],
                'campus_id' => $campusMap[$row['source_campus_id']] ?? null,
                'department_name' => $row['department_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->upsertChunks($warehouse, 'dim_hrmis_department', $departmentRows, ['source_department_id'], [
            'campus_id',
            'department_name',
            'updated_at',
        ]);
        $departmentMap = $warehouse->table('dim_hrmis_department')
            ->pluck('id', 'source_department_id')
            ->all();

        $positionRows = [];
        foreach ($extracted['positions'] as $row) {
            $classification = $this->classifier->position($row['position_title']);
            $positionRows[] = [
                'source_position_id' => $row['source_position_id'],
                'campus_id' => $campusMap[$row['source_campus_id']] ?? null,
                'position_title' => $row['position_title'],
                'position_category' => $classification['category'],
                'is_teaching' => $classification['is_teaching'],
                'is_senior_academic' => $classification['is_senior_academic'],
                'is_guidance_counselor' => $classification['is_guidance_counselor'],
                'is_physical_health' => $classification['is_physical_health'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->upsertChunks($warehouse, 'dim_hrmis_position', $positionRows, ['source_position_id'], [
            'campus_id',
            'position_title',
            'position_category',
            'is_teaching',
            'is_senior_academic',
            'is_guidance_counselor',
            'is_physical_health',
            'updated_at',
        ]);
        $positionMap = $warehouse->table('dim_hrmis_position')
            ->pluck('id', 'source_position_id')
            ->all();

        $employmentStatuses = [];
        foreach ($extracted['employees'] as $row) {
            $sourceStatus = $this->nullableString($row['source_employment_status'] ?? null, 100) ?? 'Unknown Emp Status';
            $classification = $this->classifier->employment($sourceStatus);
            $employmentStatuses[$sourceStatus] = [
                'source_status' => $sourceStatus,
                'canonical_status' => $classification['canonical_status'],
                'is_part_time' => $classification['is_part_time'],
                'is_permanent' => $classification['is_permanent'],
                'is_casual' => $classification['is_casual'],
                'is_contractual' => $classification['is_contractual'],
                'is_job_order' => $classification['is_job_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->upsertChunks($warehouse, 'dim_hrmis_employment_status', array_values($employmentStatuses), [
            'source_status',
        ], [
            'canonical_status',
            'is_part_time',
            'is_permanent',
            'is_casual',
            'is_contractual',
            'is_job_order',
            'updated_at',
        ]);
        $employmentMap = $warehouse->table('dim_hrmis_employment_status')
            ->pluck('id', 'source_status')
            ->all();

        $genderRows = [
            ['gender_code' => 'male', 'gender_label' => 'Male'],
            ['gender_code' => 'female', 'gender_label' => 'Female'],
            ['gender_code' => 'unknown', 'gender_label' => 'Unknown Gender'],
        ];
        $this->upsertChunks($warehouse, 'dim_hrmis_gender', array_map(
            fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now],
            $genderRows
        ), ['gender_code'], ['gender_label', 'updated_at']);
        $genderMap = $warehouse->table('dim_hrmis_gender')->pluck('id', 'gender_code')->all();

        $educationRows = array_map(
            fn (array $row): array => [
                'achievement_code' => $row['code'],
                'achievement_label' => $row['label'],
                'achievement_rank' => $row['rank'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::EDUCATION_CATEGORIES
        );
        $this->upsertChunks($warehouse, 'dim_hrmis_education', $educationRows, ['achievement_code'], [
            'achievement_label',
            'achievement_rank',
            'updated_at',
        ]);
        $educationMap = $warehouse->table('dim_hrmis_education')
            ->pluck('id', 'achievement_code')
            ->all();

        $employeeRows = array_map(
            fn (array $row): array => [
                'source_employee_id' => $row['source_employee_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $extracted['employees']
        );
        $this->upsertChunks($warehouse, 'dim_hrmis_employee', $employeeRows, ['source_employee_id'], ['updated_at']);
        $employeeMap = $warehouse->table('dim_hrmis_employee')
            ->whereIn('source_employee_id', array_column($extracted['employees'], 'source_employee_id'))
            ->pluck('id', 'source_employee_id')
            ->all();

        return compact(
            'campusMap',
            'departmentMap',
            'positionMap',
            'employmentMap',
            'genderMap',
            'educationMap',
            'employeeMap'
        );
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @param  array<string, array<int|string, int>>  $maps
     */
    private function loadEmployeeFacts(
        Connection $warehouse,
        array $extracted,
        array $maps,
        \Illuminate\Support\Carbon $now,
        int $runId
    ): int {
        $educationByEmployee = [];
        foreach ($extracted['education'] as $record) {
            $educationByEmployee[$record['source_employee_id']][] = [
                'student_level' => $record['student_level'],
                'degree_course' => $record['degree_course'],
                'units_earned' => $record['units_earned'],
                'year_graduated' => $record['year_graduated'],
            ];
        }

        $educationIds = $maps['educationMap'];
        $factRows = [];
        foreach ($extracted['employees'] as $employee) {
            $sourceStatus = $this->nullableString($employee['source_employment_status'] ?? null, 100) ?? 'Unknown Emp Status';
            $status = $this->classifier->employment($sourceStatus);
            $position = $this->classifier->position(
                $employee['source_position_title'],
                $sourceStatus
            );
            $isTeaching = $position['is_teaching'];
            $isNonTeaching = $position['is_non_teaching'];
            $isPartTime = $status['is_part_time'] || $position['is_part_time'];
            $highestEducation = $this->classifier->highestEducation(
                $educationByEmployee[$employee['source_employee_id']] ?? []
            );

            $factRows[] = [
                'sync_run_id' => $runId,
                'employee_id' => $maps['employeeMap'][$employee['source_employee_id']],
                'campus_id' => $maps['campusMap'][$employee['source_campus_id']],
                'department_id' => $employee['source_department_id'] === null
                    ? null
                    : ($maps['departmentMap'][$employee['source_department_id']] ?? null),
                'position_id' => $employee['source_position_id'] === null
                    ? null
                    : ($maps['positionMap'][$employee['source_position_id']] ?? null),
                'employment_status_id' => $maps['employmentMap'][$sourceStatus],
                'gender_id' => $maps['genderMap'][$employee['gender_code']],
                'education_id' => $educationIds[$highestEducation],
                'snapshot_date' => $now->toDateString(),
                'employee_count' => 1,
                'is_academic_staff' => $isTeaching,
                'is_senior_academic_staff' => $position['is_senior_academic'],
                'is_teaching_personnel' => $isTeaching,
                'is_part_time_teaching' => $position['is_part_time_teaching'],
                'is_non_teaching' => $isNonTeaching,
                'is_non_teaching_full_time' => $isNonTeaching && ! $isPartTime,
                'is_non_teaching_part_time' => $isNonTeaching && $isPartTime,
                'is_non_teaching_permanent' => $isNonTeaching && $status['is_permanent'],
                'is_non_teaching_casual' => $isNonTeaching && $status['is_casual'],
                'is_non_teaching_contractual' => $isNonTeaching && $status['is_contractual'],
                'is_non_teaching_job_order' => $isNonTeaching && $status['is_job_order'],
                'is_guidance_counselor' => $isNonTeaching && $position['is_guidance_counselor'],
                'is_physical_health' => $isNonTeaching && $position['is_physical_health'],
                'is_pwd' => $employee['is_pwd'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertChunks($warehouse, 'fact_hrmis_employee_snapshot', $factRows);

        return count($factRows);
    }

    private function insertChunks(Connection $connection, string $table, array $rows): void
    {
        foreach (array_chunk($rows, 300) as $chunk) {
            $connection->table($table)->insert($chunk);
        }
    }

    private function upsertChunks(Connection $connection, string $table, array $rows, array $uniqueBy, array $update): void
    {
        foreach (array_chunk($rows, 300) as $chunk) {
            if ($chunk !== []) {
                $connection->table($table)->upsert($chunk, $uniqueBy, $update);
            }
        }
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
