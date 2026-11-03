@extends('layouts.app')

{{--
    شاشة الكاشير (نقطة البيع) بتصميم رقمي — بدل sale_pos.create.
    نفس الحقول والـ ids والكلاسات اللي pos.js و keyboard_shortcuts بيعتمدوا عليها بالظبط
    (add_pos_sell_form، search_product، customer_id، pos_table، product_list_body، pos-finalize، pos-express-finalize، ...)،
    بس مترتبة للكاشير: السلة والإجمالي وأزرار الدفع على اليمين، والأصناف على الشمال.
    الدفع النقدي بيفتح نافذة المبلغ المستلم والباقي، وبعدها بيكمل بنفس زرار الدفع السريع الأصلي.
    الشاشة القديمة (sale_pos.create) لسه موجودة في الكود ومستخدمة في الموديولات اللي ليها sub_type (زي الصيانة).
    نفس الشاشة بتشتغل في التعديل واستكمال الفواتير المعلّقة (SellPosController::edit بيبعت $transaction)،
    بنفس حقول sale_pos.edit: فورم edit_pos_sell_form بـ PUT، والسطور والخصم والضريبة والشحن متعبّية من الفاتورة.
--}}

@php
    $icon = function ($name, $size = 18) {
        return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
    };
    $is_edit = isset($transaction);
    $only_payment = $is_edit && !empty($only_payment);
    $is_discount_enabled = $pos_settings['disable_discount'] != 1;
    $is_rp_enabled = session('business.enable_rp') == 1;
    $is_admin = auth()->user()->can('superadmin') || auth()->user()->can('admin');
    $hide_tax = session()->get('business.enable_inline_tax') == 0 ? 'hide' : '';
    $show_products = empty($pos_settings['hide_product_suggestion']) && empty($only_payment);

    // الأزرار حسب الصلاحيات وإعدادات نقطة البيع (نفس شروط pos_form_actions)
    $can_cash = (!Gate::check('disable_express_checkout') || $is_admin) && $pos_settings['disable_express_checkout'] == 0 && array_key_exists('cash', $payment_types);
    $can_card = (!Gate::check('disable_card') || $is_admin) && array_key_exists('card', $payment_types);
    $can_multi = (!Gate::check('disable_pay_checkout') || $is_admin) && $pos_settings['disable_pay_checkout'] == 0;
    $can_credit = (!Gate::check('disable_credit_sale') || $is_admin) && empty($pos_settings['disable_credit_sale_button']);
    $can_suspend = (!Gate::check('disable_suspend_sale') || $is_admin) && empty($pos_settings['disable_suspend']);
    $can_draft = (!Gate::check('disable_draft') || $is_admin) && $pos_settings['disable_draft'] == 0;
    $can_quotation = !Gate::check('disable_quotation') || $is_admin;
    $show_discount_row = !Gate::check('disable_discount') || $is_admin;
    $has_rounding = !empty($pos_settings['amount_rounding_method']) && $pos_settings['amount_rounding_method'] > 0;

    // خيارات الفاتورة الإضافية (بتظهر بس لو مفعّلة في الإعدادات)
    $has_restaurant = in_array('tables', $enabled_modules) || in_array('service_staff', $enabled_modules);
    $has_types_of_service = in_array('types_of_service', $enabled_modules) && ($is_edit ? !empty($transaction->types_of_service) : !empty($types_of_service));
    // في التعديل مجموعة الأسعار ثابتة (زي الشاشة القديمة): بتتعرض للقراءة بس
    $has_price_groups = $is_edit ? !empty($transaction->selling_price_group_id) : !empty($price_groups) && count($price_groups) > 1;
    $show_invoice_scheme = !empty($pos_settings['show_invoice_scheme']) && (!$is_edit || $transaction->status == 'draft');
    $has_options = !empty($pos_settings['show_invoice_layout']) || !empty($commission_agent) || !empty($pos_settings['enable_transaction_date'])
        || config('constants.enable_sell_in_diff_currency') == true || $has_price_groups || $has_types_of_service
        || $show_invoice_scheme || in_array('subscription', $enabled_modules) || $has_restaurant
        || in_array('kitchen', $enabled_modules) || !empty($pos_module_data);
@endphp

@section('title', $is_edit ? __('pos_screen.edit_title', ['invoice' => $transaction->invoice_no]) : __('pos_screen.title'))

@section('pos_header')
    @include('sale_pos.partials.v2_header')
@endsection

@section('content')
<main class="content rr-page rr-pos no-print{{ $only_payment ? ' rr-pos--only-payment' : '' }}" aria-label="@lang('pos_screen.title')">
    <input type="hidden" id="amount_rounding_method" value="{{ $pos_settings['amount_rounding_method'] ?? '' }}">
    @if (!empty($pos_settings['allow_overselling']))
        <input type="hidden" id="is_overselling_allowed">
    @endif
    @if ($is_rp_enabled)
        <input type="hidden" id="reward_point_enabled">
    @endif

    @if ($is_edit)
        {!! Form::open(['url' => action([\App\Http\Controllers\SellPosController::class, 'update'], [$transaction->id]), 'method' => 'post', 'id' => 'edit_pos_sell_form', 'class' => 'rr-pos__form']) !!}
        {{ method_field('PUT') }}
    @else
        {!! Form::open(['url' => action([\App\Http\Controllers\SellPosController::class, 'store']), 'method' => 'post', 'id' => 'add_pos_sell_form', 'class' => 'rr-pos__form']) !!}
    @endif
    {!! Form::hidden('location_id', $default_location->id ?? null, [
        'id' => 'location_id',
        'data-receipt_printer_type' => $is_edit ? ($location_printer_type ?: 'browser') : (!empty($default_location->receipt_printer_type) ? $default_location->receipt_printer_type : 'browser'),
        'data-default_payment_accounts' => $default_location->default_payment_accounts ?? '',
    ]) !!}
    {!! Form::hidden('sub_type', isset($sub_type) ? $sub_type : null) !!}
    <input type="hidden" id="item_addition_method" value="{{ $business_details->item_addition_method }}">
    @if ($is_edit)
        <input type="hidden" id="default_customer_id" value="{{ $transaction->contact->id }}">
        <input type="hidden" id="default_customer_name" value="{{ $transaction->contact->name }}">
        <input type="hidden" id="default_customer_balance" value="{{ $transaction->contact->balance }}">
        <input type="hidden" name="pay_term_number" id="pay_term_number" value="{{ $transaction->pay_term_number }}">
        <input type="hidden" name="pay_term_type" id="pay_term_type" value="{{ $transaction->pay_term_type }}">
    @else
        <input type="hidden" id="default_customer_id" value="{{ $walk_in_customer['id'] ?? '' }}">
        <input type="hidden" id="default_customer_name" value="{{ $walk_in_customer['name'] ?? '' }}">
        <input type="hidden" id="default_customer_balance" value="{{ $walk_in_customer['balance'] ?? '' }}">
        <input type="hidden" id="default_customer_address" value="{{ $walk_in_customer['shipping_address'] ?? '' }}">
        @if (!empty($walk_in_customer['price_calculation_type']) && $walk_in_customer['price_calculation_type'] == 'selling_price_group')
            <input type="hidden" id="default_selling_price_group" value="{{ $walk_in_customer['selling_price_group_id'] ?? '' }}">
        @endif
        <input type="hidden" name="pay_term_number" id="pay_term_number" value="{{ $walk_in_customer['pay_term_number'] ?? '' }}">
        <input type="hidden" name="pay_term_type" id="pay_term_type" value="{{ $walk_in_customer['pay_term_type'] ?? '' }}">
    @endif
    <input type="hidden" name="sell_price_tax" id="sell_price_tax" value="{{ $business_details->sell_price_tax }}">
    <input type="hidden" id="product_row_count" value="{{ $is_edit ? count($sell_details) : 0 }}">
    @if (!$is_edit && !empty($default_price_group_id))
        {!! Form::hidden('default_price_group', $default_price_group_id, ['id' => 'default_price_group']) !!}
    @endif
    @if ($is_edit && $has_price_groups)
        {!! Form::hidden('price_group', $transaction->selling_price_group_id, ['id' => 'price_group']) !!}
    @elseif (!$is_edit && !$has_price_groups)
        @php reset($price_groups); @endphp
        {!! Form::hidden('price_group', key($price_groups), ['id' => 'price_group']) !!}
    @endif

    {{-- الموبايل والتابلت: تبديل بين السلة والأصناف --}}
    @if ($show_products)
        <div class="rr-pos-switch" role="tablist" aria-label="@lang('pos_screen.title')">
            <button type="button" class="rr-pos-switch__btn" role="tab" id="rr_pos_tab_cart" aria-controls="rr_pos_cart" aria-selected="true" data-rr-pos-view="cart">
                {!! $icon('cart') !!}@lang('pos_screen.cart')<span class="rr-pos-switch__count rr-num" data-rr-pos-count>0</span>
            </button>
            <button type="button" class="rr-pos-switch__btn" role="tab" id="rr_pos_tab_products" aria-controls="rr_pos_products" aria-selected="false" data-rr-pos-view="products">
                {!! $icon('grid') !!}@lang('pos_screen.products')
            </button>
        </div>
    @endif

    <div class="rr-pos__body{{ $show_products ? '' : ' rr-pos__body--single' }}" data-rr-pos-active="cart">

        {{-- ═══ السلة ═══ --}}
        <section class="rr-pos-cart" id="rr_pos_cart" aria-label="@lang('pos_screen.cart')" @if ($show_products) role="tabpanel" aria-labelledby="rr_pos_tab_cart" @endif>

            {{-- البحث بالباركود والعميل في سطر واحد (بيلفّوا تحت بعض لو السلة ضيقة) عشان الأصناف تاخد أكبر مساحة --}}
            <div class="rr-pos-cart__top">
                <div class="rr-pos-cart__search">
                    <div class="rr-pos-search">
                        <span class="rr-pos-search__icon" aria-hidden="true">{!! $icon('barcode', 20) !!}</span>
                        {{-- من غير كلاس mousetrap: كان بيعمل مشكلة مع قارئ الباركود (زي الشاشة الأصلية) --}}
                        {!! Form::text('search_product', null, ['class' => 'form-control rr-pos-search__input', 'id' => 'search_product',
                            'placeholder' => __('pos_screen.search'), 'aria-label' => __('pos_screen.search'), 'aria-keyshortcuts' => 'F2',
                            'disabled' => is_null($default_location) || $only_payment, 'autofocus' => !is_null($default_location) && !$only_payment]) !!}
                        <kbd class="rr-pos-kbd rr-pos-search__kbd" aria-hidden="true">F2</kbd>
                    </div>
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--icon" data-toggle="modal" data-target="#configure_search_modal" title="@lang('pos_screen.search_settings')" aria-label="@lang('pos_screen.search_settings')">{!! $icon('sliders') !!}</button>
                    @if (isset($pos_settings['enable_weighing_scale']) && $pos_settings['enable_weighing_scale'] == 1)
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--icon" id="weighing_scale_btn" data-toggle="modal" data-target="#weighing_scale_modal" title="@lang('pos_screen.weighing_scale')" aria-label="@lang('pos_screen.weighing_scale')">{!! $icon('ruler') !!}</button>
                    @endif
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--icon pos_add_quick_product" data-href="{{ url('/products/quick_add') }}?v2=1&product_for=pos&_" data-container=".quick_add_product_modal" title="@lang('pos_screen.quick_product')" aria-label="@lang('pos_screen.quick_product')">{!! $icon('plus') !!}</button>
                </div>
                <div class="rr-pos-cart__customer">
                    <label class="rr-pos-customer__icon" for="customer_id" title="@lang('pos_screen.customer')">{!! $icon('user') !!}<span class="sr-only">@lang('pos_screen.customer')</span></label>
                    <div class="rr-pos-customer">
                        {!! Form::select('contact_id', [], null, ['class' => 'form-control mousetrap', 'id' => 'customer_id', 'placeholder' => 'Enter Customer name / phone', 'required', 'style' => 'width: 100%;']) !!}
                    </div>
                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--icon add_new_customer" data-name="" title="@lang('pos_screen.add_customer')" aria-label="@lang('pos_screen.add_customer')" @if (!auth()->user()->can('customer.create')) disabled @endif>{!! $icon('plus') !!}</button>
                    @if ($has_options)
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--icon" data-toggle="modal" data-target="#rr_pos_options_modal" title="@lang('pos_screen.options')" aria-label="@lang('pos_screen.options')">{!! $icon('sliders') !!}</button>
                    @endif
                    <small class="text-danger @if (empty($customer_due)) hide @endif contact_due_text rr-pos-cart__due"><strong>@lang('account.customer_due'):</strong> <span>{{ $customer_due ?? '' }}</span></small>
                </div>
            </div>

            {{-- خيارات الفاتورة المفعّلة في الإعدادات: نافذة جوه الفورم (الحقول بتتبعت مع الفاتورة) --}}
            @if ($has_options)
                <div class="modal fade rr-scope" id="rr_pos_options_modal" tabindex="-1" role="dialog" aria-labelledby="rr_pos_options_title">
                    <div class="modal-dialog rr-modal-dialog" role="document">
                        <div class="modal-content rr-modal">
                            <header class="rr-modal__head">
                                <div class="rr-modal__titles">
                                    <h2 class="rr-modal__title" id="rr_pos_options_title">@lang('pos_screen.options')</h2>
                                    <p class="rr-modal__desc">@lang('pos_screen.options_desc')</p>
                                </div>
                                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
                            </header>
                            <div class="rr-modal__body">
                                <p class="rr-pos-options__error" data-rr-options-error role="alert" hidden>@lang('pos_screen.options_required')</p>
                                <div class="rr-pos-options__grid">
                                    @if (!empty($pos_settings['show_invoice_layout']))
                                        <div class="form-group">
                                            {!! Form::select('invoice_layout_id', $invoice_layouts, $default_location->invoice_layout_id, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.select_invoice_layout'), 'id' => 'invoice_layout_id', 'style' => 'width: 100%;']) !!}
                                        </div>
                                    @endif
                                    @if (!empty($commission_agent))
                                        <div class="form-group">
                                            {!! Form::select('commission_agent', $commission_agent, $is_edit ? $transaction->commission_agent : null, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.commission_agent'), 'id' => 'commission_agent', 'required' => !empty($pos_settings['is_commission_agent_required']), 'style' => 'width: 100%;']) !!}
                                        </div>
                                    @endif
                                    @if (!empty($pos_settings['enable_transaction_date']))
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                                {!! Form::text('transaction_date', $is_edit ? @format_datetime($transaction->transaction_date) : $default_datetime, ['class' => 'form-control', 'readonly', 'required', 'id' => 'transaction_date']) !!}
                                            </div>
                                        </div>
                                    @endif
                                    @if (config('constants.enable_sell_in_diff_currency') == true)
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fas fa-exchange-alt"></i></span>
                                                {!! Form::text('exchange_rate', $is_edit ? @num_format($transaction->exchange_rate) : config('constants.currency_exchange_rate'), ['class' => 'form-control input-sm input_number', 'placeholder' => __('lang_v1.currency_exchange_rate'), 'id' => 'exchange_rate']) !!}
                                            </div>
                                        </div>
                                    @endif
                                    @if ($has_price_groups && $is_edit)
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fas fa-money-bill-alt"></i></span>
                                                {!! Form::text('price_group_text', $transaction->price_group->name, ['class' => 'form-control', 'readonly']) !!}
                                            </div>
                                        </div>
                                    @elseif ($has_price_groups)
                                        @php
                                            reset($price_groups);
                                            $selected_price_group = !empty($default_price_group_id) && array_key_exists($default_price_group_id, $price_groups) ? $default_price_group_id : null;
                                        @endphp
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fas fa-money-bill-alt"></i></span>
                                                {!! Form::hidden('hidden_price_group', key($price_groups), ['id' => 'hidden_price_group']) !!}
                                                {!! Form::select('price_group', $price_groups, $selected_price_group, ['class' => 'form-control select2', 'id' => 'price_group', 'style' => 'width: 100%;']) !!}
                                                <span class="input-group-addon">@show_tooltip(__('lang_v1.price_group_help_text'))</span>
                                            </div>
                                        </div>
                                    @endif
                                    @if ($has_types_of_service && $is_edit)
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fa fa-external-link-square-alt text-primary service_modal_btn"></i></span>
                                                {!! Form::text('types_of_service_text', $transaction->types_of_service->name, ['class' => 'form-control', 'readonly']) !!}
                                                {!! Form::hidden('types_of_service_id', $transaction->types_of_service_id, ['id' => 'types_of_service_id']) !!}
                                            </div>
                                            <small><p class="help-block @if (empty($transaction->selling_price_group_id)) hide @endif" id="price_group_text">@lang('lang_v1.price_group'): <span>{{ $transaction->price_group->name ?? '' }}</span></p></small>
                                        </div>
                                    @elseif ($has_types_of_service)
                                        <div class="form-group">
                                            <div class="input-group">
                                                <span class="input-group-addon"><i class="fa fa-external-link-square-alt text-primary service_modal_btn"></i></span>
                                                {!! Form::select('types_of_service_id', $types_of_service, null, ['class' => 'form-control', 'id' => 'types_of_service_id', 'style' => 'width: 100%;', 'placeholder' => __('lang_v1.select_types_of_service')]) !!}
                                                {!! Form::hidden('types_of_service_price_group', null, ['id' => 'types_of_service_price_group']) !!}
                                                <span class="input-group-addon">@show_tooltip(__('lang_v1.types_of_service_help'))</span>
                                            </div>
                                            <small><p class="help-block hide" id="price_group_text">@lang('lang_v1.price_group'): <span></span></p></small>
                                        </div>
                                    @endif
                                    @if ($show_invoice_scheme)
                                        @php
                                            $invoice_scheme_id = !$is_edit && !empty($default_location->invoice_scheme_id) ? $default_location->invoice_scheme_id : $default_invoice_schemes->id;
                                        @endphp
                                        <div class="form-group">
                                            {!! Form::select('invoice_scheme_id', $invoice_schemes, $invoice_scheme_id, ['class' => 'form-control', 'placeholder' => __('lang_v1.select_invoice_scheme'), 'id' => 'invoice_scheme_id']) !!}
                                        </div>
                                    @endif
                                    @if (in_array('subscription', $enabled_modules))
                                        <div class="form-group rr-pos-options__check">
                                            <label>{!! Form::checkbox('is_recurring', 1, $is_edit && $transaction->is_recurring, ['class' => 'input-icheck', 'id' => 'is_recurring']) !!} @lang('lang_v1.subscribe')?</label>
                                            <button type="button" data-toggle="modal" data-target="#recurringInvoiceModal" class="btn btn-link"><i class="fa fa-external-link-square-alt"></i></button>@show_tooltip(__('lang_v1.recurring_invoice_help'))
                                        </div>
                                    @endif
                                    @if ($has_restaurant)
                                        <span id="restaurant_module_span" class="rr-pos-options__span" @if ($is_edit) data-transaction_id="{{ $transaction->id }}" @endif><div class="col-md-3"></div></span>
                                    @endif
                                    @if (in_array('kitchen', $enabled_modules))
                                        <div class="form-group rr-pos-options__check">
                                            <div class="checkbox">
                                                <label>{!! Form::checkbox('is_kitchen_order', 1, $is_edit && $transaction->is_kitchen_order, ['class' => 'input-icheck status', 'id' => 'is_kitchen_order']) !!} {{ __('lang_v1.kitchen_order') }}</label>
                                                @show_tooltip(__('lang_v1.kitchen_order_tooltip'))
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                @foreach ($pos_module_data ?? [] as $value)
                                    @if (!empty($value['view_path']))
                                        @includeIf($value['view_path'], ['view_data' => $value['view_data']])
                                    @endif
                                @endforeach
                            </div>
                            <footer class="rr-modal__foot">
                                <button type="button" class="rr-btn rr-btn--primary" data-dismiss="modal">{!! $icon('check') !!}@lang('pos_screen.done')</button>
                            </footer>
                        </div>
                    </div>
                </div>
                @if ($has_types_of_service)
                    <div class="modal fade types_of_service_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
                        @if ($is_edit)
                            @include('types_of_service.pos_form_modal', ['types_of_service' => $transaction->types_of_service])
                        @endif
                    </div>
                @endif
            @endif

            <div class="rr-pos-cart__scroll">
            {{-- الأصناف المضافة --}}
            <div class="rr-pos-cart__lines pos_product_div">
                <table class="table rr-pos-table" id="pos_table">
                    <thead>
                        <tr>
                            <th class="product-col">@lang('sale.product')</th>
                            <th class="quantity-col">@lang('sale.qty')</th>
                            @if (!empty($pos_settings['inline_service_staff']))
                                <th class="service-staff-col">@lang('restaurant.service_staff')</th>
                            @endif
                            <th class="price-inc-tax-col {{ $hide_tax }}">@lang('sale.price_inc_tax')</th>
                            <th class="subtotal-col">@lang('sale.subtotal')</th>
                            <th class="remove-col"><span class="sr-only">@lang('messages.delete')</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($is_edit)
                            @foreach ($sell_details as $sell_line)
                                @include('sale_pos.product_row', ['product' => $sell_line, 'row_count' => $loop->index, 'tax_dropdown' => $taxes,
                                    'sub_units' => !empty($sell_line->unit_details) ? $sell_line->unit_details : [], 'action' => 'edit'])
                            @endforeach
                        @endif
                    </tbody>
                </table>
                <div class="rr-pos-empty" data-rr-pos-empty>
                    <span class="rr-pos-empty__icon" aria-hidden="true">{!! $icon('cart', 28) !!}</span>
                    <strong>@lang('pos_screen.cart_empty')</strong>
                    <span>@lang('pos_screen.cart_empty_hint')</span>
                </div>
            </div>

            </div>

            {{-- الملخص والإجمالي (نفس ids الـ pos_form_totals) --}}
            <div class="rr-pos-cart__foot">
                <dl class="rr-pos-sum pos_form_totals">
                    <div class="rr-pos-sum__row">
                        <dt>@lang('pos_screen.subtotal') <span class="rr-pos-sum__pieces">(<span class="total_quantity rr-num">0</span> @lang('pos_screen.pieces'))</span></dt>
                        <dd><span class="rr-num price_total">0</span></dd>
                    </div>
                    {{-- الصف بيستخبى لو الخصم والنقاط الاثنين مقفولين، والحقول المخفية فاضلة عشان pos.js --}}
                    <div class="rr-pos-sum__row @if (!$show_discount_row || (!$is_discount_enabled && !$is_rp_enabled)) hide @endif">
                        <dt>
                            @if ($is_discount_enabled)@lang('pos_screen.discount')@endif
                            @if ($is_rp_enabled){{ $is_discount_enabled ? ' / ' : '' }}{{ session('business.rp_name') }}@endif
                            @if ($is_discount_enabled && $edit_discount)
                                <button type="button" class="rr-pos-sum__edit" id="pos-edit-discount" data-toggle="modal" data-target="#posEditDiscountModal" title="@lang('sale.edit_discount')" aria-label="@lang('sale.edit_discount')">{!! $icon('edit', 14) !!}</button>
                            @endif
                        </dt>
                        <dd class="rr-num">
                            @if ($is_discount_enabled)<span id="total_discount">0</span>@endif
                            <input type="hidden" name="discount_type" id="discount_type" value="{{ $is_edit ? $transaction->discount_type : 'percentage' }}" data-default="percentage">
                            <input type="hidden" name="discount_amount" id="discount_amount" value="{{ @num_format($is_edit ? $transaction->discount_amount : $business_details->default_sales_discount) }}" data-default="{{ $business_details->default_sales_discount }}">
                            <input type="hidden" name="rp_redeemed" id="rp_redeemed" value="{{ $is_edit ? $transaction->rp_redeemed : 0 }}">
                            <input type="hidden" name="rp_redeemed_amount" id="rp_redeemed_amount" value="{{ $is_edit ? $transaction->rp_redeemed_amount : 0 }}">
                        </dd>
                    </div>
                    <div class="rr-pos-sum__row @if ($pos_settings['disable_order_tax'] != 0) hide @endif">
                        <dt>
                            @lang('pos_screen.order_tax')
                            <button type="button" class="rr-pos-sum__edit" id="pos-edit-tax" data-toggle="modal" data-target="#posEditOrderTaxModal" title="@lang('sale.edit_order_tax')" aria-label="@lang('sale.edit_order_tax')">{!! $icon('edit', 14) !!}</button>
                        </dt>
                        <dd class="rr-num">
                            <span id="order_tax">{{ $is_edit ? $transaction->tax_amount : 0 }}</span>
                            <input type="hidden" name="tax_rate_id" id="tax_rate_id" value="{{ $is_edit ? $transaction->tax_id : $business_details->default_sales_tax }}" data-default="{{ $business_details->default_sales_tax }}">
                            <input type="hidden" name="tax_calculation_amount" id="tax_calculation_amount" value="{{ @num_format($is_edit ? $transaction->tax?->amount : $business_details->tax_calculation_amount) }}" data-default="{{ $business_details->tax_calculation_amount }}">
                        </dd>
                    </div>
                    <div class="rr-pos-sum__row" data-rr-pos-shipping>
                        <dt>
                            @lang('pos_screen.shipping')
                            <button type="button" class="rr-pos-sum__edit" data-toggle="modal" data-target="#posShippingModal" title="@lang('sale.shipping')" aria-label="@lang('sale.shipping')">{!! $icon('edit', 14) !!}</button>
                        </dt>
                        <dd class="rr-num">
                            <span id="shipping_charges_amount">0</span>
                            <input type="hidden" name="shipping_details" id="shipping_details" value="{{ $is_edit ? $transaction->shipping_details : '' }}" data-default="">
                            <input type="hidden" name="shipping_address" id="shipping_address" value="{{ $is_edit ? $transaction->shipping_address : '' }}">
                            <input type="hidden" name="shipping_status" id="shipping_status" value="{{ $is_edit ? $transaction->shipping_status : '' }}">
                            <input type="hidden" name="delivered_to" id="delivered_to" value="{{ $is_edit ? $transaction->delivered_to : '' }}">
                            <input type="hidden" name="delivery_person" id="delivery_person" value="{{ $is_edit ? $transaction->delivery_person : '' }}">
                            <input type="hidden" name="shipping_charges" id="shipping_charges" value="{{ @num_format($is_edit ? $transaction->shipping_charges : 0.00) }}" data-default="0.00">
                        </dd>
                    </div>
                    @if (in_array('types_of_service', $enabled_modules))
                        <div class="rr-pos-sum__row">
                            <dt>@lang('pos_screen.packing') <button type="button" class="rr-pos-sum__edit service_modal_btn" aria-label="@lang('lang_v1.packing_charge')">{!! $icon('edit', 14) !!}</button></dt>
                            <dd class="rr-num"><span id="packing_charge_text">0</span></dd>
                        </div>
                    @endif
                    @if ($has_rounding)
                        <div class="rr-pos-sum__row">
                            <dt id="round_off">@lang('pos_screen.round_off')</dt>
                            <dd class="rr-num"><span id="round_off_text">0</span><input type="hidden" name="round_off_amount" id="round_off_amount" value=0></dd>
                        </div>
                    @endif
                </dl>

                <div class="rr-pos-total">
                    <span class="rr-pos-total__label">@lang('pos_screen.total')</span>
                    <input type="hidden" name="final_total" id="final_total_input" value="0.00">
                    <span class="rr-pos-total__amount"><span id="total_payable" class="rr-num">0.00</span><span class="rr-pos-total__currency">{{ session('currency.symbol') }}</span></span>
                </div>

                {{-- أزرار الدفع: نفس ids وكلاسات pos_form_actions --}}
                <div class="rr-pos-pay">
                    @if ($can_cash)
                        <button type="button" class="rr-pos-pay__btn rr-pos-pay__btn--cash" data-rr-pos-cash aria-keyshortcuts="F8" title="@lang('tooltip.express_checkout')">
                            {!! $icon('cash', 22) !!}<span>@lang('pos_screen.pay_cash')</span><kbd class="rr-pos-kbd">F8</kbd>
                        </button>
                        {{-- الزرار الأصلي للدفع النقدي السريع: نافذة المبلغ المستلم بتضغطه بعد ما تكتب المبلغ --}}
                        <button type="button" class="pos-express-finalize hide" data-pay_method="cash" data-rr-pos-cash-submit tabindex="-1" aria-hidden="true"></button>
                    @endif
                    @if ($can_card)
                        <button type="button" class="rr-pos-pay__btn rr-pos-pay__btn--card pos-express-finalize" data-pay_method="card" title="@lang('lang_v1.tooltip_express_checkout_card')">
                            {!! $icon('card', 20) !!}<span>@lang('pos_screen.pay_card')</span>
                        </button>
                    @endif
                    @if ($can_multi)
                        <button type="button" class="rr-pos-pay__btn{{ $can_cash ? ' rr-pos-pay__btn--multi' : ' rr-pos-pay__btn--cash' }}" id="pos-finalize" aria-keyshortcuts="F9" title="@lang('lang_v1.tooltip_checkout_multi_pay')">
                            {!! $icon('wallet', 20) !!}<span>@lang('pos_screen.pay_multi')</span><kbd class="rr-pos-kbd">F9</kbd>
                        </button>
                    @endif
                    @if ($can_credit)
                        <input type="hidden" name="is_credit_sale" value="0" id="is_credit_sale">
                        <button type="button" class="rr-pos-pay__btn rr-pos-pay__btn--credit pos-express-finalize" data-pay_method="credit_sale" title="@lang('lang_v1.tooltip_credit_sale')">
                            {!! $icon('clipboard', 20) !!}<span>@lang('pos_screen.pay_credit')</span>
                        </button>
                    @endif
                </div>
                <div class="rr-pos-actions">
                    @if ($can_suspend)
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm pos-express-finalize" data-pay_method="suspend" title="@lang('lang_v1.tooltip_suspend')" aria-keyshortcuts="F10" @if ($only_payment) disabled @endif>{!! $icon('pause', 16) !!}@lang('pos_screen.suspend')<kbd class="rr-pos-kbd">F10</kbd></button>
                    @endif
                    @if ($can_draft)
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" id="pos-draft" @if ($only_payment) disabled @endif>{!! $icon('file', 16) !!}@lang('pos_screen.draft')</button>
                    @endif
                    @if ($can_quotation)
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" id="pos-quotation" @if ($only_payment) disabled @endif>{!! $icon('receipt', 16) !!}@lang('pos_screen.quotation')</button>
                    @endif
                    @if ($is_edit)
                        <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}" class="rr-btn rr-btn--ghost rr-btn--sm rr-pos-actions__cancel">{!! $icon('close', 16) !!}@lang('pos_screen.edit_leave')</a>
                    @else
                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-pos-actions__cancel" id="pos-cancel">{!! $icon('close', 16) !!}@lang('pos_screen.cancel')</button>
                    @endif
                </div>
            </div>
        </section>

        {{-- ═══ الأصناف ═══ --}}
        @if ($show_products)
            <section class="rr-pos-products" id="rr_pos_products" role="tabpanel" aria-labelledby="rr_pos_tab_products" aria-label="@lang('pos_screen.products')">
                <div class="rr-pos-products__filters">
                    <div class="rr-pos-chips" role="group" aria-label="{{ __('category.category') }}">
                        @if (!empty($featured_products))
                            <button type="button" class="rr-pos-chip" id="show_featured_products">{!! $icon('gift', 16) !!}@lang('pos_screen.featured')</button>
                        @endif
                        @if (!empty($categories))
                            <button type="button" class="rr-pos-chip main-category" data-value="all" data-parent="0" aria-pressed="true">@lang('pos_screen.all')</button>
                            @foreach ($categories as $category)
                                <button type="button" class="rr-pos-chip main-category rr-pos-tone-{{ $category['id'] % 6 }}" data-value="{{ $category['id'] }}" data-parent="0" aria-pressed="false">{{ $category['name'] }}</button>
                            @endforeach
                        @endif
                    </div>
                    @if (!empty($brands))
                        <select class="form-control rr-pos-products__brand" data-rr-pos-brand aria-label="@lang('pos_screen.brand')">
                            @foreach ($brands as $key => $brand)
                                <option value="{{ $key }}">{{ $brand }}</option>
                            @endforeach
                        </select>
                        <div hidden>
                            @foreach ($brands as $key => $brand)
                                <span class="product_brand" data-value="{{ $key }}"></span>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if (!empty($categories))
                    @foreach ($categories as $category)
                        @if (!empty($category['sub_categories']))
                            <div class="rr-pos-chips rr-pos-chips--sub" data-rr-pos-sub="{{ $category['id'] }}" role="group" aria-label="{{ $category['name'] }}" hidden>
                                @foreach ($category['sub_categories'] as $sc)
                                    @if ($sc['parent_id'] != 0)
                                        <button type="button" class="rr-pos-chip rr-pos-chip--sm product_category" data-value="{{ $sc['id'] }}" aria-pressed="false">{{ $sc['name'] }}</button>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                @endif

                <div class="rr-pos-featured" id="featured_products_box" style="display: none;">
                    @if (!empty($featured_products))
                        @include('sale_pos.partials.featured_products')
                    @endif
                </div>

                <input type="hidden" id="suggestion_page" value="1">
                <div class="rr-pos-grid eq-height-row" id="product_list_body"></div>
                <div class="rr-pos-products__loader" id="suggestion_page_loader" style="display: none;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
            </section>
        @endif
    </div>

    @include('sale_pos.partials.v2_payment_modal')
    @if (empty($pos_settings['disable_suspend']))
        @include('sale_pos.partials.suspend_note_modal')
    @endif
    @if (empty($pos_settings['disable_recurring_invoice']))
        @include('sale_pos.partials.recurring_invoice_modal')
    @endif
    @include('sale_pos.partials.edit_discount_modal', $is_edit ? [
        'sales_discount' => $transaction->discount_amount,
        'discount_type' => $transaction->discount_type,
        'rp_redeemed' => $transaction->rp_redeemed,
        'rp_redeemed_amount' => $transaction->rp_redeemed_amount,
        'max_available' => !empty($redeem_details['points']) ? $redeem_details['points'] : 0,
    ] : [
        'sales_discount' => $business_details->default_sales_discount,
        'discount_type' => 'percentage',
        'rp_redeemed' => 0,
        'rp_redeemed_amount' => 0,
        'max_available' => 0,
    ])
    @include('sale_pos.partials.edit_order_tax_modal', ['selected_tax' => $is_edit ? $transaction->tax_id : $business_details->default_sales_tax])
    @include('sale_pos.partials.edit_shipping_modal')
    {!! Form::close() !!}
</main>

{{-- نافذة الدفع النقدي: المبلغ المستلم والباقي للعميل --}}
@if ($can_cash)
    <div class="modal fade rr-scope" id="rr_pos_cash_modal" tabindex="-1" role="dialog" aria-labelledby="rr_pos_cash_title">
        <div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
            <div class="modal-content rr-modal">
                <header class="rr-modal__head">
                    <div class="rr-modal__titles"><h2 class="rr-modal__title" id="rr_pos_cash_title">@lang('pos_screen.cash_title')</h2></div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
                </header>
                <div class="rr-modal__body rr-pos-cash">
                    <div class="rr-pos-cash__due">
                        <span>@lang('pos_screen.cash_due')</span>
                        <strong class="rr-num" data-rr-cash-due>0</strong>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="rr_pos_cash_received">@lang('pos_screen.cash_received')</label>
                        <input type="text" class="form-control rr-pos-cash__input input_number" id="rr_pos_cash_received" inputmode="decimal" autocomplete="off">
                    </div>
                    <div class="rr-pos-cash__quick" role="group" aria-label="@lang('pos_screen.cash_received')" data-rr-cash-quick></div>
                    <div class="rr-pos-cash__change" data-rr-cash-change-box>
                        <span>@lang('pos_screen.cash_change')</span>
                        <strong class="rr-num" data-rr-cash-change>0</strong>
                    </div>
                    <p class="rr-pos-cash__error" data-rr-cash-error role="alert" hidden>@lang('pos_screen.cash_short')</p>
                </div>
                <footer class="rr-modal__foot">
                    <button type="button" class="rr-btn rr-btn--primary rr-btn--lg rr-pos-cash__confirm" data-rr-cash-confirm>{!! $icon('check') !!}@lang('pos_screen.cash_confirm')</button>
                    <span class="rr-modal__note">@lang('pos_screen.cash_hint')</span>
                </footer>
            </div>
        </div>
    </div>
@endif

{{-- نافذة المرتجع: رقم الفاتورة ← pos.js (#send_for_sell_return) بيتحقق منه ويفتح شاشة المرتجع --}}
<div class="modal fade rr-scope" id="rr_pos_return_modal" tabindex="-1" role="dialog" aria-labelledby="rr_pos_return_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
        <div class="modal-content rr-modal">
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title" id="rr_pos_return_title">@lang('return_screen.modal_title')</h2>
                    <p class="rr-modal__desc">@lang('return_screen.modal_desc')</p>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">{!! $icon('close') !!}</button>
            </header>
            <div class="rr-modal__body">
                <div class="rr-field">
                    <label class="rr-field__label" for="send_for_sell_return_invoice_no">@lang('return_screen.modal_label')</label>
                    <div class="rr-control">
                        <span class="rr-control__lead" aria-hidden="true">{!! $icon('barcode', 18) !!}</span>
                        <input type="text" id="send_for_sell_return_invoice_no" class="rr-control__input" autocomplete="off" dir="ltr">
                    </div>
                </div>
            </div>
            <footer class="rr-modal__foot">
                <button type="button" class="rr-btn rr-btn--primary" id="send_for_sell_return">{!! $icon('undo', 16) !!}@lang('return_screen.modal_go')</button>
                <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.close')</button>
            </footer>
        </div>
    </div>
</div>

{{-- نافذة "تم البيع": الباقي للعميل، طباعة تاني، إرسال الفاتورة واتساب، فاتورة جديدة (Enter) --}}
<div class="modal fade rr-scope" id="rr_pos_done_modal" tabindex="-1" role="dialog" aria-labelledby="rr_pos_done_title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
        <div class="modal-content rr-modal">
            <div class="rr-modal__body rr-pos-done">
                <span class="rr-pos-done__icon" aria-hidden="true">{!! $icon('check', 32) !!}</span>
                <h2 class="rr-pos-done__title" id="rr_pos_done_title">@lang('pos_screen.done_title')</h2>
                <p class="rr-pos-done__meta"><span class="rr-code" data-rr-done-invoice></span> <span data-rr-done-customer></span></p>
                <div class="rr-pos-done__sum">
                    <div class="rr-pos-done__row"><span>@lang('pos_screen.total')</span><strong class="rr-num" data-rr-done-total></strong></div>
                    <div class="rr-pos-done__row rr-pos-done__row--change" data-rr-done-change-row><span>@lang('pos_screen.cash_change')</span><strong class="rr-num" data-rr-done-change></strong></div>
                </div>
            </div>
            <footer class="rr-modal__foot rr-pos-done__foot">
                <button type="button" class="rr-btn rr-btn--primary rr-btn--lg rr-pos-done__new" data-rr-done-new>{!! $icon('plus') !!}@lang('pos_screen.done_new')<kbd class="rr-pos-kbd">Enter</kbd></button>
                <button type="button" class="rr-btn rr-btn--secondary rr-btn--lg" data-rr-done-print>{!! $icon('print') !!}@lang('pos_screen.done_print')</button>
                <button type="button" class="rr-btn rr-btn--secondary rr-btn--lg rr-pos-done__wa" data-rr-done-whatsapp>{!! $icon('message') !!}@lang('pos_screen.done_whatsapp')</button>
            </footer>
        </div>
    </div>
</div>

@include('sale_pos.partials.v2_shortcuts')

<section class="invoice print_section" id="receipt_section"></section>
<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    @include('contact.quick_add_v2')
</div>
<div class="modal fade register_details_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>
<div class="modal fade" id="expense_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
@include('sale_pos.partials.configure_search_modal')
@include('sale_pos.partials.recent_transactions_modal')
@include('sale_pos.partials.weighing_scale_modal')
@stop

@section('css')
    @foreach ($pos_module_data ?? [] as $value)
        @if (!empty($value['module_css_path']))
            @includeIf($value['module_css_path'])
        @endif
    @endforeach
@stop

@section('javascript')
    <script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/printer.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
    {{-- اختصارات إعدادات النشاط (Mousetrap) مش هنا: كانت بتتعارض مع اختصارات الشاشة دي (F2/F4) والمعرّفة في v2_js --}}
    @if (in_array('tables', $enabled_modules) || in_array('modifiers', $enabled_modules) || in_array('service_staff', $enabled_modules))
        <script src="{{ asset('js/restaurant.js?v=' . $asset_v) }}"></script>
    @endif
    @foreach ($pos_module_data ?? [] as $value)
        @if (!empty($value['module_js_path']))
            @includeIf($value['module_js_path'], ['view_data' => $value['view_data']])
        @endif
    @endforeach
    @include('sale_pos.partials.v2_js')
    @if (!empty($pos_settings['customer_display_screen']))
        @include('sale_pos.partials.v2_display')
    @endif
@endsection
