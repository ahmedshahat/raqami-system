@if($can_view_sales)
    <section class="rr-card" aria-labelledby="rr-top-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <h2 id="rr-top-title" class="rr-card__title">@lang('home_v2.top_products')</h2>
                <p class="rr-card__desc">@lang('home_v2.top_products_desc')</p>
            </div>
        </header>
        @if($top_products->isEmpty())
            <div class="rr-empty"><p class="rr-empty__text">@lang('home_v2.no_data')</p></div>
        @else
            @php $topMax = (float) $top_products->max('total') ?: 1; @endphp
            <ol class="rr-rank">
                @foreach($top_products as $index => $product)
                    <li class="rr-rank__row">
                        <span class="rr-rank__n rr-num">{{ $index + 1 }}</span>
                        <span class="rr-rank__name"><bdi>{{ $product->name }}</bdi></span>
                        <span class="rr-rank__value rr-money">@include('partials.rr.money', ['value' => $product->total])</span>
                        <div class="rr-rank__bar" aria-hidden="true"><span style="width: {{ round($product->total / $topMax * 100, 1) }}%"></span></div>
                        <span class="rr-rank__sub">@lang('home_v2.qty_sold', ['qty' => (float) $product->qty + 0])</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endif
