{{--
    سطر دفع في نافذة الدفع المتعدد بشاشة الكاشير الجديدة (v2_payment_modal، وgetPaymentRow لما الطلب فيه v2=1).
    نفس الأسماء والكلاسات اللي pos.js و app.js و common.js بيعتمدوا عليها:
    .payment_row / .payment_row_index / .payment-amount / .payment_types_dropdown / .account-dropdown جوه .form-group /
    .payment_details_div / .cash_denomination_div / .enable_cash_denomination_for_payment_methods / .remove_payment_row
--}}
@php
    $icon = $icon ?? function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $pos_settings = !empty(session()->get('business.pos_settings')) ? json_decode(session()->get('business.pos_settings'), true) : [];
    $denomination_methods = !empty($pos_settings['enable_cash_denomination_for_payment_methods']) ? $pos_settings['enable_cash_denomination_for_payment_methods'] : [];
    $show_denominations = !empty($pos_settings['enable_cash_denomination_on']) && in_array($pos_settings['enable_cash_denomination_on'], ['all_screens', 'pos_screen']);
    $readonly = $payment_line['method'] == 'advance';
    $method = empty($payment_line['method']) && array_key_exists('cash', $payment_types) ? 'cash' : $payment_line['method'];
@endphp
<div class="payment_row rr-pay__row">
    <input type="hidden" class="payment_row_index" value="{{ $row_index }}">
    @if (!empty($payment_line['id']))
        {!! Form::hidden("payment[$row_index][payment_id]", $payment_line['id']) !!}
    @endif

    <div class="rr-pay__line">
        <div class="rr-field rr-pay__method">
            <label class="rr-field__label" for="method_{{ $row_index }}">@lang('pos_screen.pay_method')</label>
            {!! Form::select("payment[$row_index][method]", $payment_types, $method, ['class' => 'form-control payment_types_dropdown', 'required', 'id' => !$readonly ? "method_$row_index" : "method_advance_$row_index", 'disabled' => $readonly]) !!}
            @if ($readonly)
                {!! Form::hidden("payment[$row_index][method]", $payment_line['method'], ['class' => 'payment_types_dropdown', 'required', 'id' => "method_$row_index"]) !!}
            @endif
        </div>
        <div class="rr-field rr-pay__amount">
            <label class="rr-field__label" for="amount_{{ $row_index }}">@lang('pos_screen.pay_amount')</label>
            {!! Form::text("payment[$row_index][amount]", @num_format($payment_line['amount']), ['class' => 'form-control payment-amount input_number', 'required', 'id' => "amount_$row_index", 'readonly' => $readonly, 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
        </div>
        @if (!empty($accounts))
            <div class="rr-field form-group @if ($readonly) hide @endif">
                <label class="rr-field__label" for="account_{{ $row_index }}">@lang('pos_screen.pay_account')</label>
                {!! Form::select("payment[$row_index][account_id]", $accounts, !empty($payment_line['account_id']) ? $payment_line['account_id'] : '', ['class' => 'form-control account-dropdown', 'id' => !$readonly ? "account_$row_index" : "account_advance_$row_index", 'disabled' => $readonly]) !!}
            </div>
        @endif
        @if ($removable)
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--icon rr-pay__remove remove_payment_row" title="@lang('pos_screen.pay_remove')" aria-label="@lang('pos_screen.pay_remove')">{!! $icon('trash', 18) !!}</button>
        @endif
    </div>

    <div class="rr-pay__details">
        @include('sale_pos.partials.payment_type_details')
    </div>

    @if ($show_denominations)
        <input type="hidden" class="enable_cash_denomination_for_payment_methods" value="{{ json_encode($denomination_methods) }}">
        <div class="cash_denomination_div rr-pay__denoms @if (!in_array($payment_line['method'], $denomination_methods)) hide @endif">
            <strong class="rr-pay__sub">@lang('lang_v1.cash_denominations')</strong>
            @if (!empty($pos_settings['cash_denominations']))
                @php $denoms_total = 0; @endphp
                <table class="rr-pay__denom-table">
                    <tbody>
                        @foreach (explode(',', $pos_settings['cash_denominations']) as $dnm)
                            @php
                                $count = 0;
                                foreach ($payment_line['denominations'] ?? [] as $d) {
                                    if ($d['amount'] == $dnm) {
                                        $count = $d['total_count'];
                                        $denoms_total += $d['total_count'] * $d['amount'];
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="rr-num">{{ $dnm }}</td>
                                <td>×</td>
                                <td>{!! Form::number("payment[$row_index][denominations][$dnm]", $count, ['class' => 'form-control cash_denomination', 'min' => 0, 'data-denomination' => $dnm]) !!}</td>
                                <td>=</td>
                                <td class="rr-num"><span class="denomination_subtotal">{{ @num_format($count * $dnm) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">@lang('sale.total')</th>
                            <td class="rr-num">
                                <span class="denomination_total">{{ @num_format($denoms_total) }}</span>
                                <input type="hidden" class="denomination_total_amount" value="{{ $denoms_total }}">
                                <input type="hidden" class="is_strict" value="{{ $pos_settings['cash_denomination_strict_check'] ?? '' }}">
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <p class="cash_denomination_error error hide">@lang('lang_v1.cash_denomination_error')</p>
            @else
                <p class="rr-field__hint">@lang('lang_v1.denomination_add_help_text')</p>
            @endif
        </div>
    @endif

    {!! Form::text("payment[$row_index][note]", $payment_line['note'], ['class' => 'form-control rr-pay__note', 'id' => "note_$row_index", 'placeholder' => __('pos_screen.pay_note'), 'aria-label' => __('pos_screen.pay_note')]) !!}
</div>
