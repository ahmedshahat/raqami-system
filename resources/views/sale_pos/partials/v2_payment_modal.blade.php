{{--
    نافذة الدفع المتعدد (F9) ونافذة بيانات البطاقة في شاشة الكاشير الجديدة بتصميم رقمي (بدل payment_modal).
    على اليمين سطور الدفع (v2_payment_row)، وعلى الشمال الملخص: المطلوب، المدفوع، الباقي للعميل، المتبقي.
    نفس الـ ids اللي pos.js بيحسب ويحفظ بيها: #modal_payment، #payment_rows_div، #payment_row_index، #add-payment-row،
    #change_return_payment_data، #change_return، #total_paying_input، #in_balance_due، #advance_balance، #pos-save،
    و#card_details_modal بحقوله (#card_number ... #pos-save-card).
--}}
@php
    $row_count = 0;
@endphp
<div class="modal fade rr-scope" tabindex="-1" role="dialog" id="modal_payment" aria-labelledby="rr_pay_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact rr-modal-dialog--lg rr-pay" role="document">
        <div class="modal-content rr-modal">
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title" id="rr_pay_title">@lang('pos_screen.pay_title')</h2>
                    <p class="rr-modal__desc">@lang('pos_screen.pay_desc')</p>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
            </header>

            <div class="rr-modal__body rr-pay__body">
                <div class="rr-pay__main">
                    <div id="payment_rows_div" class="rr-pay__rows">
                        @foreach ($payment_lines as $payment_line)
                            @if ($payment_line['is_return'] == 1)
                                @php $change_return = $payment_line; @endphp
                                @continue
                            @endif
                            @include('sale_pos.partials.v2_payment_row', ['removable' => $row_count > 0, 'row_index' => $loop->index, 'payment_line' => $payment_line])
                            @php $row_count++; @endphp
                        @endforeach
                    </div>
                    <input type="hidden" id="payment_row_index" value="{{ count($payment_lines) }}">
                    <button type="button" class="rr-btn rr-btn--secondary rr-pay__add" id="add-payment-row">{!! $icon('plus', 16) !!}@lang('pos_screen.pay_add_row')</button>

                    {{-- طريقة رد الباقي: pos.js بيظهرها لما المدفوع يزيد عن المطلوب --}}
                    <div class="payment_row rr-pay__row rr-pay__change-row @if ($change_return['amount'] == 0) hide @endif" id="change_return_payment_data">
                        <strong class="rr-pay__sub">{!! $icon('undo', 16) !!}@lang('pos_screen.pay_change_method')</strong>
                        <div class="rr-pay__line">
                            @php
                                $change_method = empty($change_return['method']) && array_key_exists('cash', $payment_types) ? 'cash' : $change_return['method'];
                                $change_types = $payment_types;
                                unset($change_types['advance']);
                            @endphp
                            <div class="rr-field rr-pay__method">
                                <label class="rr-field__label" for="change_return_method">@lang('pos_screen.pay_method')</label>
                                {!! Form::select('payment[change_return][method]', $change_types, $change_method, ['class' => 'form-control payment_types_dropdown', 'id' => 'change_return_method']) !!}
                            </div>
                            @if (!empty($accounts))
                                <div class="rr-field form-group">
                                    <label class="rr-field__label" for="change_return_account">@lang('pos_screen.pay_account')</label>
                                    {!! Form::select('payment[change_return][account_id]', $accounts, !empty($change_return['account_id']) ? $change_return['account_id'] : '', ['class' => 'form-control', 'id' => 'change_return_account']) !!}
                                </div>
                            @endif
                        </div>
                        <div class="rr-pay__details">
                            @include('sale_pos.partials.payment_type_details', ['payment_line' => $change_return, 'row_index' => 'change_return'])
                        </div>
                    </div>

                    <details class="rr-exp__box rr-exp__more rr-pay__notes" @if (!empty($transaction) && ($transaction->additional_notes || $transaction->staff_note)) open @endif>
                        <summary class="rr-exp__h">{!! $icon('message', 16) !!}@lang('pos_screen.pay_notes'){!! $icon('chevron-down', 16) !!}</summary>
                        <div class="rr-exp__grid">
                            <div class="rr-field">
                                <label class="rr-field__label" for="sale_note">@lang('pos_screen.pay_sale_note')</label>
                                {!! Form::textarea('sale_note', !empty($transaction) ? $transaction->additional_notes : null, ['class' => 'form-control', 'rows' => 2, 'id' => 'sale_note', 'placeholder' => __('pos_screen.pay_sale_note_hint')]) !!}
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="staff_note">@lang('pos_screen.pay_staff_note')</label>
                                {!! Form::textarea('staff_note', !empty($transaction) ? $transaction->staff_note : null, ['class' => 'form-control', 'rows' => 2, 'id' => 'staff_note', 'placeholder' => __('pos_screen.pay_staff_note_hint')]) !!}
                            </div>
                        </div>
                    </details>
                </div>

                {{-- ═══ الملخص (pos.js بيكتب في span.total_payable_span و span.total_paying و span.balance_due و span.change_return_span) ═══ --}}
                <aside class="rr-pay__sum" aria-label="@lang('pos_screen.pay_summary')">
                    <div class="rr-pay__due">
                        <span>@lang('pos_screen.pay_due')</span>
                        <strong><span class="rr-num total_payable_span">0</span></strong>
                        <small><span class="rr-num total_quantity">0</span> @lang('pos_screen.pay_items')</small>
                    </div>
                    <dl class="rr-pay__stats">
                        <div><dt>@lang('pos_screen.pay_paying')</dt><dd><span class="rr-num total_paying">0</span></dd></div>
                        <div class="rr-pay__stat--change" data-rr-pay-change><dt>@lang('pos_screen.pay_change')</dt><dd><span class="rr-num change_return_span">0</span></dd></div>
                        <div class="rr-pay__stat--due" data-rr-pay-balance><dt>@lang('pos_screen.pay_balance')</dt><dd><span class="rr-num balance_due">0</span></dd></div>
                        <div class="rr-pay__stat--advance" data-rr-pay-advance hidden><dt>@lang('pos_screen.pay_advance')</dt><dd><span class="rr-num" id="advance_balance_text"></span></dd></div>
                    </dl>
                    <p class="rr-pay__credit" data-rr-pay-credit hidden>{!! $icon('info', 14) !!}@lang('pos_screen.pay_credit_hint')</p>
                    <input type="hidden" id="total_paying_input">
                    <input type="hidden" id="in_balance_due" value="0">
                    {!! Form::hidden('advance_balance', null, ['id' => 'advance_balance', 'data-error-msg' => __('lang_v1.required_advance_balance_not_available')]) !!}
                    {!! Form::hidden('change_return', $change_return['amount'], ['class' => 'form-control change_return input_number', 'required', 'id' => 'change_return']) !!}
                    @if (!empty($change_return['id']))
                        <input type="hidden" name="change_return_id" value="{{ $change_return['id'] }}">
                    @endif
                </aside>
            </div>

            <footer class="rr-modal__foot">
                <button type="submit" class="rr-btn rr-btn--primary rr-btn--lg rr-pay__save" id="pos-save">{!! $icon('check') !!}@lang('pos_screen.pay_save')</button>
                <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.cancel')</button>
                <span class="rr-modal__note">@lang('pos_screen.pay_hint')</span>
            </footer>
        </div>
    </div>
</div>

{{-- بيانات البطاقة لزرار "بطاقة" (الدفع السريع): pos.js بينقلها لأول سطر دفع ويحفظ --}}
<div class="modal fade rr-scope" tabindex="-1" role="dialog" id="card_details_modal" aria-labelledby="rr_card_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm rr-pay" role="document">
        <div class="modal-content rr-modal">
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title" id="rr_card_title">@lang('pos_screen.card_title')</h2>
                    <p class="rr-modal__desc">@lang('pos_screen.card_desc')</p>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
            </header>
            <div class="rr-modal__body">
                <div class="rr-exp__grid">
                    <div class="rr-field">
                        <label class="rr-field__label" for="card_transaction_number">@lang('pos_screen.card_ref')</label>
                        <input type="text" class="form-control" id="card_transaction_number" autocomplete="off" dir="ltr">
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="card_number">@lang('pos_screen.card_last4')</label>
                        <input type="text" class="form-control" id="card_number" autocomplete="off" dir="ltr" inputmode="numeric">
                    </div>
                </div>
                <details class="rr-exp__box rr-exp__more">
                    <summary class="rr-exp__h">{!! $icon('sliders', 16) !!}@lang('pos_screen.card_more'){!! $icon('chevron-down', 16) !!}</summary>
                    <div class="rr-exp__grid">
                        <div class="rr-field rr-field--full">
                            <label class="rr-field__label" for="card_holder_name">@lang('lang_v1.card_holder_name')</label>
                            <input type="text" class="form-control" id="card_holder_name" autocomplete="off">
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="card_type">@lang('lang_v1.card_type')</label>
                            {!! Form::select('', ['visa' => 'Visa', 'master' => 'MasterCard', 'credit' => 'Credit Card', 'debit' => 'Debit Card'], 'visa', ['class' => 'form-control', 'id' => 'card_type']) !!}
                        </div>
                        <div class="rr-field">
                            <label class="rr-field__label" for="card_month">@lang('lang_v1.month') / @lang('lang_v1.year')</label>
                            <div class="rr-qp__pair">
                                <input type="text" class="form-control" id="card_month" autocomplete="off" dir="ltr" placeholder="MM" aria-label="@lang('lang_v1.month')">
                                <input type="text" class="form-control" id="card_year" autocomplete="off" dir="ltr" placeholder="YY" aria-label="@lang('lang_v1.year')">
                            </div>
                        </div>
                        {{-- pos.js بينسخه لسطر الدفع؛ الكاشير مش المفروض يكتب رمز الأمان فمخفي --}}
                        <input type="hidden" id="card_security">
                    </div>
                </details>
            </div>
            <footer class="rr-modal__foot">
                <button type="button" class="rr-btn rr-btn--primary rr-btn--lg rr-pay__save" id="pos-save-card">{!! $icon('check') !!}@lang('pos_screen.pay_save')</button>
                <span class="rr-modal__note">@lang('pos_screen.card_hint')</span>
            </footer>
        </div>
    </div>
</div>
