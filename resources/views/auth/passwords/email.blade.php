@extends('layouts.guest_rr')

@section('title', __('lang_v1.reset_password'))

@section('content')
    <div class="rr-landing__wrap rr-auth rr-auth--single">
        <section class="rr-card rr-auth__panel">
            <header class="rr-auth__head">
                <h1 class="rr-auth__title">@lang('lang_v1.reset_password')</h1>
                <p class="rr-auth__sub">@lang('guest.forgot_sub')</p>
            </header>

            @if (session('status') && is_string(session('status')))
                <div class="rr-alert rr-alert--success" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="rr-auth__form">
                {{ csrf_field() }}
                <div class="rr-field">
                    <label class="rr-field__label" for="email">@lang('guest.email')</label>
                    <div class="rr-control rr-control--lg @if ($errors->has('email')) rr-control--invalid @endif">
                        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'mail', 'size' => 18])</span>
                        <input class="rr-control__input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="@lang('lang_v1.email_address')">
                    </div>
                    @if ($errors->has('email'))<span class="rr-field__error">{{ $errors->first('email') }}</span>@endif
                </div>

                <button type="submit" class="rr-btn rr-btn--primary rr-btn--lg rr-auth__submit">@lang('lang_v1.send_password_reset_link')</button>
            </form>

            <p class="rr-auth__foot"><a class="rr-auth__link" href="{{ route('login') }}">@lang('guest.back_to_login')</a></p>
        </section>
    </div>
@endsection
