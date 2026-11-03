{{-- الشريط العلوي للصفحات العامة (الترحيب والدخول والتسجيل) — نفس روابط layouts/auth2 القديم وبنفس شروطها --}}
@php
    $locale = app()->getLocale();
    $langs = config('constants.langs');
    $lang_query = !empty(request()->lang) ? '?lang=' . request()->lang : '';
    $on_login = request()->segment(1) == 'login';
    $on_register = request()->segment(1) == 'business' && request()->segment(2) == 'register';
    $show_register = config('constants.allow_registration') && !$on_register;
    $show_pricing = $show_register && Route::has('pricing') && config('app.env') != 'demo' && request()->segment(1) != 'pricing';
@endphp
<header class="rr-landing__bar">
    <div class="rr-landing__wrap rr-landing__bar-inner">
        <a href="{{ url('/') }}" class="rr-landing__brand">
            <img src="{{ asset('img/logo-small.png') }}" alt="" class="rr-landing__logo">
            <span>{{ config('app.name', 'UltimatePOS') }}</span>
        </a>

        <nav class="rr-landing__links" aria-label="@lang('guest.nav_label')">
            @if (config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
                <a href="{{ action([\Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index']) }}">@lang('repair::lang.repair_status')</a>
            @endif
            @if (Route::has('member_scanner'))
                <a href="{{ action([\Modules\Gym\Http\Controllers\MemberController::class, 'member_scanner']) }}">@lang('gym::lang.gym_member_profile')</a>
            @endif
            @if ($show_pricing)
                <a href="{{ action([\Modules\Superadmin\Http\Controllers\PricingController::class, 'index']) }}">@lang('superadmin::lang.pricing')</a>
            @endif
        </nav>

        <div class="rr-landing__actions">
            <details class="rr-landing__lang">
                <summary class="rr-btn rr-btn--ghost rr-btn--sm">
                    {{ $langs[$locale]['short_name'] ?? $locale }}
                    @include('partials.rr.icon', ['name' => 'chevron-down', 'size' => 16])
                </summary>
                <ul class="rr-landing__lang-list">
                    @foreach ($langs as $key => $val)
                        <li><a href="{{ request()->fullUrlWithQuery(['lang' => $key]) }}" @if ($key == $locale) aria-current="true" @endif>{{ $val['full_name'] }}</a></li>
                    @endforeach
                </ul>
            </details>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" id="rr_theme_toggle" aria-label="@lang('guest.toggle_theme')" title="@lang('guest.toggle_theme')">
                <span class="rr-landing__icon-light">@include('partials.rr.icon', ['name' => 'moon', 'size' => 18])</span>
                <span class="rr-landing__icon-dark">@include('partials.rr.icon', ['name' => 'sun', 'size' => 18])</span>
            </button>
            @if (!$on_login)
                <a href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login']) }}{{ $lang_query }}" class="rr-btn rr-btn--secondary rr-btn--sm rr-landing__bar-signin">{{ __('business.sign_in') }}</a>
            @endif
            @if ($show_register)
                <a href="{{ route('business.getRegister') }}{{ $lang_query }}" class="rr-btn rr-btn--primary rr-btn--sm">{{ __('business.register') }}</a>
            @endif
        </div>
    </div>
</header>
