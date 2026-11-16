<?php

namespace App\Services;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CesSourceApiClient
{
    /**
     * @return array<string, mixed>
     */
    public function manifest(string $campusCode): array
    {
        $manifest = $this->getData($this->campusPath($campusCode).'/manifest');
        foreach (['income_available', 'has_registration', 'has_tes_by', 'has_graduation_list'] as $booleanField) {
            if (! is_bool($manifest[$booleanField] ?? null)) {
                throw new RuntimeException("CES source API returned invalid manifest field {$booleanField}.");
            }
        }
        foreach (['course_ids', 'grade_tables', 'stage_tables', 'graduation_periods'] as $arrayField) {
            if (! is_array($manifest[$arrayField] ?? null)) {
                throw new RuntimeException("CES source API returned invalid manifest field {$arrayField}.");
            }
        }
        foreach ($manifest['course_ids'] as $courseId) {
            if (! is_string($courseId) && ! is_int($courseId)) {
                throw new RuntimeException('CES source API returned invalid course identifiers.');
            }
        }
        foreach ($manifest['graduation_periods'] as $term) {
            if (! is_string($term) || ! preg_match('/^\d{4}-[129]$/D', $term)) {
                throw new RuntimeException('CES source API returned invalid graduation periods.');
            }
        }
        foreach (['grade_tables', 'stage_tables'] as $tableField) {
            foreach ($manifest[$tableField] as $term => $table) {
                if (
                    ! is_string($term)
                    || ! preg_match('/^\d{4}-[129]$/D', $term)
                    || ! is_string($table)
                    || ! preg_match('/^(grades|stage)\d{4}[129]$/D', $table)
                ) {
                    throw new RuntimeException("CES source API returned invalid {$tableField} metadata.");
                }
            }
        }

        $minimumAcademicYear = (int) config('ces.minimum_academic_year', 2020);
        $gradeYears = array_map(fn (string $term): int => (int) substr($term, 0, 4), array_keys($manifest['grade_tables']));
        $latestGradeYear = $gradeYears === [] ? $minimumAcademicYear - 1 : max($gradeYears);
        foreach (['grade_tables', 'stage_tables'] as $tableField) {
            $manifest[$tableField] = array_filter(
                $manifest[$tableField],
                fn (string $tableName, string $term): bool => (int) substr($term, 0, 4) >= $minimumAcademicYear
                    && (int) substr($term, 0, 4) <= $latestGradeYear,
                ARRAY_FILTER_USE_BOTH
            );
        }
        $manifest['graduation_periods'] = array_values(array_filter(
            $manifest['graduation_periods'],
            fn (string $term): bool => (int) substr($term, 0, 4) >= $minimumAcademicYear
                && (int) substr($term, 0, 4) <= $latestGradeYear
        ));

        return $manifest;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scholarships(string $campusCode): array
    {
        return $this->getList($this->campusPath($campusCode).'/scholarships');
    }

    /**
     * @param  list<string>  $courseIds
     * @return list<array<string, mixed>>
     */
    public function programs(string $campusCode, array $courseIds): array
    {
        return $this->postList($this->campusPath($campusCode).'/programs', ['course_ids' => $courseIds]);
    }

    /**
     * @param  list<string>  $studentNumbers
     * @return list<array<string, mixed>>
     */
    public function students(string $campusCode, array $studentNumbers): array
    {
        return $this->postList($this->campusPath($campusCode).'/students', ['student_numbers' => $studentNumbers]);
    }

    /**
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function studentNumberPages(string $campusCode, string $dataset, string $table): Generator
    {
        if (! in_array($dataset, ['enrollments', 'graduations'], true)) {
            throw new RuntimeException('CES source API dataset is not supported.');
        }
        $expectedPrefix = $dataset === 'enrollments' ? 'grades' : 'stage';
        if (
            preg_match('/^'.$expectedPrefix.'(\d{4})[129]$/D', $table, $matches) !== 1
            || (int) $matches[1] < (int) config('ces.minimum_academic_year', 2020)
        ) {
            throw new RuntimeException('CES source API table is outside the configured academic-year range.');
        }

        yield from $this->pages($this->campusPath($campusCode)."/student-numbers/{$dataset}", [
            'table' => $table,
        ]);
    }

    /**
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function registrationPages(string $campusCode): Generator
    {
        foreach ($this->pages($this->campusPath($campusCode).'/registrations') as $records) {
            yield array_values(array_filter(
                $records,
                fn (array $record): bool => $this->isAtOrAfterMinimumAcademicYear($record['SchoolYear'] ?? null)
            ));
        }
    }

    /**
     * @return Generator<int, list<array<string, mixed>>>
     */
    public function graduationListPages(string $campusCode): Generator
    {
        foreach ($this->pages($this->campusPath($campusCode).'/graduation-list') as $records) {
            yield array_values(array_filter(
                $records,
                fn (array $record): bool => $this->isAtOrAfterMinimumAcademicYear($record['SchoolYear'] ?? null)
            ));
        }
    }

    /**
     * @return Generator<int, list<array<string, mixed>>>
     */
    private function pages(string $path, array $query = []): Generator
    {
        $pageSize = max(1, min(1000, (int) config('source_apis.ces.page_size', 1000)));
        for ($page = 1; $page <= 100000; $page++) {
            $payload = $this->request()->get($this->url($path), [
                ...$query,
                'page' => $page,
                'per_page' => $pageSize,
            ])->throw()->json();

            if (
                ! is_array($payload)
                || ! is_array($payload['data'] ?? null)
                || ! is_array($payload['meta'] ?? null)
                || (int) ($payload['meta']['current_page'] ?? 0) !== $page
                || ! is_bool($payload['meta']['has_more'] ?? null)
            ) {
                throw new RuntimeException('CES source API returned an invalid paginated response.');
            }

            foreach ($payload['data'] as $row) {
                if (! is_array($row)) {
                    throw new RuntimeException('CES source API returned an invalid record.');
                }
            }
            if ($payload['meta']['has_more'] && $payload['data'] === []) {
                throw new RuntimeException('CES source API pagination did not make progress.');
            }

            yield $payload['data'];
            if (! $payload['meta']['has_more']) {
                return;
            }
        }

        throw new RuntimeException('CES source API exceeded the maximum page count.');
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(string $path): array
    {
        $data = $this->request()->get($this->url($path))->throw()->json('data');
        if (! is_array($data)) {
            throw new RuntimeException('CES source API returned an invalid response.');
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getList(string $path): array
    {
        return $this->validateList($this->request()->get($this->url($path))->throw()->json('data'));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function postList(string $path, array $body): array
    {
        return $this->validateList($this->request()->post($this->url($path), $body)->throw()->json('data'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validateList(mixed $data): array
    {
        if (! is_array($data) || ! array_is_list($data)) {
            throw new RuntimeException('CES source API returned an invalid record list.');
        }
        foreach ($data as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('CES source API returned an invalid record.');
            }
        }

        return $data;
    }

    private function request(): PendingRequest
    {
        $token = config('source_apis.ces.api_key');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('CES_API_KEY must be configured before running the warehouse ETL.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->timeout(120);
    }

    private function campusPath(string $campusCode): string
    {
        if (! preg_match('/^[a-z0-9_-]+$/D', $campusCode)) {
            throw new RuntimeException('CES campus code contains unsupported characters.');
        }

        return "/{$campusCode}";
    }

    private function isAtOrAfterMinimumAcademicYear(mixed $value): bool
    {
        return is_scalar($value)
            && preg_match('/^\s*(\d{4})/', (string) $value, $matches) === 1
            && (int) $matches[1] >= (int) config('ces.minimum_academic_year', 2020);
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('source_apis.ces.url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('CES source API URL must be configured before running the warehouse ETL.');
        }

        return $baseUrl.$path;
    }
}
