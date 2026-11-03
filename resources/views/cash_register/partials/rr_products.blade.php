{{--
    الأصناف المباعة في الوردية (مشترك): مرتبة بالقيمة.
    $limit = 10 في "إغلاق الوردية" (والباقي سطر واحد)، و null في "تفاصيل الوردية" (القائمة كلها + بحث لو أكتر من 10).
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $money = fn ($value) => view('partials.rr.money', ['value' => $value])->render();
    $limit = $limit ?? null;
    $products = collect($details['product_details'])->sortByDesc(fn ($d) => (float) $d->total_amount)->values();
    $shown = $limit ? $products->take($limit) : $products;
    $rest = $limit ? $products->slice($limit) : collect();
    $searchable = !$limit && count($products) > 10;
@endphp
@if (count($products) || count($details['product_details_by_brand'] ?? []) || !empty($details['types_of_service_details']))
    <details class="rr-shift__box rr-shift__products" data-rr-shift-products>
        <summary class="rr-shift__h">{!! $icon('box', 16) !!}{{ count($rest) ? __('shift_close.top_products') : __('shift_close.products') }} <span class="rr-shift__muted">({{ count($products) }})</span>{!! $icon('chevron-down', 16) !!}</summary>
        @if ($searchable)
            <div class="rr-shift__search">
                <span aria-hidden="true">{!! $icon('search', 16) !!}</span>
                <input type="search" class="form-control" data-rr-shift-search placeholder="@lang('shift_close.search_products')" aria-label="@lang('shift_close.search_products')" autocomplete="off">
            </div>
        @endif
        <table class="rr-shift__table">
            <thead><tr><th>@lang('shift_close.product')</th><th>@lang('sale.qty')</th><th>@lang('shift_close.amount')</th></tr></thead>
            <tbody>
                @foreach ($shown as $d)
                    <tr data-rr-shift-product="{{ mb_strtolower($d->product_name . ' ' . ($d->variation_name ?? '') . ' ' . $d->sku) }}">
                        <th scope="row">{{ $d->product_name }}@if ($d->type == 'variable') {{ $d->product_variation_name }} - {{ $d->variation_name }}@endif <span class="rr-code rr-shift__muted">{{ $d->sku }}</span></th>
                        <td class="rr-num">{{ @format_quantity($d->total_quantity) }}</td>
                        <td class="rr-money">{!! $money($d->total_amount) !!}</td>
                    </tr>
                @endforeach
                @if (count($rest))
                    <tr class="rr-shift__rest" data-rr-shift-rest>
                        <th scope="row">@lang('shift_close.more_products', ['count' => count($rest)])</th>
                        <td class="rr-num">{{ @format_quantity($rest->sum('total_quantity')) }}</td>
                        <td class="rr-money">{!! $money($rest->sum('total_amount')) !!}</td>
                    </tr>
                @endif
            </tbody>
            @if (!$limit && count($products) > 1)
                <tfoot>
                    <tr class="rr-shift__rest">
                        <th scope="row">@lang('sale.total')</th>
                        <td class="rr-num">{{ @format_quantity($products->sum('total_quantity')) }}</td>
                        <td class="rr-money">{!! $money($products->sum('total_amount')) !!}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
        @if ($searchable)
            <p class="rr-shift__muted" data-rr-shift-none hidden>@lang('shift_close.no_match')</p>
        @endif
        @if (count($rest))
            <p class="rr-shift__muted">{!! $icon('info', 14) !!} @lang('shift_close.full_list_hint')</p>
        @endif
        @if (count($details['product_details_by_brand'] ?? []) > 1)
            <h4 class="rr-shift__sub">@lang('shift_close.by_brand')</h4>
            <table class="rr-shift__table">
                <tbody>
                    @foreach ($details['product_details_by_brand'] as $d)
                        <tr><th scope="row">{{ $d->brand_name ?: __('shift_close.no_brand') }}</th><td class="rr-num">{{ @format_quantity($d->total_quantity) }}</td><td class="rr-money">{!! $money($d->total_amount) !!}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if (!empty($details['types_of_service_details']) && count($details['types_of_service_details']))
            <h4 class="rr-shift__sub">@lang('shift_close.types_of_service')</h4>
            <table class="rr-shift__table">
                <tbody>
                    @foreach ($details['types_of_service_details'] as $d)
                        <tr><th scope="row">{{ $d->types_of_service_name ?? '—' }}</th><td class="rr-money">{!! $money($d->total_sales) !!}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </details>
@endif
