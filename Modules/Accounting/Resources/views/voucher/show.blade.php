<div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title">@lang('accounting::lang.'.$voucher->type) — {{ $voucher->ref_no }}</h4>
    </div>
    <div class="modal-body">
        @if($voucher->status === 'cancelled')<div class="alert alert-danger text-center"><strong>@lang('accounting::lang.voucher_cancelled')</strong></div>@endif
        <div class="row">
            <div class="col-md-4"><strong>@lang('messages.date'):</strong> {{ @format_datetime($voucher->operation_date) }}</div>
            <div class="col-md-4"><strong>@lang('accounting::lang.party_name'):</strong> {{ $voucher->party_name }}</div>
            <div class="col-md-4"><strong>@lang('sale.amount'):</strong> <span class="display_currency" data-currency_symbol="true">{{ $cashLine->amount }}</span></div>
        </div><hr>
        <div class="row">
            <div class="col-md-4"><strong>@lang('accounting::lang.cash_or_bank_account'):</strong> {{ optional($cashLine->account)->name }}</div>
            <div class="col-md-4"><strong>@lang('accounting::lang.offset_account'):</strong> {{ optional($offsetLine->account)->name }}</div>
            <div class="col-md-4"><strong>@lang('accounting::lang.payment_method'):</strong> @lang('accounting::lang.payment_method_'.$voucher->payment_method)</div>
        </div><hr>
        <p><strong>@lang('accounting::lang.statement'):</strong> {{ $voucher->note }}</p>
        @if($voucher->payment_reference)<p><strong>@lang('accounting::lang.payment_reference'):</strong> {{ $voucher->payment_reference }}</p>@endif
    </div>
    <div class="modal-footer">
        <a target="_blank" href="{{ action([\Modules\Accounting\Http\Controllers\VoucherController::class, 'print'], [$voucher->id]) }}" class="tw-dw-btn tw-dw-btn-primary tw-text-white"><i class="fas fa-print"></i> @lang('accounting::lang.print_voucher')</a>
        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
    </div>
</div></div>

