{{--
    نافذة "عميل جديد" في شاشة الكاشير الجديدة بتصميم رقمي (create_v2 بيحطها في .contact_modal بدل contact.create).
    قدام الكاشير: فرد ولا شركة، الاسم، الجوال، والرقم الضريبي للشركات (فاتورة ضريبية B2B).
    الباقي (كود العميل، المجموعة، التواصل، الرصيد الافتتاحي، الأجل، حد الائتمان، العنوان الوطني، الحقول المخصصة) جوه "بيانات إضافية".
    pos.js بيتحقق من الفورم (form#quick_add_contact، #contact_id، #mobile، #tax_number) وبيحفظه ويختار العميل في الفاتورة.
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $dialog_id = 'rr_qc_' . uniqid();
    $common_settings = session()->get('business.common_settings');
    $default_credit_limit = !empty($common_settings['default_credit_limit']) ? $common_settings['default_credit_limit'] : null;
    $custom_labels = json_decode(session('business.custom_labels'), true);
    // الحقول المخصصة اللي ليها اسم في الإعدادات بس
    $custom_fields = collect(range(1, 10))
        ->mapWithKeys(fn ($i) => ['custom_field' . $i => $custom_labels['contact']['custom_field_' . $i] ?? null])
        ->filter();
    $shipping_fields = collect(range(1, 5))
        ->filter(fn ($i) => !empty($custom_labels['shipping']['is_custom_field_' . $i . '_contact_default']) && !empty($custom_labels['shipping']['custom_field_' . $i]))
        ->mapWithKeys(fn ($i) => ['shipping_custom_field_' . $i => $custom_labels['shipping']['custom_field_' . $i]]);
    $groups = collect($customer_groups ?? [])->filter(fn ($v, $k) => $k !== '' && $k !== null);
    $text = function ($name, $label, $attrs = []) {
        $id = $attrs['id'] ?? $name;
        return '<div class="rr-field' . (isset($attrs['wrap']) ? ' ' . $attrs['wrap'] : '') . '"' . (!empty($attrs['wrap_hidden']) ? ' hidden' : '') . '><label class="rr-field__label" for="' . e($id) . '">' . e($label) . '</label>'
            . Form::text($name, $attrs['value'] ?? null, array_merge(['class' => 'form-control', 'id' => $id, 'autocomplete' => 'off'], array_diff_key($attrs, ['wrap' => 1, 'value' => 1, 'wrap_hidden' => 1])))
            . '</div>';
    };
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact rr-scope rr-exp rr-qp rr-qc" role="document" id="{{ $dialog_id }}">
    <div class="modal-content rr-modal">
        {!! Form::open(['url' => action([\App\Http\Controllers\ContactController::class, 'store']), 'method' => 'post', 'id' => 'quick_add_contact']) !!}
        {{-- pos.js بيكتب "customer" هنا ويخفي .contact_type_div --}}
        <input type="hidden" name="type" id="contact_type" value="customer">
        {!! Form::hidden('position', null, ['id' => 'position']) !!}
        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">@lang('contact_quick.title')</h2>
                <p class="rr-modal__desc">@lang('contact_quick.desc')</p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
        </header>

        <div class="rr-modal__body">
            <div class="rr-qc__kind" role="radiogroup" aria-label="@lang('contact_quick.kind')">
                <label class="rr-qc__opt"><input type="radio" name="contact_type_radio" value="individual" checked>{!! $icon('user', 16) !!}<span>@lang('contact_quick.individual')</span></label>
                <label class="rr-qc__opt"><input type="radio" name="contact_type_radio" value="business">{!! $icon('building', 16) !!}<span>@lang('contact_quick.business')</span></label>
            </div>

            <div class="rr-exp__grid">
                <div class="rr-field rr-field--full" data-rr-qc-for="individual">
                    <label class="rr-field__label" for="first_name">@lang('contact_quick.name')<span class="rr-field__req" aria-hidden="true">*</span></label>
                    {!! Form::text('first_name', null, ['class' => 'form-control', 'required', 'id' => 'first_name', 'autocomplete' => 'off', 'placeholder' => __('contact_quick.name_placeholder')]) !!}
                </div>
                <div class="rr-field rr-field--full" data-rr-qc-for="business" hidden>
                    <label class="rr-field__label" for="supplier_business_name">@lang('contact_quick.business_name')<span class="rr-field__req" aria-hidden="true">*</span></label>
                    {!! Form::text('supplier_business_name', null, ['class' => 'form-control', 'required', 'id' => 'supplier_business_name', 'autocomplete' => 'off', 'disabled']) !!}
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="mobile">@lang('contact_quick.mobile')<span class="rr-field__req" aria-hidden="true">*</span></label>
                    {!! Form::text('mobile', null, ['class' => 'form-control', 'required', 'id' => 'mobile', 'autocomplete' => 'off', 'dir' => 'ltr', 'inputmode' => 'tel', 'placeholder' => '05xxxxxxxx']) !!}
                </div>
                <div class="rr-field" data-rr-qc-tax>
                    <label class="rr-field__label" for="tax_number">@lang('contact_quick.tax_number')</label>
                    {!! Form::text('tax_number', null, ['class' => 'form-control', 'id' => 'tax_number', 'autocomplete' => 'off', 'dir' => 'ltr', 'inputmode' => 'numeric']) !!}
                    <span class="rr-field__hint" data-rr-qc-for="business" hidden>@lang('contact_quick.tax_hint')</span>
                </div>
                @if ($groups->isNotEmpty())
                    <div class="rr-field">
                        <label class="rr-field__label" for="customer_group_id">@lang('contact_quick.group')</label>
                        {!! Form::select('customer_group_id', $customer_groups, '', ['class' => 'form-control', 'id' => 'customer_group_id']) !!}
                    </div>
                @endif
            </div>

            {{-- ═══ بيانات إضافية (مطوية) ═══ --}}
            <details class="rr-exp__box rr-exp__more" data-rr-qc-more>
                <summary class="rr-exp__h">{!! $icon('sliders', 16) !!}@lang('contact_quick.more'){!! $icon('chevron-down', 16) !!}</summary>
                <div class="rr-exp__grid">
                    <div class="rr-field">
                        <label class="rr-field__label" for="contact_id">@lang('contact_quick.code')</label>
                        {!! Form::text('contact_id', null, ['class' => 'form-control', 'id' => 'contact_id', 'autocomplete' => 'off', 'dir' => 'ltr']) !!}
                        <span class="rr-field__hint">@lang('contact_quick.code_hint')</span>
                    </div>
                    {!! $text('email', __('contact_quick.email'), ['dir' => 'ltr', 'inputmode' => 'email']) !!}
                    {!! $text('alternate_number', __('contact_quick.alternate'), ['dir' => 'ltr', 'inputmode' => 'tel']) !!}
                    {!! $text('landline', __('contact_quick.landline'), ['dir' => 'ltr', 'inputmode' => 'tel']) !!}
                    <div class="rr-field" data-rr-qc-for="individual">
                        <label class="rr-field__label" for="{{ $dialog_id }}_dob">@lang('contact_quick.dob')</label>
                        {!! Form::text('dob', null, ['class' => 'form-control dob-date-picker', 'readonly', 'id' => $dialog_id . '_dob']) !!}
                    </div>
                    @if (config('constants.enable_contact_assign'))
                        <div class="rr-field rr-field--full">
                            <label class="rr-field__label" for="assigned_to_users">@lang('contact_quick.assigned_to')</label>
                            {!! Form::select('assigned_to_users[]', $users ?? [], null, ['class' => 'form-control select2', 'id' => 'assigned_to_users', 'multiple', 'style' => 'width:100%']) !!}
                        </div>
                    @endif

                    <h4 class="rr-qc__sub rr-field--full">@lang('contact_quick.account')</h4>
                    {!! $text('opening_balance', __('contact_quick.opening_balance'), ['value' => 0, 'class' => 'form-control input_number', 'inputmode' => 'decimal']) !!}
                    <div class="rr-field">
                        <label class="rr-field__label" for="credit_limit">@lang('contact_quick.credit_limit')</label>
                        {!! Form::text('credit_limit', $default_credit_limit, ['class' => 'form-control input_number', 'id' => 'credit_limit', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                        <span class="rr-field__hint">@lang('contact_quick.credit_hint')</span>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="{{ $dialog_id }}_term">@lang('contact_quick.pay_term')</label>
                        <div class="rr-qp__pair">
                            {!! Form::number('pay_term_number', null, ['class' => 'form-control', 'id' => $dialog_id . '_term', 'min' => 0]) !!}
                            {!! Form::select('pay_term_type', ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months')], 'days', ['class' => 'form-control', 'aria-label' => __('contact_quick.pay_term')]) !!}
                        </div>
                    </div>

                    <h4 class="rr-qc__sub rr-field--full">@lang('contact_quick.address')</h4>
                    {!! $text('building_number', __('contact_quick.building_number'), ['dir' => 'ltr']) !!}
                    {!! $text('street_name', __('contact_quick.street_name')) !!}
                    {{-- الحي في حقل state: فاتورة زاتكا بتقراه كـ SubDivisionName --}}
                    {!! $text('state', __('contact_quick.district')) !!}
                    {!! $text('city', __('contact_quick.city')) !!}
                    {!! $text('zip_code', __('contact_quick.zip_code'), ['dir' => 'ltr']) !!}
                    {!! $text('additional_number', __('contact_quick.additional_number'), ['dir' => 'ltr']) !!}
                    {!! $text('country', __('contact_quick.country')) !!}
                    {!! $text('address_line_1', __('contact_quick.address_line_1'), ['wrap' => 'rr-field--full']) !!}
                    {!! $text('address_line_2', __('contact_quick.address_line_2'), ['wrap' => 'rr-field--full']) !!}
                    {!! $text('land_mark', __('contact_quick.land_mark'), ['wrap' => 'rr-field--full']) !!}
                    {!! $text('shipping_address', __('contact_quick.shipping_address'), ['id' => $dialog_id . '_shipping', 'wrap' => 'rr-field--full']) !!}
                    @foreach ($shipping_fields as $field => $label)
                        {!! $text('shipping_custom_field_details[' . $field . ']', $label, ['id' => $dialog_id . '_' . $field]) !!}
                    @endforeach
                    <div class="rr-field--full rr-exp__legacy">
                        @includeIf('bostashipping::contacts.address_fields')
                    </div>

                    @if ($custom_fields->isNotEmpty())
                        <h4 class="rr-qc__sub rr-field--full">@lang('contact_quick.custom')</h4>
                        @foreach ($custom_fields as $field => $label)
                            {!! $text($field, $label) !!}
                        @endforeach
                    @endif

                    @if (!empty($common_settings['is_enabled_export']))
                        <label class="rr-toggle rr-field--full">
                            <input type="checkbox" name="is_export" class="rr-toggle__input" id="is_customer_export" data-rr-qc-export>
                            <span>@lang('lang_v1.is_export')</span>
                        </label>
                        @for ($i = 1; $i <= 6; $i++)
                            {!! $text('export_custom_field_' . $i, __('lang_v1.export_custom_field' . $i), ['wrap' => 'export_div', 'wrap_hidden' => true]) !!}
                        @endfor
                    @endif
                </div>
                @if (!empty($module_form_parts))
                    <div class="rr-exp__legacy">
                        @include('layouts.partials.module_form_part')
                    </div>
                @endif
            </details>
        </div>

        <footer class="rr-modal__foot">
            <button type="submit" class="rr-btn rr-btn--primary">{!! $icon('check', 16) !!}@lang('contact_quick.save')</button>
            <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.cancel')</button>
        </footer>
        {!! Form::close() !!}
    </div>
</div>
<script>
    // النافذة دي بتتحمّل مع الصفحة قبل jQuery: الكود بيستنى لحد ما الصفحة تخلص
    document.addEventListener('DOMContentLoaded', function() {
        var $dialog = $('#{{ $dialog_id }}'), $form = $dialog.find('form'), $modal = $dialog.closest('.modal');

        // فرد: الاسم مطلوب؛ شركة: اسم الشركة مطلوب والرقم الضريبي قدامه (الحقل المخفي مقفول عشان ميتبعتش ولا يتحقق منه)
        function paintKind() {
            var kind = $form.find('input[name="contact_type_radio"]:checked').val() || 'individual';
            $dialog.find('[data-rr-qc-for]').each(function() {
                var on = $(this).data('rr-qc-for') === kind;
                $(this).prop('hidden', !on).find('input, select').prop('disabled', !on);
            });
            $dialog.find('.rr-qc__opt').each(function() { $(this).toggleClass('is-on', $(this).find('input').is(':checked')); });
        }
        $form.on('change', 'input[name="contact_type_radio"]', function() {
            paintKind();
            $dialog.find(this.value === 'business' ? '#supplier_business_name' : '#first_name').trigger('focus');
        });
        // pos.js بيعمل reset للفورم بعد ما النافذة تتقفل: نرجّع الشكل على "فرد"
        $form.on('reset', function() { setTimeout(function() { paintKind(); $dialog.find('[data-rr-qc-more]').prop('open', false); }, 0); });
        paintKind();

        $dialog.find('[data-rr-qc-export]').on('change', function() {
            $dialog.find('.export_div').prop('hidden', !this.checked);
        });

        // زرار "+" أو "إضافة عميل" من البحث: النص اللي اتكتب يروح للجوال لو أرقام، وللاسم لو غير كده
        $(document).on('click', '.add_new_customer', function() {
            var typed = String($(this).data('name') || '').trim();
            if (typed) $dialog.find(/^[\d+\s-]+$/.test(typed) ? '#mobile' : '#first_name').val(typed);
        });
        $modal.on('shown.bs.modal', function() {
            $dialog.find('select.select2').not('.select2-hidden-accessible').select2({dropdownParent: $modal});
            var $first = $dialog.find('#first_name');
            $dialog.find($first.val() && !$dialog.find('#mobile').val() ? '#mobile' : '#first_name').trigger('focus');
        });

        // "بيانات إضافية" بتتفتح لو فيها حقل غلط
        $form.on('invalid-form.validate', function(e, validator) {
            $.each(validator.errorList, function(i, err) { $(err.element).closest('details').prop('open', true); });
        });
    });
</script>

@include('contact.partials.zatca_address_rule')
