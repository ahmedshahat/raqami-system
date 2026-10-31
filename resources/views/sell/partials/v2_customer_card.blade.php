{{-- بيانات العميل المختار في شاشات الفاتورة الجديدة (الإنشاء والتعديل). بيتملى من sells.customer_summary --}}
<div class="rr-sf-cust" id="rr_sf_cust" aria-live="polite" hidden>
	<div class="rr-sf-cust__item">
		<span class="rr-sf-cust__label">{!! view('partials.rr.icon', ['name' => 'phone', 'size' => 14])->render() !!}@lang('sell_form.cust_mobile')</span>
		<strong class="rr-num" data-rr-cust="mobile">—</strong>
	</div>
	<div class="rr-sf-cust__item">
		<span class="rr-sf-cust__label">{!! view('partials.rr.icon', ['name' => 'wallet', 'size' => 14])->render() !!}@lang('sell_form.cust_due')</span>
		<strong class="rr-num" data-rr-cust="due">—</strong>
	</div>
	<div class="rr-sf-cust__item" data-rr-cust-credit>
		<span class="rr-sf-cust__label">{!! view('partials.rr.icon', ['name' => 'shield', 'size' => 14])->render() !!}@lang('sell_form.cust_credit')</span>
		<strong class="rr-num" data-rr-cust="credit">—</strong>
	</div>
	<div class="rr-sf-cust__item" data-rr-cust-available hidden>
		<span class="rr-sf-cust__label">{!! view('partials.rr.icon', ['name' => 'coins', 'size' => 14])->render() !!}@lang('sell_form.cust_available')</span>
		<strong class="rr-num" data-rr-cust="available">—</strong>
	</div>
	<div class="rr-sf-cust__item">
		<span class="rr-sf-cust__label">{!! view('partials.rr.icon', ['name' => 'receipt', 'size' => 14])->render() !!}@lang('sell_form.cust_last')</span>
		<strong class="rr-num" data-rr-cust="last">—</strong>
	</div>
	<a class="rr-sf-cust__link" data-rr-cust="url" href="#" target="_blank" rel="noopener" hidden>@lang('sell_form.cust_open'){!! view('partials.rr.icon', ['name' => 'arrow-back', 'size' => 14])->render() !!}</a>
</div>
