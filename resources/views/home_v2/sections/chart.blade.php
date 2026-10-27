@if($can_view_sales)
    @php
        $labels = array_values($buckets);
        $count = count($labels);
        // ترتيب الأعمدة جوه كل يوم ولون كل سلسلة (rr-bar--*) زي التصميم
        $series = array_filter([
            'sales' => $chart['sales'],
            'purchases' => $chart['purchases'],
            'expenses' => $chart['expenses'],
            'profit' => $chart['profit'],
        ]);
        $axisMax = $niceMax(max(array_merge([0], ...array_values($series))));
        $step = max(1, (int) ceil($count / 16));
        // الرقم فوق عمود المبيعات لحد شهر تقريبًا؛ أكتر من كده بيزحم
        $showValues = $count <= 24;
        $pct = fn ($v) => $axisMax > 0 ? round(max(0, $v) / $axisMax * 100, 2) : 0;

        // اسم الفترة تحت المحور: الشهر (أو الشهرين) للأيام، اليوم للساعات، السنة للشهور
        $from = \Carbon::parse($period['start']);
        $to = \Carbon::parse($period['end']);
        if ($period['bucket'] == 'hour') {
            $caption = $from->translatedFormat('l j F Y');
        } elseif ($period['bucket'] == 'month') {
            $caption = $from->year == $to->year ? $from->year : $from->year . ' – ' . $to->year;
        } else {
            $caption = $from->format('Y-m') == $to->format('Y-m')
                ? $from->translatedFormat('F Y')
                : $from->translatedFormat($from->year == $to->year ? 'F' : 'F Y') . ' – ' . $to->translatedFormat('F Y');
        }
    @endphp
    <section class="rr-card rr-home__fill" aria-labelledby="rr-chart-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <h2 id="rr-chart-title" class="rr-card__title">@lang('home_v2.chart_title')</h2>
                <p class="rr-card__desc">@lang('home_v2.chart_desc_' . $period['bucket'])</p>
            </div>
        </header>
        <div class="rr-card__body">
            <ul class="rr-legend rr-legend--inline rr-bars__legend">
                @foreach($series as $key => $values)
                    <li class="rr-legend__item"><span class="rr-legend__swatch rr-bar--{{ $key }}"></span><span class="rr-legend__label">@lang('home_v2.series_' . $key)</span></li>
                @endforeach
            </ul>
            <figure class="rr-bars">
                <div class="rr-bars__axis" aria-hidden="true">
                    @foreach([0, 25, 50, 75, 100] as $tick)
                        <span class="rr-num" style="--rr-tick: {{ $tick }}%">{{ $compact($axisMax * $tick / 100) }}</span>
                    @endforeach
                </div>
                <div class="rr-bars__main">
                    <div class="rr-bars__plot">
                        @foreach([0, 25, 50, 75, 100] as $tick)
                            <span class="rr-bars__grid" style="--rr-tick: {{ $tick }}%" aria-hidden="true"></span>
                        @endforeach
                        <div class="rr-bars__cols" style="--rr-cols: {{ $count }}">
                            @foreach($labels as $i => $label)
                                <div class="rr-bars__col" tabindex="0" data-rr-tip>
                                    @foreach($series as $key => $values)
                                        <span class="rr-bars__bar rr-bar--{{ $key }}" style="height: {{ $pct($values[$i]) }}%">
                                            @if($key == 'sales' && $showValues && $values[$i] > 0)<span class="rr-bars__value rr-num">{{ $compact($values[$i]) }}</span>@endif
                                        </span>
                                    @endforeach
                                    <div class="rr-chart__tip-src" hidden>
                                        <span class="rr-chart-tip__title">{{ $label['full'] }}</span>
                                        @foreach($series as $key => $values)
                                            <div class="rr-chart-tip__row"><span class="rr-legend__swatch rr-bar--{{ $key }}"></span><span>@lang('home_v2.series_' . $key)</span><span class="rr-money">@include('partials.rr.money', ['value' => $values[$i]])</span></div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($axisMax == 0)
                            <div class="rr-bars__empty">@lang('home_v2.no_data')</div>
                        @endif
                    </div>
                    <div class="rr-bars__labels" style="--rr-cols: {{ $count }}" aria-hidden="true">
                        @foreach($labels as $i => $label)
                            <span class="rr-num">{{ $i % $step == 0 ? $label['short'] : '' }}</span>
                        @endforeach
                    </div>
                    <figcaption class="rr-bars__caption">{{ $caption }}</figcaption>
                </div>
            </figure>
        </div>
    </section>
@endif
