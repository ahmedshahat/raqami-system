@if(! empty($kpis))
    @php
        $prevRange = \Carbon::parse($period['prev_start'])->translatedFormat('j F') . ' – ' . \Carbon::parse($period['prev_end'])->translatedFormat('j F Y');
    @endphp
    {{-- كروت المؤشرات الرئيسية: كل كارت بخلفية حالته الخفيفة، وبيفتح شاشة تفاصيله على نفس الفترة --}}
    <section class="rr-kpis" aria-label="@lang('home_v2.kpis')">
        @foreach($kpis as $key => $kpi)
            @php
                $d = $delta($kpi['value'], $kpi['prev'], ! empty($kpi['inverse']));
                $tag = ! empty($kpi['url']) ? 'a' : 'article';
            @endphp
            <{{ $tag }} class="rr-kpi rr-kpi--{{ $kpi['tone'] }}" @if($tag == 'a') href="{{ $kpi['url'] }}" @endif>
                <div class="rr-kpi__top">
                    <span class="rr-kpi__icon" aria-hidden="true">@include('partials.rr.icon', ['name' => $kpi['icon'], 'size' => 18])</span>
                    <span class="rr-kpi__label">@lang('home_v2.kpi_' . $key)</span>
                    @if($d)
                        <span class="rr-badge rr-badge--{{ $d['good'] ? 'success' : 'danger' }} rr-kpi__delta" data-rr-tip>
                            @include('partials.rr.icon', ['name' => $d['pct'] >= 0 ? 'chevron-up' : 'chevron-down', 'size' => 14])
                            @lang($d['pct'] >= 0 ? 'home_v2.delta_up' : 'home_v2.delta_down', ['pct' => number_format(abs($d['pct']), abs($d['pct']) < 10 ? 1 : 0)])
                            {{-- مبلغ الفترة السابقة: تلميح عند الوقوف بالماوس، ونص مخفي لقارئ الشاشة --}}
                            <span class="rr-sr">@lang('home_v2.prev_period', ['range' => $prevRange]): @include('partials.rr.money', ['value' => $kpi['prev']])</span>
                            <span class="rr-chart__tip-src" hidden>
                                <span class="rr-chart-tip__title">@lang('home_v2.prev_period', ['range' => $prevRange])</span>
                                <span class="rr-chart-tip__row"><span></span><span>@lang('home_v2.kpi_' . $key)</span><span class="rr-money">@include('partials.rr.money', ['value' => $kpi['prev']])</span></span>
                            </span>
                        </span>
                    @endif
                </div>
                <div class="rr-kpi__value rr-money{{ (float) $kpi['value'] == 0 ? ' rr-money--zero' : '' }}">@include('partials.rr.money', ['value' => $kpi['value']])</div>
                <div class="rr-kpi__meta">
                    {{ $kpi['meta'] }}
                    @if($tag == 'a')<span class="rr-kpi__more">@lang('home_v2.view_details')@include('partials.rr.icon', ['name' => 'chevron-forward', 'size' => 14])</span>@endif
                </div>
            </{{ $tag }}>
        @endforeach
    </section>
@endif
