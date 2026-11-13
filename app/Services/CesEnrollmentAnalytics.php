<?php

namespace App\Services;

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

    public function __construct(private readonly CesAnalyticsSnapshotStore $snapshots) {}

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

        return $this->buildResponse($this->snapshots->read(), $filters);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array{campus: string|null, college: string|null, program: string|null, year: string|null, semester: string|null}  $filters
     * @return array<string, mixed>
     */
    private function buildResponse(array $snapshot, array $filters): array
    {
        $allCampuses = config('ces.campuses', []);
        $campuses = $filters['campus'] === null
            ? $allCampuses
            : array_intersect_key($allCampuses, [$filters['campus'] => true]);
        $snapshotCampuses = $snapshot['campuses'] ?? [];

        $availableTerms = [];
        foreach (array_keys($campuses) as $campusKey) {
            foreach (($snapshotCampuses[$campusKey]['terms'] ?? []) as $year => $semesters) {
                foreach (array_keys($semesters) as $semester) {
                    $availableTerms[(string) $year][(string) $semester] = true;
                }
            }
            foreach (($snapshotCampuses[$campusKey]['grade_terms'] ?? []) as $year => $semesters) {
                foreach (array_keys($semesters) as $semester) {
                    $availableTerms[(string) $year][(string) $semester] = true;
                }
            }
            foreach (($snapshotCampuses[$campusKey]['graduate_terms'] ?? []) as $term) {
                if (isset($term['year'], $term['semester'])) {
                    $availableTerms[(string) $term['year']][(string) $term['semester']] = true;
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
            $selectedSemester = in_array('1', $semesters, true)
                ? '1'
                : $semesters[count($semesters) - 1];
        }

        $catalog = array_values(array_filter(
            $snapshot['catalog'] ?? [],
            fn ($item) => array_key_exists($item['campus'] ?? '', $campuses)
        ));
        $collegeOptions = [];
        foreach ($catalog as $item) {
            $college = trim((string) ($item['college'] ?? ''));
            if ($college !== '') {
                $collegeOptions[$college] = ['value' => $college, 'label' => $college];
            }
        }
        uasort($collegeOptions, fn ($left, $right) => strnatcasecmp($left['label'], $right['label']));

        $programOptions = [];
        foreach ($catalog as $item) {
            $college = trim((string) ($item['college'] ?? ''));
            $program = trim((string) ($item['program'] ?? ''));
            if ($program !== '' && ($filters['college'] === null || $college === $filters['college'])) {
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
        $hasEnrollmentData = false;
        $demographics = [];
        $scholarships = [];
        $availableIncomeCampuses = array_filter(
            $campuses,
            fn ($campus, $key) => ($snapshotCampuses[$key]['income_available'] ?? false) === true,
            ARRAY_FILTER_USE_BOTH
        );
        $incomeCampuses = $filters['campus'] === null
            ? $availableIncomeCampuses
            : array_intersect_key($availableIncomeCampuses, [$filters['campus'] => true]);
        foreach (self::DEMOGRAPHIC_METRICS as $metric) {
            $isGenderBreakdown = isset($metric['column']);
            $countKey = $metric['count_key'] ?? null;
            $isIncomeCount = $countKey === 'household_income';
            $available = $isGenderBreakdown
                || $countKey === 'financial_aid'
                || ($isIncomeCount && $incomeCampuses !== []);
            $demographics[$metric['key']] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'available' => $available,
                'display' => $isGenderBreakdown ? 'gender' : 'count',
                'financial' => $countKey !== null,
                'total' => $available ? 0 : null,
                'male' => $isGenderBreakdown ? 0 : null,
                'female' => $isGenderBreakdown ? 0 : null,
                'note' => $isIncomeCount
                    ? ($incomeCampuses === []
                        ? 'Annual-income data is unavailable for this campus.'
                        : 'Counts include stored default value 1; income data is available only in '.implode(', ', array_column($incomeCampuses, 'name')).'.')
                    : ($metric['note'] ?? null),
            ];
        }

        $campusStatuses = [];
        $graduationUnavailable = [];
        foreach ($campuses as $key => $campus) {
            $campusSnapshot = $snapshotCampuses[$key] ?? null;
            $hasStage = $selectedYear !== null
                && $selectedSemester !== null
                && isset($campusSnapshot['terms'][$selectedYear][$selectedSemester]);
            if (! $hasStage) {
                $graduationUnavailable[$key] = true;
            }
            $campusStatuses[] = [
                'key' => $key,
                'name' => $campus['name'],
                'status' => $campusSnapshot['status'] ?? 'unavailable',
                'graduation_status' => $hasStage ? 'available' : 'unavailable',
                'income_status' => ($campusSnapshot['income_available'] ?? false) ? 'available' : 'unavailable',
            ];
        }

        foreach ($snapshot['rows'] ?? [] as $row) {
            if (
                ! array_key_exists($row['campus'] ?? '', $campuses)
                || ($row['year'] ?? null) !== $selectedYear
                || ($row['semester'] ?? null) !== $selectedSemester
                || ($filters['college'] !== null && ($row['college'] ?? '') !== $filters['college'])
                || ($filters['program'] !== null && ($row['program'] ?? '') !== $filters['program'])
            ) {
                continue;
            }

            foreach ($summaryAliases as $metric => $column) {
                $value = $row['summary'][$column] ?? null;
                if ($value !== null) {
                    $summary[$metric] += (int) $value;
                    if ($metric === 'total-enrolled-students') {
                        $hasEnrollmentData = true;
                    }
                }

            }

            foreach (self::DEMOGRAPHIC_METRICS as $metric) {
                $countKey = $metric['count_key'] ?? null;
                if ($countKey !== null) {
                    $isIncome = $countKey === 'household_income';
                    if (! $isIncome || ($snapshotCampuses[$row['campus']]['income_available'] ?? false)) {
                        $demographics[$metric['key']]['total'] += (int) ($row['demographics'][$countKey]['total'] ?? 0);
                    }

                    continue;
                }

                if (isset($metric['column'])) {
                    $values = $row['demographics'][$metric['column']] ?? [];
                    $demographics[$metric['key']]['total'] += (int) ($values['total'] ?? 0);
                    $demographics[$metric['key']]['male'] += (int) ($values['male'] ?? 0);
                    $demographics[$metric['key']]['female'] += (int) ($values['female'] ?? 0);
                }
            }

            foreach ($row['demographics']['financial_aid']['scholarships'] ?? [] as $scholarship) {
                $name = trim((string) ($scholarship['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $scholarshipKey = strtolower($name);
                $scholarships[$scholarshipKey] ??= ['name' => $name, 'total' => 0];
                $scholarships[$scholarshipKey]['total'] += (int) ($scholarship['total'] ?? 0);
            }
        }

        foreach ($snapshot['graduates'] ?? [] as $graduate) {
            if (
                ($graduate['year'] ?? null) === $selectedYear
                && array_key_exists($graduate['campus'] ?? '', $campuses)
                && ($filters['college'] === null || ($graduate['college'] ?? '') === $filters['college'])
                && ($filters['program'] === null || ($graduate['program'] ?? '') === $filters['program'])
            ) {
                $summary['already-graduated-students'] += (int) ($graduate['already_graduated'] ?? 0);
            }
            if (! $hasEnrollmentData) {
                $summary['total-enrolled-students'] = null;
            }
        }

        foreach ($campuses as $key => $campus) {
            if (($snapshotCampuses[$key]['status'] ?? null) !== 'available') {
                $graduationUnavailable[$key] = true;
            }
        }

        $scholarships = array_values($scholarships);
        usort($scholarships, fn ($left, $right) => strnatcasecmp($left['name'], $right['name']));

        return [
            'summary' => array_map(
                fn ($metric) => [...$metric, 'value' => $summary[$metric['key']]],
                self::SUMMARY_METRICS
            ),
            'demographics' => array_values($demographics),
            'scholarships' => $scholarships,
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
            'partial' => $graduationUnavailable !== [],
            'updated_at' => $snapshot['synced_at'] ?? null,
        ];
    }
}
