{{--
    نافذة الفواتير المعلّقة (بتتحمّل في .view_modal من SellController::index?suspended=1) بتصميم رقمي.
    كل فاتورة سطر: الرقم والوقت والعميل والملاحظة، الإجمالي، وزرار "استكمال" وزرار حذف.
    رابط الحذف لازم يفضل a.delete-sale.is_suspended (app.js بيحذف ويقفل النافذة)، و [data-rr-suspended-item] بيعدّه عداد "المعلّقة".
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $subtype = !empty($transaction_sub_type) ? '?sub_type=' . $transaction_sub_type : '';
    $sales = $sales->filter(fn ($sale) => $sale->is_suspend);
    $can_edit = auth()->user()->can('sell.update') || auth()->user()->can('direct_sell.update');
    $can_delete = auth()->user()->can('sell.delete') || auth()->user()->can('direct_sell.delete');
    $can_payment = !auth()->user()->can('sell.update') && auth()->user()->can('edit_pos_payment');
    $time_format = session('business.time_format') == 12 ? 'h:i A' : 'H:i';
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--lg rr-scope" role="document">
    <div class="modal-content rr-modal">
        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">@lang('pos_screen.suspended_title')</h2>
                @if ($sales->count())
                    <p class="rr-modal__desc">
                        @lang('pos_screen.suspended_summary', ['count' => $sales->count()])
                        <span class="rr-money">@include('partials.rr.money', ['value' => $sales->sum('final_total')])</span>
                    </p>
                @endif
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
        </header>

        <div class="rr-modal__body rr-susp">
            @if ($sales->count() > 4)
                <div class="rr-susp__search">
                    <span class="rr-susp__search-icon" aria-hidden="true">{!! $icon('search', 16) !!}</span>
                    <input type="search" class="form-control" data-rr-susp-search placeholder="@lang('pos_screen.suspended_search')" aria-label="@lang('pos_screen.suspended_search')" autocomplete="off">
                </div>
            @endif

            @if ($sales->count())
                <ul class="rr-susp__list" role="list">
                    @foreach ($sales as $sale)
                        @php
                            $date = \Carbon\Carbon::parse($sale->transaction_date);
                            $old = !$date->isToday();
                            $search = mb_strtolower($sale->invoice_no . ' ' . $sale->name . ' ' . $sale->additional_notes . ' ' . ($sale->table->name ?? ''));
                        @endphp
                        <li class="rr-susp__item{{ $old ? ' rr-susp__item--old' : '' }}" data-rr-suspended-item data-search="{{ $search }}">
                            <div class="rr-susp__main">
                                <div class="rr-susp__top">
                                    <span class="rr-code rr-susp__no">{{ $sale->invoice_no }}</span>
                                    <span class="rr-susp__time" title="{{ @format_datetime($sale->transaction_date) }}">
                                        {!! $icon('clock', 14) !!}
                                        <span class="rr-num">{{ $old ? $date->format('d/m ' . $time_format) : $date->format($time_format) }}</span>
                                        <span class="rr-susp__ago">({{ $date->locale(app()->getLocale())->diffForHumans() }})</span>
                                    </span>
                                    @if ($old)
                                        <span class="rr-badge rr-badge--warning">@lang('pos_screen.suspended_old')</span>
                                    @endif
                                </div>
                                <div class="rr-susp__customer">{!! $icon('user', 16) !!}<span>{{ $sale->name }}</span></div>
                                @if (!empty($sale->additional_notes))
                                    <p class="rr-susp__note">{!! $icon('edit', 14) !!}<span>{{ $sale->additional_notes }}</span></p>
                                @endif
                                <div class="rr-susp__meta">
                                    <span>{!! $icon('box', 14) !!}@lang('pos_screen.suspended_items', ['count' => count($sale->sell_lines)])</span>
                                    @if ($is_tables_enabled && !empty($sale->table->name))
                                        <span>{!! $icon('grid', 14) !!}@lang('restaurant.table'): {{ $sale->table->name }}</span>
                                    @endif
                                    @if ($is_service_staff_enabled && !empty($sale->service_staff))
                                        <span>{!! $icon('users', 14) !!}{{ $sale->service_staff->user_full_name }}</span>
                                    @endif
                                    @if (!empty(trim($sale->added_by ?? '')))
                                        <span>{!! $icon('shield', 14) !!}{{ $sale->added_by }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="rr-susp__total"><span class="rr-money">@include('partials.rr.money', ['value' => $sale->final_total])</span></div>
                            <div class="rr-susp__actions">
                                @if ($can_edit)
                                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'edit'], ['po' => $sale->id]) . $subtype }}" class="rr-btn rr-btn--primary rr-susp__resume">{!! $icon('arrow-forward', 16) !!}@lang('pos_screen.suspended_resume')</a>
                                @endif
                                @if ($can_payment)
                                    <a href="{{ route('edit-pos-payment', ['id' => $sale->id]) }}" class="rr-btn rr-btn--secondary">{!! $icon('wallet', 16) !!}@lang('lang_v1.add_edit_payment')</a>
                                @endif
                                @if ($can_delete)
                                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'destroy'], ['po' => $sale->id]) }}" class="rr-btn rr-btn--ghost rr-btn--icon rr-susp__delete delete-sale is_suspended" title="@lang('messages.delete')" aria-label="@lang('messages.delete') {{ $sale->invoice_no }}">{!! $icon('trash', 18) !!}</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="rr-susp__none" data-rr-susp-none hidden>@lang('pos_screen.suspended_no_match')</p>
            @else
                <div class="rr-empty">
                    <span class="rr-empty__icon" aria-hidden="true">{!! $icon('pause', 22) !!}</span>
                    <span class="rr-empty__title">@lang('pos_screen.suspended_empty')</span>
                    <span class="rr-empty__text">@lang('pos_screen.suspended_empty_hint')</span>
                </div>
            @endif
        </div>

        <footer class="rr-modal__foot">
            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.close')</button>
        </footer>
    </div>
</div>
<script>
    // بحث سريع في الفواتير المعلّقة (بالرقم أو العميل أو الملاحظة)
    (function() {
        var input = document.querySelector('[data-rr-susp-search]');
        if (!input) return;
        input.addEventListener('input', function() {
            var q = input.value.trim().toLowerCase(), shown = 0;
            document.querySelectorAll('[data-rr-suspended-item]').forEach(function(item) {
                var match = !q || item.getAttribute('data-search').indexOf(q) > -1;
                item.hidden = !match;
                if (match) shown++;
            });
            document.querySelector('[data-rr-susp-none]').hidden = shown > 0;
        });
        setTimeout(function() { input.focus(); }, 300);
    })();
</script>
