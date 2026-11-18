@extends('layouts/contentNavbarLayout')

@section('title', $system['name'] . ' Metrics')

@section('page-style')
@vite(['resources/assets/css/custom/dashboard.css'])
@endsection

@section('page-script')
@if ($cesAnalytics)
@vite(['resources/assets/js/ces-analytics.js'])
@elseif ($hrmisAnalytics)
@vite(['resources/assets/js/hrmis-analytics.js'])
@endif
@endsection

@section('content')
<main class="qams-system-detail">
    <header class="qams-detail-header">
        <div>
            <h4 @class(['mb-1', 'qams-ces-section-title' => $cesAnalytics || $isHrmis])>{{ $system['name'] }}</h4>
            <p class="mb-0 text-body-secondary">{{ $system['description'] }}</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('dashboard-analytics') }}">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>
            Systems Overview
        </a>
    </header>

    @if ($cesAnalytics)
        @include('personal.analytics.ces')
    @elseif ($isHrmis)
        @include('personal.analytics.hrmis')
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
