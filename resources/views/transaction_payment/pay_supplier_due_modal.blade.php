{{--
    نافذة تسجيل دفعة لمورد أو عميل بتصميم رقمي. تفتح من app.js داخل .pay_contact_due_modal (من غير rr-scope)، فالـ rr-scope على الـ dialog.
    نفس الأسماء اللي app.js و common.js بيعتمدوا عليها: #pay_contact_due_form، #paid_on، .payment_row، .payment_types_dropdown،
    .payment_amount، .payment_details_div، .cash_denomination*، .denomination_*، .is_strict، .enable_cash_denomination_for_payment_methods.
    الحفظ في TransactionPaymentController::postPayContactDue
--}}
@php
    $pay_totals = [
        'purchase' => [$contact_details->total_purchase, $contact_details->total_paid],
        'purchase_return' => [$contact_details->total_purchase_return, $contact_details->total_return_paid],
        'sell' => [$contact_details->total_invoice, $contact_details->total_paid],
        'sell_return' => [$contact_details->total_sell_return, $contact_details->total_return_paid],
    ];
    [$pay_total, $pay_paid] = $pay_totals[$due_payment_type];
    $is_return_payment = in_array($due_payment_type, ['sell_return', 'purchase_return']);
    $can_reverse = config('constants.show_payment_type_on_contact_pay') && ! $is_return_payment;

    $amount_attributes = ['class' => 'form-control input_number payment_amount', 'required', 'placeholder' => __('contact_pay.amount'), 'inputmode' => 'decimal', 'data-msg-required' => __('contact_pay.required')];
    if ($is_return_payment) {
        $amount_attributes['data-rule-max-value'] = $payment_line->amount;
        $amount_attributes['data-msg-max-value'] = __('lang_v1.max_amount_to_be_paid_is', ['amount' => $amount_formated]);
    }

    $pos_settings = ! empty(session()->get('business.pos_settings')) ? json_decode(session()->get('business.pos_settings'), true) : [];
    $enable_cash_denomination_for_payment_methods = ! empty($pos_settings['enable_cash_denomination_for_payment_methods']) ? $pos_settings['enable_cash_denomination_for_payment_methods'] : [];
@endphp
<div class="modal-dialog rr-scope rr-modal-dialog rr-modal-dialog--compact rr-modal-dialog--lg" role="document">
    <div class="modal-content rr-modal rr-pay rr-modal--flow">
        {!! Form::open(['url' => action([\App\Http\Controllers\TransactionPaymentController::class, 'postPayContactDue']), 'method' => 'post', 'id' => 'pay_contact_due_form', 'files' => true]) !!}
            {!! Form::hidden('contact_id', $contact_details->contact_id) !!}
            {!! Form::hidden('due_payment_type', $due_payment_type) !!}

            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title">@lang('contact_pay.title_' . $due_payment_type)</h2>
                    <p class="rr-modal__desc">{{ $contact_details->name }}@if (! empty($contact_details->supplier_business_name) && in_array($due_payment_type, ['purchase', 'purchase_return'])) · {{ $contact_details->supplier_business_name }}@endif</p>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">@include('partials.rr.icon', ['name' => 'close'])</button>
            </header>

            <div class="rr-modal__body rr-pay__body">
                <div class="rr-pay__main">
                    @if ($can_reverse)
                        <div class="rr-field">
                            {!! Form::label('is_reverse', __('lang_v1.payment_type'), ['class' => 'rr-field__label']) !!}
                            {!! Form::select('is_reverse', $due_payment_type == 'purchase'
                                ? [0 => __('lang_v1.pay_to_supplier'), 1 => __('lang_v1.receive_from_supplier')]
                                : [0 => __('lang_v1.receive_from_customer'), 1 => __('lang_v1.pay_to_customer')], 0, ['class' => 'form-control']) !!}
                        </div>
                    @endif

                    <div class="payment_row rr-pay__row">
                        <div class="rr-pay__line">
                            <div class="rr-field rr-pay__amount">
                                <label class="rr-field__label" for="amount">@lang('contact_pay.amount') <span class="rr-field__req">*</span></label>
                                {!! Form::text('amount', @num_format($payment_line->amount), $amount_attributes) !!}
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="method">@lang('contact_pay.method') <span class="rr-field__req">*</span></label>
                                {!! Form::select('method', $payment_types, $payment_line->method, ['class' => 'form-control payment_types_dropdown', 'required', 'id' => 'method']) !!}
                            </div>
                        </div>
                        <div class="rr-pay__line">
                            <div class="rr-field rr-pay__date">
                                <label class="rr-field__label" for="paid_on">@lang('contact_pay.paid_on') <span class="rr-field__req">*</span></label>
                                {!! Form::text('paid_on', @format_datetime($payment_line->paid_on), ['class' => 'form-control', 'readonly', 'required', 'id' => 'paid_on', 'dir' => 'ltr', 'data-msg-required' => __('contact_pay.required')]) !!}
                            </div>
                            @if (! empty($accounts))
                                <div class="rr-field">
                                    <label class="rr-field__label" for="account_id">@lang('contact_pay.account')</label>
                                    {!! Form::select('account_id', $accounts, ! empty($payment_line->account_id) ? $payment_line->account_id : '', ['class' => 'form-control', 'id' => 'account_id']) !!}
                                </div>
                            @endif
                        </div>

                        <div class="rr-pay__details">
                            @include('transaction_payment.payment_type_details')
                        </div>

                        @if (! empty($pos_settings['enable_cash_denomination_on']) && $pos_settings['enable_cash_denomination_on'] == 'all_screens')
                            <input type="hidden" class="enable_cash_denomination_for_payment_methods" value="{{ json_encode($enable_cash_denomination_for_payment_methods) }}">
                            <div class="rr-pay__denoms cash_denomination_div @if (! in_array($payment_line->method, $enable_cash_denomination_for_payment_methods)) hide @endif">
                                <strong class="rr-pay__sub">@lang('lang_v1.cash_denominations')</strong>
                                @if (! empty($pos_settings['cash_denominations']))
                                    <table class="rr-pay__denom-table">
                                        <thead>
                                            <tr>
                                                <th>@lang('lang_v1.denomination')</th>
                                                <th>&nbsp;</th>
                                                <th>@lang('lang_v1.count')</th>
                                                <th>&nbsp;</th>
                                                <th>@lang('sale.subtotal')</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (explode(',', $pos_settings['cash_denominations']) as $dnm)
                                                <tr>
                                                    <td><span class="rr-num">{{ $dnm }}</span></td>
                                                    <td>×</td>
                                                    <td>{!! Form::number("denominations[$dnm]", null, ['class' => 'form-control cash_denomination', 'min' => 0, 'data-denomination' => $dnm]) !!}</td>
                                                    <td>=</td>
                                                    <td><span class="rr-num denomination_subtotal">0</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4">@lang('sale.total')</th>
                                                <td>
                                                    <span class="rr-num denomination_total">0</span>
                                                    <input type="hidden" class="denomination_total_amount" value="0">
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
                    </div>

                    <details class="rr-exp__box rr-exp__more rr-pay__notes">
                        <summary class="rr-exp__h">@include('partials.rr.icon', ['name' => 'message', 'size' => 16])@lang('contact_pay.more')@include('partials.rr.icon', ['name' => 'chevron-down', 'size' => 16])</summary>
                        <div class="rr-exp__grid">
                            <div class="rr-field">
                                <label class="rr-field__label" for="note">@lang('contact_pay.note')</label>
                                {!! Form::textarea('note', $payment_line->note, ['class' => 'form-control', 'rows' => 2, 'id' => 'note']) !!}
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="document">@lang('contact_pay.document')</label>
                                <input class="rr-file-input" type="file" id="document" name="document" accept="{{ implode(',', array_keys(config('constants.document_upload_mimes_types'))) }}">
                                <span class="rr-field__hint">@includeIf('components.document_help_text')</span>
                            </div>
                        </div>
                    </details>
                </div>

                <aside class="rr-pay__sum" aria-label="@lang('contact_pay.summary')">
                    <div class="rr-pay__due">
                        <span>@lang('contact_pay.due_' . $due_payment_type)</span>
                        <strong><span class="rr-num display_currency" data-currency_symbol="true">{{ $payment_line->amount }}</span></strong>
                    </div>
                    <dl class="rr-pay__stats">
                        <div><dt>@lang('contact_pay.total_' . $due_payment_type)</dt><dd><span class="rr-num display_currency" data-currency_symbol="true">{{ $pay_total ?: 0 }}</span></dd></div>
                        <div><dt>@lang('contact_pay.paid')</dt><dd><span class="rr-num display_currency" data-currency_symbol="true">{{ $pay_paid ?: 0 }}</span></dd></div>
                        @if ($ob_due > 0 && ! $is_return_payment)
                            <div><dt>@lang('contact_pay.opening_due')</dt><dd><span class="rr-num display_currency" data-currency_symbol="true">{{ $ob_due }}</span></dd></div>
                        @endif
                        <div class="rr-pay__after" data-rr-pay-after data-due="{{ (float) $payment_line->amount }}" data-label-left="@lang('contact_pay.after_left')" data-label-advance="@lang('contact_pay.after_advance')">
                            <dt>@lang('contact_pay.after_left')</dt>
                            <dd><span class="rr-num">0</span></dd>
                        </div>
                    </dl>
                </aside>
            </div>

            <footer class="rr-modal__foot">
                <button type="submit" class="rr-btn rr-btn--primary rr-btn--lg rr-pay__save">@include('partials.rr.icon', ['name' => 'check'])@lang('contact_pay.save')</button>
                <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.close')</button>
            </footer>
        {!! Form::close() !!}
    </div>
</div>
<script>
    // المتبقي بعد الدفعة بيتحسب وانت بتكتب، والزيادة بتتسجل رصيد مقدم للجهة (TransactionUtil::payContact)
    (function () {
        var form = $('#pay_contact_due_form');
        var after = form.find('[data-rr-pay-after]');
        var amount = form.find('.payment_amount');
        var reverse = form.find('[name="is_reverse"]');

        function refresh() {
            var left = parseFloat(after.data('due')) - __read_number(amount);
            var is_advance = left < -0.0001;
            after.prop('hidden', reverse.val() == 1);
            after.toggleClass('rr-pay__stat--due is-on', left > 0.0001 && !is_advance);
            after.toggleClass('rr-pay__stat--change', is_advance).toggleClass('is-on', Math.abs(left) > 0.0001);
            after.find('dt').text(after.data(is_advance ? 'label-advance' : 'label-left'));
            after.find('.rr-num').text(__currency_trans_from_en(Math.abs(left), true));
        }

        amount.on('input change', refresh);
        reverse.on('change', refresh);
        refresh();
    })();
</script>
