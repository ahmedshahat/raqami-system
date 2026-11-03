{{--
    شاشة العميل بتصميم رقمي: صفحة مستقلة (من غير قائمة جانبية) بتتفتح في نافذة تانية على شاشة قدام العميل.
    البيانات جاية جاهزة من شاشة الكاشير (sale_pos.partials.v2_display) عن طريق localStorage + BroadcastChannel "rr_pos_display".
    الحالات: idle (ترحيب + صور العروض) / cart (الأصناف + الإجمالي كبير) / cash (المستلم والباقي) / paid (شكرًا).
--}}
@php
    $rtl = in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl'));
    $currency = session('currency', []);
    $is_sar = strtoupper((string) ($currency['code'] ?? '')) === 'SAR';
    $symbol_html = $is_sar
        ? '<img class="rr-cd__cur sar-currency-icon" src="' . asset('img/saudi-riyal-new.svg') . '" alt="' . e($currency['code'] ?? '') . '">'
        : '<span class="rr-cd__cur">' . e($currency['symbol'] ?? '') . '</span>';
    $heading = trim(strip_tags($pos_settings['display_screen_heading'] ?? '')) !== '' ? $pos_settings['display_screen_heading'] : null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@lang('customer_display.title') - {{ $business_name }}</title>
    <style>
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 400; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.woff2') }}') format('woff2'); }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 600; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-SemiBold.woff2') }}') format('woff2'); }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: 700; src: url('{{ asset('fonts/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.woff2') }}') format('woff2'); }
    </style>
    <link rel="stylesheet" href="{{ asset('css/raqmi.css?v=' . @filemtime(public_path('css/raqmi.css'))) }}">
</head>
<body class="rr-page rr-cd" data-rr-cd-status="idle">
    {{-- ═══ الشريط العلوي: الشعار واسم المتجر والساعة ═══ --}}
    <header class="rr-cd__bar">
        <div class="rr-cd__brand">
            @if ($logo)<img src="{{ $logo }}" alt="" class="rr-cd__logo">@endif
            <span>{{ $business_name }}</span>
        </div>
        <span class="rr-cd__clock rr-num" data-rr-cd-clock></span>
        <button type="button" class="rr-cd__fs" data-rr-cd-fullscreen>@include('partials.rr.icon', ['name' => 'maximize', 'size' => 20])@lang('customer_display.fullscreen')</button>
    </header>

    {{-- ═══ الترحيب (السلة فاضية) ═══ --}}
    <section class="rr-cd__idle" data-rr-cd-view="idle">
        @if (count($carousel_images))
            <div class="rr-cd__slides" data-rr-cd-slides>
                @foreach ($carousel_images as $src)
                    <img src="{{ $src }}" alt="" class="rr-cd__slide{{ $loop->first ? ' is-on' : '' }}">
                @endforeach
            </div>
        @endif
        <div class="rr-cd__welcome">
            @if ($logo && !count($carousel_images))<img src="{{ $logo }}" alt="" class="rr-cd__welcome-logo">@endif
            <h1>@lang('customer_display.welcome')</h1>
            @if ($heading)
                <div class="rr-cd__heading">{!! $heading !!}</div>
            @else
                <p>@lang('customer_display.welcome_sub')</p>
            @endif
        </div>
    </section>

    {{-- ═══ السلة: الأصناف + الإجمالي ═══ --}}
    {{-- لو فيه صور عروض: بتفضل ظاهرة في عمود جنب الفاتورة --}}
    <main class="rr-cd__cart{{ count($carousel_images) ? ' rr-cd__cart--ads' : '' }}" data-rr-cd-view="cart" hidden>
        <section class="rr-cd__lines" aria-label="@lang('customer_display.items')">
            <div class="rr-cd__lines-head">
                <h2>@lang('customer_display.items')</h2>
                <span class="rr-cd__count" data-rr-cd-count></span>
            </div>
            <ol class="rr-cd__list" data-rr-cd-list></ol>
        </section>
        <aside class="rr-cd__sum">
            <p class="rr-cd__customer" data-rr-cd-customer hidden></p>
            <dl class="rr-cd__rows">
                <div><dt>@lang('customer_display.subtotal')</dt><dd data-rr-cd-money="subtotal"></dd></div>
                <div data-rr-cd-row="discount"><dt>@lang('customer_display.discount')</dt><dd>− <span data-rr-cd-money="discount"></span></dd></div>
                <div data-rr-cd-row="tax"><dt>@lang('customer_display.tax')</dt><dd data-rr-cd-money="tax"></dd></div>
                <div data-rr-cd-row="shipping"><dt>@lang('customer_display.shipping')</dt><dd data-rr-cd-money="shipping"></dd></div>
            </dl>
            <div class="rr-cd__total">
                <span>@lang('customer_display.total')</span>
                <strong data-rr-cd-money="total"></strong>
            </div>
            <dl class="rr-cd__cash" data-rr-cd-cash hidden>
                <div><dt>@lang('customer_display.received')</dt><dd data-rr-cd-money="received"></dd></div>
                <div class="rr-cd__change"><dt>@lang('customer_display.change')</dt><dd data-rr-cd-money="change"></dd></div>
            </dl>
        </aside>
        @if (count($carousel_images))
            <aside class="rr-cd__ads" data-rr-cd-slides aria-hidden="true">
                @foreach ($carousel_images as $src)
                    <img src="{{ $src }}" alt="" class="rr-cd__slide{{ $loop->first ? ' is-on' : '' }}">
                @endforeach
            </aside>
        @endif
    </main>

    {{-- ═══ تم البيع ═══ --}}
    <section class="rr-cd__paid" data-rr-cd-view="paid" hidden>
        <span class="rr-cd__paid-icon">@include('partials.rr.icon', ['name' => 'check', 'size' => 64])</span>
        <h1>@lang('customer_display.thanks')</h1>
        <dl class="rr-cd__paid-rows">
            <div><dt>@lang('customer_display.paid_total')</dt><dd data-rr-cd-money="total"></dd></div>
            <div class="rr-cd__change" data-rr-cd-paid-change><dt>@lang('customer_display.change')</dt><dd data-rr-cd-money="change"></dd></div>
        </dl>
        <p class="rr-cd__invoice" data-rr-cd-invoice></p>
    </section>

    <script>
        (function() {
            var KEY = 'rr_pos_display', CUR = @json($symbol_html), CUR_BEFORE = @json(session('business.currency_symbol_placement') === 'before');
            var T = { pieces: @json(__('customer_display.pieces')), invoice: @json(__('customer_display.invoice')) };
            var body = document.body, list = document.querySelector('[data-rr-cd-list]'), lastKeys = [], idleTimer = null;

            function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
            function money(v) {
                var n = '<bdi class="rr-num">' + esc(v) + '</bdi>';
                return CUR_BEFORE ? CUR + n : n + ' ' + CUR;
            }
            function letterThumb(name) { return '<span class="rr-cd__thumb rr-cd__thumb--letter">' + esc((name || '?').charAt(0)) + '</span>'; }
            // صورة الصنف لو ما اتحملتش بيظهر أول حرف مكانها
            document.addEventListener('error', function(e) {
                var img = e.target;
                if (img.tagName === 'IMG' && img.classList.contains('rr-cd__thumb')) img.outerHTML = letterThumb(img.getAttribute('data-letter'));
            }, true);
            function isZero(v) { return !v || /^[\s0.,٠]*$/.test(String(v)); }
            function show(view) {
                body.setAttribute('data-rr-cd-status', view);
                document.querySelectorAll('[data-rr-cd-view]').forEach(function(el) { el.hidden = el.getAttribute('data-rr-cd-view') !== view; });
            }
            function setMoney(scope, s) {
                scope.querySelectorAll('[data-rr-cd-money]').forEach(function(el) {
                    var k = el.getAttribute('data-rr-cd-money');
                    if (k in s) el.innerHTML = money(s[k]);
                });
            }

            function render(s) {
                if (!s || !s.status) return;
                clearTimeout(idleTimer);
                if (s.status === 'paid') {
                    var paid = document.querySelector('[data-rr-cd-view="paid"]');
                    setMoney(paid, s);
                    paid.querySelector('[data-rr-cd-paid-change]').hidden = !s.hasChange;
                    paid.querySelector('[data-rr-cd-invoice]').textContent = s.invoice ? T.invoice.replace(':number', s.invoice) : '';
                    show('paid');
                    lastKeys = [];
                    // ترجع للترحيب لوحدها لو الكاشير ما بدأش فاتورة جديدة
                    idleTimer = setTimeout(function() { show('idle'); }, Math.max(1000, 10000 - (Date.now() - (s.ts || Date.now()))));
                    return;
                }
                if (s.status === 'idle' || !s.lines || !s.lines.length) { show('idle'); lastKeys = []; return; }

                var cart = document.querySelector('[data-rr-cd-view="cart"]');
                var keys = s.lines.map(function(l) { return l.name + '|' + l.qty + '|' + l.total; });
                list.innerHTML = s.lines.map(function(l, i) {
                    var fresh = lastKeys.length && lastKeys.indexOf(keys[i]) === -1;
                    return '<li class="rr-cd__line' + (fresh ? ' is-new' : '') + '">'
                        + (l.img ? '<img class="rr-cd__thumb" src="' + esc(l.img) + '" alt="" data-letter="' + esc((l.name || '?').charAt(0)) + '">' : letterThumb(l.name))
                        + '<div class="rr-cd__line-main"><span class="rr-cd__name">' + esc(l.name) + '</span>'
                        + '<span class="rr-cd__meta"><bdi class="rr-num">' + esc(l.qty) + '</bdi>' + (l.unit ? ' ' + esc(l.unit) : '') + ' × ' + money(l.price) + '</span></div>'
                        + '<span class="rr-cd__line-total">' + money(l.total) + '</span></li>';
                }).join('');
                lastKeys = keys;
                list.scrollTop = 0;
                cart.querySelector('[data-rr-cd-count]').textContent = T.pieces.replace(':count', s.count);
                var customer = cart.querySelector('[data-rr-cd-customer]');
                customer.hidden = !s.customer;
                customer.textContent = s.customer || '';
                ['discount', 'tax', 'shipping'].forEach(function(k) { cart.querySelector('[data-rr-cd-row="' + k + '"]').hidden = isZero(s[k]); });
                setMoney(cart, s);
                var cashBox = cart.querySelector('[data-rr-cd-cash]');
                cashBox.hidden = s.status !== 'cash';
                cashBox.querySelector('.rr-cd__change').hidden = s.status !== 'cash' || isZero(s.change);
                show('cart');
                fitTotal(cart.querySelector('.rr-cd__total strong'));
            }

            // الإجمالي الكبير: لو الرقم أطول من المربع بيصغر لحد ما يدخل
            function fitTotal(el) {
                el.style.removeProperty('font-size');
                var size = parseFloat(getComputedStyle(el).fontSize);
                while (el.scrollWidth > el.clientWidth + 1 && size > 28) {
                    size -= 4;
                    el.style.setProperty('font-size', size + 'px', 'important');
                }
            }

            function readStored() { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
            var initial = readStored();
            // لقطة "شكرًا" قديمة (من فاتورة فاتت) مش بتتعرض لما الشاشة تتفتح
            if (initial && initial.status === 'paid' && Date.now() - (initial.ts || 0) > 10000) initial = null;
            render(initial);
            window.addEventListener('storage', function(e) { if (e.key === KEY) render(readStored()); });
            try { new BroadcastChannel(KEY).onmessage = function(e) { render(e.data); }; } catch (e) {}

            // الساعة
            var clock = document.querySelector('[data-rr-cd-clock]');
            function tick() { clock.textContent = new Date().toLocaleTimeString(document.documentElement.lang === 'ar' ? 'ar-EG-u-nu-latn' : undefined, {hour: '2-digit', minute: '2-digit'}); }
            tick(); setInterval(tick, 15000);

            // صور العروض: كل 6 ثواني
            // (نفس الصور في الترحيب وفي عمود الفاتورة، والاتنين ماشيين مع بعض)
            var groups = Array.prototype.map.call(document.querySelectorAll('[data-rr-cd-slides]'), function(g) { return g.querySelectorAll('.rr-cd__slide'); }), at = 0;
            if (groups.length && groups[0].length > 1) setInterval(function() {
                var next = (at + 1) % groups[0].length;
                groups.forEach(function(sl) { sl[at].classList.remove('is-on'); sl[next].classList.add('is-on'); });
                at = next;
            }, 6000);

            // ملء الشاشة (لازم ضغطة من المستخدم) + الماوس بيختفي لو مفيش حركة
            var fs = document.querySelector('[data-rr-cd-fullscreen]');
            fs.addEventListener('click', function() { (document.documentElement.requestFullscreen || function() {}).call(document.documentElement); });
            document.addEventListener('fullscreenchange', function() { fs.hidden = !!document.fullscreenElement; });
            var hideTimer = null;
            document.addEventListener('mousemove', function() {
                body.classList.remove('rr-cd--idle-mouse');
                clearTimeout(hideTimer);
                hideTimer = setTimeout(function() { body.classList.add('rr-cd--idle-mouse'); }, 3000);
            });
        })();
    </script>
</body>
</html>
