@extends('layouts/contentNavbarLayout')

@section('title', 'Executive Dashboard')

@section('page-style')
@vite(['resources/assets/css/custom/dashboard.css'])
@endsection

@section('page-script')
@vite(['resources/assets/js/qams-dashboard.js'])
@endsection

@section('content')
@php
    $systemCollection = collect($systems);
    $enrollmentSystem = $systemCollection->firstWhere('key', 'system-17');
    $enrollmentMetric = collect($enrollmentSystem['metrics'] ?? [])->first(
        fn ($metric) => preg_match('/^total enrolled students \(ay \d{4}-\d{4}\)$/iD', trim($metric['label'] ?? '')) === 1
    );
    $personnelSystem = $systemCollection->firstWhere('key', 'human-resources');
    $liveSystemCount = $systemCollection->where('source', 'live')->count();
    $demoSystemCount = $systemCollection->where('source', 'demo')->count();
    $unavailableSystemCount = $systemCollection->where('source', 'unavailable')->count();
@endphp
<main
    class="qams-dashboard"
    data-qams-dashboard
    data-systems-url="{{ route('qams.systems') }}"
>
    <header class="qams-dashboard-header">
        <div>
            <h4 class="mb-1">Executive Dashboard</h4>
            <p class="mb-0 text-body-secondary">Monitor connected university services in one place.</p>
        </div>
        <div class="qams-dashboard-controls">
            <span class="small text-body-secondary" data-dashboard-checked-at data-checked-at="{{ now()->toIso8601String() }}">
                Last checked {{ now()->format('g:i:s A') }}
            </span>
            <button type="button" class="btn btn-primary" data-refresh-systems>
                <i class="icon-base ri ri-refresh-line me-1"></i>
                Refresh
            </button>
        </div>
    </header>

    <div class="alert alert-warning qams-refresh-alert" role="status" aria-live="polite" data-refresh-alert hidden></div>

    <section class="row row-cols-1 row-cols-sm-2 row-cols-xxl-4 g-3 qams-summary-cards" aria-label="University system summary">
        <div class="col">
            <article class="card qams-summary-card qams-summary-primary h-100">
                <div class="card-body">
                    <span class="qams-summary-icon"><i class="icon-base ri ri-layout-grid-line"></i></span>
                    <span class="qams-summary-label">Systems monitored</span>
                    <strong class="qams-summary-value" data-system-count>{{ count($systems) }}</strong>
                    <span class="qams-summary-caption"><span class="qams-status-dot qams-status-live"></span><span data-live-connection-summary><span data-live-connection-count>{{ $liveSystemCount }}</span> live connections</span></span>
                </div>
            </article>
        </div>
        <div class="col">
            <article class="card qams-summary-card h-100">
                <div class="card-body">
                    <span class="qams-summary-icon"><i class="icon-base ri ri-graduation-cap-line"></i></span>
                    <span class="qams-summary-label">Current school year enrolled students</span>
                    <strong class="qams-summary-value" data-summary-value-for="system-17">{{ $enrollmentMetric['value'] ?? '—' }}</strong>
                    <span class="qams-summary-caption">{{ $enrollmentSystem['name'] ?? 'Comprehensive Enrolment System' }}</span>
                </div>
            </article>
        </div>
        <div class="col">
            <article class="card qams-summary-card h-100">
                <div class="card-body">
                    <span class="qams-summary-icon"><i class="icon-base ri ri-team-line"></i></span>
                    <span class="qams-summary-label">University personnel</span>
                    <strong class="qams-summary-value" data-summary-value-for="human-resources">{{ $personnelSystem['metrics'][0]['value'] ?? '—' }}</strong>
                    <span class="qams-summary-caption">{{ $personnelSystem['name'] ?? 'Human Resource Management System' }}</span>
                </div>
            </article>
        </div>
        <div class="col">
            <article class="card qams-summary-card h-100">
                <div class="card-body">
                    <span class="qams-summary-icon"><i class="icon-base ri ri-database-2-line"></i></span>
                    <span class="qams-summary-label">Live systems</span>
                    <strong class="qams-summary-value" data-live-summary-count>{{ $liveSystemCount }}</strong>
                    <span class="qams-summary-caption">Connected to university APIs</span>
                </div>
            </article>
        </div>
    </section>

    <nav class="qams-dashboard-toolbar" aria-label="Filter systems">
        <div class="qams-category-filters" role="group" aria-label="System categories">
            <button type="button" class="btn qams-category-filter active" data-category-filter="all" aria-pressed="true">
                All Systems <span class="qams-filter-count">{{ count($systems) }}</span>
            </button>
            @foreach ($categories as $categoryKey => $category)
                <button type="button" class="btn qams-category-filter" data-category-filter="{{ $categoryKey }}" aria-pressed="false">
                    <span class="qams-category-dot qams-accent-{{ $categoryKey }}"></span>
                    {{ $category['name'] }}
                    <span class="qams-filter-count">{{ count(array_intersect($category['systems'], array_column($systems, 'key'))) }}</span>
                </button>
            @endforeach
        </div>
        <div class="qams-view-controls">
            <div class="btn-group qams-view-switch" role="group" aria-label="System card layout">
                <button type="button" class="btn btn-primary" data-view-mode="grid" aria-label="Grid view" aria-pressed="true">
                    <i class="icon-base ri ri-grid-line"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-view-mode="list" aria-label="List view" aria-pressed="false">
                    <i class="icon-base ri ri-list-check-2"></i>
                </button>
            </div>
            <label class="qams-sort-control">
                <i class="icon-base ri ri-arrow-up-down-line" aria-hidden="true"></i>
                <span class="visually-hidden">Sort systems</span>
                <select class="form-select form-select-sm" data-system-sort aria-label="Sort systems">
                    <option value="default">Default order</option>
                    <option value="name">Name A–Z</option>
                </select>
            </label>
        </div>
    </nav>

    <div class="qams-system-sections">
        @foreach ($categories as $categoryKey => $category)
            @php
                $categorySystems = $systemCollection->whereIn('key', $category['systems'])->values();
            @endphp
            <section class="qams-system-section" data-category-section="{{ $categoryKey }}">
                <h5 class="qams-section-heading">
                    <span class="qams-category-dot qams-accent-{{ $categoryKey }}"></span>
                    <span>{{ $category['name'] }}</span>
                    <span class="qams-section-count">({{ $categorySystems->count() }})</span>
                    <span class="qams-section-rule"></span>
                </h5>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-4 g-3 qams-system-grid">
                    @foreach ($categorySystems as $system)
                        @php
                            $cardMetrics = $system['key'] === 'system-17'
                                ? array_values(array_filter($system['metrics'], fn ($metric) => preg_match('/^total enrolled students \(ay \d{4}-\d{4}\)$/iD', trim($metric['label'] ?? '')) === 1))
                                : array_slice($system['metrics'], 0, 1);
                        @endphp
                        <div class="col qams-system-column" data-system-column data-system-order="{{ $loop->index }}">
                            <a
                                class="qams-system-card-link"
                                href="{{ route('qams.system', ['system' => \Illuminate\Support\Str::slug($system['name'])]) }}"
                                data-system-card="{{ $system['key'] }}"
                                data-system-name="{{ $system['name'] }}"
                                data-category="{{ $categoryKey }}"
                                data-source="{{ $system['source'] }}"
                                aria-label="View {{ $system['name'] }} total metrics"
                            >
                                <article class="card qams-system-card qams-accent-border-{{ $categoryKey }} h-100">
                                    <div class="card-body">
                                        <div class="qams-system-heading">
                                            <div class="qams-system-icon qams-accent-icon-{{ $categoryKey }}" aria-hidden="true">
                                                <i class="icon-base {{ $system['icon'] }}"></i>
                                            </div>
                                            <div class="qams-system-title">
                                                <span class="qams-system-short-name qams-accent-text-{{ $categoryKey }}">{{ $shortNames[$system['key']] ?? '' }}</span>
                                                <h6 class="card-title qams-system-name mb-0">{{ $system['name'] }}</h6>
                                            </div>
                                            <span class="qams-system-status qams-status-{{ $system['source'] }}" title="{{ $system['source_label'] }}" aria-label="{{ $system['source_label'] }}"></span>
                                        </div>
                                        <div class="qams-metrics" data-system-metrics>
                                            @forelse (array_slice($cardMetrics, 0, 1) as $metric)
                                                <div class="qams-metric">
                                                    <strong class="qams-metric-value">{{ $metric['value'] }}</strong>
                                                    <span class="qams-metric-label">{{ $metric['label'] }}</span>
                                                </div>
                                            @empty
                                                <p class="qams-empty-metrics mb-0">
                                                    {{ $system['key'] === 'system-17' ? 'No cached data yet. Use Refresh to load CES data.' : 'No data is currently available.' }}
                                                </p>
                                            @endforelse
                                        </div>
                                        <div class="qams-system-trend" data-system-trend>
                                            <span class="qams-system-trend-change" data-trend-change aria-live="polite"></span>
                                            <div class="qams-system-trend-chart" data-trend-chart aria-hidden="true"></div>
                                        </div>
                                    </article>
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        <p class="qams-no-results text-body-secondary" data-no-results hidden>No systems match your search.</p>
    </div>

    <p class="qams-dashboard-note small text-body-secondary mb-0 mt-4">
        Sample values are illustrative placeholders. Configure the university's actual systems and APIs to display live data.
    </p>
</main>
@endsection
