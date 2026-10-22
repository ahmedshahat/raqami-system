@extends('construction::layouts.module')

@section('title', __('construction::lang.subcontractor_statement'))

@php
    $movementTones = ['certificate' => 'info', 'payment' => 'success', 'retention_release' => 'warning', 'advance' => 'brand'];
    $amount = fn ($value) => \Modules\Construction\Support\CurrencyFormatter::parts($value)['amount'];
    $util = app(\App\Utils\Util::class);
    $meta = $statement ? [
        ['users', $selectedSubcontractor->supplier_business_name ?: $selectedSubcontractor->name],
        ['building', $selectedProject ? $selectedProject->code . ' — ' . $selectedProject->name : __('construction::lang.statement_all_projects')],
        ['calendar', ($fromDate ? $util->format_date($fromDate) : __('construction::lang.account_inception')) . ' — ' . $util->format_date($toDate)],
    ] : [];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-report-title">
    @include('construction::dashboard.partials.rr_report_switcher', ['active' => 'statement'])

    @include('construction::dashboard.partials.rr_report_head', [
        'icon' => 'users',
        'eyebrow' => __('construction::lang.accountant_report'),
        'title' => __('construction::lang.subcontractor_statement'),
        'description' => __('construction::lang.subcontractor_statement_description'),
        'printUrl' => $statement ? route('construction.reports.subcontractor-statement.print', array_filter(['subcontractor_id' => $selectedSubcontractor->id, 'project_id' => $projectId, 'date_range' => $dateRange, 'from_date' => $fromDate, 'to_date' => $toDate])) : null,
        'printLabel' => __('construction::lang.print_statement'),
        'meta' => $meta,
    ])

    <form method="GET" action="{{ route('construction.reports.subcontractor-statement.index') }}" class="rr-filter ct-date-range-filter {{ $dateRange === 'custom' ? 'is-custom-range' : 'is-preset-range' }}">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-subcontractor">@lang('construction::lang.subcontractor')<span class="rr-field__req" aria-hidden="true">*</span></label>
                <select id="ct-report-subcontractor" name="subcontractor_id" class="form-control select2" required>
                    <option value="">@lang('construction::lang.select_subcontractor')</option>
                    @foreach($subcontractors as $subcontractor)<option value="{{ $subcontractor->id }}" @selected($selectedSubcontractor?->id === $subcontractor->id)>{{ $subcontractor->supplier_business_name ?: $subcontractor->name }}</option>@endforeach
                </select>
            </div>
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-project">@lang('construction::lang.project')</label>
                <select id="ct-report-project" name="project_id" class="form-control select2">
                    <option value="">@lang('construction::lang.statement_all_projects')</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach
                </select>
            </div>
            @include('construction::dashboard.partials.date-range-filter')
        </div>
        <div class="rr-filter__actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'search', 'size' => 16])@lang('construction::lang.show_statement')</button>
        </div>
    </form>

    @if(! $statement)
        <div class="rr-card">
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 22])</span>
                <h2 class="rr-empty__title">@lang('construction::lang.statement_select_subcontractor')</h2>
                <p class="rr-empty__text">@lang('construction::lang.statement_select_subcontractor_hint')</p>
            </div>
        </div>
    @else
        @php $summary = $statement['summary']; @endphp
        <div class="rr-statgroup-wrap">
            <div class="rr-statgroup rr-statgroup--4" style="--rr-stats: 4">
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.contracts_value')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $summary['contracts_value']])</span></div>
                    <div class="rr-stat__sub"><span class="rr-num">{{ $statement['agreements_count'] }}</span> @lang('construction::lang.agreements')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.total_payable')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $summary['payable']])</span></div>
                    <div class="rr-stat__sub"><span class="rr-num">{{ $summary['certificates_count'] }}</span> @lang('construction::lang.approved_certificates')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 18])@lang('construction::lang.paid_amount')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg rr-text--success">@include('construction::partials.money', ['value' => $summary['paid']])</span></div>
                    <div class="rr-stat__sub">@lang('construction::lang.recorded_payments_only')</div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])@lang('construction::lang.current_balance')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $summary['balance'] > 0 ? 'rr-text--danger' : 'rr-text--success' }}">@include('construction::partials.money', ['value' => $summary['balance']])</span></div>
                    <div class="rr-stat__sub">@lang('construction::lang.due_to_subcontractor')</div>
                </div>
            </div>
        </div>

        {{-- ملخص الحساب --}}
        <section class="rr-card" aria-labelledby="rr-statement-summary-title">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <div class="rr-eyebrow">@lang('construction::lang.account_summary')</div>
                    <h2 id="rr-statement-summary-title" class="rr-card__title">@lang('construction::lang.certificate_retention_payment_summary')</h2>
                </div>
            </header>
            <dl class="rr-deflist rr-deflist--cols">
                @foreach(['gross' => 'approved_subcontract_work', 'tax' => 'input_vat', 'deductions' => 'other_deductions', 'retention_original' => 'original_retention', 'retention_released' => 'released_retention', 'retention_remaining' => 'remaining_retention', 'advances' => 'advances_paid', 'advance_recovered' => 'advances_recovered', 'advance_remaining' => 'advance_available', 'paid' => 'paid_amount'] as $key => $label)
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.' . $label)</dt><dd><span class="rr-money rr-money--sm {{ $summary[$key] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $summary[$key]])</span></dd></div>
                @endforeach
            </dl>
        </section>

        {{-- حركات الحساب --}}
        <section class="rr-table-wrap" aria-labelledby="rr-statement-movements-title">
            <div class="rr-table-head rr-table-head--toolbar">
                <div class="rr-table-head__titles">
                    <div class="rr-eyebrow">@lang('construction::lang.accountant_details')</div>
                    <h2 id="rr-statement-movements-title" class="rr-table-head__title">@lang('construction::lang.statement_movements')</h2>
                </div>
                <dl class="rr-inline-facts">
                    <div><dt>@lang('construction::lang.opening_balance')</dt><dd><span class="rr-num">{{ $amount($summary['opening_balance']) }}</span></dd></div>
                    <div><dt>@lang('construction::lang.closing_balance')</dt><dd><span class="rr-num">{{ $amount($summary['closing_balance']) }}</span></dd></div>
                </dl>
            </div>
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.statement_movements')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.date')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.movement_type')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.document_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.description')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.debit_paid')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.credit_due')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.running_balance')</th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @if($fromDate)
                            <tr class="rr-tr rr-tr--section">
                                <td class="rr-td"><span class="rr-num">{{ @format_date($fromDate) }}</span></td>
                                <td class="rr-td" colspan="6">@lang('construction::lang.opening_balance')</td>
                                <td class="rr-td rr-td--num"><strong class="rr-num">{{ $amount($summary['opening_balance']) }}</strong></td>
                            </tr>
                        @endif
                        @forelse($statement['movements'] as $movement)
                            <tr class="rr-tr">
                                <td class="rr-td"><span class="rr-num">{{ @format_date($movement['date']) }}</span></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $movementTones[$movement['type']] ?? 'neutral' }}">@lang('construction::lang.statement_type_' . $movement['type'])</span></td>
                                <td class="rr-td"><span class="rr-code">{{ $movement['number'] }}</span></td>
                                <td class="rr-td rr-td--muted">{{ $movement['project'] ?: '—' }}</td>
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $movement['description'] }}</span><div class="rr-td__sub"><span class="rr-code">{{ $movement['agreement'] }}</span></div></td>
                                <td class="rr-td rr-td--num"><span class="rr-num {{ $movement['debit'] == 0 ? 'rr-td--muted' : '' }}">{{ $movement['debit'] == 0 ? '—' : $amount($movement['debit']) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num {{ $movement['credit'] == 0 ? 'rr-td--muted' : '' }}">{{ $movement['credit'] == 0 ? '—' : $amount($movement['credit']) }}</span></td>
                                <td class="rr-td rr-td--num"><strong class="rr-num">{{ $amount($movement['balance']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td class="rr-td" colspan="8"><div class="rr-empty"><h3 class="rr-empty__title">@lang('construction::lang.no_statement_movements')</h3></div></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="rr-tfoot">
                        <tr>
                            <td class="rr-td" colspan="5">@lang('construction::lang.period_totals')</td>
                            <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($summary['period_debit']) }}</span></td>
                            <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($summary['period_credit']) }}</span></td>
                            <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($summary['closing_balance']) }}</span></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif
</main>
@endsection
