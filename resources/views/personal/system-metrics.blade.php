@extends('layouts/contentNavbarLayout')

@section('title', $system['name'] . ' Metrics')

@section('page-style')
@vite(['resources/assets/css/custom/dashboard.css'])
@endsection

@section('content')
<main class="qams-system-detail">
    <header class="qams-detail-header">
        <div class="d-flex align-items-center gap-3">
            <div class="qams-system-icon" aria-hidden="true">
                <i class="icon-base {{ $system['icon'] }}"></i>
            </div>
            <div>
                <h4 class="mb-1">{{ $system['name'] }}</h4>
                <p class="mb-0 text-body-secondary">{{ $system['description'] }}</p>
            </div>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('dashboard-analytics') }}">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>
            Systems Overview
        </a>
    </header>

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
</main>
@endsection
