(function ($) {
    'use strict';

    var config = window.dashboardV2Config || {};
    var state = {
        start: moment().subtract(29, 'days').startOf('day'),
        end: moment(),
        data: null,
        privateMode: false,
        chartPeriod: 'auto',
        visibleSeries: { sales: true, purchases: true, expenses: true }
    };
    var $dateFilter = $('#dashboard_v2_date_filter');
    var $locationFilter = $('#dashboard_v2_location');

    function number(value) {
        var parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function latinDigits(value) {
        return String(value)
            .replace(/[٠-٩]/g, function (digit) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit); })
            .replace(/[۰-۹]/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit); });
    }

    function currency(value) {
        // The system formatter applies the configured currency symbol and its
        // before/after placement; only its digit glyphs are normalised here.
        var formatted = typeof __currency_trans_from_en === 'function'
            ? __currency_trans_from_en(number(value), true)
            : number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
        return latinDigits(formatted);
    }

    function currencyMarkup(value) {
        var amount = typeof __currency_trans_from_en === 'function'
            ? __currency_trans_from_en(number(value), false)
            : number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
        amount = latinDigits(amount);
        var symbol = typeof __currency_symbol !== 'undefined' ? latinDigits(__currency_symbol) : '';
        var currencyCode = String($('#__code').val() || '').toUpperCase();
        var numberPart = '<span class="rq-money__number">' + escapeHtml(amount) + '</span>';
        var symbolPart = currencyCode === 'SAR'
            ? '<span class="rq-money__symbol rq-money__symbol--sar" role="img" aria-label="ريال سعودي"></span>'
            : (symbol ? '<span class="rq-money__symbol">' + escapeHtml(symbol) + '</span>' : '');
        var isAfter = typeof __currency_symbol_placement === 'undefined' || __currency_symbol_placement === 'after';
        return '<span class="rq-money">' + (isAfter ? numberPart + symbolPart : symbolPart + numberPart) + '</span>';
    }

    function quantityWithUnit(value, unit) {
        var formatted = typeof __currency_trans_from_en === 'function'
            ? __currency_trans_from_en(number(value), false, false, undefined, true)
            : number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
        return { number: latinDigits(formatted), unit: unit };
    }

    function numberWithUnit(value, unit, maximumFractionDigits) {
        var formatted = number(value).toLocaleString('en-US', {
            maximumFractionDigits: maximumFractionDigits == null ? 2 : maximumFractionDigits
        });
        return latinDigits(formatted) + '\u00a0' + unit;
    }

    function compactCurrency(value) {
        var compact = compactNumber(value);
        if (typeof __currency_symbol === 'undefined' || !__currency_symbol) return compact;
        var formatted = latinDigits(__currency_symbol_placement === 'after'
            ? compact + ' ' + __currency_symbol
            : __currency_symbol + ' ' + compact);
        return '\u202b' + formatted + '\u202c';
    }

    function chartNumber(value) {
        var amount = Math.abs(number(value));
        var scaled = amount >= 1000000000 ? number(value) / 1000000000
            : amount >= 1000000 ? number(value) / 1000000
                : amount >= 1000 ? number(value) / 1000 : number(value);
        return latinDigits(Math.abs(scaled % 1) > 0.001 ? scaled.toFixed(1) : Math.round(scaled).toString());
    }

    function compactNumber(value) {
        if (state.privateMode) return '••••';
        var amount = Math.abs(number(value));
        if (amount >= 1000000000) return (number(value) / 1000000000).toFixed(1) + ' مليار';
        if (amount >= 1000000) return (number(value) / 1000000).toFixed(1) + ' مليون';
        if (amount >= 1000) return (number(value) / 1000).toFixed(1) + ' ألف';
        return Math.round(number(value)).toLocaleString('en-US');
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function setValues(data) {
        $('[data-kpi]').each(function () {
            var key = $(this).data('kpi');
            var value = number(data.kpis[key]);
            var semantic = $(this).data('semantic-money');
            var displayValue = value;
            var $stateBadge = semantic ? $(this).closest('.rq-kpi').find('[data-money-state="' + semantic + '"]') : $();
            $(this).removeClass('is-negative is-positive');
            $stateBadge.removeClass('is-loss is-profit').text('');
            if (semantic === 'profit') {
                displayValue = Math.abs(value);
                $stateBadge.text(value < 0 ? 'خسارة' : 'ربح').addClass(value < 0 ? 'is-loss' : 'is-profit');
                $(this).addClass(value < 0 ? 'is-negative' : 'is-positive');
            } else if (semantic === 'cash') {
                displayValue = Math.abs(value);
                $stateBadge.text(value < 0 ? 'عجز سيولة' : 'رصيد متاح').addClass(value < 0 ? 'is-loss' : 'is-profit');
                $(this).addClass(value < 0 ? 'is-negative' : 'is-positive');
            } else if (semantic === 'position') {
                displayValue = Math.abs(value);
                $stateBadge.text(value < 0 ? 'عجز تقديري' : 'فائض تقديري').addClass(value < 0 ? 'is-loss' : 'is-profit');
                $(this).addClass(value < 0 ? 'is-negative' : 'is-positive');
            }
            $(this).html(currencyMarkup(displayValue));
        });
        $('[data-percent]').each(function () {
            var key = $(this).data('percent');
            $(this).text(number(data.kpis[key]).toFixed(1) + '%');
        });
        $('[data-detail]').each(function () {
            $(this).html(currencyMarkup(data.details[$(this).data('detail')]));
        });
        $('[data-stock]').each(function () {
            $(this).text(number(data.stock[$(this).data('stock')]).toLocaleString('en-US'));
        });
        $('[data-ageing]').each(function () {
            $(this).html(currencyMarkup(data.ageing[$(this).data('ageing')]));
        });

        setChange('sales', data.comparisons.sales, true);
        setChange('expenses', data.comparisons.expenses, false);
    }

    function groupedTrend(points) {
        if (state.chartPeriod === 'auto' || state.chartPeriod === 'daily' || !points.length) return points;
        var grouped = {};
        points.forEach(function (point, index) {
            var groupKey;
            var label;
            if (state.chartPeriod === 'weekly') {
                if (String(points[0].key || '').length === 7) return points;
                groupKey = 'week-' + Math.floor(index / 7);
                label = 'أسبوع ' + (Math.floor(index / 7) + 1);
            } else {
                groupKey = String(point.key).slice(0, 7);
                label = groupKey.slice(5, 7) + '/' + groupKey.slice(0, 4);
            }
            if (!grouped[groupKey]) grouped[groupKey] = { key: groupKey, label: label, sales: 0, purchases: 0, expenses: 0 };
            grouped[groupKey].sales += number(point.sales);
            grouped[groupKey].purchases += number(point.purchases);
            grouped[groupKey].expenses += number(point.expenses);
        });
        return Object.keys(grouped).map(function (key) { return grouped[key]; });
    }

    function setChange(key, value, positiveIsGood) {
        var $element = $('[data-change="' + key + '"]');
        var current = number(value);
        var up = current >= 0;
        var good = positiveIsGood ? up : !up;
        var icon = up ? '▲' : '▼';
        $element.removeClass('is-up is-down').addClass(good ? 'is-up' : 'is-down');
        $element.text(icon + ' ' + Math.abs(current).toFixed(1) + '%');
    }

    function renderTrend(points) {
        points = groupedTrend(points);
        var svg = document.getElementById('rq_trend_chart');
        if (!svg) return;
        var width = 900, height = 300, left = 48, right = 12, top = 18, bottom = 35;
        var plotWidth = width - left - right, plotHeight = height - top - bottom;
        var values = [];
        var activeKeys = ['sales', 'purchases', 'expenses'].filter(function (key) { return state.visibleSeries[key]; });
        points.forEach(function (point) { activeKeys.forEach(function (key) { values.push(number(point[key])); }); });
        var maximum = Math.max.apply(null, values.concat([1]));
        var stepX = points.length > 1 ? plotWidth / (points.length - 1) : plotWidth;

        function y(value) { return top + plotHeight - ((number(value) / maximum) * plotHeight); }
        function pathFor(key) {
            return points.map(function (point, index) { return (index ? 'L' : 'M') + (left + index * stepX).toFixed(1) + ',' + y(point[key]).toFixed(1); }).join(' ');
        }

        var salesPath = pathFor('sales');
        var html = '<defs><linearGradient id="rqSalesGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1687ff" stop-opacity=".22"/><stop offset="100%" stop-color="#1687ff" stop-opacity="0"/></linearGradient></defs>';
        for (var grid = 0; grid <= 4; grid++) {
            var gridY = top + (plotHeight / 4) * grid;
            html += '<line class="rq-chart-grid" x1="' + left + '" y1="' + gridY + '" x2="' + (width - right) + '" y2="' + gridY + '"/>';
            html += '<text class="rq-chart-label" x="' + (left - 8) + '" y="' + (gridY + 3) + '" text-anchor="end">' + escapeHtml(chartNumber(maximum * (1 - grid / 4))) + '</text>';
        }
        if (points.length && state.visibleSeries.sales) {
            html += '<path class="rq-chart-area-sales" d="' + salesPath + ' L' + (left + (points.length - 1) * stepX) + ',' + (top + plotHeight) + ' L' + left + ',' + (top + plotHeight) + ' Z"/>';
        }
        activeKeys.forEach(function (key) {
            html += '<path class="rq-chart-line rq-chart-line--' + key + '" d="' + pathFor(key) + '"/>';
        });

        points.forEach(function (point, index) {
            var x = left + index * stepX;
            if (index % Math.max(1, Math.ceil(points.length / 7)) === 0 || index === points.length - 1) {
                html += '<text class="rq-chart-label" x="' + x + '" y="' + (height - 10) + '" text-anchor="middle">' + escapeHtml(point.label) + '</text>';
            }
            activeKeys.forEach(function (key) {
                html += '<circle class="rq-chart-point rq-chart-point--' + key + '" data-index="' + index + '" cx="' + x + '" cy="' + y(point[key]) + '" r="3.5"/>';
            });
        });
        svg.innerHTML = html;

        $('#rq_trend_chart .rq-chart-point').on('mouseenter mousemove', function (event) {
            var point = points[parseInt($(this).attr('data-index'), 10)];
            var $wrap = $('#rq_trend_wrap');
            var offset = $wrap.offset();
            var rows = '<strong>' + escapeHtml(point.label) + '</strong>';
            var labels = { sales: 'إجمالي المبيعات', purchases: 'المشتريات', expenses: 'المصروفات التشغيلية' };
            activeKeys.forEach(function (key) {
                rows += '<span>' + labels[key] + ' <b>' + (state.privateMode ? '••••••' : currencyMarkup(point[key])) + '</b></span>';
            });
            $('#rq_trend_tooltip').html(rows).css({ left: Math.min(event.pageX - offset.left + 10, $wrap.width() - 155), top: event.pageY - offset.top - 85 }).addClass('is-visible');
        }).on('mouseleave', function () { $('#rq_trend_tooltip').removeClass('is-visible'); });
    }

    function renderAlerts(alerts) {
        var definitions = {
            receivables: { icon: 'fa-user-clock', title: 'ذمم مدينة متأخرة لأكثر من 90 يومًا', text: 'تتطلب أولوية في إجراءات التحصيل', url: config.customerReportUrl },
            liquidity_gap: { icon: 'fa-water', title: 'عجز في تغطية الذمم الدائنة', text: 'النقدية المتاحة أقل من أرصدة الموردين', url: config.accountUrl },
            out_of_stock: { icon: 'fa-box-open', title: 'أصناف مخزون نافدة', text: 'تتطلب مراجعة دورة إعادة الطلب', url: config.stockReportUrl },
            low_stock: { icon: 'fa-battery-quarter', title: 'أصناف بلغت حد إعادة الطلب', text: 'تحتاج إلى إجراء شراء أو توريد', url: config.stockReportUrl },
            negative_stock: { icon: 'fa-exclamation-triangle', title: 'أرصدة مخزنية سالبة', text: 'تتطلب تسوية ومراجعة حركة الصنف', url: config.stockReportUrl },
            tax_due: { icon: 'fa-landmark', title: 'صافي التزام ضريبي مستحق', text: 'راجع كشف الضرائب المستحقة', url: config.taxReportUrl },
            high_expenses: { icon: 'fa-receipt', title: 'ارتفاع نسبة المصروفات إلى الإيرادات', text: 'راجع بنود المصروفات التشغيلية', url: config.expenseReportUrl },
            all_good: { icon: 'fa-check-circle', title: 'لا توجد ملاحظات رقابية جوهرية', text: 'المؤشرات الحالية ضمن الحدود المتوقعة', url: '#' }
        };
        $('#rq_alert_count').text(alerts.filter(function (alert) { return alert.severity !== 'success'; }).length);
        $('#rq_alert_list').html(alerts.map(function (alert) {
            var item = definitions[alert.type] || definitions.all_good;
            var detail = alert.amount != null ? currencyMarkup(alert.amount) : escapeHtml(alert.count != null ? numberWithUnit(alert.count, 'عنصر', 0) : item.text);
            return '<a class="rq-alert rq-alert--' + escapeHtml(alert.severity) + '" href="' + escapeHtml(item.url) + '">' +
                '<span class="rq-alert__icon"><i class="fas ' + item.icon + '"></i></span>' +
                '<span class="rq-alert__copy"><strong>' + escapeHtml(item.title) + '</strong><small>' + detail + '</small></span>' +
                '<i class="fas fa-chevron-left rq-alert__arrow"></i></a>';
        }).join(''));
    }

    function renderLiquidity(data) {
        var payables = number(data.kpis.payables), cash = number(data.kpis.cash_balance);
        var coverage = payables > 0 ? Math.max(0, Math.min(100, (cash / payables) * 100)) : 100;
        $('#rq_liquidity_gauge').css('--value', (coverage * 3.6) + 'deg');
        $('#rq_liquidity_percent').text(Math.round(coverage) + '%');
        $('#rq_liquidity_message').text(coverage >= 100 ? 'رصيد النقدية يغطي الذمم الدائنة للموردين بالكامل.' : coverage >= 60 ? 'نسبة تغطية الالتزامات مقبولة مع أهمية متابعة التحصيل.' : 'نسبة تغطية الالتزامات منخفضة وتتطلب خطة تحصيل أو جدولة سداد.');
        var accounts = (data.cash.accounts || []).slice(0, 5);
        $('#rq_account_list').html(accounts.length ? accounts.map(function (account) {
            return '<a class="rq-account" href="' + config.accountUrl + '/' + account.id + '"><i class="fas fa-university"></i><span>' + escapeHtml(account.name) + '</span><strong>' + currencyMarkup(account.balance) + '</strong></a>';
        }).join('') : '<div class="rq-empty">لا توجد حسابات مالية مفتوحة.</div>');
    }

    function renderAgeing(ageing) {
        var keys = ['current', 'days_1_30', 'days_31_60', 'days_61_90', 'days_90_plus'];
        var colors = ['#38bdf8', '#fbbf24', '#fb923c', '#f87171', '#be123c'];
        var total = keys.reduce(function (sum, key) { return sum + number(ageing[key]); }, 0);
        var cursor = 0, segments = [];
        keys.forEach(function (key, index) {
            var next = total > 0 ? cursor + (number(ageing[key]) / total) * 100 : cursor;
            if (next > cursor) segments.push(colors[index] + ' ' + cursor.toFixed(2) + '% ' + next.toFixed(2) + '%');
            cursor = next;
        });
        $('#rq_ageing_donut').css('--segments', segments.length ? 'conic-gradient(' + segments.join(',') + ')' : 'conic-gradient(#e9eef4 0 100%)');
    }

    function renderRanking(selector, rows, valueKey, urlBase, labelBuilder) {
        var maximum = Math.max.apply(null, rows.map(function (row) { return number(row[valueKey]); }).concat([1]));
        var $container = $(selector);
        $container.closest('.rq-panel').toggleClass('has-no-data', !rows.length);
        $container.html(rows.length ? rows.map(function (row, index) {
            var label = labelBuilder ? labelBuilder(row) : null;
            var valueMarkup = label && typeof label === 'object'
                ? '<span class="rq-rank__value rq-rank__value--split">' +
                    '<span class="rq-rank__metric"><small>' + escapeHtml(label.salesLabel) + '</small><b>' + currencyMarkup(label.salesValue) + '</b></span>' +
                    '<span class="rq-rank__metric"><small>' + escapeHtml(label.quantityLabel) + '</small><b class="rq-number-unit"><span>' + escapeHtml(label.quantityValue.number) + '</span><em>' + escapeHtml(label.quantityValue.unit) + '</em></b></span></span>'
                : '<span class="rq-rank__value">' + currencyMarkup(row[valueKey]) + '</span>';
            return '<a class="rq-rank" href="' + urlBase + '/' + row.id + '"><span class="rq-rank__number">' + (index + 1) + '</span>' +
                '<span class="rq-rank__copy"><strong>' + escapeHtml(row.name) + '</strong><span class="rq-rank__bar"><span style="width:' + Math.max(4, (number(row[valueKey]) / maximum) * 100) + '%"></span></span></span>' +
                valueMarkup + '</a>';
        }).join('') : '<div class="rq-empty">لا توجد بيانات خلال الفترة المختارة.</div>');
    }

    function renderLocations(rows) {
        var maximum = Math.max.apply(null, rows.map(function (row) { return number(row.sales); }).concat([1]));
        $('#rq_locations_chart').html(rows.length ? rows.map(function (row) {
            var netClass = number(row.operating_net) < 0 ? 'is-loss' : 'is-profit';
            return '<div class="rq-location"><span class="rq-location__name">' + escapeHtml(row.name) + '</span><span class="rq-location__track"><span style="width:' + Math.max(3, number(row.sales) / maximum * 100) + '%"></span></span>' +
                '<span class="rq-location__figures"><span>المبيعات</span><b>' + currencyMarkup(row.sales) + '</b><span>المشتريات</span><b>' + currencyMarkup(row.purchases) + '</b><span>المصروفات</span><b>' + currencyMarkup(row.expenses) + '</b><span>صافي الحركة</span><b class="' + netClass + '">' + currencyMarkup(row.operating_net) + '</b></span></div>';
        }).join('') : '<div class="rq-empty">لا توجد حركة مبيعات للفروع خلال الفترة المختارة.</div>');
    }

    function applyPrivacy() {
        var selector = '.rq-money__number, .rq-number-unit span';
        $(selector).each(function () {
            var $item = $(this);
            if (state.privateMode) {
                $item.attr('data-rq-real', $item.text());
                $item.text('••••••').addClass('is-masked');
            } else if ($item.attr('data-rq-real')) {
                $item.text($item.attr('data-rq-real')).removeAttr('data-rq-real').removeClass('is-masked');
            }
        });
        $('#rq_privacy_toggle').attr('aria-pressed', state.privateMode ? 'true' : 'false')
            .attr('title', state.privateMode ? 'إظهار الأرقام الحساسة' : 'إخفاء الأرقام الحساسة')
            .find('i').attr('class', state.privateMode ? 'far fa-eye-slash' : 'far fa-eye');
        if (state.data) renderTrend(state.data.trends || []);
    }

    function render(data) {
        state.data = data;
        setValues(data);
        renderTrend(data.trends || []);
        renderAlerts(data.alerts || []);
        renderLiquidity(data);
        renderAgeing(data.ageing || {});
        renderRanking('#rq_debtors_list', data.top_debtors || [], 'amount', config.contactUrl);
        renderRanking('#rq_creditors_list', data.top_creditors || [], 'amount', config.contactUrl);
        renderRanking('#rq_products_list', data.top_products || [], 'amount', config.productUrl, function (row) {
            return {
                salesLabel: 'قيمة المبيعات',
                salesValue: row.amount,
                quantityLabel: 'الكمية',
                quantityValue: quantityWithUnit(row.quantity, 'وحدة')
            };
        });
        renderLocations(data.locations || []);
        applyPrivacy();
        var updateMoment = data.meta && data.meta.generated_at ? moment.parseZone(data.meta.generated_at) : moment();
        var updateTimezone = data.meta && data.meta.timezone ? data.meta.timezone : '';
        $('#rq_last_update').text('آخر تحديث ' + latinDigits(updateMoment.format('HH:mm')))
            .attr('title', updateTimezone ? 'المنطقة الزمنية: ' + updateTimezone : '');
    }

    function loadDashboard() {
        $('#rq_dashboard_refresh').addClass('is-spinning');
        $('#rq_dashboard_loading').removeClass('is-hidden');
        $.ajax({
            method: 'GET',
            url: config.summaryUrl,
            dataType: 'json',
            data: {
                start: state.start.format('YYYY-MM-DD'),
                end: state.end.format('YYYY-MM-DD'),
                location_id: $locationFilter.length ? $locationFilter.val() : ''
            }
        }).done(render).fail(function () {
            toastr.error(config.errorMessage);
        }).always(function () {
            $('#rq_dashboard_refresh').removeClass('is-spinning');
            $('#rq_dashboard_loading').addClass('is-hidden');
        });
    }

    $(function () {
        var settings = $.extend({}, dateRangeSettings, { startDate: state.start, endDate: state.end });
        $dateFilter.daterangepicker(settings, function (start, end) {
            state.start = start;
            state.end = end;
            $dateFilter.find('span').text(latinDigits(start.format(moment_date_format) + ' — ' + end.format(moment_date_format)));
            loadDashboard();
        });
        $dateFilter.find('span').text(latinDigits(state.start.format(moment_date_format) + ' — ' + state.end.format(moment_date_format)));
        $locationFilter.on('change', loadDashboard);
        $('#rq_dashboard_refresh').on('click', loadDashboard);
        $('#rq_privacy_toggle').on('click', function () {
            state.privateMode = !state.privateMode;
            applyPrivacy();
        });
        $('#rq_chart_period').on('change', function () {
            state.chartPeriod = $(this).val();
            if (state.data) renderTrend(state.data.trends || []);
        });
        $('.rq-legend [data-series]').on('click', function () {
            var key = $(this).data('series');
            var activeCount = Object.keys(state.visibleSeries).filter(function (series) { return state.visibleSeries[series]; }).length;
            if (state.visibleSeries[key] && activeCount === 1) return;
            state.visibleSeries[key] = !state.visibleSeries[key];
            $(this).toggleClass('is-active', state.visibleSeries[key]);
            if (state.data) renderTrend(state.data.trends || []);
        });
        $(window).on('resize', function () { if (state.data) renderTrend(state.data.trends || []); });
        loadDashboard();
    });
})(jQuery);
