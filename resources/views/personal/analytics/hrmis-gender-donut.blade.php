<article class="card qams-ces-demographic-card qams-ces-demographic-card--gender h-100">
    <div class="card-body">
        <div class="qams-ces-demographic-info">
            <h6 class="card-title fs-5 mb-0">{{ $metric['label'] }}</h6>
            <span class="qams-ces-demographic-count"><strong class="qams-ces-demographic-total fs-4">{{ number_format($metric['total']) }}</strong></span>
        </div>
        <div class="qams-ces-demographic-chart-wrap">
            @if ($metric['total'] > 0)
                <div data-hrmis-donut><script type="application/json">@json($metric)</script><div data-hrmis-plot></div></div>
            @else
                <div class="qams-ces-demographic-empty" aria-hidden="true"></div>
            @endif
        </div>
        <div class="qams-ces-gender-values">
            @if ($metric['total'] === 0)
                No Employee Record
            @else
                <span class="qams-ces-gender-count">Male <strong>{{ number_format($metric['male']) }}</strong></span>
                <span class="qams-ces-gender-count">Female <strong>{{ number_format($metric['female']) }}</strong></span>
                @if ($metric['unknown_gender'] > 0)
                    <span class="qams-ces-gender-count qams-hrmis-gender-unknown">Unknown Gender <strong>{{ number_format($metric['unknown_gender']) }}</strong></span>
                @endif
            @endif
        </div>
    </div>
</article>
