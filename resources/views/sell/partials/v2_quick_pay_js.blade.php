{{-- دفع سريع: كامل المبلغ أو آجل بدون دفع (جوه $(document).ready). بيشتغل على أول صف دفع (#amount_0) --}}
			// pos.js في الإنشاء بيرجّع المبلغ للإجمالي مع كل حساب، وفي التعديل ما بيلمسوش،
			// فالوضع المختار بيتطبّق بعد كل حساب في الحالتين. في التعديل الوضع بيتحدد من المبلغ المسجّل.
			var payMode = 'full';
			if ($('#edit_sell_form').length) {
				var paidNow = __read_number($('#amount_0')), totalNow = __read_number($('#final_total_input'));
				payMode = Math.abs(paidNow - totalNow) < 0.005 ? 'full' : (paidNow == 0 ? 'credit' : 'manual');
			}
			function applyPayMode() {
				if (payMode == 'credit') {
					__write_number($('#amount_0'), 0);
				} else if (payMode == 'full') {
					__write_number($('#amount_0'), __read_number($('#final_total_input')));
				}
				$('[data-rr-sf-pay]').each(function() { $(this).attr('aria-pressed', $(this).data('rr-sf-pay') == payMode ? 'true' : 'false'); });
			}
			$('[data-rr-sf-pay]').on('click', function() {
				payMode = $(this).data('rr-sf-pay');
				applyPayMode();
				$('#amount_0').trigger('change');
			});
			$(document).on('invoice_total_calculated', function() { if (payMode != 'manual') applyPayMode(); });
			// لو كتب مبلغ بإيده الزرارين بيتلغي تحديدهم
			$('#amount_0').on('input', function() { payMode = 'manual'; applyPayMode(); });
			applyPayMode();
