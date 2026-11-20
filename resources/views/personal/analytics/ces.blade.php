    @php
        $cesDemographics = collect($cesAnalytics['demographics']);
    @endphp
    <div class="qams-ces-analytics" data-ces-analytics data-ces-analytics-url="{{ route('qams.ces.analytics') }}">
    <script type="application/json" data-ces-analytics-data>@json($cesAnalytics)</script>
    <p class="alert" data-ces-analytics-status role="status" aria-live="polite" hidden></p>
    <section class="mb-12" aria-label="Filter enrollment analytics">
        <div class="row g-3">
                @foreach (['campus' => 'Campus', 'college' => 'Faculty', 'program' => 'Program', 'year' => 'Academic Year', 'semester' => 'Semester'] as $filterKey => $filterLabel)
                    <div class="col-12 col-md-6 col-xl">
                        <label class="form-label qams-ces-filter-label" for="ces-filter-{{ $filterKey }}">
                            <i class="icon-base {{ ['campus' => 'ri ri-building-2-line', 'college' => 'ri ri-government-line', 'program' => 'ri ri-graduation-cap-line', 'year' => 'ri ri-calendar-2-line', 'semester' => 'ri ri-calendar-event-line'][$filterKey] }} me-1" aria-hidden="true"></i>{{ $filterLabel }}
                        </label>
                        <select class="form-select qams-ces-filter-select" id="ces-filter-{{ $filterKey }}" data-ces-filter="{{ $filterKey }}">
                            <option value="">{{ ['campus' => 'All Campuses', 'college' => 'All Faculties', 'program' => 'All Programs', 'year' => 'Latest Academic Year', 'semester' => 'Latest Semester'][$filterKey] }}</option>
                            @foreach ($cesAnalytics['filters'][$filterKey] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
        </div>
    </section>

    <section class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3" aria-label="Enrollment metrics">
        @foreach ($cesAnalytics['summary'] as $metric)
            <div class="col">
                <article class="card qams-summary-card qams-analytics-summary-card {{ $metric['key'] === 'total-enrolled-students' ? 'qams-analytics-summary-primary' : '' }} h-100">
                    <div class="card-body">
                        <span class="qams-summary-icon"><i class="icon-base {{ $metric['icon'] }}" aria-hidden="true"></i></span>
                        <span class="qams-summary-label">{{ $metric['label'] }}</span>
                        <strong class="qams-summary-value" data-ces-summary="{{ $metric['key'] }}">—</strong>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <section class="row g-3 mt-3" aria-label="Enrollment overview">
        <div class="col-12 col-lg-5 col-xl-4">
            <article class="card qams-ces-demographic-overview h-100">
                <div class="card-body">
                    <h6 class="card-title mb-2">Overall Student Gender</h6>
                    <p class="small text-body-secondary mb-0" data-ces-student-gender-status>Loading student gender data…</p>
                    <div data-ces-student-gender-chart hidden></div>
                </div>
            </article>
        </div>
        <div class="col-12 col-lg-7 col-xl-8">
            <article class="card qams-ces-demographic-overview h-100">
                <div class="card-body">
                    <h6 class="card-title mb-2">Enrollment Trend</h6>
                    <p class="small text-body-secondary mb-0" data-ces-enrollment-trend-status>Loading enrollment trend…</p>
                    <div data-ces-enrollment-trend-chart hidden></div>
                </div>
            </article>
        </div>
    </section>

    <div class="qams-analytics-section d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h5 class="qams-ces-section-title mb-1">Student Demographics</h5>
            <p class="mb-0 text-body-secondary">Student counts and gender breakdowns where available.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="btn-group qams-ces-view-toggle" role="group" aria-label="Demographic display">
                <button class="btn btn-outline-secondary active" type="button" data-ces-view="chart" aria-pressed="true" aria-label="Show comparison chart" title="Comparison chart">
                    <i class="icon-base ri ri-bar-chart-horizontal-line"></i>
                </button>
                <button class="btn btn-outline-secondary" type="button" data-ces-view="cards" aria-pressed="false" aria-label="Show detailed demographic cards" title="Detailed cards">
                    <i class="icon-base ri ri-grid-line"></i>
                </button>
            </div>
        </div>
    </div>

    <section class="row g-3 gy-4" data-ces-demographic-cards aria-label="Detailed student demographics" hidden>
        @foreach ($cesDemographics->reject(fn ($demographic) => $demographic['financial'] ?? false)->sortBy(fn ($demographic) => $demographic['display'] !== 'gender') as $demographic)
            <div class="col-12 col-md-6 col-xl-4" data-ces-demographic-card="{{ $demographic['key'] }}">
                <article class="card qams-ces-demographic-card qams-ces-demographic-card--gender h-100">
                    <div class="card-body">
                        <div class="qams-ces-demographic-info">
                            <div class="qams-ces-demographic-heading">
                                <span class="qams-ces-demographic-icon" aria-hidden="true">
                                    <i class="icon-base {{ $demographic['icon'] ?? 'ri ri-user-line' }}"></i>
                                </span>
                                <h6 class="card-title mb-0">{{ $demographic['label'] }}</h6>
                            </div>
                            <div class="qams-ces-demographic-count" data-ces-demographic-count hidden>
                                <strong class="qams-ces-demographic-total" data-ces-demographic-total>&mdash;</strong>
                                <span>students</span>
                            </div>
                        </div>
                        <div class="qams-ces-demographic-chart-wrap">
                            @if ($demographic['display'] === 'gender')
                                <div class="qams-ces-demographic-donut" data-ces-demographic-chart="{{ $demographic['key'] }}"
                                    role="img" aria-label="{{ $demographic['label'] }} gender distribution" hidden></div>
                            @endif
                            <div class="qams-ces-demographic-empty" data-ces-demographic-empty aria-hidden="true"></div>
                        </div>
                        @if ($demographic['display'] === 'gender')
                            <div class="qams-ces-gender-values" data-ces-gender-values hidden>
                                <span class="qams-ces-gender-count">
                                    Male <strong class="visually-hidden" data-ces-male>&mdash;</strong>
                                </span>
                                <span class="qams-ces-gender-count">
                                    Female <strong class="visually-hidden" data-ces-female>&mdash;</strong>
                                </span>
                            </div>
                        @endif
                        <div class="qams-ces-gender-values" data-ces-demographic-empty-label>No Student Record</div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>
    <section class="card qams-ces-demographic-overview mt-4" data-ces-demographic-overview aria-label="Student demographics comparison">
        <div class="card-body">
            <h6 class="card-title mb-3" data-ces-overview-title>Student counts per group</h6>
            <div class="qams-ces-demographic-overview-chart" data-ces-overview-chart></div>
        </div>
    </section>
    <section class="qams-analytics-section" aria-labelledby="ces-financial-title">
        <h5 class="qams-ces-section-title mb-1" id="ces-financial-title">Financial Demographics</h5>
        <p class="mb-3 text-body-secondary">Student counts for financial aid and reported annual income.</p>
        <div class="qams-ces-demographic-overview qams-ces-financial-panel rounded">
            <div class="card-body">
                <p class="text-body-secondary mb-0" data-ces-financial-loading>Loading financial demographics&hellip;</p>
                <div data-ces-financial-content hidden>
                    <div data-ces-financial-chart></div>
                    <ul class="list-unstyled d-flex flex-wrap justify-content-center gap-3 small mb-0"
                        data-ces-financial-legend aria-label="Financial demographics chart legend" hidden></ul>
                </div>
            </div>
        </div>
    </section>
</div>
