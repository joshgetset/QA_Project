@extends('layouts/contentNavbarLayout')

@section('title', $system['name'] . ' Metrics')

@section('page-style')
@vite(['resources/assets/css/custom/dashboard.css'])
@endsection

@section('page-script')
@if ($cesAnalytics)
@vite(['resources/assets/js/ces-analytics.js'])
@endif
@endsection

@section('content')
<main class="qams-system-detail">
    <header class="qams-detail-header">
        <div>
            <h4 class="mb-1">{{ $system['name'] }}</h4>
            <p class="mb-0 text-body-secondary">{{ $system['description'] }}</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('dashboard-analytics') }}">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>
            Systems Overview
        </a>
    </header>

    @if ($cesAnalytics)
            @php
                $cesDemographics = collect($cesAnalytics['demographics'])
                    ->sortByDesc(fn ($demographic) => $demographic['male'] + $demographic['female'])
                    ->values();
            @endphp
            <div class="qams-ces-analytics" data-ces-analytics>
            <script type="application/json" data-ces-analytics-data>@json($cesAnalytics)</script>
            <section class="mb-4" aria-label="Filter enrollment analytics">
                <div class="row g-3">
                        @foreach (['campus' => 'Campus', 'college' => 'College', 'program' => 'Program', 'year' => 'Academic Year'] as $filterKey => $filterLabel)
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label qams-ces-filter-label" for="ces-filter-{{ $filterKey }}">
                                    <i class="icon-base {{ ['campus' => 'ri ri-building-2-line', 'college' => 'ri ri-government-line', 'program' => 'ri ri-graduation-cap-line', 'year' => 'ri ri-calendar-2-line'][$filterKey] }} me-1" aria-hidden="true"></i>{{ $filterLabel }}
                                </label>
                                <select class="form-select qams-ces-filter-select" id="ces-filter-{{ $filterKey }}" data-ces-filter="{{ $filterKey }}">
                                    <option value="">{{ ['campus' => 'All Campuses', 'college' => 'All Colleges', 'program' => 'All Programs', 'year' => 'All Academic Years'][$filterKey] }}</option>
                                    @foreach ($cesAnalytics['filters'][$filterKey] as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                </div>
            </section>

            <section class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4" aria-label="Enrollment metrics">
                @foreach ($cesAnalytics['summary'] as $metric)
                    <div class="col">
                        <article class="card qams-summary-card qams-ces-summary-card {{ $metric['key'] === 'total-enrolled-students' ? 'qams-ces-summary-primary' : '' }} h-100">
                            <div class="card-body">
                                <span class="qams-summary-icon"><i class="icon-base {{ $metric['icon'] }}" aria-hidden="true"></i></span>
                                <span class="qams-summary-label">{{ $metric['label'] }}</span>
                                <strong class="qams-summary-value" data-ces-summary="{{ $metric['key'] }}">{{ number_format($metric['value']) }}</strong>
                                <span class="qams-summary-caption">Illustrative student count</span>
                            </div>
                        </article>
                    </div>
                @endforeach
            </section>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Student Demographics</h5>
                    <p class="mb-0 text-body-secondary">Gender distribution across each student group.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="qams-ces-gender-legend" aria-label="Chart legend">
                        <span><i class="qams-ces-legend-dot qams-ces-male"></i> Male</span>
                        <span><i class="qams-ces-legend-dot qams-ces-female"></i> Female</span>
                    </div>
                    <div class="btn-group qams-ces-view-toggle" role="group" aria-label="Demographic display">
                        <button class="btn btn-outline-secondary active" type="button" data-ces-view="cards" aria-pressed="true" aria-label="Show demographic cards" title="Cards">
                            <i class="icon-base ri ri-grid-line"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-ces-view="chart" aria-pressed="false" aria-label="Show demographic chart" title="Chart">
                            <i class="icon-base ri ri-bar-chart-horizontal-line"></i>
                        </button>
                    </div>
                </div>
            </div>

            <section class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" data-ces-demographic-cards aria-label="Demographic breakdowns by gender">
                @foreach ($cesDemographics as $demographic)
                    <div class="col">
                        <article class="card qams-ces-demographic-card h-100">
                            <div class="card-body">
                                <div class="qams-ces-demographic-heading">
                                    <span class="qams-ces-demographic-icon" aria-hidden="true">
                                        <i class="icon-base {{ $demographic['icon'] }}"></i>
                                    </span>
                                    <div class="qams-ces-demographic-copy">
                                        <h6 class="card-title mb-1">{{ $demographic['label'] }}</h6>
                                    </div>
                                </div>
                                <div class="qams-ces-demographic-count">
                                    <strong class="qams-ces-demographic-total" data-ces-demographic-total="{{ $demographic['key'] }}">{{ number_format($demographic['male'] + $demographic['female']) }}</strong>
                                    <span>students</span>
                                </div>
                                <div class="qams-ces-demographic-chart-wrap">
                                    <div
                                        class="progress qams-ces-demographic-chart"
                                        data-ces-demographic-chart="{{ $demographic['key'] }}"
                                        data-male="{{ $demographic['male'] }}"
                                        data-female="{{ $demographic['female'] }}"
                                        role="img"
                                        aria-label="{{ $demographic['label'] }} gender distribution"
                                    >
                                        <div class="progress-bar qams-ces-male" data-ces-male-segment aria-hidden="true"></div>
                                        <div class="progress-bar qams-ces-female" data-ces-female-segment aria-hidden="true"></div>
                                    </div>
                                    <p class="qams-ces-demographic-empty mb-0" data-ces-demographic-empty="{{ $demographic['key'] }}" hidden>
                                        No students recorded in this group
                                    </p>
                                </div>
                                <div class="qams-ces-gender-values">
                                    <span>
                                        <span class="qams-ces-gender-label"><i class="qams-ces-legend-dot qams-ces-male"></i>Male</span>
                                        <span class="qams-ces-gender-count"><strong data-ces-male="{{ $demographic['key'] }}">{{ number_format($demographic['male']) }}</strong><small data-ces-male-share="{{ $demographic['key'] }}">{{ $demographic['male'] + $demographic['female'] ? number_format($demographic['male'] / ($demographic['male'] + $demographic['female']) * 100) : 0 }}%</small></span>
                                    </span>
                                    <span>
                                        <span class="qams-ces-gender-label"><i class="qams-ces-legend-dot qams-ces-female"></i>Female</span>
                                        <span class="qams-ces-gender-count"><strong data-ces-female="{{ $demographic['key'] }}">{{ number_format($demographic['female']) }}</strong><small data-ces-female-share="{{ $demographic['key'] }}">{{ $demographic['male'] + $demographic['female'] ? number_format($demographic['female'] / ($demographic['male'] + $demographic['female']) * 100) : 0 }}%</small></span>
                                    </span>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </section>
            <section class="card qams-ces-demographic-overview mt-3" data-ces-demographic-overview aria-label="Demographic gender comparison" hidden>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="card-title mb-1" data-ces-overview-title>Male vs female counts per group</h6>
                        <p class="mb-0 text-body-secondary small">Groups sorted as listed; bar length shows each group's size.</p>
                    </div>
                    <div class="qams-ces-demographic-overview-chart" data-ces-overview-chart></div>
                </div>
            </section>
        </div>
    @else
    <section class="card">
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <h5 class="card-title mb-0">Total Metrics</h5>
            <span class="badge qams-source-badge" data-source="{{ $system['source'] }}">{{ $system['source_label'] }}</span>
        </div>
        <div class="card-body">
            @if ($system['metrics'])
                <div class="row g-3">
                    @foreach ($system['metrics'] as $metric)
                        <div class="col-6 col-lg-4 qams-detail-metric">
                            <div class="qams-metric h-100">
                                <span class="qams-metric-label">{{ $metric['label'] }}</span>
                                <strong class="qams-metric-value">{{ $metric['value'] }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="qams-empty-metrics mb-0">No metrics are currently available.</p>
            @endif
        </div>
    </section>
    @endif
</main>
@endsection
