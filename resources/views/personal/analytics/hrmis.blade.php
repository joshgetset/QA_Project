@if ($hrmisAnalytics === null)
    <p class="alert alert-secondary mb-0">HRMIS warehouse data is unavailable.</p>
@else
    <div data-hrmis-analytics>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-12">
            <form class="d-flex flex-wrap gap-3" method="get" data-hrmis-filters>
                <div>
                    <label class="form-label qams-ces-filter-label" for="hrmis-campus">
                        <i class="icon-base ri ri-building-2-line me-1" aria-hidden="true"></i>Campus
                    </label>
                    <select class="form-select qams-ces-filter-select" id="hrmis-campus" name="campus">
                        <option value="">All Campuses</option>
                        @foreach ($hrmisAnalytics['campuses'] as $campus)
                            <option value="{{ $campus->id }}" @selected((int) $campus->id === $hrmisAnalytics['campus_id'])>{{ $campus->source_campus_label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label qams-ces-filter-label" for="hrmis-year">
                        <i class="icon-base ri ri-calendar-2-line me-1" aria-hidden="true"></i>Reporting Year
                    </label>
                    <select class="form-select qams-ces-filter-select" id="hrmis-year" name="year">
                        @foreach ($hrmisAnalytics['years'] as $year)
                            <option value="{{ $year }}" @selected($year === $hrmisAnalytics['year'])>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <section class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3" aria-label="Employee totals">
            @foreach ($hrmisAnalytics['summary'] as $metric)
                <div class="col">
                    <article @class(['card qams-summary-card qams-analytics-summary-card h-100', 'qams-analytics-summary-primary' => $loop->first])>
                        <div class="card-body">
                            <span class="qams-summary-icon"><i class="icon-base ri {{ $metric['icon'] }}" aria-hidden="true"></i></span>
                            <span class="qams-summary-label">{{ $metric['label'] }}</span>
                            <strong class="qams-summary-value">{{ number_format($metric['total']) }}</strong>
                        </div>
                    </article>
                </div>
            @endforeach
        </section>

        <section class="row row-cols-1 row-cols-md-2 g-3 mt-3" aria-label="Overall personnel gender breakdown">
            @foreach ($hrmisAnalytics['overall_gender'] as $metric)
                <div class="col">
                    @include('personal.analytics.hrmis-gender-donut', ['metric' => $metric])
                </div>
            @endforeach
        </section>

        @include('personal.analytics.student-teacher-ratio')

        <section class="qams-analytics-section" aria-labelledby="hrmis-senior-roles-title">
            <h5 class="qams-ces-section-title mb-1" id="hrmis-senior-roles-title">Senior Academic Staff by Role</h5>
            <p class="small text-body-secondary mb-3">Employee counts by senior academic rank or leadership role, broken down by gender.</p>
            <div class="card qams-ces-demographic-overview">
                <div class="card-body">
                    <div data-hrmis-chart="gender" data-hrmis-chart-orientation="vertical">
                        <script type="application/json">@json($hrmisAnalytics['senior_roles'])</script>
                        <div data-hrmis-plot></div>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($hrmisAnalytics['sections'] as $section)
            <section class="qams-analytics-section" aria-labelledby="hrmis-personnel-{{ $loop->index }}">
                <h5 class="qams-ces-section-title mb-1" id="hrmis-personnel-{{ $loop->index }}">{{ $section['title'] }}</h5>
                <p class="small text-body-secondary mb-3">{{ $section['description'] }}</p>
                <div class="row g-3">
                    @foreach (['gender' => 'Gender Breakdown', 'education' => 'Highest Academic Achievement'] as $kind => $title)
                        <div class="col-12 col-xl-6">
                            <article class="card qams-ces-demographic-overview h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-1">{{ $title }}</h6>
                                    <p class="small text-body-secondary mb-2">{{ $kind === 'gender'
                                        ? 'Employee counts by personnel category and recorded gender.'
                                        : 'Employee counts by highest recorded education level.' }}</p>
                                    <div data-hrmis-chart="{{ $kind }}" @if ($kind === 'education') data-hrmis-chart-orientation="vertical" @endif>
                                        <script type="application/json">@json($section[$kind])</script>
                                        <div data-hrmis-plot></div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

    </div>
@endif
