{{--
    ملخص الوردية (مشترك بين "إغلاق الوردية" و"تفاصيل الوردية"): 4 أرقام + حركة الدرج + طرق الدفع اللي فيها حركة.
    المتوقع في الدرج = الافتتاح + النقدي − المرتجع النقدي − المصروف النقدي (نفس رقم الشاشة القديمة).
    [data-rr-shift-print] هو اللي بيتنسخ في الطباعة.
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $money = fn ($value) => view('partials.rr.money', ['value' => $value])->render();
    $r = $register_details;
    $td = $details['transaction_details'];
    $expected = $r->cash_in_hand + $r->total_cash - $r->total_cash_refund - $r->total_cash_expense;
    $credit_sales = ($td->total_sales ?? 0) - $r->total_sale;

    // طرق الدفع اللي ليها حركة بس
    $method_keys = ['cash' => __('cash_register.cash_payment'), 'card' => __('cash_register.card_payment'), 'cheque' => __('cash_register.checque_payment'),
        'bank_transfer' => __('cash_register.bank_transfer'), 'advance' => __('lang_v1.advance_payment')];
    for ($i = 1; $i <= 7; $i++) {
        if (array_key_exists('custom_pay_' . $i, $payment_types)) {
            $method_keys['custom_pay_' . $i] = $payment_types['custom_pay_' . $i];
        }
    }
    $method_keys['other'] = __('cash_register.other_payments');
    $methods = [];
    foreach ($method_keys as $key => $label) {
        $row = ['label' => $payment_types[$key] ?? $label, 'in' => $r->{'total_' . $key} ?? 0, 'refund' => $r->{'total_' . $key . '_refund'} ?? 0, 'out' => $r->{'total_' . $key . '_expense'} ?? 0];
        if ($row['in'] != 0 || $row['refund'] != 0 || $row['out'] != 0) {
            $methods[$key] = $row;
        }
    }
    $show_refund_col = collect($methods)->sum('refund') != 0;
@endphp
<div class="rr-shift__print" data-rr-shift-print>
    {{-- ═══ الأرقام الأساسية ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-shift__stats" style="--rr-stats: 4">
            <div class="rr-stat rr-stat--brand">
                <div class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('receipt', 16) !!}</span>@lang('shift_close.total_sales')</div>
                <div class="rr-stat__value rr-money">{!! $money($td->total_sales ?? 0) !!}</div>
                <div class="rr-stat__sub">@lang('shift_close.invoices', ['count' => (int) ($td->total_invoices ?? 0)])</div>
            </div>
            <div class="rr-stat rr-stat--success">
                <div class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('cash', 16) !!}</span>@lang('shift_close.cash_sales')</div>
                <div class="rr-stat__value rr-money">{!! $money($r->total_cash) !!}</div>
            </div>
            <div class="rr-stat rr-stat--warning">
                <div class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('undo', 16) !!}</span>@lang('shift_close.refunds')</div>
                <div class="rr-stat__value rr-money">{!! $money($r->total_refund) !!}</div>
            </div>
            <div class="rr-stat rr-stat--danger">
                <div class="rr-stat__label"><span class="rr-stat__icon">{!! $icon('wallet', 16) !!}</span>@lang('shift_close.expenses')</div>
                <div class="rr-stat__value rr-money">{!! $money($r->total_expense) !!}</div>
            </div>
        </div>
    </div>

    <div class="rr-shift__cols">
        {{-- ═══ الدرج: الافتتاح + النقدي − المرتجع − المصروف = المتوقع ═══ --}}
        <section class="rr-shift__box" aria-labelledby="rr_shift_drawer">
            <h3 class="rr-shift__h" id="rr_shift_drawer">{!! $icon('cash', 16) !!}@lang('shift_close.drawer')</h3>
            <dl class="rr-shift__calc">
                <div><dt>@lang('shift_close.opening')</dt><dd class="rr-money">{!! $money($r->cash_in_hand) !!}</dd></div>
                <div><dt>@lang('shift_close.cash_in')</dt><dd class="rr-money rr-shift__plus">+ {!! $money($r->total_cash) !!}</dd></div>
                @if ($r->total_cash_refund != 0)
                    <div><dt>@lang('shift_close.cash_refund')</dt><dd class="rr-money rr-shift__minus">− {!! $money($r->total_cash_refund) !!}</dd></div>
                @endif
                @if ($r->total_cash_expense != 0)
                    <div><dt>@lang('shift_close.cash_expense')</dt><dd class="rr-money rr-shift__minus">− {!! $money($r->total_cash_expense) !!}</dd></div>
                @endif
                <div class="rr-shift__expected"><dt>@lang('shift_close.expected')</dt><dd class="rr-money">{!! $money($expected) !!}</dd></div>
                @isset($counted)
                    {{-- وردية مقفولة: المعدود وقت الإغلاق والفرق --}}
                    @php $diff = round($counted - $expected, 2); @endphp
                    <div><dt>@lang('shift_close.counted')</dt><dd class="rr-money">{!! $money($counted) !!}</dd></div>
                    <div class="rr-shift__diff-row">
                        <dt>@lang('shift_close.difference')</dt>
                        <dd><span class="rr-badge {{ $diff == 0 ? 'rr-badge--success' : ($diff < 0 ? 'rr-badge--danger' : 'rr-badge--warning') }}" data-rr-shift-diff-static>{!! $diff == 0 ? e(__('shift_close.diff_ok')) : __($diff < 0 ? 'shift_close.diff_short' : 'shift_close.diff_over', ['amount' => $money(abs($diff))]) !!}</span></dd>
                    </div>
                @endisset
            </dl>
        </section>

        {{-- ═══ طرق الدفع اللي فيها حركة ═══ --}}
        <section class="rr-shift__box" aria-labelledby="rr_shift_methods">
            <h3 class="rr-shift__h" id="rr_shift_methods">{!! $icon('card', 16) !!}@lang('shift_close.by_method')</h3>
            @if (count($methods) || $credit_sales != 0)
                <table class="rr-shift__table">
                    <thead>
                        <tr>
                            <th>@lang('shift_close.method')</th>
                            <th>@lang('shift_close.received')</th>
                            @if ($show_refund_col)<th>@lang('shift_close.refunds')</th>@endif
                            <th>@lang('shift_close.paid_out')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($methods as $m)
                            <tr>
                                <th scope="row">{{ $m['label'] }}</th>
                                <td class="rr-money">{!! $money($m['in']) !!}</td>
                                @if ($show_refund_col)<td class="rr-money">{!! $m['refund'] != 0 ? $money($m['refund']) : '—' !!}</td>@endif
                                <td class="rr-money">{!! $m['out'] != 0 ? $money($m['out']) : '—' !!}</td>
                            </tr>
                        @endforeach
                        @if ($credit_sales != 0)
                            <tr class="rr-shift__credit">
                                <th scope="row">@lang('shift_close.credit_sales') <span class="rr-shift__muted">({{ __('shift_close.credit_hint') }})</span></th>
                                <td class="rr-money">{!! $money($credit_sales) !!}</td>
                                @if ($show_refund_col)<td>—</td>@endif
                                <td>—</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @else
                <p class="rr-shift__muted">@lang('shift_close.no_movement')</p>
            @endif
        </section>
    </div>
</div>
