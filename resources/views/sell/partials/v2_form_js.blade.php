{{-- سكربت مشترك لشاشات الفاتورة الجديدة (الإنشاء والتعديل) جوه $(document).ready: التابات، النقط، كارت العميل، الدفع السريع، ملاحظة الصنف. $exclude_id = الفاتورة اللي بتتعدل --}}
			// ── التابات ──
			var $tabs = $('[data-rr-sf-tab]');
			function openTab(key, focus) {
				$tabs.each(function() {
					var on = $(this).data('rr-sf-tab') == key;
					$(this).attr({'aria-selected': on ? 'true' : 'false', tabindex: on ? '0' : '-1'});
					if (on) { $(this).attr('aria-current', 'page'); } else { $(this).removeAttr('aria-current'); }
					if (on && focus) this.focus();
				});
				$('[data-rr-sf-panel]').each(function() { this.hidden = $(this).data('rr-sf-panel') != key; });
			}
			$tabs.on('click', function() { openTab($(this).data('rr-sf-tab'), false); });
			$tabs.on('keydown', function(e) {
				var keys = {ArrowLeft: 1, ArrowRight: -1, Home: 0, End: 0};
				if (!(e.key in keys)) return;
				e.preventDefault();
				var list = $tabs.toArray(), i = list.indexOf(this);
				if (e.key == 'Home') i = 0; else if (e.key == 'End') i = list.length - 1;
				else i = (i + keys[e.key] + list.length) % list.length; // RTL: السهم الشمال = التالي
				openTab($(list[i]).data('rr-sf-tab'), true);
			});
			// صفوف الملخص (الخصم، الضريبة، الشحن) بتفتح التاب بتاعها
			$(document).on('click', '[data-rr-sf-open]', function() {
				var key = $(this).data('rr-sf-open');
				openTab(key, false);
				var panel = document.getElementById('rr_sf_panel_' + key);
				if (panel) {
					panel.closest('.rr-sf-details').scrollIntoView({behavior: 'smooth', block: 'start'});
					$(panel).find('input:visible, select:visible, textarea:visible').first().trigger('focus');
				}
			});

			// نقطة على التاب لو فيه بيانات اتغيرت جواه (عشان ما يتنسيش)
			var fieldValue = function(el) { return el.type == 'checkbox' ? String(el.checked) : String($(el).val() || ''); };
			// الدفع مستثنى: مبلغه بيتملى لوحده مع كل صنف
			var $dataPanels = $('[data-rr-sf-panel]:not([data-rr-sf-panel="payment"])');
			$dataPanels.find('input, select, textarea').each(function() { $(this).data('rr-sf-init', fieldValue(this)); });
			function markTabs() {
				$dataPanels.each(function() {
					var key = $(this).data('rr-sf-panel');
					var changed = $(this).find('input:not([type=hidden]), select, textarea').filter(function() {
						return fieldValue(this) !== $(this).data('rr-sf-init');
					}).length > 0;
					$('#rr_sf_tab_' + key).toggleClass('has-data', changed);
				});
				// سطر الشحن في الملخص
				var shipping = __read_number($('#shipping_charges'));
				$('#rr_sf_shipping_row').prop('hidden', !(shipping > 0));
				$('#rr_sf_shipping_amount').text(__currency_trans_from_en(shipping, false));
			}
			$('.rr-sf-form').on('change keyup', '[data-rr-sf-panel] input, [data-rr-sf-panel] select, [data-rr-sf-panel] textarea', markTabs);
			markTabs();

			@include('sell.partials.v2_customer_card_js')

			@include('sell.partials.v2_quick_pay_js')

			// الدفع: "المبلغ" هنا هو المدفوع مش إجمالي الفاتورة، وسطر الرصيد المقدم يظهر بس لو للعميل رصيد
			$('.rr-sf-pay label[for^="amount_"]').text(@json(__('sell_form.paid_amount')) + ':*');
			var $advance = $('#advance_balance_text').closest('.rr-sf-advance');
			var toggleAdvance = function() { $advance.prop('hidden', !(parseFloat($('#advance_balance').val()) > 0)); };
			toggleAdvance();
			if ($advance.length) new MutationObserver(toggleAdvance).observe($('#advance_balance_text')[0], {childList: true, characterData: true, subtree: true});

			// صفوف الأصناف: الرقم التسلسلي سطر واحد بنص توضيحي جوه الخانة، وكل خانة بتاخد اسم عمودها
			// (data-label) عشان على الموبايل الصف بيتعرض كارت والعناوين بتظهر فوق كل خانة
			var LINE_NOTE = @json(__('sell_form.line_note'));
			var colLabels = $('#pos_table thead th').map(function() { return $(this).text().trim(); }).get();
			// خانة الرقم التسلسلي بتظهر بس للصنف اللي مفعّل فيه "تفعيل IMEI أو الرقم التسلسلي" في إعدادات المنتج
			// (أو لو فيها قيمة متسجلة قبل كده في التعديل)
			var markRows = function() {
				$('#pos_table tbody tr:not([data-rr-sf-labeled])').attr('data-rr-sf-labeled', 1).each(function() {
					var $note = $(this).find('textarea[name$="[sell_line_note]"]');
					var srOn = $(this).find('input.enable_sr_no').val() == 1;
					$note.addClass('rr-sf-note').attr({placeholder: LINE_NOTE, rows: 1, 'aria-label': LINE_NOTE})
						.toggleClass('rr-sf-note--off', !srOn && !$.trim($note.val()));
					$(this).children('td').each(function(i) {
						if (colLabels[i]) this.setAttribute('data-label', colLabels[i]);
					});
				});
			};
			markRows();
			new MutationObserver(markRows).observe(document.querySelector('#pos_table tbody'), {childList: true});

			// شريط الموبايل: الإجمالي وعدد الأصناف + الحفظ
			function updateMobileBar() {
				$('[data-rr-sf-mtotal]').text($('#total_payable').text());
				$('[data-rr-sf-mcount]').text($('span.total_quantity').first().text());
			}
			updateMobileBar();
			$(document).on('invoice_total_calculated', function() { setTimeout(updateMobileBar, 0); });
			$('[data-rr-sf-submit]').on('click', function() { $('#submit-sell').trigger('mousedown').trigger('click'); });
			$('[data-rr-sf-go="summary"]').on('click', function() { document.querySelector('.rr-sf-summary').scrollIntoView({behavior: 'smooth', block: 'start'}); });

			// قبل الحفظ: لو فيه حقل إجباري فاضي في تاب مقفول، التاب ده بيتفتح عشان رسالة الخطأ تبان
			$('#submit-sell, #save-and-print').on('mousedown keydown', function() {
				$('[data-rr-sf-panel]').each(function() {
					if (!this.hidden) return;
					var empty = $(this).find('[required]').filter(function() { return !$(this).val(); });
					if (empty.length) { openTab($(this).data('rr-sf-panel'), false); return false; }
				});
			});

			// اختصارات الكيبورد: F2 البحث عن صنف، F9 حفظ، Ctrl+P حفظ وطباعة
			$(document).on('keydown', function(e) {
				if ($('.modal.in').length) return;
				var key = e.key;
				if (key === 'F2') { e.preventDefault(); $('#search_product').focus().select(); }
				else if (key === 'F9') { e.preventDefault(); $('#submit-sell').trigger('mousedown').trigger('click'); }
				else if ((e.ctrlKey || e.metaKey) && (key === 'p' || key === 'P')) { e.preventDefault(); $('#save-and-print').trigger('mousedown').trigger('click'); }
			});

			// تنبيهات سطر الصنف: الكمية أكبر من المتاح في المخزن، أو السعر بعد الخصم أقل من التكلفة
			var WARN_STOCK = @json(__('sell_form.warn_stock')), WARN_COST = @json(__('sell_form.warn_cost'));
			var checkRows = function() {
				$('#pos_table tbody tr').each(function() {
					var $tr = $(this), $check = $tr.find('input.rr_sf_line_check');
					if (!$check.length) return;
					var multiplier = parseFloat($tr.find('input.base_unit_multiplier').val()) || 1;
					var $qty = $tr.find('input.pos_quantity'), msgs = [];
					var available = $check.val() === '' ? null : parseFloat($check.val());
					var overStock = available !== null && __read_number($qty) * multiplier > available + 0.0001;
					if (overStock) msgs.push([WARN_STOCK, ':qty', __number_f(available / multiplier, false)]);

					var cost = $check.data('cost') === '' ? null : parseFloat($check.data('cost'));
					var $price = $tr.find('input.pos_unit_price'), belowCost = false;
					if (cost !== null && cost > 0 && $price.length) {
						var price = __read_number($price), discount = __read_number($tr.find('input.row_discount_amount'));
						price = $tr.find('select.row_discount_type').val() === 'percentage' ? price * (1 - discount / 100) : price - discount;
						belowCost = price < cost * multiplier - 0.0001;
						if (belowCost) msgs.push([WARN_COST, ':cost', __currency_trans_from_en(cost * multiplier, true)]);
					}

					$qty.toggleClass('rr-sf-input--warn', overStock);
					$price.toggleClass('rr-sf-input--warn', belowCost);
					var $warn = $tr.find('td.product-col .rr-sf-warn');
					if (!msgs.length) { $warn.remove(); return; }
					if (!$warn.length) $warn = $('<div class="rr-sf-warn" role="status"></div>').appendTo($tr.find('td.product-col'));
					// الرقم والعملة في bdi واحدة عشان ما يتقسموش على سطرين
					$warn.empty().append($.map(msgs, function(m) {
						var parts = m[0].split(m[1]);
						return $('<span>').append(document.createTextNode(parts[0]), $('<bdi class="rr-num">').text(m[2]), document.createTextNode(parts[1] || ''))[0];
					}));
				});
			};
			checkRows();
			$(document).on('invoice_total_calculated', function() { setTimeout(checkRows, 0); });
			$('#pos_table').on('input change', 'input.pos_quantity, input.pos_unit_price, input.row_discount_amount, select.row_discount_type, select.sub_unit', function() { setTimeout(checkRows, 0); });

			// تأكيد قبل الخروج من الصفحة لو اتغير حاجة ولسه ما اتحفظتش (بنبدأ نتابع بعد التحميل، لأن pos.js بيحسب ويغيّر قيم وقت الفتح)
			var rrSaving = false, rrDirty = false;
			var trackChanges = function() {
				setTimeout(function() {
					$('#add_sell_form, #edit_sell_form').on('input change', function() { rrDirty = true; });
					new MutationObserver(function() { rrDirty = true; }).observe(document.querySelector('#pos_table tbody'), {childList: true});
				}, 500);
			};
			if (document.readyState === 'complete') trackChanges(); else $(window).on('load', trackChanges);
			$('#submit-sell, #save-and-print').on('mousedown click', function() { rrSaving = true; setTimeout(function() { rrSaving = false; }, 3000); });
			window.addEventListener('beforeunload', function(e) {
				if (rrSaving || !rrDirty || !$('#pos_table tbody tr').length) return;
				e.preventDefault();
				e.returnValue = '';
			});
