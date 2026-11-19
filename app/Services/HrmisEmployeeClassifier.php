<?php

namespace App\Services;

class HrmisEmployeeClassifier
{
    /**
     * @return array{
     *     category: string,
     *     is_teaching: bool,
     *     is_non_teaching: bool,
     *     is_part_time: bool,
     *     is_part_time_teaching: bool,
     *     is_senior_academic: bool,
     *     is_guidance_counselor: bool,
     *     is_physical_health: bool
     * }
     */
    public function position(?string $title, ?string $employmentStatus = null): array
    {
        $normalized = $this->normalize($title);
        $status = $this->normalize($employmentStatus);

        $category = match (true) {
            preg_match('/\buniversity\s+professor\b/', $normalized) === 1 => 'university_professor',
            preg_match('/\b(associate|assoc|asso|asociate|associte)\s+prof/', $normalized) === 1 => 'associate_professor',
            preg_match('/\b(assistant|asst|assitant|asssistant)\s+prof/', $normalized) === 1 => 'assistant_professor',
            preg_match('/\b(professor|prof)\b/', $normalized) === 1 => 'professor',
            preg_match('/\b(instructor|insrtructor|lecturer)\b/', $normalized) === 1 => 'instructor',
            preg_match('/\b(part\s+time|part\s+timer)\b/', $normalized) === 1
                && preg_match('/\b(faculty|teachers?|teaching|instructor|lecturer)\b/', $normalized) === 1 => 'part_time_teaching',
            preg_match('/\bteachers?\b/', $normalized) === 1 => 'teacher',
            default => 'unknown_position',
        };

        $isTeaching = $category !== 'unknown_position'
            || preg_match('/\b(teaching|faculty|instructor|lecturer)\b/', $normalized) === 1
            || preg_match('/\b(teachers?|teaching|faculty|instructor|lecturer)\b/', $status) === 1;
        $isTeaching = match ($status) {
            'permanent faculty', 'temporary faculty', 'part timer', 'parttimer', 'visiting instructor' => true,
            'permanent staff', 'job', 'job order', 'contract of service', 'contract of services', 'cos',
            'co terminous', 'coterminous' => false,
            default => $isTeaching,
        };
        $statusIdentifiesNonTeaching = preg_match(
            '/\b(staff|job order|security guards?|student assist(?:ance|ant)|on job training|casual|contractual|contract of service|co ?terminous)\b/',
            $status
        ) === 1;
        $isNonTeaching = ! $isTeaching && ($normalized !== '' || $statusIdentifiesNonTeaching || in_array($status, ['job', 'cos'], true));
        if ($isNonTeaching && $normalized !== '') {
            $category = 'non_teaching';
        } elseif ($category === 'unknown_position' && $normalized !== '') {
            $category = $isTeaching ? 'teaching_unranked' : 'non_teaching';
        }
        $isPartTime = preg_match('/\bpart\s+time(r)?\b/', $normalized) === 1
            || preg_match('/\bpart\s*time(r)?\b/', $status) === 1
            || str_contains($status, 'visiting instructor');
        $isPartTimeTeaching = $isTeaching
            && $isPartTime;
        $isGuidanceCounselor = preg_match('/\b(guidance|counselor|counselling|counseling)\b/', $normalized) === 1;
        $isPhysicalHealth = preg_match('/\b(nurse|physician|doctor|medical|dentist)\b/', $normalized) === 1;
        $seniorRanks = config('hrmis.senior_academic_ranks', [
            'associate_professor',
            'professor',
            'university_professor',
        ]);

        return [
            'category' => $category,
            'is_teaching' => $isTeaching,
            'is_non_teaching' => $isNonTeaching,
            'is_part_time' => $isPartTime,
            'is_part_time_teaching' => $isPartTimeTeaching,
            'is_senior_academic' => in_array($category, $seniorRanks, true),
            'is_guidance_counselor' => $isGuidanceCounselor,
            'is_physical_health' => $isPhysicalHealth,
        ];
    }

    /**
     * @return array{
     *     canonical_status: string,
     *     is_part_time: bool,
     *     is_permanent: bool,
     *     is_casual: bool,
     *     is_contractual: bool,
     *     is_job_order: bool
     * }
     */
    public function employment(?string $status): array
    {
        $normalized = $this->normalize($status);
        $isPartTime = preg_match('/\bpart\s*time(r)?\b/', $normalized) === 1
            || str_contains($normalized, 'visiting instructor');
        $isPermanent = str_contains($normalized, 'permanent');
        $isCasual = str_contains($normalized, 'casual');
        $isContractual = str_contains($normalized, 'contractual')
            || str_contains($normalized, 'contract of service')
            || $normalized === 'cos'
            || str_contains($normalized, 'co terminous');
        $isJobOrder = $normalized === 'job' || str_contains($normalized, 'job order');

        $canonical = match (true) {
            $isJobOrder => 'job_order',
            $isPartTime => 'part_time',
            $isPermanent => 'permanent',
            $isCasual => 'casual',
            $isContractual => 'contractual',
            str_contains($normalized, 'temporary') => 'temporary',
            default => 'unknown_emp_status',
        };

        return [
            'canonical_status' => $canonical,
            'is_part_time' => $isPartTime,
            'is_permanent' => $isPermanent,
            'is_casual' => $isCasual,
            'is_contractual' => $isContractual,
            'is_job_order' => $isJobOrder,
        ];
    }

    public function gender(mixed $value): string
    {
        return match ($this->normalize(is_scalar($value) ? (string) $value : null)) {
            'male', 'm', 'man' => 'male',
            'female', 'f', 'woman' => 'female',
            default => 'unknown',
        };
    }

    public function educationCategory(
        ?string $studentLevel,
        ?string $degreeCourse,
        ?string $unitsEarned,
        ?string $yearGraduated
    ): ?string {
        $level = $this->normalize($studentLevel);
        $degree = $this->normalize($degreeCourse);
        $unitsRaw = trim((string) $unitsEarned);
        $units = $this->normalize($unitsEarned);
        $graduated = preg_match('/\b(19|20)\d{2}\b/', trim((string) $yearGraduated)) === 1;
        $hasUnits = $units !== ''
            && ! in_array($units, ['none', 'no unit', 'no units', 'n a', 'na', 'not applicable'], true)
            && (! is_numeric($unitsRaw) || (float) $unitsRaw > 0);

        if (str_contains($level, 'secondary') || str_contains($level, 'high school')) {
            return 'high_school';
        }

        if (str_contains($level, 'vocational') || str_contains($level, 'tesda')) {
            return 'tesda';
        }

        if (str_contains($level, 'college')) {
            return $graduated ? 'bachelor' : null;
        }

        if (! str_contains($level, 'graduate')) {
            return null;
        }

        $doctorate = preg_match('/\b(doctor|doctoral|doctorate|phd|ph d|edd|dba|dpa)\b/', $degree) === 1;
        $masters = preg_match('/\b(master|masters|mba|ma|m a|ms|m s|msc|m sc|maed|m a ed|msed|m s ed|mse|m s e|msw|m sw)\b/', $degree) === 1;

        if ($doctorate) {
            return $graduated ? 'doctorate_degree' : ($hasUnits ? 'doctorate_units' : null);
        }

        if ($masters) {
            return $graduated ? 'masters_degree' : ($hasUnits ? 'master_units' : null);
        }

        return null;
    }

    /**
     * @param  iterable<array{student_level?: ?string, degree_course?: ?string, units_earned?: ?string, year_graduated?: ?string}>  $records
     */
    public function highestEducation(iterable $records): string
    {
        $rank = [
            'high_school' => 1,
            'tesda' => 2,
            'bachelor' => 3,
            'master_units' => 4,
            'masters_degree' => 5,
            'doctorate_units' => 6,
            'doctorate_degree' => 7,
        ];
        $highest = 'unknown';

        foreach ($records as $record) {
            $category = $this->educationCategory(
                $record['student_level'] ?? null,
                $record['degree_course'] ?? null,
                $record['units_earned'] ?? null,
                $record['year_graduated'] ?? null
            );

            if ($category !== null && ($rank[$category] ?? 0) > ($rank[$highest] ?? 0)) {
                $highest = $category;
            }
        }

        return $highest;
    }

    private function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim($value ?? '');
    }
}
