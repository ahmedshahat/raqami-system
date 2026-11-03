<div class="modal fade rr-scope" id="add_discount_modal" tabindex="-1" role="dialog" aria-labelledby="rr_discount_add_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
        <div class="modal-content rr-modal rr-pay rr-modal--flow">
            {!! Form::open(['url' => action([\App\Http\Controllers\LedgerDiscountController::class, 'store']), 'method' => 'post', 'id' => 'add_discount_form']) !!}
                <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr_discount_add_title">@lang('ledger_discount.title_add')</h2>
                        <p class="rr-modal__desc">{{ $contact->supplier_business_name ?: $contact->name }}</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">@include('partials.rr.icon', ['name' => 'close'])</button>
                </header>
                @include('ledger_discount.partials.fields', ['date_id' => 'discount_date'])
            {!! Form::close() !!}
        </div>
    </div>
</div>
