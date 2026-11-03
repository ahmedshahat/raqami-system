@php
    $isReceipt = $voucher_type === 'receipt_voucher';
    $editing = isset($voucher);
@endphp

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('business_location_id', __('business.business_location').':*') !!}
            {!! Form::select('business_location_id', $locations, old('business_location_id', $voucher->business_location_id ?? null), ['class' => 'form-control select2', 'required', 'placeholder' => __('messages.please_select')]) !!}
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('operation_date', __('messages.date').':*') !!}
            {!! Form::text('operation_date', old('operation_date', isset($voucher) ? @format_datetime($voucher->operation_date) : @format_datetime('now')), ['class' => 'form-control', 'required', 'readonly']) !!}
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label('ref_no', __('accounting::lang.voucher_number').':') !!}
            @show_tooltip(__('lang_v1.leave_empty_to_autogenerate'))
            {!! Form::text('ref_no', old('ref_no', $voucher->ref_no ?? null), ['class' => 'form-control', 'placeholder' => $isReceipt ? 'RCV-' : 'PAY-']) !!}
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('party_name', ($isReceipt ? __('accounting::lang.received_from') : __('accounting::lang.paid_to')).':*') !!}
            {!! Form::text('party_name', old('party_name', $voucher->party_name ?? null), ['class' => 'form-control', 'required', 'maxlength' => 191, 'placeholder' => __('accounting::lang.enter_person_or_entity_name')]) !!}
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('amount', __('sale.amount').':*') !!}
            {!! Form::text('amount', old('amount', isset($cashLine) ? @num_format($cashLine->amount) : null), ['class' => 'form-control input_number', 'required']) !!}
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('cash_account_id', __('accounting::lang.cash_or_bank_account').':*') !!}
            {!! Form::select('cash_account_id', isset($cashLine) ? [$cashLine->accounting_account_id => optional($cashLine->account)->name] : [], old('cash_account_id', $cashLine->accounting_account_id ?? null), ['class' => 'form-control accounts-dropdown', 'required', 'placeholder' => __('messages.please_select')]) !!}
            <p class="help-block">{{ $isReceipt ? __('accounting::lang.receipt_cash_account_help') : __('accounting::lang.payment_cash_account_help') }}</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('offset_account_id', __('accounting::lang.offset_account').':*') !!}
            {!! Form::select('offset_account_id', isset($offsetLine) ? [$offsetLine->accounting_account_id => optional($offsetLine->account)->name] : [], old('offset_account_id', $offsetLine->accounting_account_id ?? null), ['class' => 'form-control accounts-dropdown', 'required', 'placeholder' => __('messages.please_select')]) !!}
            <p class="help-block">{{ $isReceipt ? __('accounting::lang.receipt_offset_account_help') : __('accounting::lang.payment_offset_account_help') }}</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('payment_method', __('accounting::lang.payment_method').':*') !!}
            {!! Form::select('payment_method', $paymentMethods, old('payment_method', $voucher->payment_method ?? 'cash'), ['class' => 'form-control select2', 'required']) !!}
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('payment_reference', __('accounting::lang.payment_reference').':') !!}
            {!! Form::text('payment_reference', old('payment_reference', $voucher->payment_reference ?? null), ['class' => 'form-control', 'maxlength' => 191, 'placeholder' => __('accounting::lang.cheque_or_transfer_reference')]) !!}
        </div>
    </div>
</div>

<div class="form-group">
    {!! Form::label('note', __('accounting::lang.statement').':*') !!}
    {!! Form::textarea('note', old('note', $voucher->note ?? null), ['class' => 'form-control', 'required', 'rows' => 4, 'maxlength' => 2000, 'placeholder' => __('accounting::lang.voucher_statement_placeholder')]) !!}
</div>

{!! Form::hidden('voucher_type', $voucher_type) !!}

