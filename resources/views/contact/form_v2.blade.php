{{--
    نافذة إضافة وتعديل المورد أو العميل بتصميم رقمي (/contacts/create و /contacts/{id}/edit).
    app.js هو اللي بيتحقق من الفورم وبيحفظه، فالـ ids دي لازم تفضل زي ما هي:
    form#contact_add_form أو form#contact_edit_form، #contact_type، #contact_id، #hidden_id، #mobile، #tax_number، #position، #shipping_address، #map.
    وبيبدّل الحقول بالـ classes: div.individual و div.business (فرد أو شركة)، div.customer_fields (العملاء بس)، div.export_div (التصدير).
    contact.create القديمة لسه بتتضمن في شاشات الكاشير والمشتريات القديمة (quick_add)، فالنافذة دي مش بديل ليها هناك.
--}}
@php
    $contact = $contact ?? null;
    $is_edit = ! empty($contact);
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $val = fn ($field) => $is_edit ? $contact->$field : null;
    $text = function ($name, $label, $attrs = []) use ($val) {
        $wrap = $attrs['wrap'] ?? '';
        $value = array_key_exists('value', $attrs) ? $attrs['value'] : $val($name);
        $style = ! empty($attrs['off']) ? ' style="display: none;"' : '';
        return '<div class="rr-field' . ($wrap ? ' ' . $wrap : '') . '"' . $style . '><label class="rr-field__label" for="' . e($name) . '">' . e($label) . '</label>'
            . Form::text($name, $value, array_merge(['class' => 'form-control', 'id' => $name, 'autocomplete' => 'off'], array_diff_key($attrs, ['wrap' => 1, 'value' => 1, 'off' => 1])))
            . '</div>';
    };
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';

    // فرد أو شركة: البيانات القديمة ممكن يكون نوعها فاضي، فبنستنتجه من اسم الشركة
    $kind = $is_edit ? $contact->contact_type : 'individual';
    if (! in_array($kind, ['individual', 'business'])) {
        $kind = $is_edit && ! empty($contact->supplier_business_name) && empty($contact->first_name) ? 'business' : 'individual';
    }
    $type_value = $is_edit ? $contact->type : ($selected_type ?? '');
    $form_id = $is_edit ? 'contact_edit_form' : 'contact_add_form';
    $url = $is_edit
        ? action([\App\Http\Controllers\ContactController::class, 'update'], [$contact->id])
        : action([\App\Http\Controllers\ContactController::class, 'store']);

    if ($is_edit) {
        $title = __('contact_form.title_edit');
        $desc = $contact->supplier_business_name ?: $contact->name;
    } else {
        $title = in_array($type_value, ['supplier', 'customer']) ? __('contact_form.title_new_' . $type_value) : __('contact_form.title_new');
        $desc = __('contact_form.desc_new');
    }

    $common_settings = session()->get('business.common_settings');
    $assigned = $is_edit ? $contact->userHavingAccess->pluck('id') : [];

    // الحقول المخصصة: اللي ليها اسم في الإعدادات، أو فيها قيمة محفوظة عشان ما تستخباش عن صاحبها
    $custom_labels = json_decode(session('business.custom_labels'), true) ?: [];
    $custom_fields = collect(range(1, 10))->mapWithKeys(function ($i) use ($custom_labels, $val) {
        $label = $custom_labels['contact']['custom_field_' . $i] ?? null;
        if (empty($label) && ! empty($val('custom_field' . $i))) {
            $label = $i <= 4 ? __('lang_v1.contact_custom_field' . $i) : __('lang_v1.custom_field', ['number' => $i]);
        }
        return ['custom_field' . $i => $label];
    })->filter();
    $shipping_fields = collect(range(1, 5))
        ->filter(fn ($i) => ! empty($custom_labels['shipping']['is_custom_field_' . $i . '_contact_default']) && ! empty($custom_labels['shipping']['custom_field_' . $i]))
        ->mapWithKeys(fn ($i) => ['shipping_custom_field_' . $i => $custom_labels['shipping']['custom_field_' . $i]]);
    $shipping_details = $is_edit ? ($contact->shipping_custom_field_details ?? []) : [];
    $has_export = ! empty($common_settings['is_enabled_export']);

    // الحقول قليلة الاستخدام مش بتظهر في الإضافة، وبتظهر في التعديل لو ليها قيمة محفوظة:
    // الاسم الكامل بيتبني من اللقب والاسم الأوسط عند الحفظ، فلو اختفوا وليهم قيمة هيتشالوا من الاسم
    $saved = fn ($field) => $is_edit && ! empty($contact->$field);
    $name_parts = collect(['prefix', 'middle_name', 'last_name'])->filter($saved);
    // من شاشة الموردين أو العملاء العنوان بيقول النوع، والحقل بيفضل موجود لأن app.js بيقرا منه
    $hide_type = ! $is_edit && in_array($type_value, ['supplier', 'customer']) && array_key_exists($type_value, $types);
    $dialog_id = 'rr_cf_' . uniqid();
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--lg rr-modal-dialog--compact rr-scope rr-exp rr-qp rr-qc rr-cf" role="document" id="{{ $dialog_id }}">
    <div class="modal-content rr-modal">
        {!! Form::open(['url' => $url, 'method' => $is_edit ? 'PUT' : 'POST', 'id' => $form_id]) !!}
        {!! Form::hidden('position', $val('position'), ['id' => 'position']) !!}
        @if($is_edit)
            <input type="hidden" id="hidden_id" value="{{ $contact->id }}">
        @endif

        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">{{ $title }}</h2>
                <p class="rr-modal__desc">{{ $desc }}</p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('contact_form.close')">{!! $icon('close') !!}</button>
        </header>

        <nav class="rr-tabs rr-tabs--inset rr-cf__tabs" aria-label="@lang('contact_form.sections')">
            <button type="button" class="rr-tab" data-rr-cf-tab="basic" aria-current="page">@lang('contact_form.tab_basic')</button>
            <button type="button" class="rr-tab" data-rr-cf-tab="account">@lang('contact_form.tab_account')</button>
            <button type="button" class="rr-tab" data-rr-cf-tab="address">@lang('contact_form.tab_address')</button>
            <button type="button" class="rr-tab" data-rr-cf-tab="more">@lang('contact_form.tab_more')</button>
        </nav>

        <div class="rr-modal__body">
            {{-- ═══ الأساسية ═══ --}}
            <section class="rr-cf__panel" data-rr-cf-panel="basic">
                <div class="rr-exp__grid">
                    <div class="rr-field" @if($hide_type) style="display: none;" @endif>
                        <label class="rr-field__label" for="contact_type">@lang('contact_form.type'){!! $req !!}</label>
                        {!! Form::select('type', $types, $type_value, ['class' => 'form-control', 'id' => 'contact_type', 'placeholder' => __('messages.please_select'), 'required']) !!}
                    </div>
                    <div class="rr-field">
                        <span class="rr-field__label" id="{{ $dialog_id }}_kind">@lang('contact_form.kind')</span>
                        <div class="rr-qc__kind" role="radiogroup" aria-labelledby="{{ $dialog_id }}_kind">
                            <label class="rr-qc__opt"><input type="radio" name="contact_type_radio" value="individual" @checked($kind == 'individual')>{!! $icon('user', 16) !!}<span>@lang('contact_form.individual')</span></label>
                            <label class="rr-qc__opt"><input type="radio" name="contact_type_radio" value="business" @checked($kind == 'business')>{!! $icon('building', 16) !!}<span>@lang('contact_form.business')</span></label>
                        </div>
                    </div>
                    <div class="rr-field rr-cf__span2 business" @if($kind != 'business') style="display: none;" @endif>
                        <label class="rr-field__label" for="supplier_business_name">@lang('contact_form.business_name'){!! $req !!}</label>
                        {!! Form::text('supplier_business_name', $val('supplier_business_name'), ['class' => 'form-control', 'id' => 'supplier_business_name', 'autocomplete' => 'off', 'required']) !!}
                    </div>
                    @if($name_parts->isEmpty())
                        <div class="rr-field rr-cf__span2 individual" @if($kind != 'individual') style="display: none;" @endif>
                            <label class="rr-field__label" for="first_name">@lang('contact_form.name'){!! $req !!}</label>
                            {!! Form::text('first_name', $val('first_name'), ['class' => 'form-control', 'id' => 'first_name', 'autocomplete' => 'off', 'required']) !!}
                        </div>
                    @else
                        <div class="rr-exp__grid rr-field--full rr-cf__names individual" @if($kind != 'individual') style="display: none;" @endif>
                            @if($name_parts->contains('prefix'))
                                {!! $text('prefix', __('contact_form.prefix')) !!}
                            @endif
                            <div class="rr-field">
                                <label class="rr-field__label" for="first_name">@lang('contact_form.first_name'){!! $req !!}</label>
                                {!! Form::text('first_name', $val('first_name'), ['class' => 'form-control', 'id' => 'first_name', 'autocomplete' => 'off', 'required']) !!}
                            </div>
                            @if($name_parts->contains('middle_name'))
                                {!! $text('middle_name', __('contact_form.middle_name')) !!}
                            @endif
                            @if($name_parts->contains('last_name'))
                                {!! $text('last_name', __('contact_form.last_name')) !!}
                            @endif
                        </div>
                    @endif
                    <div class="rr-field">
                        <label class="rr-field__label" for="mobile">@lang('contact_form.mobile'){!! $req !!}</label>
                        {!! Form::text('mobile', $val('mobile'), ['class' => 'form-control', 'id' => 'mobile', 'autocomplete' => 'off', 'dir' => 'ltr', 'inputmode' => 'tel', 'required']) !!}
                    </div>
                    @if($saved('alternate_number'))
                        {!! $text('alternate_number', __('contact_form.alternate'), ['dir' => 'ltr', 'inputmode' => 'tel']) !!}
                    @endif
                    @if($saved('landline'))
                        {!! $text('landline', __('contact_form.landline'), ['dir' => 'ltr', 'inputmode' => 'tel']) !!}
                    @endif
                    <div class="rr-field">
                        <label class="rr-field__label" for="email">@lang('contact_form.email')</label>
                        {!! Form::email('email', $val('email'), ['class' => 'form-control', 'id' => 'email', 'autocomplete' => 'off', 'dir' => 'ltr']) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="tax_number">@lang('contact_form.tax_number')</label>
                        {!! Form::text('tax_number', $val('tax_number'), ['class' => 'form-control', 'id' => 'tax_number', 'autocomplete' => 'off', 'dir' => 'ltr']) !!}
                        <div class="rr-field__hint business" @if($kind != 'business') style="display: none;" @endif>@lang('contact_form.tax_hint')</div>
                    </div>
                    @if(count($customer_groups) > 1 || $saved('customer_group_id'))
                        <div class="rr-field customer_fields">
                            <label class="rr-field__label" for="customer_group_id">@lang('contact_form.group')</label>
                            {!! Form::select('customer_group_id', $customer_groups, $val('customer_group_id') ?? '', ['class' => 'form-control', 'id' => 'customer_group_id']) !!}
                        </div>
                    @endif
                    @if($saved('dob'))
                        <div class="rr-field individual" @if($kind != 'individual') style="display: none;" @endif>
                            <label class="rr-field__label" for="{{ $dialog_id }}_dob">@lang('contact_form.dob')</label>
                            {!! Form::text('dob', @format_date($contact->dob), ['class' => 'form-control dob-date-picker', 'id' => $dialog_id . '_dob', 'readonly']) !!}
                        </div>
                    @endif
                </div>
            </section>

            {{-- ═══ الحساب والسداد ═══ --}}
            <section class="rr-cf__panel" data-rr-cf-panel="account" hidden>
                <div class="rr-exp__grid">
                    <div class="rr-field opening_balance">
                        <label class="rr-field__label" for="opening_balance">@lang('contact_form.opening_balance')</label>
                        {!! Form::text('opening_balance', $is_edit ? $opening_balance : 0, ['class' => 'form-control input_number', 'id' => 'opening_balance', 'autocomplete' => 'off', 'inputmode' => 'decimal', 'dir' => 'ltr']) !!}
                        @if($is_edit)
                            <span class="rr-field__hint">@lang('contact_form.opening_hint')</span>
                        @endif
                    </div>
                    <div class="rr-field pay_term">
                        <label class="rr-field__label" for="pay_term_number">@lang('contact_form.pay_term')</label>
                        <div class="rr-qp__pair">
                            {!! Form::number('pay_term_number', $val('pay_term_number'), ['class' => 'form-control', 'id' => 'pay_term_number', 'min' => 0]) !!}
                            {!! Form::select('pay_term_type', ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months')], $val('pay_term_type') ?: 'days', ['class' => 'form-control', 'aria-label' => __('contact_form.pay_term')]) !!}
                        </div>
                        <span class="rr-field__hint">@lang('contact_form.pay_term_hint')</span>
                    </div>
                    <div class="rr-field customer_fields">
                        <label class="rr-field__label" for="credit_limit">@lang('contact_form.credit_limit')</label>
                        {!! Form::text('credit_limit', $is_edit ? (! is_null($contact->credit_limit) ? @num_format($contact->credit_limit) : null) : ($common_settings['default_credit_limit'] ?? null), ['class' => 'form-control input_number', 'id' => 'credit_limit', 'autocomplete' => 'off', 'inputmode' => 'decimal', 'dir' => 'ltr']) !!}
                        <span class="rr-field__hint">@lang('contact_form.credit_hint')</span>
                    </div>
                </div>
            </section>

            {{-- ═══ العنوان ═══ --}}
            <section class="rr-cf__panel" data-rr-cf-panel="address" hidden>
                <div class="rr-exp__grid">
                    {!! $text('building_number', __('contact_form.building_number'), ['dir' => 'ltr']) !!}
                    {!! $text('street_name', __('contact_form.street_name')) !!}
                    {{-- الحي في حقل state: فاتورة زاتكا بتقراه كـ SubDivisionName --}}
                    {!! $text('state', __('contact_form.district')) !!}
                    {!! $text('city', __('contact_form.city')) !!}
                    {!! $text('zip_code', __('contact_form.zip_code'), ['dir' => 'ltr']) !!}
                    {!! $text('additional_number', __('contact_form.additional_number'), ['dir' => 'ltr']) !!}
                    {!! $text('country', __('contact_form.country')) !!}
                    {!! $text('address_line_1', __('contact_form.address_line_1'), ['wrap' => 'rr-cf__span2']) !!}
                    @if($saved('address_line_2'))
                        {!! $text('address_line_2', __('contact_form.address_line_2'), ['wrap' => 'rr-field--full']) !!}
                    @endif
                    @if($saved('land_mark'))
                        {!! $text('land_mark', __('contact_form.land_mark'), ['wrap' => 'rr-field--full']) !!}
                    @endif
                </div>
                <div class="rr-exp__grid shipping_addr_div">
                    @if($saved('shipping_address'))
                        <div class="rr-field rr-field--full">
                            <label class="rr-field__label" for="shipping_address">@lang('contact_form.shipping_address')</label>
                            {!! Form::text('shipping_address', $val('shipping_address'), ['class' => 'form-control', 'id' => 'shipping_address', 'autocomplete' => 'off']) !!}
                        </div>
                        <div class="rr-field--full" id="map"></div>
                    @endif
                    @foreach($shipping_fields as $field => $label)
                        <div class="rr-field">
                            <label class="rr-field__label" for="{{ $field }}">{{ $label }}</label>
                            {!! Form::text('shipping_custom_field_details[' . $field . ']', $shipping_details[$field] ?? null, ['class' => 'form-control', 'id' => $field, 'autocomplete' => 'off']) !!}
                        </div>
                    @endforeach
                </div>
                <div class="rr-exp__legacy">
                    @includeIf('bostashipping::contacts.address_fields', $is_edit ? ['contact' => $contact] : [])
                </div>
            </section>

            {{-- ═══ بيانات أخرى ═══ --}}
            <section class="rr-cf__panel" data-rr-cf-panel="more" hidden>
                <div class="rr-exp__grid">
                    <div class="rr-field">
                        <label class="rr-field__label" for="contact_id">@lang('contact_form.code')</label>
                        {!! Form::text('contact_id', $val('contact_id'), ['class' => 'form-control', 'id' => 'contact_id', 'autocomplete' => 'off', 'dir' => 'ltr']) !!}
                        <span class="rr-field__hint">@lang('contact_form.code_hint')</span>
                    </div>
                    @if(config('constants.enable_contact_assign'))
                        <div class="rr-field rr-cf__span2">
                            <label class="rr-field__label" for="assigned_to_users">@lang('contact_form.assigned_to')</label>
                            {!! Form::select('assigned_to_users[]', $users ?? [], $assigned, ['class' => 'form-control select2', 'id' => 'assigned_to_users', 'multiple', 'style' => 'width: 100%;']) !!}
                        </div>
                    @endif
                    @foreach($custom_fields as $field => $label)
                        {!! $text($field, $label) !!}
                    @endforeach
                </div>
                @if($has_export)
                    <label class="rr-toggle">
                        <input type="checkbox" name="is_export" class="rr-toggle__input" id="is_customer_export" @checked($is_edit && ! empty($contact->is_export))>
                        <span>@lang('lang_v1.is_export')</span>
                    </label>
                    <div class="rr-exp__grid">
                        @for($i = 1; $i <= 6; $i++)
                            {!! $text('export_custom_field_' . $i, __('lang_v1.export_custom_field' . $i), ['wrap' => 'export_div', 'off' => true]) !!}
                        @endfor
                    </div>
                @endif
                @if(! empty($module_form_parts))
                    <div class="rr-exp__legacy">
                        @include('layouts.partials.module_form_part')
                    </div>
                @endif
            </section>
        </div>

        <footer class="rr-modal__foot">
            <button type="submit" class="rr-btn rr-btn--primary">{!! $icon('check', 16) !!}@lang('contact_form.save')</button>
            <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('contact_form.cancel')</button>
        </footer>
        {!! Form::close() !!}
    </div>
</div>
<script>
    (function() {
        var $dialog = $('#{{ $dialog_id }}'), $modal = $dialog.closest('.modal');

        function openTab(tab) {
            $dialog.find('[data-rr-cf-tab]').removeAttr('aria-current').filter('[data-rr-cf-tab="' + tab + '"]').attr('aria-current', 'page');
            $dialog.find('[data-rr-cf-panel]').each(function() {
                $(this).prop('hidden', $(this).data('rr-cf-panel') !== tab);
            });
        }
        $dialog.on('click', '[data-rr-cf-tab]', function() {
            openTab($(this).data('rr-cf-tab'));
        });

        // التحقق بيتجاهل الحقول المخفية، فقبل الحفظ بنفحص كل تاب ونقف عند أول تاب فيه خطأ
        $dialog.on('click', 'button[type="submit"]', function() {
            var current = $dialog.find('[data-rr-cf-tab][aria-current]').data('rr-cf-tab'), valid = true;
            $dialog.find('[data-rr-cf-tab]').each(function() {
                openTab($(this).data('rr-cf-tab'));
                return valid = $dialog.find('form').valid();
            });
            if (valid) {
                openTab(current);
            }
        });

        function paintKind() {
            $dialog.find('.rr-qc__opt').each(function() { $(this).toggleClass('is-on', $(this).find('input').is(':checked')); });
        }
        // app.js هو اللي بيبدّل حقول الفرد والشركة، فبننقل المؤشر بعد ما يخلص
        $dialog.on('change', 'input[name="contact_type_radio"]', function() {
            var target = this.value === 'business' ? '#supplier_business_name' : '#first_name';
            paintKind();
            setTimeout(function() { $dialog.find(target).trigger('focus'); }, 0);
        });
        paintKind();

        $modal.one('shown.bs.modal', function() {
            var kind = $dialog.find('input[name="contact_type_radio"]:checked').val();
            $dialog.find(kind === 'business' ? '#supplier_business_name' : '#first_name').trigger('focus');
        });
    })();
</script>
@include('contact.partials.zatca_address_rule')
