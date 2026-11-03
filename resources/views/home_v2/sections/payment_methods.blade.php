@if($is_admin)
    <section class="rr-card" aria-labelledby="rr-methods-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <h2 id="rr-methods-title" class="rr-card__title">@lang('home_v2.payment_methods')</h2>
                <p class="rr-card__desc">@lang('home_v2.payment_methods_desc')</p>
            </div>
        </header>
        <div class="rr-card__body rr-dist">
            @if(empty($payment_methods))
                <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.no_data')</p></div>
            @else
                @php $methodsTotal = array_sum($payment_methods); @endphp
                <div class="rr-dist__bar" aria-hidden="true">
                    @foreach($payment_methods as $method => $amount)
                        <span class="rr-pay--{{ $loop->index % 5 + 1 }}" style="flex: {{ round($amount / $methodsTotal * 100, 2) }}"></span>
                    @endforeach
                </div>
                <ul class="rr-dist__list">
                    @foreach($payment_methods as $method => $amount)
                        <li class="rr-dist__row">
                            <span class="rr-legend__swatch rr-pay--{{ $loop->index % 5 + 1 }}" aria-hidden="true"></span>
                            <span class="rr-dist__name"><span class="rr-dist__title">{{ $payment_types[$method] ?? $method }}</span></span>
                            <span class="rr-money rr-money--sm">@include('partials.rr.money', ['value' => $amount])</span>
                            <span class="rr-dist__pct rr-num">{{ round($amount / $methodsTotal * 100) }}%</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
