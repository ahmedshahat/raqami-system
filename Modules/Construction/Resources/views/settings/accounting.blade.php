@extends('construction::layouts.module')

@section('title', __('construction::lang.accounting_link_settings'))

@php
    // تجميع الحسابات حسب الاستخدام؛ أي حقل جديد مش في المجموعات يظهر في "أخرى" تلقائيًا
    $groups = [
        'accounts_group_customer' => ['construction_revenue_account_id', 'customer_receivable_account_id', 'customer_retention_account_id', 'customer_advance_account_id', 'customer_deduction_account_id'],
        'accounts_group_tax' => ['sales_tax_payable_account_id', 'input_tax_account_id'],
        'accounts_group_site' => ['inventory_account_id', 'material_cost_account_id', 'labor_cost_account_id', 'labor_payable_account_id'],
        'accounts_group_subcontract' => ['subcontract_cost_account_id', 'subcontract_payable_account_id', 'subcontract_retention_account_id', 'subcontract_deduction_account_id', 'subcontract_advance_account_id'],
        'accounts_group_cash' => ['cash_account_id'],
    ];
    $allFields = \Modules\Construction\Entities\ConstructionAccountingSetting::ACCOUNT_FIELDS;
    $groups = array_map(fn ($fields) => array_values(array_intersect($fields, $allFields)), $groups);
    $ungrouped = array_values(array_diff($allFields, array_merge(...array_values($groups))));
    if ($ungrouped) {
        $groups['accounts_group_other'] = $ungrouped;
    }
    $linkedCount = collect($allFields)->filter(fn ($field) => ! empty(old($field, $settings->{$field})))->count();
@endphp

@section('module_content')
<main class="content rr-page rr-page--form" aria-labelledby="rr-accounting-settings-title">
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.settings.index') }}">@lang('construction::lang.settings')</a></li>
                    <li><span aria-current="page">@lang('construction::lang.accounting_link_settings')</span></li>
                </ol>
            </nav>
            <h1 id="rr-accounting-settings-title" class="rr-head__title">@lang('construction::lang.accounting_link_settings')</h1>
            <p class="rr-head__sub">@lang('construction::lang.accounting_link_settings_description')</p>
        </div>
        <div class="rr-head__actions">
            <a href="{{ url('/accounting/chart-of-accounts') }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'bars'])@lang('construction::lang.open_chart_of_accounts')</a>
        </div>
    </header>

    {{-- إنشاء الحسابات تلقائيًا --}}
    <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-create-accounts-title">
        <div class="rr-next">
            <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 22])</span>
            <div class="rr-next__copy">
                <h2 id="rr-create-accounts-title" class="rr-next__title">@lang('construction::lang.create_construction_accounts')</h2>
                <p class="rr-next__text">@lang('construction::lang.create_construction_accounts_hint') @lang('construction::lang.accounts_no_duplicates_notice')</p>
            </div>
            <form class="rr-inline-form" method="POST" action="{{ route('construction.settings.accounting.create-accounts') }}">
                @csrf
                <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.create_and_link_accounts')</button>
            </form>
        </div>
    </section>

    <form method="POST" class="rr-form" action="{{ route('construction.settings.accounting.update') }}">
        @csrf
        @method('PUT')
        @include('construction::labor.partials.form_errors')

        <div class="rr-statgroup-wrap">
            <div class="rr-statgroup" style="--rr-stats: 1">
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.linked_construction_accounts')</div>
                    <div class="rr-stat__value"><span class="rr-num">{{ $linkedCount }}</span> <span class="rr-stat__of">/ <span class="rr-num">{{ count($allFields) }}</span></span></div>
                    <div class="rr-stat__sub">@lang('construction::lang.linked_construction_accounts_hint')</div>
                    <div class="rr-progress"><div class="rr-progress__fill rr-progress__fill--{{ $linkedCount === count($allFields) ? 'success' : 'warning' }}" style="width: {{ round($linkedCount / max(1, count($allFields)) * 100) }}%"></div></div>
                </div>
            </div>
        </div>

        @foreach($groups as $groupLabel => $fields)
            @php $groupLinked = collect($fields)->filter(fn ($field) => ! empty(old($field, $settings->{$field})))->count(); @endphp
            <section class="rr-card" aria-labelledby="rr-{{ $groupLabel }}">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-{{ $groupLabel }}" class="rr-card__title">@lang('construction::lang.' . $groupLabel)</h2>
                    </div>
                    <span class="rr-badge rr-badge--{{ $groupLinked === count($fields) ? 'success' : 'warning' }}"><span class="rr-num">{{ $groupLinked }}/{{ count($fields) }}</span></span>
                </header>
                <div class="rr-card__body">
                    <div class="rr-form-wrap">
                        <div class="rr-form-grid">
                            @foreach($fields as $field)
                                <div class="rr-field">
                                    <label class="rr-field__label" for="acc-{{ $field }}">@lang('construction::lang.' . $field)<span class="rr-field__req" aria-hidden="true">*</span></label>
                                    <select id="acc-{{ $field }}" name="{{ $field }}" class="form-control select2" required>
                                        <option value="">@lang('construction::lang.select_account')</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" @selected((int) old($field, $settings->{$field}) === $account->id)>{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    @error($field)<p class="rr-field__error">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endforeach

        <footer class="rr-form-actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])<span>@lang('construction::lang.save_accounting_settings')</span></button>
            <a href="{{ route('construction.settings.index') }}" class="rr-btn rr-btn--secondary">@lang('construction::lang.back_to_settings')</a>
        </footer>
    </form>
</main>
@endsection
