@inject('request', 'Illuminate\Http\Request')
@php
    // رقم الدعم بصيغة دولية بدون علامة + أو مسافات
    $supportWhatsappNumber = '201001179178';

    // بيانات العميل المسجل دخوله
    $supportBusinessName = session('business.name', 'غير محدد');
    $supportUserName = trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? ''));

    // الرسالة التي ستظهر تلقائيًا داخل واتساب
    $supportWhatsappMessage =
        "مرحبًا، لدي استفسار عن استخدام رقمي سيستم.\n" .
        "اسم الشركة: {$supportBusinessName}\n" .
        "اسم المستخدم: {$supportUserName}";

    $supportWhatsappUrl = 'https://wa.me/' . $supportWhatsappNumber . '?text=' . rawurlencode($supportWhatsappMessage);
@endphp
{{-- الشريط العلوي بنظام رقمي (rr-appbar): سطح أبيض + أزرار ثانوية، حسب تصميم الشاشة الرئيسية --}}
<header class="rr-scope rr-appbar no-print">
    <div class="rr-appbar__row header-main-row">

        {{-- البداية: القائمة الجانبية + اليوزر + التاريخ --}}
        <div class="rr-appbar__start header-menu-buttons">
            <button type="button" class="small-view-button rr-btn rr-btn--secondary rr-btn--icon rr-hide-lg" aria-label="Sidebar Menu">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <button type="button" class="side-bar-collapse rr-btn rr-btn--secondary rr-btn--icon rr-show-lg" aria-label="Collapse Sidebar">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" /><path d="M15 4v16M10 10l-2 2 2 2" /></svg>
            </button>

            <details class="rr-appbar__menu">
                <summary class="rr-appbar__user">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" /></svg>
                    <bdi class="rr-show-md">{{ Auth::User()->first_name }} {{ Auth::User()->last_name }}</bdi>
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                </summary>
                <div class="rr-appbar__dropdown" role="menu">
                    <div class="rr-appbar__signed">
                        <span>@lang('lang_v1.signed_in_as')</span>
                        <strong><bdi>{{ Auth::User()->first_name }} {{ Auth::User()->last_name }}</bdi></strong>
                    </div>
                    <a class="rr-appbar__item" role="menuitem" href="{{ action([\App\Http\Controllers\UserController::class, 'getProfile']) }}">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" /></svg>
                        @lang('lang_v1.profile')
                    </a>
                    <a class="rr-appbar__item" role="menuitem" href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'logout']) }}">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 8V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2" /><path d="M9 12h12l-3-3M18 15l3-3" /></svg>
                        @lang('lang_v1.sign_out')
                    </a>
                </div>
            </details>

            <span class="rr-appbar__divider rr-show-lg" aria-hidden="true"></span>
            <span class="rr-appbar__date rr-num rr-show-lg">{{ @format_date('now') }}</span>
        </div>

        {{-- When using superadmin, this button is used to switch users --}}
        @if(!empty(session('previous_user_id')) && !empty(session('previous_username')))
            <a href="{{ route('sign-in-as-user', session('previous_user_id')) }}" class="rr-btn rr-btn--danger rr-btn--sm"><i class="fas fa-undo"></i> @lang('lang_v1.back_to_username', ['username' => session('previous_username')])</a>
        @endif

        {{-- النهاية: الإشعارات والأدوات والأزرار --}}
        <div class="rr-appbar__end header-actions">
            {{-- مظهر اليوزر نفسه بس (users.theme_mode)؛ مابيغيّرش إعداد النشاط لباقي المستخدمين --}}
            @php $rrNextTheme = ! empty($is_dark_mode) ? 'light' : 'dark'; @endphp
            <form method="POST" action="{{ route('user.theme-mode') }}" class="rr-appbar__theme">
                @csrf
                <input type="hidden" name="mode" value="{{ $rrNextTheme }}">
                <button type="submit" class="rr-btn rr-btn--secondary rr-btn--icon" title="@lang('lang_v1.switch_to_' . $rrNextTheme)" aria-label="@lang('lang_v1.switch_to_' . $rrNextTheme)">
                    @include('partials.rr.icon', ['name' => $rrNextTheme == 'dark' ? 'moon' : 'sun'])
                </button>
            </form>

            {{-- حجم الخط: بيتحفظ على الجهاز ده بس (localStorage) وبيكبّر البرنامج كله، والاختيار بيتطبق من الـ head قبل ما الصفحة ترسم --}}
            <details class="rr-appbar__menu rr-appbar__font">
                <summary class="rr-btn rr-btn--secondary rr-btn--icon" title="@lang('display.font_size')" aria-label="@lang('display.font_size')">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 19l5.5-14L14 19M5.2 14h6.6M15 19l3-7.5 3 7.5M16.1 16.5h3.8" /></svg>
                </summary>
                <div class="rr-appbar__dropdown rr-appbar__dropdown--end" role="menu" aria-label="@lang('display.font_size')">
                    @foreach (['md' => 'font_normal', 'lg' => 'font_large', 'xl' => 'font_xlarge'] as $rrFont => $rrFontLabel)
                        <button type="button" class="rr-appbar__item rr-appbar__font-opt" role="menuitemradio" data-rr-font="{{ $rrFont }}" aria-checked="false">
                            <span class="rr-appbar__font-a rr-appbar__font-a--{{ $rrFont }}" aria-hidden="true">أ</span>
                            @lang('display.' . $rrFontLabel)
                        </button>
                    @endforeach
                </div>
            </details>
            <script>
                (function() {
                    var root = document.documentElement, opts = document.querySelectorAll('.rr-appbar__font-opt');
                    var mark = function() {
                        var cur = root.getAttribute('data-rr-font') || 'md';
                        opts.forEach(function(b) { b.setAttribute('aria-checked', b.getAttribute('data-rr-font') === cur ? 'true' : 'false'); });
                    };
                    opts.forEach(function(b) {
                        b.addEventListener('click', function() {
                            var size = b.getAttribute('data-rr-font');
                            if (size === 'md') root.removeAttribute('data-rr-font'); else root.setAttribute('data-rr-font', size);
                            try { localStorage.setItem('rr-font', size); } catch (e) {}
                            mark();
                            b.closest('details').removeAttribute('open');
                        });
                    });
                    mark();
                })();
            </script>

            @include('layouts.partials.header-notifications')

            {{-- الحاسبة والتقويم وجولة البرنامج اتشالوا عشان الشريط يبقى أخف (من غير زرار الجولة، الجولة مابتفتحش لوحدها كمان) --}}
            @if (Module::has('Essentials'))
                <details class="rr-appbar__menu">
                    <summary class="rr-btn rr-btn--secondary rr-btn--icon" aria-label="@lang('essentials::lang.add_to_do')">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M9 12h6M12 9v6" /></svg>
                    </summary>
                    <div class="rr-appbar__dropdown rr-appbar__dropdown--end" role="menu">
                        <a href="#" data-href="{{ action([\Modules\Essentials\Http\Controllers\ToDoController::class, 'create']) }}" data-container="#task_modal" class="btn-modal rr-appbar__item" role="menuitem">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M9 12l2 2 4-4" /></svg>
                            @lang('essentials::lang.add_to_do')
                        </a>
                    </div>
                </details>
            @endif

            @can('profit_loss_report.view')
                <button type="button" id="view_todays_profit" title="{{ __('home.todays_profit') }}" aria-label="{{ __('home.todays_profit') }}"
                    data-toggle="tooltip" data-placement="bottom" class="rr-btn rr-btn--secondary rr-btn--icon rr-show-sm">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2" /><circle cx="12" cy="12" r="3" /><path d="M18 12h.01M6 12h.01" /></svg>
                </button>
            @endcan

            @if (Module::has('Essentials'))
                @includeIf('essentials::layouts.partials.header_part')
            @endif
            @if (Module::has('Repair'))
                @includeIf('repair::layouts.partials.header')
            @endif

            @if (in_array('pos_sale', $enabled_modules))
                @can('sell.create')
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}" class="rr-btn rr-btn--secondary">
                        <svg aria-hidden="true" class="rr-show-md" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="6" height="6" rx="1" /><rect x="14" y="4" width="6" height="6" rx="1" /><rect x="4" y="14" width="6" height="6" rx="1" /><rect x="14" y="14" width="6" height="6" rx="1" /></svg>
                        @lang('sale.pos_sale')
                    </a>
                @endcan
            @endif

            {{-- WhatsApp Technical Support --}}
            <a href="{{ $supportWhatsappUrl }}" target="_blank" rel="noopener noreferrer"
               title="التواصل مع الدعم الفني عبر واتساب" aria-label="التواصل مع الدعم الفني عبر واتساب"
               class="rr-btn rr-btn--secondary rr-appbar__support">
                <svg aria-hidden="true" class="rr-appbar__whatsapp" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.5-.669-.51-.173-.009-.372-.011-.57-.011-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.272.148.396 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.821 9.821 0 0 1 7.021 2.91 9.825 9.825 0 0 1 2.9 7.03c-.003 5.45-4.438 9.884-9.884 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.3-1.654a11.882 11.882 0 0 0 5.69 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                </svg>
                <span class="rr-appbar__label">الدعم والاستفسارات</span>
            </a>

            @if(Module::has('Superadmin'))
                @includeIf('superadmin::layouts.partials.active_subscription')
            @endif
        </div>
    </div>
</header>
