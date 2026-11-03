{{--
    نافذة "صنف جديد" من شاشة الكاشير الجديدة بتصميم رقمي (create_v2 بيحمّلها من /products/quick_add?v2=1 في .quick_add_product_modal؛ pos.js بيزوّد "?product_for=pos" على الرابط عشان كده آخره "&_").
    قدام الكاشير: الاسم والباركود والوحدة والتصنيف، والأسعار، والكمية الحالية في الفرع.
    الباقي (نوع الباركود، الوحدات الفرعية، الفروع، الوزن، الضمان، الصلاحية، السيريال، الحقول المخصصة، الوصف) جوه "بيانات إضافية".
    نفس أسماء الحقول اللي saveQuickProduct بيستناها، ونفس الـ ids اللي product.js و app.js بيحسبوا بيها الأسعار والتصنيفات الفرعية.
    نافذة الشراء وشاشة الكاشير القديمة لسه بيستخدموا quick_add_product.
--}}
@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $dialog_id = 'rr_qp_' . uniqid();
    // لو الأسعار مقفولة في إعدادات النشاط: القيم صفر ومخفية (زي النافذة القديمة)
    $price_enabled = (bool) session('business.enable_price_tax');
    $price_default = $price_enabled ? null : 0;
    $default_location = count($business_locations) == 1 ? array_key_first($business_locations->toArray()) : null;
    $show_sub_category = session('business.enable_category') && session('business.enable_sub_category');
    $show_sub_units = (bool) session('business.enable_sub_units');
    $expiry_enabled = session('business.enable_product_expiry');
    $expiry_hidden = session('business.expiry_type') == 'add_expiry';
    // الحقول المخصصة اللي ليها اسم في الإعدادات بس (الباقي "حقل مخصص 1..." مالوش لازمة للكاشير)
    $custom_labels = json_decode(session('business.custom_labels'), true);
    $custom_fields = collect(range(1, 4))
        ->mapWithKeys(fn ($i) => ['product_custom_field' . $i => $custom_labels['product']['custom_field_' . $i] ?? null])
        ->filter();
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact rr-modal-dialog--lg rr-scope rr-exp rr-qp" role="document" id="{{ $dialog_id }}">
    <div class="modal-content rr-modal">
        {!! Form::open(['url' => action([\App\Http\Controllers\ProductController::class, 'saveQuickProduct']), 'method' => 'post', 'id' => 'quick_add_product_form']) !!}
        <input type="hidden" name="type" id="type" value="single">
        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">@lang('product_quick.title')</h2>
                <p class="rr-modal__desc">@lang('product_quick.desc')</p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
        </header>

        <div class="rr-modal__body">
            {{-- ═══ بيانات الصنف ═══ --}}
            <div class="rr-qp__grid">
                <div class="rr-field rr-qp__wide">
                    <label class="rr-field__label" for="name">@lang('product_quick.name')<span class="rr-field__req" aria-hidden="true">*</span></label>
                    {!! Form::text('name', $product_name, ['class' => 'form-control', 'required', 'id' => 'name', 'autocomplete' => 'off', 'placeholder' => __('product_quick.name_placeholder')]) !!}
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="sku">@lang('product_quick.sku')</label>
                    {!! Form::text('sku', null, ['class' => 'form-control', 'id' => 'sku', 'autocomplete' => 'off', 'dir' => 'ltr', 'placeholder' => __('product_quick.sku_placeholder'), 'title' => __('product_quick.sku_hint')]) !!}
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="unit_id">@lang('product_quick.unit')<span class="rr-field__req" aria-hidden="true">*</span></label>
                    {!! Form::select('unit_id', $units, collect($units)->keys()->first(fn ($k) => $k !== ''), ['class' => 'form-control', 'required', 'id' => 'unit_id']) !!}
                </div>
                <div class="rr-field">
                    <label class="rr-field__label" for="category_id">@lang('product_quick.category')</label>
                    {!! Form::select('category_id', $categories, null, ['placeholder' => __('product_quick.none'), 'class' => 'form-control', 'id' => 'category_id']) !!}
                </div>
                @if ($show_sub_category)
                    <div class="rr-field" data-rr-qp-sub hidden>
                        <label class="rr-field__label" for="sub_category_id">@lang('product_quick.sub_category')</label>
                        {!! Form::select('sub_category_id', [], null, ['placeholder' => __('product_quick.none'), 'class' => 'form-control', 'id' => 'sub_category_id']) !!}
                    </div>
                @endif
                <div class="rr-field">
                    <label class="rr-field__label" for="brand_id">@lang('product_quick.brand')</label>
                    {!! Form::select('brand_id', $brands, null, ['placeholder' => __('product_quick.none'), 'class' => 'form-control', 'id' => 'brand_id']) !!}
                </div>
            </div>

            {{-- ═══ الأسعار (product.js بيحسب الربح والضريبة من الحقول دي) ═══ --}}
            <section class="rr-exp__box add-product-price-table" aria-labelledby="{{ $dialog_id }}_price" @unless ($price_enabled) hidden @endunless>
                <h3 class="rr-exp__h" id="{{ $dialog_id }}_price">{!! $icon('tag', 16) !!}@lang('product_quick.prices')</h3>
                <div class="rr-qp__grid rr-qp__grid--prices">
                    <div class="rr-field">
                        <label class="rr-field__label" for="single_dpp" title="@lang('product_quick.before_tax')">@lang('product_quick.purchase_price')<span class="rr-field__req" aria-hidden="true">*</span></label>
                        {!! Form::text('single_dpp', $price_default, ['class' => 'form-control dpp input_number', 'required', 'id' => 'single_dpp', 'placeholder' => '0', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                        {!! Form::text('single_dpp_inc_tax', $price_default, ['class' => 'hide dpp_inc_tax input_number', 'id' => 'single_dpp_inc_tax', 'tabindex' => '-1', 'aria-hidden' => 'true']) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="profit_percent">@lang('product_quick.profit')</label>
                        {!! Form::text('profit_percent', @num_format($default_profit_percent), ['class' => 'form-control input_number', 'required', 'id' => 'profit_percent', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                    </div>
                    <div class="rr-field rr-qp__sell">
                        <label class="rr-field__label" for="single_dsp">@lang('product_quick.sell_price')<span class="rr-field__req" aria-hidden="true">*</span></label>
                        {!! Form::text('single_dsp', $price_default, ['class' => 'form-control dsp input_number', 'required', 'id' => 'single_dsp', 'placeholder' => '0', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                        {!! Form::text('single_dsp_inc_tax', $price_default, ['class' => 'form-control hide input_number', 'required', 'id' => 'single_dsp_inc_tax', 'placeholder' => '0', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="tax">@lang('product_quick.tax')</label>
                        {!! Form::select('tax', $taxes, null, ['class' => 'form-control', 'id' => 'tax'], $tax_attributes) !!}
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="tax_type">@lang('product_quick.tax_type')</label>
                        {!! Form::select('tax_type', ['exclusive' => __('product_quick.exclusive'), 'inclusive' => __('product_quick.inclusive')], 'exclusive', ['class' => 'form-control', 'required', 'id' => 'tax_type']) !!}
                    </div>
                </div>
            </section>

            {{-- ═══ المخزون ═══ --}}
            <section class="rr-exp__box" aria-labelledby="{{ $dialog_id }}_stock">
                <div class="rr-qp__head">
                    <h3 class="rr-exp__h" id="{{ $dialog_id }}_stock">{!! $icon('box', 16) !!}@lang('product_quick.stock')</h3>
                    <label class="rr-toggle">
                        <input type="checkbox" name="enable_stock" value="1" id="enable_stock" class="rr-toggle__input" checked>
                        <span>@lang('product_quick.manage_stock')</span>
                    </label>
                </div>
                <div class="rr-qp__stock" data-rr-qp-stock>
                    <div class="rr-qp__grid">
                        @foreach ($locations as $key => $value)
                            <div class="rr-field" data-rr-qp-os>
                                <label class="rr-field__label" for="{{ $dialog_id }}_qty_{{ $key }}">
                                    @lang('product_quick.opening_qty')@if (count($locations) > 1)<span class="rr-qp__loc">{{ $value }}</span>@endif
                                </label>
                                {!! Form::text('opening_stock[' . $key . '][quantity]', 0, ['class' => 'form-control input_number purchase_quantity', 'required', 'id' => $dialog_id . '_qty_' . $key, 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                                {{-- تكلفة الرصيد الافتتاحي = سعر الشراء --}}
                                <input type="hidden" name="opening_stock[{{ $key }}][purchase_price]" class="unit_price" value="{{ $price_default }}">
                                @if ($enable_expiry)
                                    {!! Form::text('opening_stock[' . $key . '][exp_date]', null, ['class' => 'form-control os_exp_date', 'readonly', 'placeholder' => __('product_quick.expiry_date'), 'aria-label' => __('product_quick.expiry_date')]) !!}
                                @endif
                                @if ($enable_lot)
                                    {!! Form::text('opening_stock[' . $key . '][lot_number]', null, ['class' => 'form-control', 'placeholder' => __('product_quick.lot'), 'aria-label' => __('product_quick.lot')]) !!}
                                @endif
                            </div>
                        @endforeach
                        <div class="rr-field" id="alert_quantity_div">
                            <label class="rr-field__label" for="alert_quantity">@lang('product_quick.alert_qty')</label>
                            {!! Form::text('alert_quantity', null, ['class' => 'form-control input_number', 'id' => 'alert_quantity', 'placeholder' => '0', 'autocomplete' => 'off', 'inputmode' => 'decimal']) !!}
                        </div>
                    </div>
                    <span class="rr-field__hint">@lang('product_quick.opening_hint')</span>
                </div>
                <p class="rr-field__hint" data-rr-qp-nostock hidden>@lang('product_quick.no_stock_hint')</p>
            </section>

            {{-- ═══ بيانات إضافية (مطوية) ═══ --}}
            <details class="rr-exp__box rr-exp__more" data-rr-qp-more>
                <summary class="rr-exp__h">{!! $icon('sliders', 16) !!}@lang('product_quick.more'){!! $icon('chevron-down', 16) !!}</summary>
                <div class="rr-qp__grid">
                    <div class="rr-field">
                        <label class="rr-field__label" for="barcode_type">@lang('product_quick.barcode_type')</label>
                        {!! Form::select('barcode_type', $barcode_types, 'C128', ['class' => 'form-control', 'required', 'id' => 'barcode_type']) !!}
                    </div>
                    <div class="rr-field rr-qp__wide">
                        <label class="rr-field__label" for="product_locations">@lang('product_quick.locations')</label>
                        {!! Form::select('product_locations[]', $business_locations, $default_location, ['class' => 'form-control select2', 'multiple', 'id' => 'product_locations', 'style' => 'width:100%']) !!}
                        <span class="rr-field__hint">@lang('product_quick.locations_hint')</span>
                    </div>
                    @if ($show_sub_units)
                        <div class="rr-field rr-qp__wide">
                            <label class="rr-field__label" for="sub_unit_ids">@lang('product_quick.sub_units')</label>
                            {!! Form::select('sub_unit_ids[]', [], null, ['class' => 'form-control select2', 'multiple', 'id' => 'sub_unit_ids', 'style' => 'width:100%']) !!}
                        </div>
                    @endif
                    <div class="rr-field">
                        <label class="rr-field__label" for="weight">@lang('product_quick.weight')</label>
                        {!! Form::text('weight', null, ['class' => 'form-control', 'id' => 'weight']) !!}
                    </div>
                    @if (!empty($common_settings['enable_product_warranty']))
                        <div class="rr-field">
                            <label class="rr-field__label" for="warranty_id">@lang('product_quick.warranty')</label>
                            {!! Form::select('warranty_id', $warranties, null, ['class' => 'form-control', 'placeholder' => __('product_quick.none'), 'id' => 'warranty_id']) !!}
                        </div>
                    @endif
                    @if ($expiry_enabled)
                        <div class="rr-field" @if ($expiry_hidden) hidden @endif>
                            <label class="rr-field__label" for="expiry_period">@lang('product_quick.expires_in')</label>
                            <div class="rr-qp__pair">
                                {!! Form::text('expiry_period', $expiry_hidden ? 12 : null, ['class' => 'form-control input_number', 'id' => 'expiry_period', 'placeholder' => '0']) !!}
                                {!! Form::select('expiry_period_type', ['months' => __('product.months'), 'days' => __('product.days'), '' => __('product.not_applicable')], 'months', ['class' => 'form-control', 'id' => 'expiry_period_type']) !!}
                            </div>
                        </div>
                    @endif
                    @foreach ($custom_fields as $field => $label)
                        <div class="rr-field">
                            <label class="rr-field__label" for="{{ $field }}">{{ $label }}</label>
                            {!! Form::text($field, null, ['class' => 'form-control', 'id' => $field]) !!}
                        </div>
                    @endforeach
                    <div class="rr-field rr-field--full rr-qp__checks">
                        <label class="rr-toggle">
                            <input type="checkbox" name="enable_sr_no" value="1" class="rr-toggle__input">
                            <span>@lang('product_quick.sr_no')</span>
                        </label>
                        <label class="rr-toggle">
                            <input type="checkbox" name="not_for_selling" value="1" class="rr-toggle__input">
                            <span>@lang('product_quick.not_for_selling')</span>
                        </label>
                    </div>
                    <div class="rr-field rr-field--full">
                        <label class="rr-field__label" for="{{ $dialog_id }}_desc">@lang('product_quick.description')</label>
                        {{-- مش #product_description عشان app.js ميحمّلش محرر tinymce التقيل في نافذة الكاشير --}}
                        {!! Form::textarea('product_description', null, ['class' => 'form-control', 'rows' => 2, 'id' => $dialog_id . '_desc']) !!}
                    </div>
                    @if (!empty($module_form_parts))
                        <div class="rr-field--full rr-exp__legacy">
                            @foreach ($module_form_parts as $value)
                                @if (!empty($value['template_path']))
                                    @include($value['template_path'], $value['template_data'] ?: [])
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </details>
        </div>

        <footer class="rr-modal__foot">
            <button type="submit" class="rr-btn rr-btn--primary" id="submit_quick_product">{!! $icon('check', 16) !!}@lang('product_quick.save')</button>
            <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.cancel')</button>
        </footer>
        {!! Form::close() !!}
    </div>
</div>
<script>
    (function() {
        var $dialog = $('#{{ $dialog_id }}'), $form = $dialog.find('form');
        var $modal = $dialog.closest('.modal'), ns = '.{{ $dialog_id }}';

        // الصنف بيتاح في فرع الكاشير الحالي من غير ما حد يختاره
        var posLocation = $('input#location_id').val();
        if (posLocation && $dialog.find('#product_locations option[value="' + posLocation + '"]').length && !$dialog.find('#product_locations').val().length) {
            $dialog.find('#product_locations').val([posLocation]);
        }
        // الكمية الحالية: فرع الكاشير بس (التانيين بيتضافوا من شاشة الأصناف)
        if (posLocation && $dialog.find('[data-rr-qp-os]').length > 1) {
            $dialog.find('[data-rr-qp-os]').each(function() {
                var isPos = $(this).find('input.purchase_quantity').attr('name') === 'opening_stock[' + posLocation + '][quantity]';
                $(this).prop('hidden', !isPos).find('input, select').prop('disabled', !isPos);
                if (isPos) $(this).find('.rr-qp__loc').remove();
            });
        }

        $modal.one('shown.bs.modal', function() { $dialog.find('#name').trigger('focus'); });
        if (typeof toggle_dsp_input === 'function') toggle_dsp_input();

        // تكلفة الرصيد الافتتاحي بتمشي مع سعر الشراء
        $dialog.find('#single_dpp').on('change', function() {
            var price = __read_number($(this));
            $dialog.find('input.unit_price').each(function() { __write_number($(this), price); });
        });

        // تتبع المخزون (checkbox عادي بدل iCheck، فبنعمل اللي product.js كان بيعمله)
        $dialog.find('#enable_stock').on('change', function() {
            var on = this.checked;
            $dialog.find('[data-rr-qp-stock]').prop('hidden', !on).find('input').prop('disabled', !on);
            $dialog.find('[data-rr-qp-nostock]').prop('hidden', on);
            $dialog.find('#expiry_period_type').prop('disabled', !on);
            if (!on) $dialog.find('#alert_quantity').val('');
        });

        // التصنيف الفرعي يظهر بس لو التصنيف ليه فرعي (app.js بيملا القائمة)
        var $sub = $dialog.find('#sub_category_id');
        $dialog.find('#category_id').on('change', function() {
            $sub.html('<option value="">' + @json(__('product_quick.none')) + '</option>');
            $dialog.find('[data-rr-qp-sub]').prop('hidden', true);
        });
        $(document).on('ajaxSuccess' + ns, function(e, xhr, settings) {
            if (!$.contains(document, $dialog[0])) return $(document).off(ns);
            if ((settings.url || '').indexOf('/products/get_sub_categories') !== -1) {
                $dialog.find('[data-rr-qp-sub]').prop('hidden', $sub.find('option[value!=""]').length === 0);
            }
        });
        // الوحدات الفرعية: app.js بيحمّلها لما الحقل يكون ظاهر، فبنحمّلها لما "بيانات إضافية" تتفتح
        $dialog.find('[data-rr-qp-more]').on('toggle', function() {
            if (this.open && typeof get_sub_units === 'function') get_sub_units();
        });

        $form.validate({
            rules: {
                sku: {
                    remote: {
                        url: '/products/check_product_sku',
                        type: 'post',
                        data: { sku: function() { return $dialog.find('#sku').val(); }, product_id: '' }
                    }
                },
                expiry_period: {
                    required: { depends: function() { return ($dialog.find('#expiry_period_type').val() || '').trim() !== ''; } }
                }
            },
            messages: { sku: { remote: LANG.sku_already_exists } },
            // "بيانات إضافية" بتتفتح لو فيها حقل غلط عشان الكاشير يشوفه
            invalidHandler: function(e, validator) {
                $.each(validator.errorList, function(i, err) { $(err.element).closest('details').prop('open', true); });
            },
            submitHandler: function() {
                var $save = $dialog.find('#submit_quick_product').prop('disabled', true);
                $.ajax({
                    method: 'POST',
                    url: $form.attr('action'),
                    dataType: 'json',
                    data: $form.serialize(),
                    success: function(data) {
                        if (data.success) {
                            $modal.modal('hide');
                            toastr.success(data.msg);
                            $(document).trigger({type: 'quickProductAdded', product: data.product, variation: data.variation});
                        } else {
                            toastr.error(data.msg);
                        }
                    },
                    complete: function() { $save.prop('disabled', false); }
                });
                return false;
            }
        });
    })();
</script>
