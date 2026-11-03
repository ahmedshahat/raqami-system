@extends('layouts.app')

@section('title', __('dashboard_v2.title'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/dashboard-v2.css?v=' . $asset_v) }}">
@endsection

@section('content')
    @php
        $greeting = now()->hour < 12 ? 'صباح الخير' : 'مساء الخير';
    @endphp

    <div class="rq-executive" dir="rtl">
        <div class="rq-loading" id="rq_dashboard_loading" aria-live="polite">
            <div class="rq-loading__mark"><span></span><span></span><span></span></div>
            <strong>نجهّز الملخص المالي والإداري…</strong>
            <small>نجمع الإيرادات والسيولة والمخزون والذمم المالية</small>
        </div>

        <section class="rq-command-header">
            <div class="rq-command-header__orb rq-command-header__orb--1"></div>
            <div class="rq-command-header__orb rq-command-header__orb--2"></div>
            <div class="rq-command-header__main">
                <div class="rq-command-header__copy">
                    <span class="rq-manager-chip"><i class="fas fa-crown"></i> إدارة النظام</span>
                    <h1>{{ $greeting }}، <b>{{ Session::get('user.first_name') }}</b></h1>
                    <p>ملخص مالي وإداري لاتخاذ القرار — نتيجة النشاط، السيولة، الذمم، المخزون، والتنبيهات — في شاشة واحدة.</p>
                </div>

                <div class="rq-command-header__filters">
                    @if (count($allLocations) > 1)
                        <label class="rq-glass-filter">
                            <i class="fas fa-map-marker-alt"></i>
                            <select id="dashboard_v2_location" aria-label="اختيار الفرع">
                                <option value="">كل الفروع</option>
                                @foreach ($allLocations as $locationId => $locationName)
                                    <option value="{{ $locationId }}">{{ $locationName }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <button type="button" id="dashboard_v2_date_filter" class="rq-glass-filter rq-glass-filter--button">
                        <i class="far fa-calendar-alt"></i>
                        <span>هذا الشهر</span>
                        <i class="fas fa-chevron-down rq-filter-chevron"></i>
                    </button>
                    <button type="button" class="rq-refresh rq-privacy-toggle" id="rq_privacy_toggle" title="إخفاء الأرقام الحساسة" aria-pressed="false"><i class="far fa-eye"></i></button>
                    <button type="button" class="rq-refresh" id="rq_dashboard_refresh" title="تحديث البيانات"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>

            <div class="rq-command-header__status">
                <span><i class="fas fa-circle"></i> بيانات مباشرة</span>
                <span id="rq_last_update">آخر تحديث الآن</span>
                <a href="{{ route('home') }}">الرئيسية القديمة <i class="fas fa-arrow-left"></i></a>
            </div>
        </section>

        <section class="rq-primary-kpis">
            <article class="rq-kpi rq-kpi--sales">
                <div class="rq-kpi__head"><span class="rq-kpi__icon"><i class="fas fa-chart-line"></i></span><span class="rq-change" data-change="sales">—</span></div>
                <span class="rq-kpi__label">إجمالي المبيعات</span>
                <strong class="rq-kpi__value" data-kpi="sales">—</strong>
                <small>شامل الضريبة مقارنة بالفترة السابقة</small>
            </article>
            <article class="rq-kpi rq-kpi--profit">
                <div class="rq-kpi__head"><span class="rq-kpi__icon"><i class="fas fa-coins"></i></span><span class="rq-kpi__meta"><b data-percent="net_margin">—</b> هامش</span></div>
                <span class="rq-kpi__label">صافي نتيجة النشاط <b class="rq-money-state" data-money-state="profit"></b></span>
                <strong class="rq-kpi__value" data-kpi="net_profit" data-semantic-money="profit">—</strong>
                <small>بعد تكلفة المبيعات والمصروفات</small>
            </article>
            <article class="rq-kpi rq-kpi--cash">
                <div class="rq-kpi__head"><span class="rq-kpi__icon"><i class="fas fa-wallet"></i></span><span class="rq-live-dot">متاح الآن</span></div>
                <span class="rq-kpi__label">النقدية وما في حكمها <b class="rq-money-state" data-money-state="cash"></b></span>
                <strong class="rq-kpi__value" data-kpi="cash_balance" data-semantic-money="cash">—</strong>
                <small>إجمالي أرصدة الخزائن والحسابات المالية</small>
            </article>
            <article class="rq-kpi rq-kpi--position">
                <div class="rq-kpi__head"><span class="rq-kpi__icon"><i class="fas fa-balance-scale"></i></span><span class="rq-kpi__meta">مؤشر تقديري</span></div>
                <span class="rq-kpi__label">صافي الموارد المتاحة <b class="rq-money-state" data-money-state="position"></b></span>
                <strong class="rq-kpi__value" data-kpi="net_position" data-semantic-money="position">—</strong>
                <small>نقدية + ذمم مدينة + مخزون − التزامات</small>
            </article>
        </section>

        <section class="rq-obligation-strip">
            <a href="{{ url('/reports/customer-supplier?contact_type=customer') }}" class="rq-obligation rq-obligation--receivable">
                <span class="rq-obligation__icon"><i class="fas fa-hand-holding-usd"></i></span>
                <span><small>الذمم المدينة للعملاء</small><strong data-kpi="receivables">—</strong></span>
                <span class="rq-obligation__note"><b data-percent="collection_rate">—</b> معدل التحصيل</span>
            </a>
            <a href="{{ url('/reports/customer-supplier?contact_type=supplier') }}" class="rq-obligation rq-obligation--payable">
                <span class="rq-obligation__icon"><i class="fas fa-file-invoice-dollar"></i></span>
                <span><small>الذمم الدائنة للموردين</small><strong data-kpi="payables">—</strong></span>
                <span class="rq-obligation__note">رصيد مستحق</span>
            </a>
            <a href="{{ url('/reports/stock-report') }}" class="rq-obligation rq-obligation--stock">
                <span class="rq-obligation__icon"><i class="fas fa-boxes"></i></span>
                <span><small>رصيد المخزون بالتكلفة</small><strong data-kpi="stock_cost">—</strong></span>
                <span class="rq-obligation__note rq-number-unit"><b data-stock="products_count">—</b><em>منتج</em></span>
            </a>
            <a href="{{ url('/reports/expense-report') }}" class="rq-obligation rq-obligation--expense">
                <span class="rq-obligation__icon"><i class="fas fa-receipt"></i></span>
                <span><small>المصروفات التشغيلية</small><strong data-kpi="expenses">—</strong></span>
                <span class="rq-change" data-change="expenses">—</span>
            </a>
        </section>

        <section class="rq-dashboard-grid rq-dashboard-grid--top">
            <article class="rq-panel rq-trend-panel">
                <header class="rq-panel__header">
                    <div><span class="rq-section-kicker">تحليل الحركة المالية</span><h2>المبيعات والمشتريات والمصروفات</h2><p>مقارنة حركة البنود المالية خلال الفترة واكتشاف الانحرافات.</p></div>
                    <div class="rq-chart-controls">
                        <select id="rq_chart_period" aria-label="تجميع الرسم"><option value="auto">تلقائي</option><option value="daily">يومي</option><option value="weekly">أسبوعي</option><option value="monthly">شهري</option></select>
                        <div class="rq-legend">
                            <button type="button" class="is-active" data-series="sales"><i class="rq-dot rq-dot--sales"></i> المبيعات</button>
                            <button type="button" class="is-active" data-series="purchases"><i class="rq-dot rq-dot--purchases"></i> المشتريات</button>
                            <button type="button" class="is-active" data-series="expenses"><i class="rq-dot rq-dot--expenses"></i> المصروفات</button>
                        </div>
                    </div>
                </header>
                <div class="rq-chart-wrap" id="rq_trend_wrap">
                    <svg id="rq_trend_chart" viewBox="0 0 900 300" preserveAspectRatio="none" role="img" aria-label="رسم حركة النشاط"></svg>
                    <div class="rq-chart-tooltip" id="rq_trend_tooltip"></div>
                </div>
            </article>

            <article class="rq-panel rq-alerts-panel">
                <header class="rq-panel__header rq-panel__header--compact">
                    <div><span class="rq-section-kicker rq-section-kicker--danger">تنبيهات رقابية</span><h2>بنود تتطلب المراجعة</h2></div>
                    <span class="rq-alert-count" id="rq_alert_count">0</span>
                </header>
                <div class="rq-alert-list" id="rq_alert_list"></div>
            </article>
        </section>

        <section class="rq-dashboard-grid rq-dashboard-grid--money">
            <article class="rq-panel rq-liquidity-panel">
                <header class="rq-panel__header"><div><span class="rq-section-kicker">مؤشرات السيولة</span><h2>النقدية والأرصدة المالية</h2><p>الأرصدة المتاحة ومدى تغطيتها للالتزامات قصيرة الأجل.</p></div><a href="{{ url('/account/account') }}" class="rq-panel-link">كشف الحسابات <i class="fas fa-arrow-left"></i></a></header>
                <div class="rq-liquidity-score">
                    <div class="rq-gauge" id="rq_liquidity_gauge"><div><strong id="rq_liquidity_percent">0%</strong><small>تغطية الالتزامات</small></div></div>
                    <div class="rq-liquidity-copy"><span>رصيد النقدية المتاح</span><strong data-kpi="cash_balance">—</strong><p id="rq_liquidity_message">جارٍ احتساب نسبة تغطية الالتزامات قصيرة الأجل.</p></div>
                </div>
                <div class="rq-account-list" id="rq_account_list"></div>
            </article>

            <article class="rq-panel rq-ageing-panel">
                <header class="rq-panel__header"><div><span class="rq-section-kicker">تحليل الذمم المدينة</span><h2>أعمار أرصدة العملاء</h2><p>تصنيف الذمم المدينة حسب فترة الاستحقاق وأولوية التحصيل.</p></div><a href="{{ url('/reports/customer-supplier?contact_type=customer') }}" class="rq-panel-link">كشف العملاء <i class="fas fa-arrow-left"></i></a></header>
                <div class="rq-ageing-content">
                    <div class="rq-donut" id="rq_ageing_donut"><div><strong data-ageing="overdue_total">—</strong><small>متأخر</small></div></div>
                    <div class="rq-ageing-legend">
                        <div><i style="--color:#38bdf8"></i><span>غير مستحق</span><strong data-ageing="current">—</strong></div>
                        <div><i style="--color:#fbbf24"></i><span>1–30 يوم</span><strong data-ageing="days_1_30">—</strong></div>
                        <div><i style="--color:#fb923c"></i><span>31–60 يوم</span><strong data-ageing="days_31_60">—</strong></div>
                        <div><i style="--color:#f87171"></i><span>61–90 يوم</span><strong data-ageing="days_61_90">—</strong></div>
                        <div><i style="--color:#be123c"></i><span>أكثر من 90 يوم</span><strong data-ageing="days_90_plus">—</strong></div>
                    </div>
                </div>
            </article>

            <article class="rq-panel rq-debtors-panel">
                <header class="rq-panel__header rq-panel__header--compact"><div><span class="rq-section-kicker">أولوية التحصيل</span><h2>أعلى أرصدة الذمم المدينة</h2></div></header>
                <div class="rq-ranking" id="rq_debtors_list"></div>
            </article>
        </section>

        <section class="rq-dashboard-grid rq-dashboard-grid--operations">
            <article class="rq-panel rq-stock-panel">
                <header class="rq-panel__header"><div><span class="rq-section-kicker">تقييم المخزون</span><h2>قيمة وحالة المخزون</h2><p>تكلفة المخزون وقيمته البيعية ومؤشرات الرقابة التشغيلية.</p></div><a href="{{ url('/reports/stock-report') }}" class="rq-panel-link">كشف المخزون <i class="fas fa-arrow-left"></i></a></header>
                <div class="rq-stock-values">
                    <div><small>تكلفة المخزون</small><strong data-kpi="stock_cost">—</strong></div>
                    <div><small>القيمة البيعية المتوقعة</small><strong data-kpi="stock_sale_value">—</strong></div>
                    <div class="rq-stock-values__profit"><small>مجمل ربح متوقع</small><strong data-kpi="stock_potential_profit">—</strong></div>
                </div>
                <div class="rq-stock-health">
                    <a href="{{ url('/reports/stock-report') }}"><i class="fas fa-cubes"></i><span>إجمالي المنتجات</span><strong data-stock="products_count">—</strong></a>
                    <a href="{{ url('/products?stock_status=out') }}" class="rq-stock-health--danger"><i class="fas fa-box-open"></i><span>نافد</span><strong data-stock="out_of_stock_count">—</strong></a>
                    <a href="{{ url('/home#stock_alert_table') }}" class="rq-stock-health--warning"><i class="fas fa-battery-quarter"></i><span>مخزون منخفض</span><strong data-stock="low_stock_count">—</strong></a>
                    <a href="{{ url('/reports/stock-report') }}" class="rq-stock-health--danger"><i class="fas fa-exclamation-triangle"></i><span>مخزون سالب</span><strong data-stock="negative_stock_count">—</strong></a>
                </div>
            </article>

            <article class="rq-panel rq-products-panel">
                <header class="rq-panel__header rq-panel__header--compact"><div><span class="rq-section-kicker">أداء المنتجات</span><h2>المنتجات الأعلى مبيعًا</h2></div><a href="{{ url('/reports/trending-products') }}" class="rq-panel-link">التفاصيل <i class="fas fa-arrow-left"></i></a></header>
                <div class="rq-ranking rq-ranking--products" id="rq_products_list"></div>
            </article>

            <article class="rq-panel rq-creditors-panel">
                <header class="rq-panel__header rq-panel__header--compact"><div><span class="rq-section-kicker rq-section-kicker--violet">تحليل الذمم الدائنة</span><h2>أعلى أرصدة الموردين الدائنة</h2></div></header>
                <div class="rq-ranking" id="rq_creditors_list"></div>
            </article>
        </section>

        <section class="rq-dashboard-grid rq-dashboard-grid--bottom">
            <article class="rq-panel rq-locations-panel">
                <header class="rq-panel__header"><div><span class="rq-section-kicker">التحليل حسب مركز النشاط</span><h2>المؤشرات المالية للفروع</h2><p>المبيعات والمشتريات والمصروفات وصافي الحركة التقديري لكل فرع.</p></div></header>
                <div class="rq-location-chart" id="rq_locations_chart"></div>
            </article>

            <article class="rq-panel rq-financial-details">
                <header class="rq-panel__header"><div><span class="rq-section-kicker">ملخص محاسبي</span><h2>قائمة الدخل والالتزامات</h2></div><a href="{{ url('/reports/profit-loss') }}" class="rq-panel-link">قائمة الدخل <i class="fas fa-arrow-left"></i></a></header>
                <div class="rq-detail-matrix">
                    <div><span>مجمل الربح</span><strong data-kpi="gross_profit">—</strong></div>
                    <div><span>المشتريات</span><strong data-kpi="purchases">—</strong></div>
                    <div><span>صافي الضريبة المستحقة</span><strong data-kpi="tax_due">—</strong></div>
                    <div><span>ذمم مبيعات الفترة</span><strong data-detail="sales_due_in_period">—</strong></div>
                    <div><span>ذمم مشتريات الفترة</span><strong data-detail="purchase_due_in_period">—</strong></div>
                    <div><span>مرتجعات المبيعات</span><strong data-detail="sales_returns">—</strong></div>
                </div>
            </article>

            <article class="rq-panel rq-quick-panel">
                <header class="rq-panel__header rq-panel__header--compact"><div><span class="rq-section-kicker">تقارير مختصرة</span><h2>الوصول المالي السريع</h2></div></header>
                <div class="rq-quick-grid">
                    <a href="{{ url('/reports/profit-loss') }}"><i class="fas fa-chart-pie"></i><span>قائمة الدخل</span></a>
                    <a href="{{ url('/reports/customer-supplier') }}"><i class="fas fa-address-book"></i><span>كشف الذمم</span></a>
                    <a href="{{ url('/account/account') }}"><i class="fas fa-university"></i><span>الحسابات المالية</span></a>
                    <a href="{{ url('/reports/stock-report') }}"><i class="fas fa-warehouse"></i><span>تقييم المخزون</span></a>
                </div>
            </article>
        </section>
    </div>
@endsection

@section('javascript')
    <script>
        window.dashboardV2Config = {
            summaryUrl: @json(route('dashboard.v2.summary')),
            contactUrl: @json(url('/contacts')),
            productUrl: @json(url('/products')),
            accountUrl: @json(url('/account/account')),
            stockReportUrl: @json(url('/reports/stock-report')),
            customerReportUrl: @json(url('/reports/customer-supplier?contact_type=customer')),
            taxReportUrl: @json(url('/reports/tax-report')),
            expenseReportUrl: @json(url('/reports/expense-report')),
            errorMessage: 'تعذر تحميل بعض بيانات لوحة المدير. راجع الاتصال ثم حاول مرة أخرى.'
        };
    </script>
    <script src="{{ asset('js/dashboard-v2.js?v=' . $asset_v) }}"></script>
@endsection
