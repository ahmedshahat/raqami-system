@extends('construction::layouts.module')

@section('title', __('construction::lang.settings'))

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-settings-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'bars', 'size' => 24])</span>
        <div class="rr-head__titles">
            <h1 id="rr-settings-title" class="rr-head__title">@lang('construction::lang.settings')</h1>
            <p class="rr-head__sub">@lang('construction::lang.settings_description')</p>
        </div>
    </header>

    @unless($accountingConfigured)
        <div class="rr-callout rr-callout--danger">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 20])</span>
            <div class="rr-callout__copy">
                <strong>@lang('construction::lang.setting_needs_setup'): @lang('construction::lang.accounting_link_settings')</strong>
                <p>@lang('construction::lang.accounting_link_settings_description')</p>
            </div>
            <div class="rr-callout__actions">
                <a href="{{ route('construction.settings.accounting.index') }}" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'arrow-forward'])@lang('construction::lang.accounting_link_settings')</a>
            </div>
        </div>
    @endunless

    <section class="rr-card" aria-labelledby="rr-settings-list-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <h2 id="rr-settings-list-title" class="rr-card__title">@lang('construction::lang.settings')</h2>
            </div>
        </header>
        <ul class="rr-list rr-settings-list">
            <li>
                <a class="rr-nav-row" href="{{ route('construction.settings.accounting.index') }}">
                    <span class="rr-nav-row__icon">@include('construction::partials.rr_icon', ['name' => 'list'])</span>
                    <span class="rr-nav-row__label">
                        @lang('construction::lang.accounting_link_settings')
                        <span class="rr-nav-row__desc">@lang('construction::lang.accounting_link_settings_description')</span>
                    </span>
                    <span class="rr-badge rr-badge--{{ $accountingConfigured ? 'success' : 'warning' }}"><span class="rr-badge__dot" aria-hidden="true"></span>{{ $accountingConfigured ? __('construction::lang.setting_configured') : __('construction::lang.setting_needs_setup') }}</span>
                    <span class="rr-nav-row__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-forward', 'size' => 18])</span>
                </a>
            </li>
            @can('construction.accounting.view')
                <li>
                    <a class="rr-nav-row" href="{{ route('construction.settings.accounting.reconciliation') }}">
                        <span class="rr-nav-row__icon">@include('construction::partials.rr_icon', ['name' => 'shield'])</span>
                        <span class="rr-nav-row__label">
                            @lang('construction::lang.accounting_reconciliation')
                            <span class="rr-nav-row__desc">@lang('construction::lang.accounting_reconciliation_description')</span>
                        </span>
                        <span class="rr-nav-row__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-forward', 'size' => 18])</span>
                    </a>
                </li>
            @endcan
        </ul>
    </section>
</main>
@endsection
