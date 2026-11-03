{{-- حقول نافذتي إضافة وتعديل الخصم. الحفظ والتقويم من contact.partials.show_js --}}
@php
    $hint_kind = in_array($contact->type, ['customer', 'supplier']) ? $contact->type : 'both';
@endphp
<div class="rr-modal__body">
    <div class="rr-pay__line">
        <div class="rr-field rr-pay__amount">
            <label class="rr-field__label" for="amount">@lang('ledger_discount.amount') <span class="rr-field__req">*</span></label>
            {!! Form::text('amount', ! empty($discount) ? @num_format($discount->final_total) : null, ['class' => 'form-control input_number payment-amount', 'id' => 'amount', 'required', 'data-decimal' => 'no_neg']) !!}
        </div>
        <div class="rr-field rr-pay__date">
            <label class="rr-field__label" for="{{ $date_id }}">@lang('ledger_discount.date') <span class="rr-field__req">*</span></label>
            {!! Form::text('date', @format_datetime(! empty($discount) ? $discount->transaction_date : \Carbon::now()->toDateTimeString()), ['class' => 'form-control', 'id' => $date_id, 'readonly', 'required', 'dir' => 'ltr']) !!}
        </div>
    </div>
    <span class="rr-field__hint">@lang('ledger_discount.hint_' . $hint_kind)</span>

    @if($contact->type == 'both')
        <div class="rr-field">
            <label class="rr-field__label" for="sub_type">@lang('ledger_discount.discount_for')</label>
            {!! Form::select('sub_type', ['sell_discount' => __('ledger_discount.for_sell'), 'purchase_discount' => __('ledger_discount.for_purchase')], ! empty($discount) ? $discount->sub_type : 'sell_discount', ['class' => 'form-control', 'id' => 'sub_type', 'required']) !!}
        </div>
    @endif

    <div class="rr-field">
        <label class="rr-field__label" for="note">@lang('ledger_discount.note')</label>
        {!! Form::textarea('note', ! empty($discount) ? $discount->additional_notes : null, ['class' => 'form-control', 'id' => 'note', 'rows' => 2]) !!}
    </div>
</div>

<footer class="rr-modal__foot">
    <button type="submit" class="rr-btn rr-btn--primary">@include('partials.rr.icon', ['name' => 'check'])@lang('ledger_discount.save')</button>
    <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.close')</button>
</footer>
