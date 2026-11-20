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
    $systemsByKey = $systemCollection->keyBy('key');
    $prioritySystems = [
        'system-17' => ['label' => 'Students Enrolled', 'caption' => 'CES'],
        'human-resources' => ['label' => 'University personnel', 'caption' => 'HRMIS'],
        'system-13' => ['label' => 'Gender Responsive Proposals', 'caption' => 'SAGA/GAD DB', 'metricLabel' => 'Total Gender Responsive Proposals'],
        'research-management' => ['label' => 'Active projects', 'caption' => 'RIES'],
    ];
    $priorityMetrics = [];
    foreach ($prioritySystems as $key => $definition) {
        $system = $systemsByKey->get($key);
        $metrics = collect($system['metrics'] ?? []);
        $metric = match ($key) {
            'system-17' => $metrics->first(fn ($metric) => preg_match('/^total enrolled students \(ay \d{4}-\d{4}\)$/iD', trim($metric['label'] ?? '')) === 1),
            'system-13' => $metrics->first(fn ($metric) => strcasecmp(trim($metric['label'] ?? ''), $definition['metricLabel']) === 0),
            default => $metrics->first(),
        };
        $priorityMetrics[] = [
            'key' => $key,
            'label' => $definition['label'],
            'metricLabel' => $definition['metricLabel'] ?? null,
            'caption' => $definition['caption'],
            'value' => $key === 'human-resources'
                ? ($universityPersonnel === null ? null : number_format($universityPersonnel))
                : ($metric['value'] ?? null),
            'icon' => $system['icon'] ?? 'ri ri-bar-chart-line',
            'url' => $system ? route('qams.system', ['system' => \Illuminate\Support\Str::slug($system['name'])]) : null,
            'sourceLabel' => $key === 'human-resources'
                ? ($universityPersonnel === null ? 'Unavailable' : 'Warehouse snapshot')
                : ($system['source_label'] ?? 'Unavailable'),
        ];
    }
    $overviewMetrics = [
        ['label' => 'Systems Monitored', 'value' => count($systems), 'caption' => 'University-wide', 'icon' => 'ri ri-layout-grid-line', 'primary' => true],
        ['label' => 'Total Energy Consumption', 'value' => null, 'caption' => 'No data available', 'icon' => 'ri ri-lightbulb-flash-line'],
        ['label' => 'Campuses', 'value' => count(config('ces.campuses', [])), 'caption' => 'Configured in CES', 'icon' => 'ri ri-school-line'],
    ];
@endphp
<main
    class="qams-dashboard"
    data-qams-dashboard
    data-systems-url="{{ route('qams.systems') }}"
>
    <header class="qams-dashboard-header">
        <div>
            <h4 class="mb-1 text-primary">Executive Dashboard</h4>
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

    <section class="qams-summary-cards" aria-label="University system metrics">
        @foreach ([$overviewMetrics, $priorityMetrics] as $metricRow)
            <div @class(['row row-cols-1 row-cols-sm-2 g-3', 'row-cols-md-3' => $loop->first, 'row-cols-xl-4 mt-0' => $loop->last])>
                @foreach ($metricRow as $metric)
                    <div class="col">
                        @if ($metric['url'] ?? null)
                            <a class="qams-system-card-link" href="{{ $metric['url'] }}" title="{{ $metric['sourceLabel'] }}">
                        @endif
                        <article @class(['card qams-summary-card h-100', 'qams-summary-primary' => $metric['primary'] ?? false])>
                            <div class="card-body">
                                <span class="qams-summary-icon" aria-hidden="true"><i class="icon-base {{ $metric['icon'] }}"></i></span>
                                <span class="qams-summary-label">{{ $metric['label'] }}</span>
                                @if (($metric['key'] ?? null) === 'human-resources')
                                    <strong class="qams-summary-value" data-university-personnel>{{ $metric['value'] ?? '—' }}</strong>
                                @elseif (isset($metric['key']))
                                    <strong class="qams-summary-value" @if ($metric['metricLabel']) data-summary-metric-label="{{ $metric['metricLabel'] }}" @endif data-summary-value-for="{{ $metric['key'] }}">{{ $metric['value'] ?? '—' }}</strong>
                                @else
                                    <strong class="qams-summary-value">{{ $metric['value'] ?? '—' }}</strong>
                                @endif
                                <span class="qams-summary-caption">{{ $metric['caption'] }}</span>
                            </div>
                        </article>
                        @if ($metric['url'] ?? null)
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>

    <nav class="qams-dashboard-toolbar" aria-label="Filter systems">
        <div class="qams-category-filters" role="group" aria-label="System categories">
            <button type="button" class="btn qams-category-filter active" data-category-filter="all" aria-pressed="true">
                All Systems
            </button>
            @foreach ($categories as $categoryKey => $category)
                <button type="button" class="btn qams-category-filter" data-category-filter="{{ $categoryKey }}" aria-pressed="false">
                    {{ $category['name'] }}
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
                $sectionSystems = $systemCollection->whereIn('key', array_diff($category['systems'], array_keys($prioritySystems)));
            @endphp
            @continue($sectionSystems->isEmpty())
            <section class="qams-system-section" data-category-section="{{ $categoryKey }}">
                <h5 class="qams-section-heading">
                    <span class="qams-category-dot qams-accent-{{ $categoryKey }}"></span>
                    <span>{{ $category['name'] }}</span>
                    <span class="qams-section-count">({{ $sectionSystems->count() }})</span>
                    <span class="qams-section-rule"></span>
                </h5>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 qams-system-grid" data-system-grid>
                    @foreach ($sectionSystems as $system)
                        <div class="col qams-system-column" data-system-column>
                            <a
                                class="qams-system-card-link"
                                href="{{ route('qams.system', ['system' => \Illuminate\Support\Str::slug($system['name'])]) }}"
                                data-system-card="{{ $system['key'] }}"
                                data-system-name="{{ $system['name'] }}"
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
                                            @forelse (array_slice($system['metrics'], 0, 1) as $metric)
                                                <div class="qams-metric">
                                                    <strong class="qams-metric-value">{{ $metric['value'] }}</strong>
                                                    <span class="qams-metric-label">{{ $metric['label'] }}</span>
                                                </div>
                                            @empty
                                                <p class="qams-empty-metrics mb-0">
                                                    No data is currently available.
                                                </p>
                                            @endforelse
                                        </div>
                                        <div class="qams-system-decoration qams-accent-text-{{ $categoryKey }}" aria-hidden="true"></div>
                                    </div>
                                </article>
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <p class="qams-dashboard-note small text-body-secondary mb-0 mt-4">
        Sample values are illustrative placeholders. Configure the university's actual systems and APIs to display live data.
    </p>
</main>
@endsection
