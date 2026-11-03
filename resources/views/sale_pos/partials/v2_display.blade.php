{{--
    مزامنة "شاشة العميل" (sale_pos.display) من شاشة الكاشير الجديدة.
    بيبعت لقطة جاهزة للعرض (أسماء الأصناف والكميات والأسعار والإجماليات بأرقام منسّقة) في localStorage "rr_pos_display"
    + BroadcastChannel بنفس الاسم عشان التحديث يبقى فوري. شاشة العميل مش بتحتاج تسأل السيرفر عن أي حاجة.
    الحالات: idle (السلة فاضية) / cart / cash (الكاشير فتح نافذة الدفع النقدي) / paid (تم البيع: الإجمالي والباقي).
--}}
<script>
    $(document).ready(function() {
        var KEY = 'rr_pos_display', channel = null, timer = null, paidUntil = 0, cash = null;
        try { channel = new BroadcastChannel(KEY); } catch (e) {}
        var num = function(n) { return __currency_trans_from_en(n, false); };

        function lineName($tr) {
            var $cell = $tr.find('td.product-col').first();
            var $src = $cell.find('.text-link').first();
            var html = ($src.length ? $src : $cell).html() || '';
            // الاسم قبل أول <br> (بعده الكود والعلامة)
            return $('<div>').html(html.split(/<br\s*\/?>/i)[0]).text().replace(/\s+/g, ' ').trim();
        }

        function snapshot() {
            var lines = [];
            $('#pos_table tbody tr.product_row').each(function() {
                var $tr = $(this), qty = __read_number($tr.find('input.pos_quantity'));
                var img = $tr.find('td.product-col img').attr('src') || '';
                lines.push({
                    name: lineName($tr),
                    // 1 مش 1.00
                    qty: qty % 1 === 0 ? String(qty) : __number_f(qty, false, false, __quantity_precision),
                    unit: $tr.find('select.sub_unit option:selected').data('unit_name') || '',
                    price: num(__read_number($tr.find('input.pos_unit_price_inc_tax'))),
                    total: num(__read_number($tr.find('input.pos_line_total'))),
                    img: /\/img\/default\.png/.test(img) ? '' : $.trim(img)
                });
            });
            // العميل النقدي الافتراضي مش بيتعرض
            var customer = $.trim($('#customer_id option:selected').text()).replace(/\s*\(.*\)\s*$/, '');
            var defaultCustomer = $.trim($('#default_customer_name').val() || '').replace(/\s*\(.*\)\s*$/, '');
            var state = {
                v: 1, ts: Date.now(), status: lines.length ? 'cart' : 'idle',
                customer: customer && customer !== defaultCustomer ? customer.replace(/\s*\(.*\)\s*$/, '') : '',
                lines: lines.reverse(), // الأحدث فوق زي ما الكاشير بيضيف
                count: $('span.total_quantity').first().text() || '0',
                subtotal: num(__num_uf($('span.price_total').first().text() || 0)),
                discount: num(__num_uf($('#total_discount').text() || 0)),
                tax: num(__num_uf($('#order_tax').text() || 0)),
                shipping: num(__num_uf($('#shipping_charges_amount').text() || 0)),
                total: num(__read_number($('#final_total_input')))
            };
            if (cash && lines.length) { state.status = 'cash'; state.received = num(cash.received); state.change = num(Math.max(cash.received - cash.due, 0)); }
            return state;
        }

        function send(state) {
            try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
            if (channel) { try { channel.postMessage(state); } catch (e) {} }
        }

        function publish() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                var state = snapshot();
                // بعد البيع pos.js بيفضّي السلة؛ "شكرًا" تفضل ظاهرة شوية بدل ما تتمسح فورًا
                if (state.status === 'idle' && Date.now() < paidUntil) return;
                send(state);
            }, 120);
        }

        publish();
        new MutationObserver(publish).observe($('#pos_table tbody')[0], {childList: true});
        $(document).on('invoice_total_calculated', publish);
        $('#customer_id').on('change', publish);
        $('#pos_table').on('input change', 'input, select', publish);

        // نافذة الدفع النقدي: العميل يشوف المستلم والباقي وهو بيتكتب
        var $cashModal = $('#rr_pos_cash_modal'), $received = $('#rr_pos_cash_received');
        function cashUpdate() { cash = {due: __read_number($('#final_total_input')), received: __read_number($received)}; publish(); }
        $cashModal.on('shown.bs.modal', cashUpdate).on('hidden.bs.modal', function() { cash = null; publish(); });
        $received.on('input change', cashUpdate);
        $cashModal.on('click', '.rr-pos-cash__amount', function() { setTimeout(cashUpdate, 0); });

        // تم البيع: الإجمالي والمدفوع والباقي ("شكرًا")
        var formUrl = $('.rr-pos__form').attr('action'), pending = null;
        $(document).ajaxSend(function(e, xhr, settings) {
            if (settings.type !== 'POST' || settings.url !== formUrl) return;
            pending = {total: __read_number($('#final_total_input')), change: __read_number($('input#change_return')), count: $('span.total_quantity').first().text()};
        });
        $(document).ajaxSuccess(function(e, xhr, settings) {
            if (settings.type !== 'POST' || settings.url !== formUrl || !pending) return;
            var r = xhr.responseJSON, info = pending;
            pending = null;
            if (!(r && r.success == 1 && r.sale)) return;
            paidUntil = Date.now() + 10000;
            cash = null;
            send({v: 1, ts: Date.now(), status: 'paid', invoice: r.sale.invoice_no || '', count: info.count,
                total: num(info.total), received: num(info.total + info.change), change: num(info.change), hasChange: info.change > 0.0001});
        });
    });
</script>
