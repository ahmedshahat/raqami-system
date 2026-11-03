@extends('layouts.app')

{{--
    تعديل فاتورة مبيعات / أمر بيع بتصميم رقمي (الشاشة الافتراضية لـ /sells/{id}/edit).
    نفس حقول sell.edit (الأسماء والـ ids اللي pos.js بيعتمد عليها) بترتيب شاشة الإنشاء الجديدة.
    الشاشة القديمة لليوزر اللي اختار يرجع لها (users.use_classic_sell_create، نفس اختيار شاشة الإنشاء).
    مهم: صفوف الدفع لازم تتبعت كلها زي الشاشة القديمة، لأن التحديث بيمسح أي دفعة مش موجودة في الفورم.
--}}

@php
	$is_sales_order = $transaction->type == 'sales_order';
	$title = $is_sales_order ? __('lang_v1.edit_sales_order') : __('sale.edit_sale');
	$icon = function ($name, $size = 18) {
		return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
	};
	$custom_labels = json_decode(session('business.custom_labels'), true);
	$common_settings = session()->get('business.common_settings');
	$hide_tax = session()->get('business.enable_inline_tax') == 0 ? 'hide' : '';
	$max_discount = !is_null(auth()->user()->max_sales_discount_percent) ? auth()->user()->max_sales_discount_percent : '';

	if ($transaction->status == 'draft' && $transaction->is_quotation == 1) {
		$status = 'quotation';
	} elseif ($transaction->status == 'draft' && $transaction->sub_status == 'proforma') {
		$status = 'proforma';
	} else {
		$status = $transaction->status;
	}

	$sell_custom_fields = [];
	for ($i = 1; $i <= 4; $i++) {
		if (!empty($custom_labels['sell']['custom_field_'.$i])) {
			$sell_custom_fields[$i] = ['label' => $custom_labels['sell']['custom_field_'.$i], 'required' => !empty($custom_labels['sell']['is_custom_field_'.$i.'_required'])];
		}
	}
	$shipping_custom_fields = [];
	for ($i = 1; $i <= 5; $i++) {
		if (!empty($custom_labels['shipping']['custom_field_'.$i])) {
			$shipping_custom_fields[$i] = ['label' => $custom_labels['shipping']['custom_field_'.$i], 'required' => !empty($custom_labels['shipping']['is_custom_field_'.$i.'_required'])];
		}
	}
	$is_pay_term_required = !empty($pos_settings['is_pay_term_required']);
	$pay_term_values = ['number' => $transaction->pay_term_number, 'type' => $transaction->pay_term_type];
	$required_custom_fields = array_filter($sell_custom_fields, fn ($f) => $f['required']);
	$optional_custom_fields = array_filter($sell_custom_fields, fn ($f) => ! $f['required']);

	$is_enabled_download_pdf = config('constants.enable_download_pdf');
	$show_prefer_payment = $is_enabled_download_pdf && !$is_sales_order && auth()->user()->can('sell.payments');
	// زي الشاشة القديمة بالظبط: صفوف الدفع بتظهر لأي حد عنده صلاحية الدفع
	$show_payment = auth()->user()->can('sell.payments');
	$payment_rows = [];
	foreach ($payment_lines as $payment_line) {
		if ($payment_line['is_return'] == 1) {
			$change_return = $payment_line;
			continue;
		}
		$payment_rows[] = $payment_line;
	}
	$show_export = !empty($common_settings['is_enabled_export']) && !$is_sales_order;
	$has_rounding = !empty($pos_settings['amount_rounding_method']) && $pos_settings['amount_rounding_method'] > 0;
	$has_expenses = collect(range(1, 4))->contains(fn ($e) => !empty($transaction->{'additional_expense_key_'.$e}) || (float) $transaction->{'additional_expense_value_'.$e} != 0);

	$tabs = [];
	if ($show_payment || $show_prefer_payment) $tabs['payment'] = ['wallet', __('sell_form.tab_payment'), 'success'];
	if (!$is_sales_order) $tabs['discount'] = ['coins', __('sell_form.tab_discount'), 'danger'];
	$tabs['shipping'] = ['truck', __('sell_form.tab_shipping'), 'info'];
	$tabs['notes'] = ['file', __('sell_form.tab_notes'), 'warning'];
	$tabs['more'] = ['sliders', __('sell_form.tab_more'), 'neutral'];
	$first_tab = array_key_first($tabs);
@endphp

@section('title', $title)

@section('content')
<main class="content rr-page rr-sf no-print" aria-labelledby="rr_sf_title">

	{{-- ═══ رأس الصفحة ═══ --}}
	<header class="rr-head">
		<div class="rr-head__titles">
			<nav aria-label="@lang('sell_form.breadcrumb')">
				<ol class="rr-crumbs">
					<li><a href="{{ action([\App\Http\Controllers\SellController::class, 'index']) }}">@lang('sell_form.crumb_sells')</a></li>
					<li><span aria-current="page">{{ $title }}</span></li>
				</ol>
			</nav>
			<h1 id="rr_sf_title" class="rr-head__title">{{ $title }} <span class="rr-code">#{{ $transaction->invoice_no }}</span></h1>
			<form method="POST" action="{{ route('sells.create.switch') }}" class="rr-inline-form">
				@csrf
				<input type="hidden" name="version" value="classic">
				<input type="hidden" name="edit_id" value="{{ $transaction->id }}">
				<button type="submit" class="rr-linkbtn">@lang('sell_form.old_edit_screen')</button>
			</form>
		</div>
		<div class="rr-head__actions">
			<div class="rr-sf-location-ro">
				{!! $icon('map-pin', 16) !!}
				<span>@lang('sell_form.location'):</span>
				<strong>{{ $transaction->location->name }}</strong>
			</div>
		</div>
	</header>

	<input type="hidden" id="amount_rounding_method" value="{{$pos_settings['amount_rounding_method'] ?? ''}}">
	@if(!empty($pos_settings['allow_overselling']))
		<input type="hidden" id="is_overselling_allowed">
	@endif
	@if(session('business.enable_rp') == 1)
		<input type="hidden" id="reward_point_enabled">
	@endif
	<input type="hidden" id="item_addition_method" value="{{$business_details->item_addition_method}}">

	{!! Form::open(['url' => action([\App\Http\Controllers\SellPosController::class, 'update'], ['po' => $transaction->id ]), 'method' => 'put', 'id' => 'edit_sell_form', 'class' => 'rr-sf-form', 'files' => true, 'data-transaction-id' => $transaction->id ]) !!}
	{!! Form::hidden('location_id', $transaction->location_id, ['id' => 'location_id', 'data-receipt_printer_type' => !empty($location_printer_type) ? $location_printer_type : 'browser', 'data-default_payment_accounts' => $transaction->location->default_payment_accounts]); !!}
	@if($is_sales_order)
		<input type="hidden" id="sale_type" value="{{$transaction->type}}">
	@endif
	<input type="hidden" name="is_direct_sale" value="1">
	<input type="hidden" name="is_serial_no" value="1">
	{!! Form::hidden('is_save_and_print', 0, ['id' => 'is_save_and_print']); !!}

	<div class="rr-cols">
		<div class="rr-cols__main">

			{{-- ═══ العميل والتاريخ والحالة ═══ --}}
			<section class="rr-card rr-sf-head-card" aria-label="@lang('sell_form.sec_customer')">
				<div class="rr-card__body rr-form-wrap">
					<div class="rr-form-grid rr-form-grid--3">
						<div class="rr-field rr-sf-customer">
							{!! Form::label('contact_id', __('contact.customer'), ['class' => 'rr-field__label']) !!}
							<input type="hidden" id="default_customer_id" value="{{ $transaction->contact->id }}">
							<input type="hidden" id="default_customer_name" value="{{ $transaction->contact->name }}">
							<div class="rr-sf-group">
								{!! Form::select('contact_id', [], null, ['class' => 'form-control mousetrap', 'id' => 'customer_id', 'placeholder' => __('sell_form.customer_placeholder'), 'required']); !!}
								<button type="button" class="rr-btn rr-btn--secondary rr-btn--icon add_new_customer" data-name="" title="@lang('sell_form.add_customer')" aria-label="@lang('sell_form.add_customer')">{!! $icon('plus') !!}</button>
							</div>
							<small class="rr-field__error @if(empty($customer_due)) hide @endif contact_due_text"><strong>@lang('account.customer_due'):</strong> <span>{{$customer_due ?? ''}}</span></small>
						</div>

						<div class="rr-field">
							{!! Form::label('transaction_date', __('sale.sale_date'), ['class' => 'rr-field__label']) !!}
							{!! Form::text('transaction_date', $transaction->transaction_date, ['class' => 'form-control', 'readonly', 'required']); !!}
						</div>

						@if($is_sales_order)
							<input type="hidden" name="status" id="status" value="{{$transaction->status}}">
						@else
							<div class="rr-field">
								{!! Form::label('status', __('sale.status'), ['class' => 'rr-field__label']) !!}
								{!! Form::select('status', $statuses, $status, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
							</div>
						@endif

						@if(!empty($commission_agent))
							<div class="rr-field">
								{!! Form::label('commission_agent', __('lang_v1.commission_agent'), ['class' => 'rr-field__label']) !!}
								{!! Form::select('commission_agent', $commission_agent, $transaction->commission_agent, ['class' => 'form-control select2', 'id' => 'commission_agent', 'required' => !empty($pos_settings['is_commission_agent_required'])]); !!}
							</div>
						@endif

						@if(!empty($transaction->selling_price_group_id))
							<div class="rr-field">
								<div class="rr-field__head">
									<label class="rr-field__label" for="price_group_text">@lang('lang_v1.price_group')</label>
									@show_tooltip(__('lang_v1.price_group_help_text'))
								</div>
								{!! Form::hidden('price_group', $transaction->selling_price_group_id, ['id' => 'price_group']) !!}
								{!! Form::text('price_group_text', $transaction->price_group->name, ['class' => 'form-control', 'readonly', 'id' => 'price_group_text']); !!}
							</div>
						@endif

						@if(in_array('types_of_service', $enabled_modules) && !empty($transaction->types_of_service))
							<div class="rr-field">
								<div class="rr-field__head">
									<label class="rr-field__label" for="types_of_service_text">@lang('lang_v1.types_of_service')</label>
									<button type="button" class="rr-link-btn service_modal_btn">{!! $icon('edit', 14) !!}@lang('sell_form.service_details')</button>
								</div>
								{!! Form::text('types_of_service_text', $transaction->types_of_service->name, ['class' => 'form-control', 'readonly', 'id' => 'types_of_service_text']); !!}
								{!! Form::hidden('types_of_service_id', $transaction->types_of_service_id, ['id' => 'types_of_service_id']) !!}
							</div>
						@endif

						@if($is_pay_term_required)
							@include('sell.partials.create_v2_pay_term')
						@endif
						@foreach($required_custom_fields as $i => $field)
							<div class="rr-field">
								{!! Form::label('custom_field_'.$i, $field['label'], ['class' => 'rr-field__label']) !!}
								{!! Form::text('custom_field_'.$i, $transaction->{'custom_field_'.$i}, ['class' => 'form-control', 'placeholder' => $field['label'], 'required' => true]); !!}
							</div>
						@endforeach

						@if(in_array('tables', $enabled_modules) || in_array('service_staff', $enabled_modules))
							<div class="rr-field--full rr-sf-legacy"><div class="row"><span id="restaurant_module_span" data-transaction_id="{{$transaction->id}}"></span></div></div>
						@endif
					</div>
					@include('sell.partials.v2_customer_card')
				</div>
			</section>

			{{-- ═══ الأصناف ═══ --}}
			<section class="rr-card rr-sf-items" aria-labelledby="rr_sf_items">
				<header class="rr-card__head">
					<span class="rr-sf-ico rr-sf-ico--info">{!! $icon('cart') !!}</span>
					<div class="rr-card__titles"><h2 id="rr_sf_items" class="rr-card__title">@lang('sell_form.sec_items')</h2></div>
				</header>
				<div class="rr-sf-items__search">
					<div class="rr-sf-group">
						<button type="button" class="rr-btn rr-btn--secondary rr-btn--icon" data-toggle="modal" data-target="#configure_search_modal" title="{{ __('lang_v1.configure_product_search') }}" aria-label="{{ __('lang_v1.configure_product_search') }}">{!! $icon('sliders') !!}</button>
						<div class="rr-sf-search">
							<span class="rr-sf-search__icon" aria-hidden="true">{!! $icon('search') !!}</span>
							{!! Form::text('search_product', null, ['class' => 'form-control mousetrap', 'id' => 'search_product', 'aria-keyshortcuts' => 'F2', 'placeholder' => __('lang_v1.search_product_placeholder'), 'autofocus' => true, 'aria-label' => __('lang_v1.search_product_placeholder')]); !!}
							<kbd class="rr-sf-kbd rr-sf-search__kbd" aria-hidden="true">F2</kbd>
						</div>
						<button type="button" class="rr-btn rr-btn--secondary pos_add_quick_product" data-href="{{ action([\App\Http\Controllers\ProductController::class, 'quickAdd']) }}" data-container=".quick_add_product_modal">{!! $icon('plus') !!}<span class="rr-sf-hide-sm">@lang('sell_form.new_product')</span></button>
					</div>
				</div>

				<input type="hidden" name="sell_price_tax" id="sell_price_tax" value="{{$business_details->sell_price_tax}}">
				<input type="hidden" id="product_row_count" value="{{count($sell_details)}}">
				<div class="rr-sf-table-wrap">
					<table class="table direct-sell-product-table rr-sf-table" id="pos_table">
						<thead>
							<tr>
								<th class="text-center serial-no-col">#</th>
								<th class="product-col">@lang('sale.product')</th>
								<th class="text-center quantity-col">@lang('sale.qty')</th>
								@if(!empty($pos_settings['inline_service_staff']))
									<th class="text-center service-staff-col">@lang('restaurant.service_staff')</th>
								@endif
								<th class="unit-price-col @if(!auth()->user()->can('edit_product_price_from_sale_screen')) hide @endif">@lang('sale.unit_price')</th>
								<th class="discount-col @if(!auth()->user()->can('edit_product_discount_from_sale_screen')) hide @endif">@lang('receipt.discount')</th>
								<th class="text-center tax-col {{$hide_tax}}">@lang('sale.tax')</th>
								<th class="text-center price-inc-tax-col {{$hide_tax}}">@lang('sale.price_inc_tax')</th>
								@if(!empty($common_settings['enable_product_warranty']))
									<th class="warranty-col">@lang('lang_v1.warranty')</th>
								@endif
								<th class="text-center subtotal-col">@lang('sale.subtotal')</th>
								<th class="text-center remove-col"><span class="rr-sr">@lang('sell_form.remove')</span></th>
							</tr>
						</thead>
						<tbody>
							@foreach($sell_details as $sell_line)
								@include('sale_pos.product_row', ['product' => $sell_line, 'row_count' => $loop->index, 'tax_dropdown' => $taxes, 'sub_units' => !empty($sell_line->unit_details) ? $sell_line->unit_details : [], 'action' => 'edit', 'is_direct_sell' => true, 'so_line' => $sell_line->so_line, 'is_sales_order' => $is_sales_order, 'is_serial_no' => true])
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="rr-sf-items__empty">
					{!! $icon('search', 28) !!}
					<p>@lang('sell_form.items_empty')</p>
				</div>
				<footer class="rr-sf-items__foot">
					<span>@lang('sell_form.items_count') <strong class="rr-num"><span class="total_quantity">0</span></strong></span>
					<span>@lang('sell_form.items_total') <strong class="rr-num"><span class="price_total">0</span></strong></span>
				</footer>
			</section>

			{{-- ═══ تفاصيل الفاتورة في تابات ═══ --}}
			<section class="rr-card rr-sf-details" aria-label="@lang('sell_form.details')">
				<nav class="rr-scope rr-tabs rr-tabs--inset rr-sf-tabs" role="tablist" aria-label="@lang('sell_form.details')">
					@foreach($tabs as $key => [$tab_icon, $tab_label, $tab_tone])
						<button type="button" class="rr-tab rr-sf-tab--{{ $tab_tone }}" role="tab" id="rr_sf_tab_{{ $key }}" aria-controls="rr_sf_panel_{{ $key }}" data-rr-sf-tab="{{ $key }}"
							aria-selected="{{ $key == $first_tab ? 'true' : 'false' }}" @if($key == $first_tab) aria-current="page" @endif tabindex="{{ $key == $first_tab ? '0' : '-1' }}">
							<span class="rr-sf-tab__ico">{!! $icon($tab_icon, 16) !!}</span>{{ $tab_label }}<span class="rr-sf-tab__dot" aria-hidden="true"></span>
						</button>
					@endforeach
				</nav>

				{{-- الدفع: نفس صفوف الدفع المسجّلة (لازم تتبعت كلها) --}}
				@if(isset($tabs['payment']))
					<div class="rr-card__body rr-sf-panel rr-sf-legacy rr-sf-pay" role="tabpanel" id="rr_sf_panel_payment" aria-labelledby="rr_sf_tab_payment" data-rr-sf-panel="payment" @if($first_tab != 'payment') hidden @endif>
						@if($show_prefer_payment)
							<div class="row rr-sf-prefer">
								<div class="col-md-6">
									<div class="form-group">
										{!! Form::label('prefer_payment_method', __('lang_v1.prefer_payment_method') . ':') !!}
										@show_tooltip(__('lang_v1.this_will_be_shown_in_pdf'))
										{!! Form::select('prefer_payment_method', $payment_types, $transaction->prefer_payment_method, ['class' => 'form-control', 'style' => 'width:100%;']); !!}
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										{!! Form::label('prefer_payment_account', __('lang_v1.prefer_payment_account') . ':') !!}
										@show_tooltip(__('lang_v1.this_will_be_shown_in_pdf'))
										{!! Form::select('prefer_payment_account', $accounts, $transaction->prefer_payment_account, ['class' => 'form-control', 'style' => 'width:100%;']); !!}
									</div>
								</div>
							</div>
						@endif
						@if($show_payment)
							@if(count($payment_rows) == 1)
								<div class="rr-sf-quickpay" role="group" aria-label="@lang('sell_form.quick_pay')">
									<button type="button" class="rr-sf-quickpay__btn" data-rr-sf-pay="full" aria-pressed="false">{!! $icon('check', 16) !!}@lang('sell_form.pay_full')</button>
									<button type="button" class="rr-sf-quickpay__btn" data-rr-sf-pay="credit" aria-pressed="false">{!! $icon('clock', 16) !!}@lang('sell_form.pay_credit')</button>
								</div>
							@endif
							<div id="payment_rows_div">
								@foreach($payment_rows as $payment_line)
									@if(!empty($payment_line['id']))
										{!! Form::hidden("payment[$loop->index][payment_id]", $payment_line['id']); !!}
									@endif
									<div class="payment_row">
										@include('sale_pos.partials.payment_row_form', ['row_index' => $loop->index, 'show_date' => true, 'payment_line' => $payment_line, 'show_denomination' => true])
									</div>
								@endforeach
							</div>
							{!! Form::hidden('change_return', $change_return['amount'], ['class' => 'form-control change_return input_number', 'required', 'id' => 'change_return']); !!}
							@if(!empty($change_return['id']))
								<input type="hidden" name="change_return_id" value="{{$change_return['id']}}">
							@endif
							<div class="row @if($change_return['amount'] == 0) hide @endif payment_row" id="change_return_payment_data">
								<div class="col-md-12"><p class="rr-sf-advance">@lang('lang_v1.change_return')</p></div>
								<div class="col-md-4">
									<div class="form-group">
										{!! Form::label('change_return_method', __('lang_v1.change_return_payment_method') . ':*') !!}
										@php
											$_payment_method = empty($change_return['method']) && array_key_exists('cash', $payment_types) ? 'cash' : $change_return['method'];
											$_payment_types = $payment_types;
											unset($_payment_types['advance']);
										@endphp
										{!! Form::select('payment[change_return][method]', $_payment_types, $_payment_method, ['class' => 'form-control col-md-12 payment_types_dropdown', 'id' => 'change_return_method', 'style' => 'width:100%;']); !!}
									</div>
								</div>
								@if(!empty($accounts))
									<div class="col-md-4">
										<div class="form-group">
											{!! Form::label('change_return_account', __('lang_v1.change_return_payment_account') . ':') !!}
											{!! Form::select('payment[change_return][account_id]', $accounts, !empty($change_return['account_id']) ? $change_return['account_id'] : '', ['class' => 'form-control select2', 'id' => 'change_return_account', 'style' => 'width:100%;']); !!}
										</div>
									</div>
								@endif
								@include('sale_pos.partials.payment_type_details', ['payment_line' => $change_return, 'row_index' => 'change_return'])
							</div>
						@endif
					</div>
				@endif

				{{-- الخصم والضريبة (+ نقاط المكافأة) --}}
				<div class="rr-card__body rr-form-wrap rr-sf-panel" role="tabpanel" id="rr_sf_panel_discount" aria-labelledby="rr_sf_tab_discount" data-rr-sf-panel="discount" @if($first_tab != 'discount') hidden @endif>
					<div class="rr-form-grid rr-form-grid--3">
						<div class="rr-field @if($is_sales_order) hide @endif">
							{!! Form::label('discount_type', __('sale.discount_type'), ['class' => 'rr-field__label']) !!}
							{!! Form::select('discount_type', ['fixed' => __('lang_v1.fixed'), 'percentage' => __('lang_v1.percentage')], $transaction->discount_type, ['class' => 'form-control', 'placeholder' => __('messages.please_select'), 'required', 'data-default' => 'percentage']); !!}
						</div>
						<div class="rr-field @if($is_sales_order) hide @endif">
							{!! Form::label('discount_amount', __('sale.discount_amount'), ['class' => 'rr-field__label']) !!}
							{!! Form::text('discount_amount', @num_format($transaction->discount_amount), ['class' => 'form-control input_number', 'data-default' => $business_details->default_sales_discount, 'data-max-discount' => $max_discount, 'data-max-discount-error_msg' => __('lang_v1.max_discount_error_msg', ['discount' => $max_discount != '' ? @num_format($max_discount) : '']) ]); !!}
						</div>
						<div class="rr-field @if($is_sales_order) hide @endif">
							{!! Form::label('tax_rate_id', __('sale.order_tax'), ['class' => 'rr-field__label']) !!}
							{!! Form::select('tax_rate_id', $taxes['tax_rates'], $transaction->tax_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control', 'data-default' => $business_details->default_sales_tax], $taxes['attributes']); !!}
							<input type="hidden" name="tax_calculation_amount" id="tax_calculation_amount" value="{{@num_format($transaction->tax?->amount)}}" data-default="{{$business_details->tax_calculation_amount}}">
						</div>

						<div class="rr-field--full rr-sf-rp @if(session('business.enable_rp') != 1 || $is_sales_order) hide @endif">
							<input type="hidden" name="rp_redeemed" id="rp_redeemed" value="{{$transaction->rp_redeemed}}">
							<input type="hidden" name="rp_redeemed_amount" id="rp_redeemed_amount" value="{{$transaction->rp_redeemed_amount}}">
							<input type="hidden" id="rp_name" value="{{session('business.rp_name')}}">
							<div class="rr-field">
								{!! Form::label('rp_redeemed_modal', session('business.rp_name').' — '.__('lang_v1.redeemed'), ['class' => 'rr-field__label']) !!}
								{!! Form::number('rp_redeemed_modal', $transaction->rp_redeemed, ['class' => 'form-control direct_sell_rp_input', 'data-amount_per_unit_point' => session('business.redeem_amount_per_unit_rp'), 'min' => 0, 'data-max_points' => !empty($redeem_details['points']) ? $redeem_details['points'] : 0, 'data-min_order_total' => session('business.min_order_total_for_redeem') ]); !!}
							</div>
							<p>@lang('lang_v1.available'): <strong class="rr-num" id="available_rp">{{$redeem_details['points'] ?? 0}}</strong></p>
							<p>@lang('lang_v1.redeemed_amount'): (-)<strong class="rr-num" id="rp_redeemed_amount_text">{{@num_format($transaction->rp_redeemed_amount)}}</strong></p>
						</div>
					</div>
				</div>

				{{-- الشحن والتوصيل + المصاريف الإضافية --}}
				<div class="rr-card__body rr-form-wrap rr-sf-panel" role="tabpanel" id="rr_sf_panel_shipping" aria-labelledby="rr_sf_tab_shipping" data-rr-sf-panel="shipping" @if($first_tab != 'shipping') hidden @endif>
					<div class="rr-form-grid rr-form-grid--3">
						<div class="rr-field">
							{!! Form::label('shipping_charges', __('sale.shipping_charges'), ['class' => 'rr-field__label']) !!}
							{!! Form::text('shipping_charges', @num_format($transaction->shipping_charges), ['class' => 'form-control input_number', 'placeholder' => __('sale.shipping_charges')]); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('shipping_status', __('lang_v1.shipping_status'), ['class' => 'rr-field__label']) !!}
							{!! Form::select('shipping_status', $shipping_statuses, $transaction->shipping_status, ['class' => 'form-control', 'placeholder' => __('messages.please_select')]); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('delivery_person', __('lang_v1.delivery_person'), ['class' => 'rr-field__label']) !!}
							{!! Form::select('delivery_person', $users, $transaction->delivery_person, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('shipping_address', __('lang_v1.shipping_address'), ['class' => 'rr-field__label']) !!}
							{!! Form::textarea('shipping_address', $transaction->shipping_address, ['class' => 'form-control', 'placeholder' => __('lang_v1.shipping_address'), 'rows' => '2', 'cols' => '30']); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('shipping_details', __('sale.shipping_details'), ['class' => 'rr-field__label']) !!}
							{!! Form::textarea('shipping_details', $transaction->shipping_details, ['class' => 'form-control', 'placeholder' => __('sale.shipping_details'), 'rows' => '2', 'cols' => '30']); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('delivered_to', __('lang_v1.delivered_to'), ['class' => 'rr-field__label']) !!}
							{!! Form::text('delivered_to', $transaction->delivered_to, ['class' => 'form-control', 'placeholder' => __('lang_v1.delivered_to')]); !!}
						</div>
						@foreach($shipping_custom_fields as $i => $field)
							<div class="rr-field">
								{!! Form::label('shipping_custom_field_'.$i, $field['label'], ['class' => 'rr-field__label']) !!}
								{!! Form::text('shipping_custom_field_'.$i, $transaction->{'shipping_custom_field_'.$i} ?: null, ['class' => 'form-control', 'placeholder' => $field['label'], 'required' => $field['required']]); !!}
							</div>
						@endforeach
						<div class="rr-field rr-field--full">
							{!! Form::label('shipping_documents', __('lang_v1.shipping_documents'), ['class' => 'rr-field__label']) !!}
							{!! Form::file('shipping_documents[]', ['id' => 'shipping_documents', 'multiple', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
							@php $medias = $transaction->media->where('model_media_type', 'shipping_document')->all(); @endphp
							<div class="rr-sf-legacy">@include('sell.partials.media_table', ['medias' => $medias, 'delete' => true])</div>
						</div>

						<div class="rr-field--full">
							<button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" id="toggle_additional_expense">{!! $icon('plus', 16) !!}@lang('lang_v1.add_additional_expenses')</button>
							<div id="additional_expenses_div" class="rr-sf-expenses" @if(! $has_expenses) style="display: none;" @endif>
								<table class="table">
									<thead>
										<tr>
											<th>@lang('lang_v1.additional_expense_name')</th>
											<th>@lang('sale.amount')</th>
										</tr>
									</thead>
									<tbody>
										@for($e = 1; $e <= 4; $e++)
											<tr>
												<td>{!! Form::text('additional_expense_key_'.$e, $transaction->{'additional_expense_key_'.$e}, ['class' => 'form-control', 'id' => 'additional_expense_key_'.$e]); !!}</td>
												<td>{!! Form::text('additional_expense_value_'.$e, @num_format($transaction->{'additional_expense_value_'.$e}), ['class' => 'form-control input_number', 'id' => 'additional_expense_value_'.$e]); !!}</td>
											</tr>
										@endfor
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				{{-- ملاحظات ومرفقات --}}
				<div class="rr-card__body rr-form-wrap rr-sf-panel" role="tabpanel" id="rr_sf_panel_notes" aria-labelledby="rr_sf_tab_notes" data-rr-sf-panel="notes" @if($first_tab != 'notes') hidden @endif>
					<div class="rr-form-grid">
						<div class="rr-field rr-field--full">
							{!! Form::label('sell_note', __('sale.sell_note'), ['class' => 'rr-field__label']) !!}
							{!! Form::textarea('sale_note', $transaction->additional_notes, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('sell_form.note_placeholder')]); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('upload_document', __('purchase.attach_document'), ['class' => 'rr-field__label']) !!}
							{!! Form::file('sell_document', ['id' => 'upload_document', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
							<p class="rr-field__hint">@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)]) @includeIf('components.document_help_text')</p>
						</div>
					</div>
				</div>

				{{-- خيارات أخرى --}}
				<div class="rr-card__body rr-form-wrap rr-sf-panel" role="tabpanel" id="rr_sf_panel_more" aria-labelledby="rr_sf_tab_more" data-rr-sf-panel="more" @if($first_tab != 'more') hidden @endif>
					<div class="rr-form-grid rr-form-grid--3">
						@if(! $is_pay_term_required)
							@include('sell.partials.create_v2_pay_term')
						@endif

						@if($transaction->status == 'draft')
							<div class="rr-field">
								{!! Form::label('invoice_scheme_id', __('invoice.invoice_scheme'), ['class' => 'rr-field__label']) !!}
								{!! Form::select('invoice_scheme_id', $invoice_schemes, $default_invoice_schemes->id, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
							</div>
						@endif

						@can('edit_invoice_number')
							<div class="rr-field">
								{!! Form::label('invoice_no', $is_sales_order ? __('restaurant.order_no') : __('sale.invoice_no'), ['class' => 'rr-field__label']) !!}
								{!! Form::text('invoice_no', $transaction->invoice_no, ['class' => 'form-control', 'placeholder' => $is_sales_order ? __('restaurant.order_no') : __('sale.invoice_no')]); !!}
							</div>
						@endcan

						@if((!empty($pos_settings['enable_sales_order']) && !$is_sales_order) || $is_order_request_enabled)
							<div class="rr-field">
								{!! Form::label('sales_order_ids', __('lang_v1.sales_order'), ['class' => 'rr-field__label']) !!}
								{!! Form::select('sales_order_ids[]', $sales_orders, $transaction->sales_order_ids, ['class' => 'form-control select2 not_loaded', 'multiple', 'id' => 'sales_order_ids']); !!}
							</div>
						@endif

						@foreach($optional_custom_fields as $i => $field)
							<div class="rr-field">
								{!! Form::label('custom_field_'.$i, $field['label'], ['class' => 'rr-field__label']) !!}
								{!! Form::text('custom_field_'.$i, $transaction->{'custom_field_'.$i}, ['class' => 'form-control', 'placeholder' => $field['label']]); !!}
							</div>
						@endforeach

						@if(in_array('subscription', $enabled_modules))
							<div class="rr-field rr-sf-check">
								<label class="rr-field__label">
									{!! Form::checkbox('is_recurring', 1, $transaction->is_recurring, ['class' => 'input-icheck', 'id' => 'is_recurring']); !!} @lang('lang_v1.subscribe')?
								</label>
								<button type="button" data-toggle="modal" data-target="#recurringInvoiceModal" class="rr-link-btn">{!! $icon('edit', 14) !!}@lang('sell_form.subscription_settings')</button>
							</div>
						@endif

						<div class="rr-field rr-field--full rr-sf-addr">
							<div>
								<span class="rr-sf-addr__label">@lang('lang_v1.billing_address')</span>
								<div id="billing_address_div">{!! $transaction->contact->contact_address ?? '' !!}</div>
							</div>
							<div>
								<span class="rr-sf-addr__label">@lang('lang_v1.shipping_address')</span>
								<div id="shipping_address_div">
									{!! $transaction->contact->supplier_business_name ?? '' !!},<br>
									{!! $transaction->contact->name ?? '' !!},<br>
									{!! $transaction->contact->shipping_address ?? '' !!}
								</div>
							</div>
						</div>

						@if($show_export)
							<label class="rr-field rr-field--full rr-sf-check" for="is_export">
								<input type="checkbox" name="is_export" id="is_export" @if(!empty($transaction->is_export)) checked @endif>
								<span class="rr-field__label">@lang('lang_v1.is_export')</span>
							</label>
							@for($i = 1; $i <= 6; $i++)
								<div class="rr-field export_div" @if(empty($transaction->is_export)) style="display: none;" @endif>
									{!! Form::label('export_custom_field_'.$i, __('lang_v1.export_custom_field'.$i), ['class' => 'rr-field__label']) !!}
									{!! Form::text('export_custom_fields_info['.'export_custom_field_'.$i.']', $transaction->export_custom_fields_info['export_custom_field_'.$i] ?? null, ['class' => 'form-control', 'placeholder' => __('lang_v1.export_custom_field'.$i), 'id' => 'export_custom_field_'.$i]); !!}
								</div>
							@endfor
						@endif
					</div>
				</div>
			</section>
		</div>

		{{-- ═══ الملخص ═══ --}}
		<aside class="rr-cols__side rr-sf-side" aria-labelledby="rr_sf_summary">
			<section class="rr-card rr-sf-summary">
				<header class="rr-card__head">
					<span class="rr-sf-ico rr-sf-ico--brand">{!! $icon('receipt') !!}</span>
					<div class="rr-card__titles"><h2 id="rr_sf_summary" class="rr-card__title">@lang('sell_form.summary')</h2></div>
				</header>
				<dl class="rr-deflist">
					<div class="rr-deflist__item"><dt>@lang('sell_form.items_count')</dt><dd class="rr-num"><span class="total_quantity">0</span></dd></div>
					<div class="rr-deflist__item"><dt>@lang('sell_form.items_total')</dt><dd class="rr-num"><span class="price_total">0</span></dd></div>
					@if(!$is_sales_order)
						<div class="rr-deflist__item">
							<dt><button type="button" class="rr-linkbtn" data-rr-sf-open="discount">@lang('sell_form.discount')</button></dt>
							<dd class="rr-num rr-sf-tone--danger">(-) <span class="display_currency" id="total_discount">0</span></dd>
						</div>
						<div class="rr-deflist__item">
							<dt><button type="button" class="rr-linkbtn" data-rr-sf-open="discount">@lang('sell_form.order_tax')</button></dt>
							<dd class="rr-num rr-sf-tone--warning">(+) <span class="display_currency" id="order_tax">{{$transaction->tax_amount}}</span></dd>
						</div>
					@endif
					<div class="rr-deflist__item" id="rr_sf_shipping_row" hidden>
						<dt><button type="button" class="rr-linkbtn" data-rr-sf-open="shipping">@lang('sell_form.shipping')</button></dt>
						<dd class="rr-num rr-sf-tone--info">(+) <span id="rr_sf_shipping_amount">0</span></dd>
					</div>
					@if($has_rounding)
						<div class="rr-deflist__item" id="round_off"><dt>@lang('lang_v1.round_off')</dt><dd class="rr-num"><span id="round_off_text">0</span></dd></div>
					@endif
					<div class="rr-deflist__item rr-sf-summary__total">
						<dt>@lang('sale.total_payable')</dt>
						<dd class="rr-num"><input type="hidden" name="final_total" id="final_total_input"><span id="total_payable">0</span></dd>
					</div>
					@if($show_payment)
						<div class="rr-deflist__item rr-sf-balance"><dt>@lang('lang_v1.balance')</dt><dd class="rr-num"><span class="balance_due">0.00</span></dd></div>
						<div class="rr-deflist__item"><dt>@lang('lang_v1.change_return')</dt><dd class="rr-num rr-sf-tone--success"><span class="change_return_span">0</span></dd></div>
					@endif
				</dl>
				@if($has_rounding)
					<input type="hidden" name="round_off_amount" id="round_off_amount" value=0>
				@endif
				<p class="rr-sf-credit-alert" id="rr_sf_credit_alert" role="alert" hidden>{!! $icon('info', 16) !!}<span></span></p>
				<div class="rr-sf-summary__actions">
					<button type="button" id="submit-sell" class="rr-btn rr-btn--primary" aria-keyshortcuts="F9">{!! $icon('check') !!}@lang('messages.update')<kbd class="rr-sf-kbd" aria-hidden="true">F9</kbd></button>
					<button type="button" id="save-and-print" class="rr-btn rr-btn--secondary" aria-keyshortcuts="Control+P">{!! $icon('print') !!}@lang('lang_v1.update_and_print')<kbd class="rr-sf-kbd" aria-hidden="true">Ctrl+P</kbd></button>
				</div>
			</section>
		</aside>
	</div>

	@include('sell.partials.v2_mobile_bar', ['save_label' => __('messages.update')])

	@if(in_array('subscription', $enabled_modules))
		@include('sale_pos.partials.recurring_invoice_modal')
	@endif

	{!! Form::close() !!}
</main>

@if(in_array('types_of_service', $enabled_modules) && !empty($transaction->types_of_service))
	<div class="modal fade types_of_service_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
		@include('types_of_service.pos_form_modal', ['types_of_service' => $transaction->types_of_service])
	</div>
@endif
<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
	@include('contact.create', ['quick_add' => true])
</div>
<div class="modal fade register_details_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>

@include('sale_pos.partials.configure_search_modal')

@stop

@section('javascript')
	<script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script>
	<script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
	<script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
	@if(in_array('tables', $enabled_modules) || in_array('modifiers', $enabled_modules) || in_array('service_staff', $enabled_modules))
		<script src="{{ asset('js/restaurant.js?v=' . $asset_v) }}"></script>
	@endif
	<script type="text/javascript">
		$(document).ready(function() {
			$('#shipping_documents').fileinput({
				showUpload: false,
				showPreview: false,
				browseLabel: LANG.file_browse_label,
				removeLabel: LANG.remove,
			});
			$('#is_export').on('change', function() {
				$('div.export_div').toggle($(this).is(':checked'));
			});
			$('#status').change(function() {
				if ($(this).val() == 'final') {
					$('#payment_rows_div').removeClass('hide');
				} else {
					$('#payment_rows_div').addClass('hide');
				}
			});
			$('.paid_on').datetimepicker({
				format: moment_date_format + ' ' + moment_time_format,
				ignoreReadonly: true,
			});

			@include('sell.partials.v2_form_js', ['exclude_id' => $transaction->id])
		});
	</script>
@endsection
