{{-- سكربت شاشة الكاشير الجديدة (sale_pos.create_v2): التبديل بين السلة والأصناف، الفلاتر، الاختصارات، تنبيهات السطر، نافذة الدفع النقدي --}}
<script>
    $(document).ready(function() {
        var $body = $('.rr-pos__body');

        // ── الموبايل والتابلت: تبديل بين السلة والأصناف ──
        var $views = $('[data-rr-pos-view]');
        function showView(view) {
            $body.attr('data-rr-pos-active', view);
            $views.each(function() { $(this).attr('aria-selected', $(this).data('rr-pos-view') == view ? 'true' : 'false'); });
        }
        $views.on('click', function() { showView($(this).data('rr-pos-view')); });

        // ── السلة الفاضية + عدد القطع على تاب السلة ──
        var $tbody = $('#pos_table tbody');
        function updateCart() {
            var empty = !$tbody.find('tr').length;
            $('[data-rr-pos-empty]').prop('hidden', !empty);
            $('#pos_table').toggleClass('rr-pos-table--empty', empty);
            $('[data-rr-pos-count]').text($('span.total_quantity').first().text() || 0);
            // الشحن في الملخص بيظهر بس لو له قيمة
            $('[data-rr-pos-shipping]').toggleClass('rr-pos-sum__row--zero', !(__read_number($('#shipping_charges')) > 0));
        }
        updateCart();
        new MutationObserver(updateCart).observe($tbody[0], {childList: true});
        $(document).on('invoice_total_calculated', function() { setTimeout(updateCart, 0); });

        // ── فلاتر الأصناف: التصنيف الرئيسي يظهر تصنيفاته الفرعية، والصفحة ترجع 1 قبل ما pos.js يحمّل ──
        $(document).on('mousedown keydown', '.rr-pos-chip.main-category, .rr-pos-chip.product_category', function() {
            $('#suggestion_page').val(1);
        });
        $('.rr-pos-chip.main-category').on('click', function() {
            $('.rr-pos-chip.main-category, .rr-pos-chip.product_category').attr('aria-pressed', 'false');
            $(this).attr('aria-pressed', 'true');
            $('[data-rr-pos-sub]').prop('hidden', true);
            $('[data-rr-pos-sub="' + $(this).data('value') + '"]').prop('hidden', false);
        });
        $('.rr-pos-chip.product_category').on('click', function() {
            $(this).closest('[data-rr-pos-sub]').find('.product_category').attr('aria-pressed', 'false');
            $(this).attr('aria-pressed', 'true');
        });

        // ── صفوف التصنيفات: صف واحد بيتسحب بالعرض، وأسهم على الطرفين لما يكون فيه تصنيفات مش ظاهرة ──
        var chipsRtl = $('html').attr('dir') === 'rtl';
        var chipsState = function(row) {
            var max = row.scrollWidth - row.clientWidth, pos = Math.abs(row.scrollLeft);
            row.classList.toggle('is-more-start', max > 2 && pos > 2);
            row.classList.toggle('is-more-end', max > 2 && pos < max - 2);
        };
        $('.rr-pos-chips').each(function() {
            var row = this;
            $.each(['start', 'end'], function(i, side) {
                $('<button type="button" tabindex="-1" aria-hidden="true">')
                    .addClass('rr-pos-chips__nav rr-pos-chips__nav--' + side)
                    .html(side === 'start' ? @json($icon('chevron-back', 16)) : @json($icon('chevron-forward', 16)))
                    .on('click', function() {
                        // scrollLeft في العربي بالسالب: ناحية "النهاية" يعني شمال
                        var step = row.clientWidth * 0.7 * (side === 'end' ? 1 : -1) * (chipsRtl ? -1 : 1);
                        row.scrollBy({left: step, behavior: 'smooth'});
                    })[side === 'start' ? 'prependTo' : 'appendTo'](row);
            });
            // عجلة الماوس العادية (لفوق/لتحت) بتحرّك الصف بالعرض
            row.addEventListener('wheel', function(e) {
                if (row.scrollWidth <= row.clientWidth || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
                e.preventDefault();
                row.scrollBy({left: e.deltaY * (chipsRtl ? -1 : 1), behavior: 'auto'});
            }, {passive: false});
            row.addEventListener('scroll', function() { chipsState(row); }, {passive: true});
            if (window.ResizeObserver) new ResizeObserver(function() { chipsState(row); }).observe(row);
            chipsState(row);
        });
        // التصنيف اللي اتختار بيبان كامل، والصف الفرعي يبدأ من أوله
        $('.rr-pos-chip.main-category, .rr-pos-chip.product_category').on('click', function() {
            this.scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'smooth'});
        });
        $('[data-rr-pos-brand]').on('change', function() {
            $('.product_brand[data-value="' + $(this).val() + '"]').trigger('click');
        });
        // تحميل الصفحة التالية من الأصناف: نفس منطق pos.js بس بهامش بسيط (الارتفاع بيطلع بكسور فالشرط الأصلي ما بيتحققش)،
        // ومرة واحدة لحد ما الأصناف الجديدة توصل
        var $grid = $('#product_list_body'), loadedAt = 0;
        $grid.off('scroll').on('scroll', function() {
            if (this.scrollTop + this.clientHeight < this.scrollHeight - 40 || this.scrollHeight === loadedAt) return;
            if ($grid.find('#no_products_found').length) return;
            loadedAt = this.scrollHeight;
            $('#suggestion_page').val(parseInt($('#suggestion_page').val()) + 1);
            get_product_suggestion_list(global_p_category_id, global_brand_id, $('input#location_id').val(), null,
                $('#is_enabled_stock').length ? $('#is_enabled_stock').val() : null,
                $('#repair_model_id').length ? $('#repair_model_id').val() : null);
        });

        $('#show_featured_products').on('click', function() {
            $(this).attr('aria-pressed', $(this).attr('aria-pressed') == 'true' ? 'false' : 'true');
        });
        // على التابلت: بعد ما تختار صنف من القائمة، السلة بتفضل زي ما هي (الصنف بيتضاف والعدد بيزيد على التاب)

        // ── نافذة خيارات الفاتورة: القوائم بتفتح جوه النافذة، ولو فيه حقل إجباري فاضي النافذة بتفتح قبل الدفع ──
        var $options = $('#rr_pos_options_modal');
        $options.find('select.select2').each(function() {
            var $s = $(this);
            if ($s.data('select2')) $s.select2('destroy');
            $s.select2({dropdownParent: $options});
        });
        var optionsMissing = function() {
            if (!$options.length) return false;
            var empty = $options.find('[required]').filter(function() { return !$(this).val(); });
            if (!empty.length) return false;
            $options.find('[data-rr-options-error]').prop('hidden', false);
            $options.modal('show');
            return true;
        };
        $options.on('hidden.bs.modal', function() { $(this).find('[data-rr-options-error]').prop('hidden', true); });
        // capture: قبل ما pos.js يشتغل على الزرار
        var foot = document.querySelector('.rr-pos-cart__foot');
        if (foot) foot.addEventListener('click', function(e) {
            var btn = e.target.closest('.rr-pos-pay button, .rr-pos-actions button');
            if (!btn || btn.id === 'pos-cancel' || !optionsMissing()) return;
            e.preventDefault();
            e.stopImmediatePropagation();
        }, true);

        // ── اختصارات الكيبورد (القائمة في v2_shortcuts): مفاتيح F بتشتغل حتى والمؤشر في خانة البحث ──
        var clickIf = function(sel) { var $b = $(sel).filter(':not(:disabled)').first(); if ($b.length) $b.trigger('click'); };
        var fnKeys = {
            F1: function() { $('#rr_pos_keys_modal').modal('show'); },
            F2: function() { showView('cart'); $('#search_product').focus().select(); },
            F3: function() { clickIf('#recent-transactions'); },
            F4: function() { showView('cart'); $('#customer_id').select2('open'); },
            F7: function() { clickIf('#pos-edit-discount'); },
            F8: function() { if ($('[data-rr-pos-cash]').length && !optionsMissing()) clickIf('[data-rr-pos-cash]'); },
            F9: function() { if ($('#pos-finalize').length && !optionsMissing()) clickIf('#pos-finalize'); },
            F10: function() { if (!optionsMissing()) clickIf('.rr-pos-actions .pos-express-finalize[data-pay_method="suspend"]'); }
        };
        $(document).on('keydown', function(e) {
            // F1 بيقفل نافذة الاختصارات لو مفتوحة؛ غير كده المفاتيح دي مش بتشتغل وفيه نافذة مفتوحة
            if (e.key === 'F1' && $('#rr_pos_keys_modal').hasClass('in')) { e.preventDefault(); $('#rr_pos_keys_modal').modal('hide'); return; }
            if ($('.modal.in').length || e.altKey || e.metaKey) return;
            if (fnKeys[e.key] && !e.ctrlKey && !e.shiftKey) { e.preventDefault(); fnKeys[e.key](); return; }
            if (e.key === 'Delete' && e.ctrlKey) { e.preventDefault(); clickIf('#pos-cancel'); return; }
            // Esc في خانة البحث بيمسحها
            if (e.key === 'Escape' && document.activeElement && document.activeElement.id === 'search_product' && document.activeElement.value !== '') {
                e.preventDefault();
                $('#search_product').val('').trigger('input');
                $('.ui-autocomplete').hide();
            }
        });

        // ── تنبيهات سطر الصنف: الكمية أكبر من المتاح، أو السعر بعد الخصم أقل من التكلفة ──
        var WARN_STOCK = @json(__('sell_form.warn_stock')), WARN_COST = @json(__('sell_form.warn_cost'));
        var checkRows = function() {
            $tbody.find('tr').each(function() {
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
                $tr.toggleClass('rr-pos-line--warn', overStock || belowCost).toggleClass('rr-pos-line--stock', overStock);
                var $warn = $tr.find('td.product-col .rr-sf-warn');
                if (!msgs.length) { $warn.remove(); return; }
                if (!$warn.length) $warn = $('<div class="rr-sf-warn" role="status"></div>').appendTo($tr.find('td.product-col'));
                $warn.empty().append($.map(msgs, function(m) {
                    var parts = m[0].split(m[1]);
                    return $('<span>').append(document.createTextNode(parts[0]), $('<bdi class="rr-num">').text(m[2]), document.createTextNode(parts[1] || ''))[0];
                }));
            });
        };
        $(document).on('invoice_total_calculated', function() { setTimeout(checkRows, 0); });
        setTimeout(checkRows, 0);
        $('#pos_table').on('input change', 'input.pos_quantity, input.pos_unit_price, input.row_discount_amount, select.row_discount_type, select.sub_unit', function() { setTimeout(checkRows, 0); });
        $(document).on('hidden.bs.modal', '.row_edit_product_price_model', function() { setTimeout(checkRows, 0); });

        // ── نافذة الدفع النقدي: المبلغ المستلم والباقي، وبعدها زرار الدفع السريع الأصلي ──
        var $cash = $('#rr_pos_cash_modal');
        if ($cash.length) {
            var $received = $('#rr_pos_cash_received'), due = 0;
            var money = function(n) { return __currency_trans_from_en(n, true); };
            var refreshChange = function() {
                var received = __read_number($received), change = received - due;
                $('[data-rr-cash-change]').text(money(Math.max(change, 0)));
                $('[data-rr-cash-change-box]').toggleClass('rr-pos-cash__change--on', change > 0.0001);
                $('[data-rr-cash-error]').prop('hidden', true);
                $received.removeClass('rr-sf-input--warn');
            };
            // مبالغ سريعة: المبلغ بالضبط + أقرب فئات أكبر منه
            var quickAmounts = function() {
                var list = [due];
                [5, 10, 20, 50, 100, 200, 500, 1000].forEach(function(d) {
                    var v = Math.ceil(due / d) * d;
                    if (v > due + 0.0001 && list.indexOf(v) < 0 && list.length < 5) list.push(v);
                });
                var $wrap = $('[data-rr-cash-quick]').empty();
                list.forEach(function(v, i) {
                    $('<button type="button" class="rr-pos-cash__amount">')
                        .toggleClass('rr-pos-cash__amount--exact', i === 0)
                        .attr('data-amount', v)
                        .append(i === 0 ? $('<span>').text(@json(__('pos_screen.cash_exact'))) : null, $('<bdi class="rr-num">').text(__number_f(v, false)))
                        .appendTo($wrap);
                });
            };
            $('[data-rr-pos-cash]').on('click', function() {
                if ($tbody.find('.product_row').length <= 0) { toastr.warning(LANG.no_products_added); return; }
                due = __read_number($('#final_total_input'));
                $('[data-rr-cash-due]').text(money(due));
                __write_number($received, due);
                quickAmounts();
                refreshChange();
                $cash.modal('show');
            });
            $cash.on('shown.bs.modal', function() { $received.focus().select(); });
            $cash.on('hidden.bs.modal', function() { $('#search_product').focus(); });
            $received.on('input change', refreshChange);
            $cash.on('click', '.rr-pos-cash__amount', function() {
                __write_number($received, parseFloat($(this).data('amount')));
                refreshChange();
                $received.focus().select();
            });
            var confirmCash = function() {
                var received = __read_number($received);
                if (received + 0.0001 < due) {
                    $('[data-rr-cash-error]').prop('hidden', false);
                    $received.addClass('rr-sf-input--warn').focus().select();
                    return;
                }
                // المبلغ المستلم بيتكتب في أول سطر دفع، وpos.js بيحسب الباقي (change_return) ويحفظ زي الدفع السريع بالظبط
                var $first = $('#payment_rows_div').find('.payment-amount').first();
                __write_number($first, received);
                $first.trigger('change');
                $cash.modal('hide');
                $('[data-rr-pos-cash-submit]').trigger('click');
            };
            $('[data-rr-cash-confirm]').on('click', confirmCash);
            $received.on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); confirmCash(); } });
        }
        // ── منع حفظ الفاتورة مرتين: Enter مرتين بسرعة (أو Enter وضغطة) كانت بتعمل فاتورتين بنفس الرقم ──
        // pos.js بيقفل الأزرار، بس زرار الدفع السريع المخفي بيتداس بالكود فبيعدّي؛ فطلب الحفظ التاني بيتلغي طول ما الأول شغال
        var saleXhr = null;
        var isSaleSave = function(s) {
            return s.type === 'POST' && s.url === $('form#add_pos_sell_form, form#edit_pos_sell_form').attr('action');
        };
        $(document).on('ajaxSend', function(e, xhr, s) {
            if (!isSaleSave(s)) return;
            if (saleXhr) { xhr.abort(); return; }
            saleXhr = xhr;
        }).on('ajaxComplete', function(e, xhr) {
            if (xhr === saleXhr) saleXhr = null;
        });

        // ── نافذة الدفع المتعدد (v2_payment_modal) ──
        // سطر الدفع الجديد بيجي بتصميم رقمي: pos.js بيطلبه من get_payment_row فبنزوّد v2=1 على الطلب
        $.ajaxPrefilter(function(options) {
            if ((options.url || '').indexOf('/sells/pos/get_payment_row') !== -1 && typeof options.data === 'string') options.data += '&v2=1';
        });
        var $pay = $('#modal_payment');
        // الملخص بيتلوّن: الباقي للعميل أخضر، والمتبقي (هيتسجل آجل على العميل) برتقالي مع تنبيه
        var paintPaySummary = function() {
            var balance = __read_number($('#in_balance_due')), change = __read_number($('input#change_return'));
            $pay.find('[data-rr-pay-change]').toggleClass('is-on', change > 0.0001);
            $pay.find('[data-rr-pay-balance]').toggleClass('is-on', balance > 0.0001);
            $pay.find('[data-rr-pay-credit]').prop('hidden', !(balance > 0.0001));
            $pay.find('[data-rr-pay-advance]').prop('hidden', !(parseFloat($('#advance_balance').val()) > 0));
        };
        if ($pay.length && window.MutationObserver) {
            var payObserver = new MutationObserver(function() { setTimeout(paintPaySummary, 0); });
            $pay.find('span.total_paying, span.balance_due, span.change_return_span').each(function() {
                payObserver.observe(this, {childList: true, characterData: true, subtree: true});
            });
        }
        $pay.on('shown.bs.modal', paintPaySummary);
        // Enter في نافذة البطاقة = إتمام الدفع
        $('#card_details_modal').on('keydown', 'input', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#pos-save-card').trigger('click'); }
        });

        // ── رسائل التنبيه في الشاشة دي: زرار إغلاق ومدة أقصر ──
        toastr.options.closeButton = true;
        toastr.options.timeOut = 2500;
        toastr.options.extendedTimeOut = 500;

        // ── نافذة المرتجع: فوكس على رقم الفاتورة، و Enter (أو مسح الباركود) = متابعة (pos.js بيتحقق ويحوّل) ──
        var $returnNo = $('#send_for_sell_return_invoice_no');
        $('#rr_pos_return_modal').on('shown.bs.modal', function() { $returnNo.val('').focus(); });
        $returnNo.on('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#send_for_sell_return').trigger('click'); }
        });

        // ── صوت المسح: نغمة قصيرة لما الصنف يتضاف، ونغمة واطية للخطأ (بتتقفل من "المزيد" وبتتحفظ على الجهاز) ──
        var soundOn = true, audio = null;
        try { soundOn = localStorage.getItem('rr_pos_sound') !== 'off'; } catch (e) {}
        var $soundBtn = $('[data-rr-pos-sound]');
        var paintSound = function() { $soundBtn.attr('aria-checked', soundOn ? 'true' : 'false'); };
        paintSound();
        $soundBtn.on('click', function() {
            soundOn = !soundOn;
            try { localStorage.setItem('rr_pos_sound', soundOn ? 'on' : 'off'); } catch (e) {}
            paintSound();
            if (soundOn) beep(true);
        });
        function beep(ok) {
            if (!soundOn) return;
            try {
                audio = audio || new (window.AudioContext || window.webkitAudioContext)();
                var osc = audio.createOscillator(), gain = audio.createGain(), t = audio.currentTime;
                osc.type = ok ? 'sine' : 'square';
                osc.frequency.value = ok ? 1320 : 220;
                gain.gain.setValueAtTime(ok ? 0.12 : 0.08, t);
                gain.gain.exponentialRampToValueAtTime(0.001, t + (ok ? 0.09 : 0.3));
                osc.connect(gain).connect(audio.destination);
                osc.start(t);
                osc.stop(t + (ok ? 0.1 : 0.32));
            } catch (e) {}
        }

        // ── السطر الحالي: آخر صنف اتضاف أو اتزود بيلمع ويبقى هو اللي الاختصارات بتشتغل عليه ──
        function setActive($tr, flash) {
            if (!$tr || !$tr.length) return;
            $tbody.find('tr.rr-pos-line--active').removeClass('rr-pos-line--active');
            $tr.addClass('rr-pos-line--active');
            if (flash) {
                $tr.removeClass('rr-pos-line--flash');
                void $tr[0].offsetWidth;
                $tr.addClass('rr-pos-line--flash');
            }
            var box = document.querySelector('.rr-pos-cart__scroll'), r = $tr[0].getBoundingClientRect(), b = box.getBoundingClientRect();
            var head = $('#pos_table thead').outerHeight() || 0;
            if (r.top < b.top + head) box.scrollTop -= b.top + head - r.top;
            else if (r.bottom > b.bottom) box.scrollTop += r.bottom - b.bottom;
        }
        new MutationObserver(function(list) {
            list.forEach(function(m) {
                $(m.addedNodes).filter('tr.product_row').each(function() { setActive($(this), true); beep(true); });
            });
            if (!$tbody.find('tr.rr-pos-line--active').length) setActive($tbody.find('tr.product_row').last(), false);
        }).observe($tbody[0], {childList: true});
        // التعديل: الفاتورة بتفتح وفيها أصناف، فآخر سطر هو المحدد
        setActive($tbody.find('tr.product_row').last(), false);
        // نفس الصنف اتمسح تاني: pos.js بيزود الكمية من غير ما يضيف سطر (والفوكس لسه في خانة البحث)
        $('#pos_table').on('change', 'input.pos_quantity', function() {
            if (document.activeElement && document.activeElement.id === 'search_product') {
                setActive($(this).closest('tr'), true);
                beep(true);
            }
        });
        $('#pos_table').on('mousedown focusin', 'tr.product_row', function() { setActive($(this), false); });

        // ── أخطاء البحث بالباركود (صنف مش موجود / مش متاح): صوت + خانة البحث بتحمر ──
        var toastrError = toastr.error;
        toastr.error = function() {
            if (document.activeElement && document.activeElement.id === 'search_product') {
                beep(false);
                var $search = $('#search_product').addClass('rr-pos-search__input--error');
                setTimeout(function() { $search.removeClass('rr-pos-search__input--error'); }, 1200);
            }
            return toastrError.apply(this, arguments);
        };

        // ── اختصارات السطور: الأسهم تتنقل، + و - للكمية، Delete يمسح السطر ──
        $(document).on('keydown', function(e) {
            if ($('.modal.in').length || e.ctrlKey || e.altKey || e.metaKey) return;
            var el = document.activeElement, inSearch = el && el.id === 'search_product';
            var typing = el && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) && !inSearch;
            // من خانة البحث بس لو فاضية (عشان الأكواد اللي فيها "-")، أو لو مفيش خانة عليها الفوكس
            if (typing || (inSearch && el.value !== '') || $('.ui-autocomplete:visible').length) return;
            var $rows = $tbody.find('tr.product_row'), $cur = $rows.filter('.rr-pos-line--active');
            if (!$rows.length) return;
            if (!$cur.length) $cur = $rows.last();
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                e.preventDefault();
                var i = $rows.index($cur) + (e.key === 'ArrowUp' ? -1 : 1);
                setActive($rows.eq(Math.max(0, Math.min($rows.length - 1, i))), false);
            } else if (e.key === '+' || e.key === '-') {
                e.preventDefault();
                $cur.find(e.key === '+' ? '.quantity-up' : '.quantity-down').trigger('click');
                setActive($cur, true);
            } else if (e.key === 'Delete') {
                e.preventDefault();
                var $next = $cur.next('tr.product_row').length ? $cur.next('tr.product_row') : $cur.prev('tr.product_row');
                $cur.find('.pos_remove_row').trigger('click');
                setActive($next, false);
            }
        });

        // ── الطباعة التلقائية بعد البيع: مقفولة افتراضيًا والطباعة من نافذة "تم البيع"؛ بتتفتح لكل جهاز من "المزيد" ──
        var autoPrint = false, holdPrint = false;
        try { autoPrint = localStorage.getItem('rr_pos_auto_print') === 'on'; } catch (e) {}
        var $autoPrintBtn = $('[data-rr-pos-autoprint]');
        $autoPrintBtn.attr('aria-checked', autoPrint ? 'true' : 'false').on('click', function() {
            autoPrint = !autoPrint;
            try { localStorage.setItem('rr_pos_auto_print', autoPrint ? 'on' : 'off'); } catch (e) {}
            $autoPrintBtn.attr('aria-checked', autoPrint ? 'true' : 'false');
        });
        // pos.js بينادي pos_print جوه callback الحفظ؛ لو البيع نهائي (هيظهر له نافذة) الطباعة بتستنى زرار "طباعة"
        var corePrint = window.pos_print;
        window.pos_print = function(receipt) {
            if (holdPrint) return;
            return corePrint.apply(this, arguments);
        };
        $.ajaxPrefilter(function(options) {
            if (options.type !== 'POST' || options.url !== $('.rr-pos__form').attr('action') || typeof options.success !== 'function') return;
            var success = options.success;
            options.success = function(result) {
                holdPrint = !autoPrint && result && result.success == 1 && !!result.sale;
                try { return success.apply(this, arguments); } finally { holdPrint = false; }
            };
        });

        // ── نافذة "تم البيع": بتظهر بعد البيع النهائي (مش المعلّق ولا المسودة) ──
        var $done = $('#rr_pos_done_modal'), lastReceipt = null, lastSale = null, pending = null;
        var formUrl = $('.rr-pos__form').attr('action');
        // في التعديل pos.js بيحوّل لشاشة البيع بعد 4 ثواني؛ مع النافذة التحويل بيستنى زرار "فاتورة جديدة"
        var isEdit = $('#edit_pos_sell_form').length > 0, redirectTo = null;
        $(document).ajaxSend(function(e, xhr, settings) {
            if (settings.type !== 'POST' || settings.url !== formUrl) return;
            // الإجمالي والباقي قبل ما pos.js يفضّي الفورم
            pending = {total: __read_number($('#final_total_input')), change: __read_number($('input#change_return'))};
        });
        $(document).ajaxSuccess(function(e, xhr, settings) {
            if (settings.type !== 'POST' || settings.url !== formUrl || !pending) return;
            var r = xhr.responseJSON, sale = r && r.success == 1 ? r.sale : null, info = pending;
            pending = null;
            if (!sale) return;
            lastSale = sale;
            lastSale.total = info.total;
            if (isEdit && redirectTo === null) {
                redirectTo = $('#pos_redirect_url').val();
                $('#pos_redirect_url').val('#');
            }
            lastReceipt = r.receipt && (r.receipt.html_content || r.receipt.print_type == 'printer') ? r.receipt : null;
            $done.find('[data-rr-done-invoice]').text(sale.invoice_no);
            $done.find('[data-rr-done-customer]').text(sale.customer || '');
            $done.find('[data-rr-done-total]').text(__currency_trans_from_en(info.total, true));
            $done.find('[data-rr-done-change]').text(__currency_trans_from_en(info.change, true));
            $done.find('[data-rr-done-change-row]').prop('hidden', !(info.change > 0.0001));
            $done.find('[data-rr-done-print]').prop('hidden', !lastReceipt);
            // رسالة "تمت إضافة عملية البيع" مالهاش لازمة مع النافذة (وكانت بتفضل ظاهرة طول ما نافذة الطباعة مفتوحة)
            $('.toast-success').remove();
            // لو الطباعة بتفتح نافذة المتصفح، النافذة بتظهر بعدها
            setTimeout(function() { $('.toast-success').remove(); $done.modal('show'); }, 150);
        });
        $done.on('shown.bs.modal', function() { $done.find('[data-rr-done-new]').focus(); });
        $done.on('hidden.bs.modal', function() {
            if (redirectTo !== null) window.location = redirectTo;
            else $('#search_product').focus();
        });
        $done.find('[data-rr-done-new]').on('click', function() { $done.modal('hide'); });
        $done.find('[data-rr-done-print]').on('click', function() { if (lastReceipt) corePrint(lastReceipt); });
        $done.find('[data-rr-done-whatsapp]').on('click', function() {
            if (!lastSale) return;
            var text = @json(__('pos_screen.done_whatsapp_text'))
                .replace(':invoice', lastSale.invoice_no)
                .replace(':total', __number_f(lastSale.total, false) + ' ' + @json(session('currency.code', '')))
                .replace(':url', lastSale.invoice_url);
            // الرقم بيتبعت بس لو مكتوب بكود الدولة؛ غير كده واتساب بيسأل تبعته لمين
            var phone = String(lastSale.mobile || '').replace(/\D/g, '').replace(/^00/, '');
            if (phone.length < 10 || phone.charAt(0) === '0') phone = '';
            window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(text), '_blank', 'noopener');
        });
        // ── عداد "المعلّقة" في الشريط العلوي: بيتحدث من غير refresh (بعد التعليق، بعد حذف فاتورة معلّقة، ولما القائمة تتفتح) ──
        var $suspendedBtn = $('#view_suspended_sales'), deletingSuspended = false;
        var suspendedCount = parseInt($suspendedBtn.find('.rr-pos-bar__count').text(), 10) || 0;
        function setSuspendedCount(n) {
            suspendedCount = Math.max(0, n);
            var $badge = $suspendedBtn.find('.rr-pos-bar__count');
            if (!suspendedCount) { $badge.remove(); return; }
            if (!$badge.length) $badge = $('<span class="rr-pos-bar__count rr-num"></span>').appendTo($suspendedBtn);
            $badge.text(suspendedCount);
        }
        $(document).on('mousedown keydown', 'a.delete-sale.is_suspended', function() { deletingSuspended = true; });
        $(document).ajaxSuccess(function(e, xhr, settings) {
            var r = xhr.responseJSON, url = settings.url || '';
            if (settings.type === 'POST' && url === $('.rr-pos__form').attr('action') && r && r.success == 1
                && /(^|&)is_suspend=1(&|$)/.test(typeof settings.data === 'string' ? settings.data : '')) {
                setSuspendedCount(suspendedCount + 1);
            } else if (settings.type === 'DELETE' && deletingSuspended && r && r.success) {
                deletingSuspended = false;
                setSuspendedCount(suspendedCount - 1);
            } else if (url.indexOf('suspended=1') > -1) {
                // القائمة نفسها هي العدد الصح (لو حد تاني علّق أو استكمل فاتورة من جهاز تاني)
                setTimeout(function() { setSuspendedCount($('.view_modal [data-rr-suspended-item]').length); }, 0);
            }
        });
    });
</script>
