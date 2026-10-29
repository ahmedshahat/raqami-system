@extends('layouts.guest_rr')
@php
    $lang_query = !empty(request()->lang) ? '?lang=' . request()->lang : '';
    $features = [
        ['icon' => 'cart', 'key' => 'sales'],
        ['icon' => 'box', 'key' => 'inventory'],
        ['icon' => 'coins', 'key' => 'accounts'],
        ['icon' => 'building', 'key' => 'construction'],
    ];
@endphp

@section('content')
    <section class="rr-landing__wrap rr-landing__hero">
        <div class="rr-landing__hero-text">
            @if (env('APP_TITLE'))
                <span class="rr-badge rr-badge--brand">{{ env('APP_TITLE') }}</span>
            @endif
            <h1 class="rr-landing__title">@lang('guest.hero_title')</h1>
            <p class="rr-landing__lead">@lang('guest.hero_lead')</p>
            <div class="rr-landing__cta">
                @if (config('constants.allow_registration'))
                    <a href="{{ route('business.getRegister') }}{{ $lang_query }}" class="rr-btn rr-btn--primary rr-btn--lg">
                        @lang('guest.start_now')
                        @include('partials.rr.icon', ['name' => 'arrow-forward', 'size' => 18])
                    </a>
                @endif
                <a href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login']) }}{{ $lang_query }}" class="rr-btn rr-btn--secondary rr-btn--lg">{{ __('business.sign_in') }}</a>
            </div>
        </div>

        @include('partials.rr.app_art')
    </section>

    <section class="rr-landing__wrap rr-landing__features" aria-label="@lang('guest.features_label')">
        @foreach ($features as $feature)
            <article class="rr-card rr-landing__feature">
                <span class="rr-landing__feature-icon">@include('partials.rr.icon', ['name' => $feature['icon'], 'size' => 22])</span>
                <h2 class="rr-card__title">@lang('guest.feature_' . $feature['key'])</h2>
                <p class="rr-landing__feature-desc">@lang('guest.feature_' . $feature['key'] . '_desc')</p>
            </article>
        @endforeach
    </section>
@endsection
