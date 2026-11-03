@extends('layouts.guest_rr')

@section('title', __('lang_v1.reset_password'))

@section('content')
    <div class="rr-landing__wrap rr-auth rr-auth--single">
        <section class="rr-card rr-auth__panel">
            <header class="rr-auth__head">
                <h1 class="rr-auth__title">@lang('lang_v1.reset_password')</h1>
                <p class="rr-auth__sub">@lang('guest.reset_sub')</p>
            </header>

            <form method="POST" action="{{ route('password.request') }}" class="rr-auth__form">
                {{ csrf_field() }}
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="rr-field">
                    <label class="rr-field__label" for="email">@lang('guest.email')</label>
                    <div class="rr-control rr-control--lg @if ($errors->has('email')) rr-control--invalid @endif">
                        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'mail', 'size' => 18])</span>
                        <input class="rr-control__input" id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus autocomplete="email" placeholder="@lang('lang_v1.email_address')">
                    </div>
                    @if ($errors->has('email'))<span class="rr-field__error">{{ $errors->first('email') }}</span>@endif
                </div>

                <div class="rr-field">
                    <label class="rr-field__label" for="password">@lang('lang_v1.password')</label>
                    <div class="rr-control rr-control--lg @if ($errors->has('password')) rr-control--invalid @endif">
                        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'lock', 'size' => 18])</span>
                        <input class="rr-control__input" id="password" type="password" name="password" required autocomplete="new-password" placeholder="@lang('lang_v1.password')">
                        @include('partials.rr.password_toggle', ['target' => '#password'])
                    </div>
                    @if ($errors->has('password'))<span class="rr-field__error">{{ $errors->first('password') }}</span>@endif
                </div>

                <div class="rr-field">
                    <label class="rr-field__label" for="password_confirmation">@lang('business.confirm_password')</label>
                    <div class="rr-control rr-control--lg @if ($errors->has('password_confirmation')) rr-control--invalid @endif">
                        <span class="rr-control__lead">@include('partials.rr.icon', ['name' => 'lock', 'size' => 18])</span>
                        <input class="rr-control__input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="@lang('business.confirm_password')">
                        @include('partials.rr.password_toggle', ['target' => '#password_confirmation'])
                    </div>
                    @if ($errors->has('password_confirmation'))<span class="rr-field__error">{{ $errors->first('password_confirmation') }}</span>@endif
                </div>

                <button type="submit" class="rr-btn rr-btn--primary rr-btn--lg rr-auth__submit">@lang('lang_v1.reset_password')</button>
            </form>
        </section>
    </div>
@endsection
