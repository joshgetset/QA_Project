@extends('layouts/contentNavbarLayout')

@section('title', 'Executive Dashboard')

@section('page-style')
@vite(['resources/assets/css/custom/dashboard.css'])
@endsection

@section('page-script')
@vite(['resources/assets/js/qams-dashboard.js'])
@endsection

@section('content')
<main class="qams-dashboard" data-qams-dashboard data-systems-url="{{ route('qams.systems') }}" data-refresh-interval="{{ config('qams.refresh_interval', 30) }}">
    <header class="qams-dashboard-header">
        <div>
            <h4 class="mb-1">Executive Dashboard</h4>
            <p class="mb-0 text-body-secondary">Monitor connected university services in one place.</p>
        </div>
        <div class="qams-dashboard-controls">
            <span class="small text-body-secondary" data-dashboard-checked-at>
                Last checked {{ now()->format('g:i:s A') }} · Refreshes every {{ config('qams.refresh_interval', 30) }} seconds
            </span>
            <button type="button" class="btn btn-primary" data-refresh-systems>
                <i class="icon-base ri ri-refresh-line me-1"></i>
                Refresh
            </button>
        </div>
    </header>

    <div class="alert alert-warning qams-refresh-alert" role="status" aria-live="polite" data-refresh-alert hidden></div>

    <div class="row row-cols-2 row-cols-md-3 row-cols-xxl-5 g-3">
        @foreach ($systems as $system)
            <div class="col">
                <a class="qams-system-card-link" href="{{ route('qams.system', ['system' => $system['key']]) }}" data-system-card="{{ $system['key'] }}" aria-label="View {{ $system['name'] }} total metrics">
                <article class="card qams-system-card h-100">
                    <div class="card-body">
                        <div class="qams-system-heading">
                            <div class="qams-system-icon" aria-hidden="true">
                                <i class="icon-base {{ $system['icon'] }}"></i>
                            </div>
                            <h6 class="card-title qams-system-name mb-0" data-system-name>{{ $system['name'] }}</h6>
                        </div>
                        <div class="qams-metrics" data-system-metrics>
                            @forelse (array_slice($system['metrics'], 0, 1) as $metric)
                                <div class="qams-metric">
                                    <strong class="qams-metric-value">{{ $metric['value'] }}</strong>
                                    <span class="qams-metric-label">{{ $metric['label'] }}</span>
                                </div>
                            @empty
                                <p class="qams-empty-metrics mb-0">No data is currently available.</p>
                            @endforelse
                        </div>
                    </div>
                </article>
                </a>
            </div>
        @endforeach
    </div>
    <p class="qams-dashboard-note small text-body-secondary mb-0 mt-5">
        Sample values are illustrative placeholders. Configure the university's actual systems and APIs to display live data.
    </p>
</main>
@endsection
