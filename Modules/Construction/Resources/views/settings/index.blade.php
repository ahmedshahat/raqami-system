@extends('construction::layouts.module')

@section('title', __('construction::lang.settings'))

@section('module_content')
<section class="content ct-settings-page">
    <header class="ct-financial-report-heading ct-settings-heading">
        <div>
            <h1>@lang('construction::lang.settings')</h1>
            <p>@lang('construction::lang.settings_description')</p>
        </div>
    </header>

    <div class="ct-settings-grid">
        <a href="{{ route('construction.settings.accounting.index') }}" class="ct-settings-card">
            <span class="ct-settings-card__icon"><i class="fa fa-calculator"></i></span>
            <span class="ct-settings-card__content">
                <strong>@lang('construction::lang.accounting_link_settings')</strong>
                <small>@lang('construction::lang.accounting_link_settings_description')</small>
            </span>
            <span class="ct-settings-card__status {{ $accountingConfigured ? 'is-ready' : 'is-pending' }}">
                <i class="fa {{ $accountingConfigured ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                {{ $accountingConfigured ? __('construction::lang.setting_configured') : __('construction::lang.setting_needs_setup') }}
            </span>
            <i class="fa fa-chevron-left ct-settings-card__arrow"></i>
        </a>
    </div>
</section>
@endsection
