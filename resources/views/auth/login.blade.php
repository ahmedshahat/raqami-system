@extends('layouts.guest_rr')
@section('title', __('lang_v1.login'))
@section('with_scripts', true)
@section('body_class', 'rr-landing--auth')

@section('content')
@php
    $username = old('username');
    $password = null;
    if (config('app.env') == 'demo') {
        $username = 'admin';
        $password = '123456';
    }
@endphp

<div class="rr-landing__wrap rr-auth rr-auth--single">
    <section class="rr-card rr-auth__panel">
        <img class="rr-auth__logo" src="https://raqmycloud.com/wp-content/uploads/2026/06/هوية-رقمى-سيستم.png" alt="{{ config('app.name') }}">
        <header class="rr-auth__head">
            <h1 class="rr-auth__title" data-rr-greeting>@lang('guest.welcome_back')</h1>
            <p class="rr-auth__sub">@lang('guest.login_sub')</p>
        </header>

        @if (session('status') && empty(session('status.success')) && !empty(session('status.msg')))
            <div class="rr-alert rr-alert--danger" role="alert">{{ session('status.msg') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="login-form" class="rr-auth__form" novalidate>
            {{ csrf_field() }}

            {{-- آخر مستخدم دخل من الجهاز ده (يظهر بالـ JS لو محفوظ) --}}
            <div class="rr-auth__user" id="rr_last_user" hidden>
                <span class="rr-auth__avatar" id="rr_last_user_initial" aria-hidden="true"></span>
                <span class="rr-auth__user-text">
                    <small>@lang('guest.continue_as')</small>
                    <strong class="rr-num" id="rr_last_user_name"></strong>
                </span>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm" id="rr_switch_user">@lang('guest.switch_user')</button>
            </div>

            <div class="rr-field" id="rr_username_field">
                <label class="rr-field__label" for="username">@lang('lang_v1.username')</label>
                <div class="rr-control rr-control--lg @if ($errors->has('username')) rr-control--invalid @endif">
                    <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'user', 'size' => 18])</span>
                    <input class="rr-control__input" id="username" type="text" name="username" value="{{ $username }}" required autofocus autocomplete="username" placeholder="@lang('lang_v1.username')">
                </div>
                @if ($errors->has('username'))<span class="rr-field__error">{{ $errors->first('username') }}</span>@endif
            </div>

            <div class="rr-field">
                <label class="rr-field__label" for="password">@lang('lang_v1.password')</label>
                <div class="rr-control rr-control--lg @if ($errors->has('password')) rr-control--invalid @endif">
                    <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'lock', 'size' => 18])</span>
                    <input class="rr-control__input" id="password" type="password" name="password" value="{{ $password }}" required autocomplete="current-password" placeholder="@lang('lang_v1.password')">
                    @include('partials.rr.password_toggle', ['target' => '#password'])
                </div>
                <span class="rr-field__hint rr-auth__caps" id="rr_caps" hidden>
                    @include('partials.rr.icon', ['name' => 'info', 'size' => 14]) @lang('guest.caps_lock_on')
                </span>
                @if ($errors->has('password'))<span class="rr-field__error">{{ $errors->first('password') }}</span>@endif
            </div>

            <div class="rr-auth__row">
                <label class="rr-auth__check"><input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}> @lang('lang_v1.remember_me')</label>
                @if (config('app.env') != 'demo')
                    <a class="rr-auth__link" href="{{ route('password.request') }}">@lang('lang_v1.forgot_your_password')</a>
                @endif
            </div>

            <label class="rr-auth__check" title="@lang('guest.cashier_mode_desc')">
                <input type="checkbox" name="open_pos" value="1" id="open_pos" {{ old('open_pos') ? 'checked' : '' }}>
                @lang('guest.cashier_mode') <span class="rr-auth__check-hint">— @lang('guest.cashier_mode_desc')</span>
            </label>

            @if (config('constants.enable_recaptcha'))
                <div class="rr-auth__captcha"><div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div></div>
                @if ($errors->has('g-recaptcha-response'))<span class="rr-field__error">{{ $errors->first('g-recaptcha-response') }}</span>@endif
            @endif

            <button type="submit" class="rr-btn rr-btn--primary rr-btn--lg rr-auth__submit">
                <span class="rr-auth__submit-idle">@lang('lang_v1.login')</span>
                <span class="rr-auth__submit-busy"><span class="rr-auth__spinner" aria-hidden="true"></span>@lang('guest.signing_in')</span>
            </button>
        </form>
    </section>

    @if (config('constants.allow_registration'))
        <p class="rr-auth__below">
            @lang('guest.new_here')
            <a class="rr-auth__link" href="{{ route('business.getRegister') }}?package=3">@lang('guest.trial_title')</a>
            <span aria-hidden="true">·</span>
            <a class="rr-auth__link" href="{{ url('/pricing') }}">@lang('guest.packages')</a>
        </p>
    @endif
</div>
@stop

@section('footer_extra')
    <img class="rr-landing__footer-badge" src="https://raqmycloud.com/wp-content/uploads/2026/07/zatca-approved.png" alt="@lang('guest.zatca_alt')" title="@lang('guest.zatca_alt')">
@endsection

@section('javascript')
<script>
$(function () {
    var KEY_USER = 'rr-last-user', KEY_POS = 'rr-open-pos';
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
        del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
    };
    var $username = $('#username'), $password = $('#password');

    // تحية حسب الوقت
    var hour = new Date().getHours();
    $('[data-rr-greeting]').text(hour < 12 ? @json(__('guest.good_morning')) : @json(__('guest.good_evening')));

    // آخر مستخدم: بيتحفظ اسم المستخدم بس، ولما يختار "تذكرني"
    var lastUser = store.get(KEY_USER);
    if (lastUser && !$username.val() && !$('#username').closest('.rr-control').hasClass('rr-control--invalid')) {
        $username.val(lastUser);
        $('#rr_last_user_name').text(lastUser);
        $('#rr_last_user_initial').text(lastUser.charAt(0).toUpperCase());
        $('#rr_last_user').prop('hidden', false);
        $('#rr_username_field').prop('hidden', true);
        $('#remember').prop('checked', true);
        $password.trigger('focus');
    }
    $('#rr_switch_user').on('click', function () {
        store.del(KEY_USER);
        $username.val('');
        $('#rr_last_user').prop('hidden', true);
        $('#rr_username_field').prop('hidden', false);
        $username.trigger('focus');
    });

    // وضع الكاشير: الاختيار بيتفضل محفوظ على الجهاز
    if (store.get(KEY_POS) === '1') $('#open_pos').prop('checked', true);
    $('#open_pos').on('change', function () { this.checked ? store.set(KEY_POS, '1') : store.del(KEY_POS); });

    // تنبيه Caps Lock
    $password.on('keydown keyup', function (e) {
        if (e.originalEvent && e.originalEvent.getModifierState) {
            $('#rr_caps').prop('hidden', !e.originalEvent.getModifierState('CapsLock'));
        }
    }).on('blur', function () { $('#rr_caps').prop('hidden', true); });

    $('#login-form').on('submit', function () {
        if ($('#remember').is(':checked') && $username.val()) { store.set(KEY_USER, $username.val()); } else { store.del(KEY_USER); }
        $(this).find('.rr-auth__submit').prop('disabled', true).attr('aria-busy', 'true');
    });
});
</script>
@endsection
