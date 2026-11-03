{{-- مدة الأجل في شاشات الفاتورة الجديدة (بتظهر فوق لو إجبارية، وإلا في "خيارات أخرى"). التعديل بيبعت $pay_term_values (number, type) --}}
<div class="rr-field">
	<div class="rr-field__head">
		{!! Form::label('pay_term_number', __('contact.pay_term'), ['class' => 'rr-field__label']) !!}
		@show_tooltip(__('tooltip.pay_term'))
	</div>
	<div class="rr-sf-pair">
		{!! Form::number('pay_term_number', isset($pay_term_values) ? $pay_term_values['number'] : $walk_in_customer['pay_term_number'], ['class' => 'form-control', 'placeholder' => __('contact.pay_term'), 'required' => $is_pay_term_required]); !!}
		{!! Form::select('pay_term_type', ['months' => __('lang_v1.months'), 'days' => __('lang_v1.days')], isset($pay_term_values) ? $pay_term_values['type'] : $walk_in_customer['pay_term_type'], ['class' => 'form-control', 'placeholder' => __('messages.please_select'), 'required' => $is_pay_term_required]); !!}
	</div>
</div>
