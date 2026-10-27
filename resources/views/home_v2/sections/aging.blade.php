@if($aging)
    @php $overdue = array_sum($aging['counts']) - $aging['counts']['not_due']; @endphp
    <section class="rr-card" aria-labelledby="rr-aging-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <h2 id="rr-aging-title" class="rr-card__title">@lang('home_v2.aging')</h2>
                <p class="rr-card__desc">@lang('home_v2.aging_desc')</p>
            </div>
        </header>
        <div class="rr-card__body rr-dist">
            @if($aging['total'] <= 0)
                <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.aging_empty')</p></div>
            @else
                <div>
                    <div class="rr-dist__label">
                        @lang('home_v2.aging_total')
                        @if($overdue > 0)
                            <span class="rr-badge rr-badge--warning">@include('partials.rr.icon', ['name' => 'info', 'size' => 14])@lang('home_v2.aging_overdue_badge', ['count' => $overdue])</span>
                        @endif
                    </div>
                    <span class="rr-money rr-money--lg">@include('partials.rr.money', ['value' => $aging['total']])</span>
                </div>
                <div class="rr-dist__bar" aria-hidden="true">
                    @foreach($aging['totals'] as $bucket => $amount)
                        @if($amount > 0)<span class="rr-age--{{ $loop->iteration }}" style="flex: {{ round($amount / $aging['total'] * 100, 2) }}"></span>@endif
                    @endforeach
                </div>
                <ul class="rr-dist__list">
                    @foreach($aging['totals'] as $bucket => $amount)
                        <li class="rr-dist__row">
                            <span class="rr-legend__swatch rr-age--{{ $loop->iteration }}" aria-hidden="true"></span>
                            <span class="rr-dist__name">
                                <span class="rr-dist__title">@lang('home_v2.aging_' . $bucket)</span>
                                <span class="rr-dist__meta">@lang('home_v2.invoices_count', ['count' => $aging['counts'][$bucket]])</span>
                            </span>
                            <span class="rr-money rr-money--sm{{ $amount == 0 ? ' rr-money--zero' : '' }}">@include('partials.rr.money', ['value' => $amount])</span>
                            <span class="rr-dist__pct rr-num">{{ round($amount / $aging['total'] * 100) }}%</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
