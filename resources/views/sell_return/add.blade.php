@extends('layouts.app')

{{--
    مرتجع مبيعات بتصميم رقمي (من زرار "مرتجع" في الكاشير أو من قائمة المبيعات، وبرضه تعديل مرتجع موجود).
    نفس حقول الشاشة القديمة بالظبط: sell_return.js بيعمل الحفظ بالـ ajax من form#sell_return_form ويطبع،
    والحساب في update_sell_return_total() تحت (نفس معادلة الشاشة القديمة).
    بعد الحفظ بتظهر نافذة "تم حفظ المرتجع" (الطباعة منها، أو تلقائي لو "طباعة تلقائية بعد البيع" شغالة في الكاشير).
--}}

@php
	$icon = function ($name, $size = 18) {
		return view('partials.rr.icon', ['name' => $name, 'size' => $size])->render();
	};
	$is_edit = !empty($sell->return_parent);
	$title = $is_edit ? __('return_screen.edit_title') : __('return_screen.title');
	$returns_url = action([\App\Http\Controllers\SellReturnController::class, 'index']);
	// جاي من الكاشير (?from=pos من validateInvoiceToReturn)؟ زرار الرجوع يرجّعه هناك
	$from_pos = request('from') === 'pos';
	$back_url = $from_pos ? action([\App\Http\Controllers\SellPosController::class, 'create']) : $returns_url;

	$discount_type = !empty($sell->return_parent->discount_type) ? $sell->return_parent->discount_type : $sell->discount_type;
	$discount_amount = !empty($sell->return_parent->discount_amount) ? $sell->return_parent->discount_amount : $sell->discount_amount;
	$tax_percent = !empty($sell->tax) ? $sell->tax->amount : 0;
	$transaction_date = !empty($sell->return_parent->transaction_date) ? $sell->return_parent->transaction_date : 'now';
@endphp

@section('title', $title)

@section('content')
<main class="content rr-page rr-sf rr-ret no-print" aria-labelledby="rr_ret_title">

	<header class="rr-head">
		<div class="rr-head__titles">
			<nav aria-label="{{ $title }}">
				<ol class="rr-crumbs">
					<li><a href="{{ action([\App\Http\Controllers\SellController::class, 'index']) }}">@lang('return_screen.crumb_sells')</a></li>
					<li><a href="{{ $returns_url }}">@lang('return_screen.crumb_returns')</a></li>
					<li><span aria-current="page">{{ $title }}</span></li>
				</ol>
			</nav>
			<h1 id="rr_ret_title" class="rr-head__title">{{ $title }} <span class="rr-code">{{ $sell->invoice_no }}</span></h1>
		</div>
		<div class="rr-head__actions">
			<a href="{{ $back_url }}" class="rr-btn rr-btn--secondary">{!! $icon('arrow-forward', 16) !!}{{ $from_pos ? __('return_screen.back_pos') : __('return_screen.crumb_returns') }}</a>
		</div>
	</header>

	{!! Form::hidden('location_id', $sell->location->id, ['id' => 'location_id', 'data-receipt_printer_type' => $sell->location->receipt_printer_type ]); !!}

	{!! Form::open(['url' => action([\App\Http\Controllers\SellReturnController::class, 'store']), 'method' => 'post', 'id' => 'sell_return_form', 'class' => 'rr-sf-form' ]) !!}
	{!! Form::hidden('transaction_id', $sell->id); !!}
	{!! Form::hidden('tax_id', $sell->tax_id); !!}
	{!! Form::hidden('tax_amount', 0, ['id' => 'tax_amount']); !!}
	{!! Form::hidden('tax_percent', $tax_percent, ['id' => 'tax_percent']); !!}

	<div class="rr-cols">
		<div class="rr-cols__main">

			{{-- ═══ الفاتورة الأصلية ═══ --}}
			<section class="rr-card" aria-labelledby="rr_ret_parent">
				<header class="rr-card__head">
					<span class="rr-sf-ico rr-sf-ico--info">{!! $icon('receipt') !!}</span>
					<div class="rr-card__titles"><h2 id="rr_ret_parent" class="rr-card__title">@lang('return_screen.parent')</h2></div>
					@if ($is_edit)
						<span class="rr-badge rr-badge--warning">@lang('return_screen.has_return')</span>
					@endif
				</header>
				<dl class="rr-ret-parent">
					<div><dt>@lang('sale.invoice_no')</dt><dd><span class="rr-code">{{ $sell->invoice_no }}</span></dd></div>
					<div><dt>@lang('messages.date')</dt><dd class="rr-num">{{ @format_datetime($sell->transaction_date) }}</dd></div>
					<div><dt>@lang('contact.customer')</dt><dd>{{ $sell->contact->name ?? '' }}@if (!empty($sell->contact->mobile) && empty($sell->contact->is_default)) <span class="rr-ret-parent__sub rr-num">{{ $sell->contact->mobile }}</span>@endif</dd></div>
					<div><dt>@lang('purchase.business_location')</dt><dd>{{ $sell->location->name }}</dd></div>
					<div><dt>@lang('return_screen.parent_total')</dt><dd class="rr-money">@include('partials.rr.money', ['value' => $sell->final_total])</dd></div>
				</dl>
			</section>

			{{-- ═══ الأصناف ═══ --}}
			<section class="rr-card rr-ret-items" aria-labelledby="rr_ret_items">
				<header class="rr-card__head">
					<span class="rr-sf-ico rr-sf-ico--brand">{!! $icon('undo') !!}</span>
					<div class="rr-card__titles">
						<h2 id="rr_ret_items" class="rr-card__title">@lang('return_screen.items')</h2>
						<p class="rr-card__desc">@lang('return_screen.items_hint')</p>
					</div>
					<div class="rr-ret-tools">
						<button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-rr-ret-all>{!! $icon('check', 16) !!}@lang('return_screen.return_all')</button>
						<button type="button" class="rr-btn rr-btn--ghost rr-btn--sm" data-rr-ret-clear>@lang('return_screen.clear_all')</button>
					</div>
				</header>
				<div class="rr-sf-table-wrap">
					<table class="table rr-sf-table rr-ret-table" id="sell_return_table">
						<thead>
							<tr>
								<th class="serial-no-col">#</th>
								<th class="product-col">@lang('product.product_name')</th>
								<th class="rr-ret-table__price">@lang('sale.unit_price')</th>
								<th class="rr-ret-table__sold">@lang('return_screen.sold')</th>
								<th class="rr-ret-table__qty">@lang('return_screen.return_qty')</th>
								<th class="rr-ret-table__sub">@lang('lang_v1.return_subtotal')</th>
							</tr>
						</thead>
						<tbody>
							@foreach($sell->sell_lines as $sell_line)
								@php
									$unit = !empty($sell_line->sub_unit) ? $sell_line->sub_unit : $sell_line->product->unit;
									$check_decimal = $unit->allow_decimal == 0 ? 'true' : 'false';
									$unit_name = $unit->short_name;
								@endphp
								<tr class="rr-ret-row">
									<td class="serial-no-col">{{ $loop->iteration }}</td>
									<td class="product-col">
										<span class="rr-ret-row__name">{{ $sell_line->product->name }}
											@if( $sell_line->product->type == 'variable')
												- {{ $sell_line->variations->product_variation->name}} - {{ $sell_line->variations->name}}
											@endif
										</span>
										<span class="rr-ret-row__sku rr-code">{{ $sell_line->variations->sub_sku }}</span>
									</td>
									<td class="rr-ret-table__price rr-num"><span class="display_currency" data-currency_symbol="true">{{ $sell_line->unit_price_inc_tax }}</span></td>
									<td class="rr-ret-table__sold">
										<span class="rr-num">{{ $sell_line->formatted_qty }}</span> {{ $unit_name }}
										<button type="button" class="rr-linkbtn rr-ret-row__all" data-rr-ret-row-all>@lang('return_screen.all')</button>
									</td>
									<td class="rr-ret-table__qty">
										<div class="rr-ret-qty" data-max="{{ $sell_line->quantity }}">
											<button type="button" class="rr-btn rr-btn--secondary rr-btn--sm rr-btn--icon" data-rr-ret-step="-1" aria-label="-1">{!! $icon('minus', 16) !!}</button>
											<input type="text" name="products[{{$loop->index}}][quantity]" value="{{@format_quantity($sell_line->quantity_returned)}}" class="form-control input-sm input_number return_qty input_quantity" aria-label="@lang('return_screen.return_qty') {{ $sell_line->product->name }}" data-rule-abs_digit="{{$check_decimal}}" data-msg-abs_digit="@lang('lang_v1.decimal_value_not_allowed')" data-rule-max-value="{{$sell_line->quantity}}" data-msg-max-value="@lang('validation.custom-messages.quantity_not_available', ['qty' => $sell_line->formatted_qty, 'unit' => $unit_name ])">
											<button type="button" class="rr-btn rr-btn--secondary rr-btn--sm rr-btn--icon" data-rr-ret-step="1" aria-label="+1">{!! $icon('plus', 16) !!}</button>
										</div>
										<input name="products[{{$loop->index}}][unit_price_inc_tax]" type="hidden" class="unit_price" value="{{@num_format($sell_line->unit_price_inc_tax)}}">
										<input name="products[{{$loop->index}}][sell_line_id]" type="hidden" value="{{$sell_line->id}}">
									</td>
									<td class="rr-ret-table__sub rr-num"><span class="return_subtotal"></span></td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</section>

			{{-- ═══ بيانات المرتجع ═══ --}}
			<section class="rr-card" aria-labelledby="rr_ret_details">
				<header class="rr-card__head">
					<span class="rr-sf-ico rr-sf-ico--info">{!! $icon('file') !!}</span>
					<div class="rr-card__titles"><h2 id="rr_ret_details" class="rr-card__title">@lang('return_screen.details')</h2></div>
				</header>
				<div class="rr-card__body rr-form-wrap">
					<div class="rr-form-grid rr-ret-form-grid">
						<div class="rr-field">
							{!! Form::label('invoice_no', __('sale.invoice_no'), ['class' => 'rr-field__label']) !!}
							{!! Form::text('invoice_no', !empty($sell->return_parent->invoice_no) ? $sell->return_parent->invoice_no : null, ['class' => 'form-control', 'placeholder' => __('return_screen.invoice_no_hint')]); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('transaction_date', __('messages.date'), ['class' => 'rr-field__label']) !!}
							{!! Form::text('transaction_date', @format_datetime($transaction_date), ['class' => 'form-control', 'readonly', 'required']); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('discount_type', __('purchase.discount_type'), ['class' => 'rr-field__label']) !!}
							{!! Form::select('discount_type', [ '' => __('lang_v1.none'), 'fixed' => __( 'lang_v1.fixed' ), 'percentage' => __( 'lang_v1.percentage' )], $discount_type, ['class' => 'form-control']); !!}
						</div>
						<div class="rr-field">
							{!! Form::label('discount_amount', __('purchase.discount_amount'), ['class' => 'rr-field__label']) !!}
							{!! Form::text('discount_amount', @num_format($discount_amount), ['class' => 'form-control input_number']); !!}
						</div>
					</div>
				</div>
			</section>
		</div>

		{{-- ═══ الملخص والحفظ ═══ --}}
		<aside class="rr-cols__side rr-sf-side" aria-labelledby="rr_ret_summary">
			<section class="rr-card rr-sf-summary">
				<header class="rr-card__head">
					<span class="rr-sf-ico rr-sf-ico--brand">{!! $icon('receipt') !!}</span>
					<div class="rr-card__titles"><h2 id="rr_ret_summary" class="rr-card__title">@lang('return_screen.summary')</h2></div>
				</header>
				<dl class="rr-deflist">
					<div class="rr-deflist__item"><dt>@lang('return_screen.lines')</dt><dd class="rr-num"><span id="rr_ret_lines">0</span></dd></div>
					<div class="rr-deflist__item"><dt>@lang('return_screen.items_total')</dt><dd class="rr-num"><span id="rr_ret_items_total">0</span></dd></div>
					<div class="rr-deflist__item"><dt>@lang('return_screen.discount')</dt><dd class="rr-num rr-sf-tone--danger">(-) <span id="total_return_discount">0</span></dd></div>
					<div class="rr-deflist__item">
						<dt>@lang('return_screen.tax')@if(!empty($sell->tax)) <span class="rr-ret-taxname">({{ $sell->tax->name }} {{ $sell->tax->amount + 0 }}%)</span>@endif</dt>
						<dd class="rr-num rr-sf-tone--warning">(+) <span id="total_return_tax">0</span></dd>
					</div>
					<div class="rr-deflist__item rr-sf-summary__total"><dt>@lang('return_screen.total')</dt><dd class="rr-num"><span id="net_return">0</span></dd></div>
				</dl>
				<p class="rr-ret-alert" id="rr_ret_nothing" role="alert" hidden>{!! $icon('info', 16) !!}<span>@lang('return_screen.nothing')</span></p>
				<div class="rr-sf-summary__actions">
					<button type="submit" id="rr_ret_save" class="rr-btn rr-btn--primary" aria-keyshortcuts="F9">{!! $icon('check') !!}@lang('return_screen.save')<kbd class="rr-sf-kbd" aria-hidden="true">F9</kbd></button>
					<p class="rr-ret-note">{!! $icon('info', 14) !!}<span>@lang('return_screen.refund_note')</span></p>
				</div>
			</section>
		</aside>
	</div>
	{!! Form::close() !!}
</main>

{{-- ═══ نافذة "تم حفظ المرتجع" ═══ --}}
<div class="modal fade rr-scope no-print" id="rr_ret_done_modal" tabindex="-1" role="dialog" aria-labelledby="rr_ret_done_title" data-backdrop="static">
	<div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
		<div class="modal-content rr-modal">
			<div class="rr-modal__body rr-pos-done">
				<span class="rr-pos-done__icon" aria-hidden="true">{!! $icon('check', 32) !!}</span>
				<h2 class="rr-pos-done__title" id="rr_ret_done_title">@lang('return_screen.done_title')</h2>
				<p class="rr-pos-done__meta"><span class="rr-code" data-rr-ret-done-invoice></span></p>
				<div class="rr-pos-done__sum">
					<div class="rr-pos-done__row"><span>@lang('return_screen.total')</span><strong class="rr-num" data-rr-ret-done-total></strong></div>
				</div>
			</div>
			<footer class="rr-modal__foot rr-pos-done__foot">
				<a href="{{ $back_url }}" class="rr-btn rr-btn--primary">{!! $icon('arrow-forward', 16) !!}{{ $from_pos ? __('return_screen.back_pos') : __('return_screen.done_list') }}</a>
				<button type="button" class="rr-btn rr-btn--secondary" data-rr-ret-done-print>{!! $icon('print', 16) !!}@lang('return_screen.done_print')</button>
				@if ($from_pos)
					<a href="{{ $returns_url }}" class="rr-btn rr-btn--ghost">{!! $icon('list', 16) !!}@lang('return_screen.done_list')</a>
				@endif
			</footer>
		</div>
	</div>
</div>
@stop

@section('javascript')
<script src="{{ asset('js/printer.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('js/sell_return.js?v=' . $asset_v) }}"></script>
<script type="text/javascript">
	$(document).ready(function() {
		$('form#sell_return_form').validate();
		update_sell_return_total();
	});
	$(document).on('change', 'input.return_qty, #discount_amount, #discount_type', function() {
		update_sell_return_total()
	});
	$(document).on('input', 'input.return_qty', function() {
		update_sell_return_total()
	});

	function update_sell_return_total() {
		var net_return = 0, lines = 0;
		$('table#sell_return_table tbody tr').each(function() {
			var quantity = __read_number($(this).find('input.return_qty'));
			var unit_price = __read_number($(this).find('input.unit_price'));
			var subtotal = quantity * unit_price;
			$(this).find('.return_subtotal').text(__currency_trans_from_en(subtotal, true));
			$(this).toggleClass('rr-ret-row--on', quantity > 0);
			if (quantity > 0) lines++;
			net_return += subtotal;
		});
		var discount = 0;
		if ($('#discount_type').val() == 'fixed') {
			discount = __read_number($("#discount_amount"));
		} else if ($('#discount_type').val() == 'percentage') {
			var discount_percent = __read_number($("#discount_amount"));
			discount = __calculate_amount('percentage', discount_percent, net_return);
		}
		discounted_net_return = net_return - discount;

		var tax_percent = $('input#tax_percent').val();
		var total_tax = __calculate_amount('percentage', tax_percent, discounted_net_return);
		var net_return_inc_tax = total_tax + discounted_net_return;

		$('input#tax_amount').val(total_tax);
		$('span#total_return_discount').text(__currency_trans_from_en(discount, true));
		$('span#total_return_tax').text(__currency_trans_from_en(total_tax, true));
		$('span#net_return').text(__currency_trans_from_en(net_return_inc_tax, true));
		$('#rr_ret_lines').text(lines);
		$('#rr_ret_items_total').text(__currency_trans_from_en(net_return, true));
		if (lines > 0) $('#rr_ret_nothing').prop('hidden', true);
	}

	(function() {
		var $form = $('form#sell_return_form');
		var isEdit = {{ $is_edit ? 'true' : 'false' }};

		function setQty($input, qty) {
			var max = parseFloat($input.closest('.rr-ret-qty').data('max')) || 0;
			__write_number($input, Math.min(Math.max(qty, 0), max));
			$input.trigger('change');
		}

		// −/+ و"الكل" لكل صنف، و"إرجاع الكل" / "تصفير" لكل الأصناف
		$(document).on('click', '[data-rr-ret-step]', function() {
			var $input = $(this).closest('.rr-ret-qty').find('input.return_qty');
			setQty($input, __read_number($input) + parseFloat($(this).data('rr-ret-step')));
		});
		$(document).on('click', '[data-rr-ret-row-all]', function() {
			var $input = $(this).closest('tr').find('input.return_qty');
			setQty($input, parseFloat($input.closest('.rr-ret-qty').data('max')) || 0);
		});
		$('[data-rr-ret-all]').on('click', function() {
			$('input.return_qty').each(function() { setQty($(this), parseFloat($(this).closest('.rr-ret-qty').data('max')) || 0); });
		});
		$('[data-rr-ret-clear]').on('click', function() {
			$('input.return_qty').each(function() { setQty($(this), 0); });
		});
		$(document).on('focus', 'input.return_qty', function() { this.select(); });

		// مرتجع جديد من غير ولا صنف = مرتجع فاضي؛ نوقف الحفظ (التعديل مسموح يصفّر)
		// capture عشان يسبق handler الحفظ بتاع jquery.validate (sell_return.js)
		$form[0].addEventListener('submit', function(e) {
			if (isEdit || $('#rr_ret_lines').text() !== '0') return;
			e.preventDefault();
			e.stopImmediatePropagation();
			$('#rr_ret_nothing').prop('hidden', false);
			$('input.return_qty').first().focus();
		}, true);
		document.addEventListener('keydown', function(e) {
			if (e.key === 'F9') { e.preventDefault(); $('#rr_ret_save').trigger('click'); }
		});

		// الطباعة من نافذة "تم حفظ المرتجع" (أو تلقائي لو مفعّلة في الكاشير على الجهاز ده)
		var autoPrint = false;
		try { autoPrint = localStorage.getItem('rr_pos_auto_print') === 'on'; } catch (err) {}
		var corePrint = window.pos_print, lastReceipt = null, printUrl = null;
		window.pos_print = function(receipt) {
			lastReceipt = receipt;
			if (autoPrint) return corePrint.apply(this, arguments);
		};

		var action = $form.attr('action');
		$(document).ajaxSend(function(e, xhr, settings) {
			if (settings.type === 'POST' && settings.url === action) $('#rr_ret_save').prop('disabled', true);
		});
		$(document).ajaxComplete(function(e, xhr, settings) {
			if (settings.type === 'POST' && settings.url === action) $('#rr_ret_save').prop('disabled', false);
		});
		$(document).ajaxSuccess(function(e, xhr, settings, r) {
			if (settings.type !== 'POST' || settings.url !== action || !r || r.success != 1) return;
			var info = r.sell_return || {};
			printUrl = info.print_url || null;
			if (!r.receipt || !r.receipt.is_enabled) lastReceipt = null;
			$('[data-rr-ret-done-invoice]').text(info.invoice_no || '');
			$('[data-rr-ret-done-total]').text($('#net_return').text());
			$('.toast-success').remove();
			$('#rr_ret_done_modal').modal('show');
		});
		$('[data-rr-ret-done-print]').on('click', function() {
			if (lastReceipt) return corePrint(lastReceipt);
			if (!printUrl) return;
			$.getJSON(printUrl, function(result) {
				if (result.success == 1 && result.receipt) corePrint(result.receipt);
				else toastr.error(result.msg);
			});
		});
	})();
</script>
@endsection
