{{-- شريط ثابت تحت على الموبايل (أقل من 992px): الإجمالي + زرار الحفظ. الأزرار بتدوس على أزرار الملخص الأصلية --}}
<div class="rr-sf-mbar no-print" role="region" aria-label="@lang('sell_form.summary')">
	<button type="button" class="rr-sf-mbar__total" data-rr-sf-go="summary" aria-label="@lang('sell_form.show_summary')">
		<span>@lang('sale.total_payable') · <span class="rr-num" data-rr-sf-mcount>0</span> @lang('sell_form.items_short')</span>
		<strong class="rr-num" data-rr-sf-mtotal>0</strong>
	</button>
	<button type="button" class="rr-btn rr-btn--primary" data-rr-sf-submit>{!! view('partials.rr.icon', ['name' => 'check', 'size' => 18])->render() !!}{{ $save_label }}</button>
</div>
