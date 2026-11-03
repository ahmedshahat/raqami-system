{{-- سكربت كارت العميل + تنبيه حد الائتمان قبل الحفظ (جوه $(document).ready). $exclude_id = الفاتورة اللي بتتعدل --}}
@php
	$cust_url = route('sells.customer_summary', ['contact_id' => '__ID__']);
	$cust_text = ['none' => __('sell_form.cust_none'), 'no_limit' => __('sell_form.cust_no_limit'), 'over' => __('sell_form.credit_over')];
@endphp
			var CUST_URL = @json($cust_url);
			var CUST_TEXT = @json($cust_text);
			var custCredit = null, custReq = null;
			function loadCustomer(id) {
				var $card = $('#rr_sf_cust');
				custCredit = null;
				checkCredit();
				if (!id) { $card.prop('hidden', true); return; }
				if (custReq) custReq.abort();
				custReq = $.getJSON(CUST_URL.replace('__ID__', id), {exclude: @json($exclude_id)}, function(c) {
					$card.find('[data-rr-cust="mobile"]').text(c.mobile || '—');
					$card.find('[data-rr-cust="due"]').text(c.due_text).toggleClass('is-due', c.due > 0);
					$card.find('[data-rr-cust="credit"]').text(c.credit_limit === null ? CUST_TEXT.no_limit : c.credit_limit_text);
					$card.find('[data-rr-cust-available]').prop('hidden', c.credit_limit === null);
					$card.find('[data-rr-cust="available"]').text(c.credit_available_text || '').toggleClass('is-due', c.credit_limit !== null && c.credit_available <= 0);
					$card.find('[data-rr-cust="last"]').text(c.last_invoice ? c.last_invoice.invoice_no + ' · ' + c.last_invoice.date + ' · ' + c.last_invoice.total_text : CUST_TEXT.none);
					$card.find('[data-rr-cust="url"]').attr('href', c.url || '#').prop('hidden', !c.url);
					$card.prop('hidden', false);
					custCredit = c.credit_limit === null ? null : {available: c.credit_available, text: c.credit_available_text};
					checkCredit();
				});
			}
			// المتبقي على الفاتورة دي أكبر من الرصيد المتاح؟ (الخادم بيرفض الحفظ في الحالة دي، فبننبه قبلها)
			function checkCredit() {
				var status = $('#status').val(), over = false;
				if (custCredit && status == 'final' && $('#sale_type').val() != 'sales_order') {
					var paid = 0;
					$('#payment_rows_div .payment-amount').each(function() { paid += __read_number($(this)) || 0; });
					over = __read_number($('#final_total_input')) - paid > custCredit.available + 0.001;
				}
				$('#rr_sf_credit_alert').prop('hidden', !over).find('span').text(over ? CUST_TEXT.over.replace(':amount', custCredit.text) : '');
			}
			$('#customer_id').on('change', function() { loadCustomer($(this).val()); });
			loadCustomer($('#customer_id').val());
			$(document).on('invoice_total_calculated', function() { setTimeout(checkCredit, 0); });
			$(document).on('change', '.payment-amount, #status', checkCredit);
