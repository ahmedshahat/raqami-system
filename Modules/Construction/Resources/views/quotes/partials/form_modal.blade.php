@php
    /**
     * نافذة بيانات عرض السعر (إنشاء / تعديل).
     * @var string $mode create|edit
     * @var \Modules\Construction\Entities\ConstructionQuote|null $quote
     *      لو اتبعت عرض: النافذة بتيجي مليانة ببياناته وبتعمل حفظ عادي (صفحة المشاهدة).
     *      من غيره في وضع التعديل: الحقول بتتملى بالسكريبت من زرار الصف (صفحة القائمة) والحفظ بـ AJAX.
     */
    $isCreate = $mode === 'create';
    $quote = $isCreate ? null : ($quote ?? null);
    $isAjax = $quote === null;
    $modalId = $isCreate ? 'ct-quote-create' : 'ct-quote-edit';
    $prefix = 'rr-quote-' . $mode;
    $fieldValue = function (string $field, $default = '') use ($isCreate, $quote) {
        if ($isCreate || $quote) {
            return old($field, $quote ? $quote->{$field} : $default);
        }

        return '';
    };
    $dateValue = $quote ? old('quote_date', $quote->quote_date->toDateString()) : ($isCreate ? old('quote_date', now()->toDateString()) : '');
    $headerErrors = $isAjax ? [] : collect(['quote_date', 'customer_id', 'title', 'validity_days', 'notes'])
        ->map(fn ($field) => $errors->first($field))->filter()->values()->all();
@endphp
<div class="modal fade rr-scope" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $prefix }}-title" @if($headerErrors) data-rr-open-on-load @endif>
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST"
                  @if($isCreate) action="{{ route('construction.quotes.store') }}" data-ct-open-mode="redirect" data-ct-quick-create
                  @elseif($quote) action="{{ route('construction.quotes.update', $quote->id) }}"
                  @else data-update-url="{{ route('construction.quotes.update', ['quote' => '__QUOTE__']) }}" data-ct-quick-create @endif
                  data-rr-quote-form>
                @csrf
                @unless($isCreate) @method('PUT') @endunless

                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="{{ $prefix }}-title">@lang($isCreate ? 'construction::lang.new_quote' : 'construction::lang.edit_quote')</h2>
                        <p class="rr-modal__desc">@lang($isCreate ? 'construction::lang.quote_create_hint' : 'construction::lang.quote_edit_hint')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>

                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert">{{ implode("\n", $headerErrors) }}</div>

                    <div class="rr-form-wrap">
                        <div class="rr-form-grid">
                            <div class="rr-field">
                                <label class="rr-field__label" for="{{ $prefix }}-date">@lang('construction::lang.quote_date')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                <div class="rr-control">
                                    <input id="{{ $prefix }}-date" class="rr-control__input" type="date" name="quote_date" value="{{ $dateValue }}" required>
                                </div>
                            </div>

                            <div class="rr-field">
                                <label class="rr-field__label" for="{{ $prefix }}-validity">@lang('construction::lang.quote_validity_days')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                <div class="rr-control">
                                    <input id="{{ $prefix }}-validity" class="rr-control__input rr-control__input--num" type="number" name="validity_days" min="1" max="365" inputmode="numeric" value="{{ $fieldValue('validity_days', 30) }}" aria-describedby="{{ $prefix }}-expiry" required>
                                    <span class="rr-control__affix">@lang('construction::lang.days')</span>
                                </div>
                                <div class="rr-field__hint" id="{{ $prefix }}-expiry" data-rr-expiry aria-live="polite"></div>
                            </div>

                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="{{ $prefix }}-customer">@lang('construction::lang.customer')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                <div class="rr-control">
                                    <select id="{{ $prefix }}-customer" class="rr-control__input rr-control__select" name="customer_id" required>
                                        <option value="">@lang('construction::lang.choose')</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" @selected((string) $fieldValue('customer_id') === (string) $customer->id)>{{ $customer->supplier_business_name ?: $customer->name }}</option>
                                        @endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>

                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="{{ $prefix }}-title">@lang('construction::lang.quote_title')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                <div class="rr-control">
                                    <input id="{{ $prefix }}-title" class="rr-control__input" name="title" maxlength="190" value="{{ $fieldValue('title') }}" placeholder="@lang('construction::lang.quote_title_placeholder')" required>
                                </div>
                            </div>

                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="{{ $prefix }}-notes">@lang('construction::lang.quote_notes') <span class="rr-field__opt">(@lang('construction::lang.optional') · <span id="{{ $prefix }}-notes-hint">@lang('construction::lang.quote_notes_hint')</span>)</span></label>
                                <div class="rr-control rr-control--area">
                                    <textarea id="{{ $prefix }}-notes" class="rr-control__input rr-control__input--short" name="notes" rows="2" maxlength="5000" aria-describedby="{{ $prefix }}-notes-hint">{{ $fieldValue('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => $isCreate ? 'plus' : 'check'])@lang($isCreate ? 'construction::lang.create_quote' : 'construction::lang.save')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                    @if($isCreate)
                        <span class="rr-modal__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.quote_items_after_create')</span>
                    @endif
                </footer>
            </form>
        </div>
    </div>
</div>

@once
@push('ct_quick_js')
<script>
(function () {
    var expiryTemplate = @json(__('construction::lang.quote_expires_on', ['date' => ':date']));
    function updateExpiry(form) {
        var hint = form && form.querySelector('[data-rr-expiry]');
        if (!hint) return;
        var date = form.elements.quote_date.value, days = parseInt(form.elements.validity_days.value, 10);
        if (!date || !(days > 0) || !window.moment) { hint.textContent = ''; return; }
        var format = typeof moment_date_format !== 'undefined' ? moment_date_format : 'DD-MM-YYYY';
        hint.textContent = expiryTemplate.replace(':date', moment(date, 'YYYY-MM-DD').add(days, 'days').format(format));
    }
    $(document).on('input change', '[data-rr-quote-form] [name="quote_date"], [data-rr-quote-form] [name="validity_days"]', function () { updateExpiry(this.form); });
    $(document).on('shown.bs.modal', '#ct-quote-create, #ct-quote-edit', function () {
        var form = this.querySelector('form');
        updateExpiry(form);
        var firstEmpty = Array.prototype.find.call(form.querySelectorAll('[required]'), function (field) { return !field.value; });
        (firstEmpty || form.elements.title).focus();
    });
    $(function () { $('[data-rr-open-on-load]').modal('show'); });
})();
</script>
@endpush
@endonce
