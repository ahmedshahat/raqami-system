{{-- ═══ أوقات الذروة: كارت مختصر بالخلاصة، والخريطة التفصيلية بتتفتح بزرار ═══ --}}
@if($heatmap)
    @php
        $heatCells = $heatmap['cells'];
        $heatHours = collect($heatCells)->flatMap(fn ($row) => array_keys($row));
        $heatMax = (float) collect($heatCells)->flatMap(fn ($row) => array_column($row, 'total'))->max();
        $heatDayNames = [];
        foreach ($heatmap['days'] as $dow) {
            // DAYOFWEEK: 1 = الأحد … 7 = السبت
            $heatDayNames[$dow] = \Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($dow - 1)->translatedFormat('l');
        }
        $peakDay = collect($heatCells)->map(fn ($row) => array_sum(array_column($row, 'total')))->sortDesc()->keys()->first();
        $peakHour = collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => collect($heatCells)->sum(fn ($row) => $row[$h]['total'] ?? 0)])->sortDesc()->keys()->first();
        $heatRange = __('home_v2.heatmap_range', ['start' => \Carbon::parse($heatmap['from'])->translatedFormat('j F'), 'end' => \Carbon::parse($heatmap['to'])->translatedFormat('j F Y')]);
    @endphp
    <section class="rr-card" aria-labelledby="rr-heat-title">
        <div class="rr-card__body rr-heat-summary">
            <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('partials.rr.icon', ['name' => 'calendar'])</span>
            <div class="rr-heat-summary__text">
                <h2 id="rr-heat-title" class="rr-heat-summary__title">@lang('home_v2.heatmap')</h2>
                <p class="rr-heat-summary__desc">
                    @if($heatMax > 0)
                        @lang('home_v2.heatmap_peak', ['day' => $heatDayNames[$peakDay], 'hour' => sprintf('%02d:00', $peakHour)]) · {{ $heatRange }}
                    @else
                        @lang('home_v2.no_data') · {{ $heatRange }}
                    @endif
                </p>
            </div>
            @if($heatMax > 0)
                <button type="button" class="rr-btn rr-btn--ghost" aria-expanded="false" aria-controls="rr-heat-panel" data-rr-disclose
                        data-label-open="@lang('home_v2.heatmap_show')" data-label-close="@lang('home_v2.heatmap_hide')">
                    <span data-rr-disclose-label>@lang('home_v2.heatmap_show')</span>@include('partials.rr.icon', ['name' => 'chevron-forward', 'size' => 16])
                </button>
            @endif
        </div>
        @if($heatMax > 0)
            @php $hours = range($heatHours->min(), $heatHours->max()); @endphp
            <div id="rr-heat-panel" class="rr-heat-panel" hidden>
                <div class="rr-heat-scroll">
                    <div class="rr-heat" style="--rr-hours: {{ count($hours) }}">
                        <span></span>
                        @foreach($hours as $h)<span class="rr-heat__hour">{{ $h }}</span>@endforeach
                        @foreach($heatmap['days'] as $dow)
                            <span class="rr-heat__day">{{ $heatDayNames[$dow] }}</span>
                            @foreach($hours as $h)
                                @php $cell = $heatCells[$dow][$h] ?? ['total' => 0, 'count' => 0]; @endphp
                                <div class="rr-heat__cell" style="--rr-heat: {{ $cell['total'] > 0 ? max(8, round($cell['total'] / $heatMax * 100)) : 0 }}" tabindex="0" data-rr-tip>
                                    <div class="rr-chart__tip-src" hidden>
                                        <span class="rr-chart-tip__title">{{ $heatDayNames[$dow] }} · {{ sprintf('%02d:00', $h) }}</span>
                                        <div class="rr-chart-tip__row"><span class="rr-legend__swatch rr-bar--sales"></span><span>@lang('home_v2.series_sales')</span><span class="rr-money">@include('partials.rr.money', ['value' => $cell['total']])</span></div>
                                        <div class="rr-chart-tip__row"><span></span><span>@lang('home_v2.invoices')</span><span class="rr-num">{{ $cell['count'] }}</span></div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <p class="rr-card__note"><span class="rr-heat-scale" aria-hidden="true">@lang('home_v2.less')<span class="rr-heat-scale__bar"></span>@lang('home_v2.more')</span></p>
            </div>
        @endif
    </section>
@endif
