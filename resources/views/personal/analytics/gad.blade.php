@if ($gadAnalytics === null)
    <div class="alert alert-warning" role="status">No GAD warehouse snapshot is available.</div>
@else
    @php
        $gad = $gadAnalytics;
        $statusIcons = [
            'submitted' => 'ri-file-list-3-line',
            'approved' => 'ri-checkbox-circle-line',
            'ongoing' => 'ri-time-line',
            'overdue' => 'ri-error-warning-line',
            'completed' => 'ri-calendar-check-line',
        ];
        $statusDescriptions = [
            'submitted' => 'Filed in this period',
            'approved' => 'Granted approval',
            'ongoing' => 'Within planned dates',
            'overdue' => 'Past due, awaiting approval',
            'completed' => 'Final report approved',
        ];
        $monthNames = collect(range(1, 12))->mapWithKeys(
            fn ($month) => [$month => date('M', mktime(0, 0, 0, $month, 1, 2000))]
        );
        $count = fn ($value) => $value === null ? '—' : number_format($value);
        $money = fn ($value) => $value === null ? '—' : '₱'.number_format((float) $value, 2);
        $campusLabels = collect($gad['byCampus'])->keys()->map(fn ($id) => $gad['campuses']->get($id, 'Unspecified campus'));
        $chartData = [
            'months' => $monthNames->values(),
            'monthCounts' => array_values($gad['months']),
            'campuses' => $campusLabels,
            'campusCounts' => array_values($gad['byCampus']),
        ];
    @endphp

    <div data-gad-analytics data-chart-data="{{ json_encode($chartData, JSON_THROW_ON_ERROR) }}">
        <section class="mb-12" aria-label="Filter proposal analytics">
            <form method="GET" class="row g-3 align-items-end" data-gad-filters>
                <div class="col-12 col-md-6 col-xl">
                    <label class="form-label qams-ces-filter-label" for="gad-campus"><i class="icon-base ri ri-building-2-line me-1" aria-hidden="true"></i>Campus</label>
                    <select class="form-select qams-ces-filter-select" id="gad-campus" name="campus">
                        <option value="">University-wide</option>
                        @foreach ($gad['campuses'] as $id => $name)
                            <option value="{{ $id }}" @selected((int) $id === $gad['campusId'])>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <label class="form-label qams-ces-filter-label" for="gad-department"><i class="icon-base ri ri-government-line me-1" aria-hidden="true"></i>Department</label>
                    <select class="form-select qams-ces-filter-select" id="gad-department" name="department" @disabled($gad['campusId'] === null)>
                        <option value="">{{ $gad['campusId'] === null ? 'Select a campus first' : 'All departments' }}</option>
                        @foreach ($gad['departments'] as $id => $name)
                            <option value="{{ $id }}" @selected((int) $id === $gad['departmentId'])>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <label class="form-label qams-ces-filter-label" for="gad-year"><i class="icon-base ri ri-calendar-2-line me-1" aria-hidden="true"></i>Submission Year</label>
                    <select class="form-select qams-ces-filter-select" id="gad-year" name="year">
                        @foreach ($gad['years'] as $year)
                            <option value="{{ $year }}" @selected($year === $gad['year'])>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl">
                    <label class="form-label qams-ces-filter-label" for="gad-month"><i class="icon-base ri ri-calendar-event-line me-1" aria-hidden="true"></i>Submission Month</label>
                    <select class="form-select qams-ces-filter-select" id="gad-month" name="month">
                        <option value="">All months</option>
                        @foreach ($monthNames as $month => $label)
                            <option value="{{ $month }}" @selected($month === $gad['month'])>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </section>

        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
            @foreach ([
                ['label' => 'Total Gender Responsive Proposals', 'value' => $gad['genderResponsiveTotal'], 'icon' => 'ri-checkbox-circle-line', 'primary' => true],
                ['label' => 'Planned proposal beneficiaries', 'value' => $gad['beneficiaryTotal'], 'icon' => 'ri-group-line'],
                ['label' => 'Accomplishment reports', 'value' => $gad['accomplishmentTotal'], 'icon' => 'ri-file-list-3-line'],
            ] as $metric)
                <div class="col">
                    <div @class(['card qams-summary-card qams-analytics-summary-card h-100', 'qams-analytics-summary-primary' => $metric['primary'] ?? false])>
                        <div class="card-body">
                            <span class="qams-summary-icon" aria-hidden="true"><i class="icon-base ri {{ $metric['icon'] }}"></i></span>
                            <span class="qams-summary-label">{{ $metric['label'] }}</span>
                            <strong class="qams-summary-value">{{ $count($metric['value']) }}</strong>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3">
            @foreach ($gad['summary'] as $status => $value)
                <div class="col">
                    <article class="card qams-summary-card qams-analytics-summary-card h-100">
                        <div class="card-body">
                            <span class="qams-summary-icon" aria-hidden="true"><i class="icon-base ri {{ $statusIcons[$status] }}"></i></span>
                            <span class="qams-summary-label">{{ ucfirst($status) }}</span>
                            <strong class="qams-summary-value">{{ $count($value) }}</strong>
                            <span class="qams-summary-caption d-block text-truncate" title="{{ $statusDescriptions[$status] }}">{{ $statusDescriptions[$status] }}</span>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="qams-analytics-section mb-3">
            <h5 class="qams-ces-section-title mb-1" id="gad-monthly-cohort-title">Monthly Proposal Cohort</h5>
            <p class="mb-0 text-body-secondary">{{ $gad['year'] }} submissions and their latest outcomes.</p>
        </div>
        <section class="card" aria-labelledby="gad-monthly-cohort-title">
            <div class="card-body">
                <div class="qams-gad-monthly-charts">
                    @foreach ([
                        'Submission and Approval' => ['submitted', 'approved'],
                        'Implementation Status' => ['ongoing', 'overdue', 'completed'],
                    ] as $title => $statuses)
                        <div>
                            <h6 class="mb-2">{{ $title }}</h6>
                            <div data-gad-monthly-chart="{{ implode(' ', $statuses) }}" role="img" aria-label="Monthly {{ strtolower($title) }} counts"></div>
                        </div>
                    @endforeach
                </div>
                <details class="mt-3">
                    <summary class="text-primary">View monthly chart data</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm">
                            <thead><tr><th scope="col">Month</th>@foreach ($statusIcons as $status => $icon)<th scope="col">{{ ucfirst($status) }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($gad['months'] as $month => $values)
                                    <tr><th scope="row">{{ $monthNames[$month] }}</th>@foreach ($values as $value)<td>{{ $count($value) }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </section>

        <div class="qams-analytics-section mb-3">
            <h5 class="qams-ces-section-title mb-1" id="gad-campus-status-title">Proposal Status by Campus</h5>
            <p class="mb-0 text-body-secondary">Compare submissions, approvals, and implementation outcomes across campuses.</p>
        </div>
        <section class="card mb-4" aria-labelledby="gad-campus-status-title">
            <div class="card-body">
                <div data-gad-campus-chart role="img" aria-label="Proposal counts by campus"></div>
                <details class="mt-2">
                    <summary class="text-primary">View campus comparison data</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm qams-campus-comparison-table">
                            <thead>
                                <tr>
                                    <th scope="col">Campus</th>
                                    @foreach ($statusIcons as $status => $icon)
                                        <th scope="col">{{ ucfirst($status) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($gad['byCampus'] as $id => $values)
                                    <tr>
                                        <th scope="row">{{ $gad['campuses']->get($id, 'Unspecified campus') }}</th>
                                        @foreach ($values as $value)
                                            <td>{{ $count($value) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </section>

        <div class="qams-analytics-section mb-3">
            <h5 class="qams-ces-section-title mb-1" id="gad-proposals-title">Proposals</h5>
            <p class="mb-0 text-body-secondary">Proposal details and beneficiary counts for the selected cohort.</p>
        </div>
        <section class="card mb-4" id="gad-proposals" aria-labelledby="gad-proposals-title">
            <div class="table-responsive overflow-x-auto" tabindex="0" role="region" aria-label="Proposals table">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-nowrap">GAD Ref Number</th>
                            <th scope="col">Title</th>
                            <th scope="col">Proposed date</th>
                            <th scope="col">Budget</th>
                            <th scope="col">GAD attribution</th>
                            <th scope="col">Department</th>
                            <th scope="col">Beneficiaries</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gad['proposalRows'] as $proposal)
                            <tr>
                                <th scope="row" class="text-nowrap">{{ $proposal->reference }}</th>
                                <td><span class="qams-gad-proposal-title" title="{{ $proposal->title }}">{{ $proposal->title }}</span></td>
                                <td class="text-nowrap">{{ $proposal->proposed_on ?? '—' }}</td><td>{{ $money($proposal->budget) }}</td>
                                <td>{{ $money($proposal->attribution) }}</td>
                                <td>{{ $proposal->department ?? '—' }}</td><td>{{ $count($proposal->beneficiary_total) }}</td>
                                <td>{{ ucfirst($proposal->outcome) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No proposals match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($gad['proposalRows']->hasPages())
                <div class="card-footer pb-0">{{ $gad['proposalRows']->fragment('gad-proposals')->links('pagination::bootstrap-5') }}</div>
            @endif
        </section>

        <div class="qams-analytics-section mb-3">
            <h5 class="qams-ces-section-title mb-1" id="gad-accomplishments-title">Accomplishment Reports</h5>
            <p class="mb-0 text-body-secondary">Reports with final GAD approval for the selected cohort.</p>
        </div>
        <section class="card" id="gad-accomplishments" aria-labelledby="gad-accomplishments-title">
            <div class="table-responsive overflow-x-auto" tabindex="0" role="region" aria-label="Accomplishment reports table">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col" class="text-nowrap">GAD Ref Number</th>
                            <th scope="col">Proposal</th>
                            <th scope="col">Actual end date</th>
                            <th scope="col">Participants</th>
                            <th scope="col">Actual budget use</th>
                            <th scope="col">GAD attribution</th>
                            <th scope="col">Review status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gad['accomplishments'] as $report)
                            <tr>
                                <th scope="row" class="text-nowrap">{{ $report->reference }}</th>
                                <td><span class="qams-gad-proposal-title" title="{{ $report->title }}">{{ $report->title }}</span></td>
                                <td class="text-nowrap">{{ $report->ended_on ?? '—' }}</td>
                                <td>{{ $count($report->participants) }}</td><td>{{ $money($report->actual_budget_use) }}</td>
                                <td>{{ $money($report->attribution) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $report->review_status ?? $report->status)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No approved accomplishment reports match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($gad['accomplishments']->hasPages())
                <div class="card-footer pb-0">{{ $gad['accomplishments']->fragment('gad-accomplishments')->links('pagination::bootstrap-5') }}</div>
            @endif
        </section>
    </div>
@endif
