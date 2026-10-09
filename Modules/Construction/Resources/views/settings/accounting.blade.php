@extends('construction::layouts.module')

@section('title', __('construction::lang.accounting_link_settings'))

@section('module_content')
<section class="content ct-accounting-settings-page">
    <header class="ct-financial-report-heading ct-accounting-settings-heading">
        <div><h1>@lang('construction::lang.accounting_link_settings')</h1><p>@lang('construction::lang.accounting_link_settings_description')</p></div>
        <div class="ct-settings-heading__actions">
            <a href="{{ route('construction.settings.index') }}" class="btn btn-default"><i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_settings')</a>
            <a href="{{ url('/accounting/chart-of-accounts') }}" class="btn ct-primary-action"><i class="fa fa-sitemap"></i> @lang('construction::lang.open_chart_of_accounts')</a>
        </div>
    </header>

    <div class="ct-material-panel">
        <div class="ct-panel-heading"><div><h2>@lang('construction::lang.create_construction_accounts')</h2><p>@lang('construction::lang.create_construction_accounts_hint')</p></div></div>
        <div class="ct-accounting-setup-action">
            <form method="POST" action="{{ route('construction.settings.accounting.create-accounts') }}">@csrf
                <button class="btn ct-primary-action"><i class="fa fa-magic"></i> @lang('construction::lang.create_and_link_accounts')</button>
            </form>
            <span><i class="fa fa-shield-alt"></i> @lang('construction::lang.accounts_no_duplicates_notice')</span>
        </div>
    </div>

    <form method="POST" action="{{ route('construction.settings.accounting.update') }}" class="ct-material-panel">@csrf @method('PUT')
        <div class="ct-panel-heading"><div><h2>@lang('construction::lang.linked_construction_accounts')</h2><p>@lang('construction::lang.linked_construction_accounts_hint')</p></div></div>
        <div class="ct-accounting-settings-grid">
            @foreach(\Modules\Construction\Entities\ConstructionAccountingSetting::ACCOUNT_FIELDS as $field)
                <div class="form-group">
                    <label>@lang('construction::lang.'.$field)</label>
                    <select name="{{ $field }}" class="form-control select2" required>
                        <option value="">@lang('construction::lang.select_account')</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old($field, $settings->{$field}) === $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error($field)<small class="text-danger">{{ $message }}</small>@enderror
                </div>
            @endforeach
        </div>
        <div class="ct-form-footer"><button class="btn ct-primary-action"><i class="fa fa-save"></i> @lang('construction::lang.save_accounting_settings')</button></div>
    </form>
</section>
@endsection
