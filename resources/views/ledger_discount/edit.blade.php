<div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
    <div class="modal-content rr-modal rr-pay rr-modal--flow">
        {!! Form::open(['url' => action([\App\Http\Controllers\LedgerDiscountController::class, 'update'], $discount->id), 'method' => 'put', 'id' => 'edit_discount_form']) !!}
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title">@lang('ledger_discount.title_edit')</h2>
                    <p class="rr-modal__desc">{{ $contact->supplier_business_name ?: $contact->name }}</p>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">@include('partials.rr.icon', ['name' => 'close'])</button>
            </header>
            @include('ledger_discount.partials.fields', ['date_id' => 'edit_discount_date'])
        {!! Form::close() !!}
    </div>
</div>
