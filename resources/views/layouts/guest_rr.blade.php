{{-- قالب الصفحات العامة قبل تسجيل الدخول بتصميم رقمي (الترحيب، الدخول، التسجيل، استعادة كلمة المرور) --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), config('constants.langs_rtl', [])) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') - @endif{{ config('app.name', 'POS') }}</title>
    <script>
        {{-- الزائر مش مسجّل، فالوضع الفاتح/الغامق من اختياره المحفوظ أو من إعداد الجهاز --}}
        (function () {
            var mode = null;
            try { mode = localStorage.getItem('rr-guest-theme'); } catch (e) {}
            if (mode !== 'light' && mode !== 'dark') {
                mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            if (mode === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>
    @include('layouts.partials.css')
    <link rel="stylesheet" href="{{ asset('css/raqmi.css?v=' . @filemtime(public_path('css/raqmi.css'))) }}">
    @if (config('constants.enable_recaptcha'))
        <script src="https://www.google.com/recaptcha/api.js"></script>
    @endif
</head>

<body class="rr-scope rr-landing @yield('body_class')">
    @if (session('status') && session('status.success'))
        <input type="hidden" id="status_span" data-status="{{ session('status.success') }}" data-msg="{{ session('status.msg') }}">
    @endif

    @include('partials.rr.guest_bar')

    <main class="rr-landing__main">
        @yield('content')
    </main>

    <footer class="rr-landing__footer">
        <div class="rr-landing__wrap rr-landing__footer-inner">
            <span>&copy; <span class="rr-num">{{ date('Y') }}</span> {{ config('app.name', 'UltimatePOS') }}</span>
            @yield('footer_extra')
        </div>
    </footer>

    {{-- layouts.partials.javascripts بيعمل @yield('javascript') بنفسه، فما نكررهوش هنا وإلا سكربتات الصفحة تشتغل مرتين --}}
    @hasSection('with_scripts')
        @include('layouts.partials.javascripts')
        <script src="{{ asset('js/login.js?v=' . $asset_v) }}"></script>
    @else
        @yield('javascript')
    @endif
    <script>
        document.getElementById('rr_theme_toggle').addEventListener('click', function () {
            var root = document.documentElement;
            var mode = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            if (mode === 'dark') { root.setAttribute('data-theme', 'dark'); } else { root.removeAttribute('data-theme'); }
            try { localStorage.setItem('rr-guest-theme', mode); } catch (e) {}
        });
    </script>
</body>
</html>
