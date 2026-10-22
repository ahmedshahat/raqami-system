@extends('construction::layouts.module')

@section('title', __('construction::lang.retention_guarantees_report'))

@php
    $dueTones = ['due' => 'danger', 'upcoming' => 'warning', 'not_due' => 'info', 'no_date' => 'neutral', 'closed' => 'success'];
    $releaseTones = ['released' => 'success', 'partial' => 'warning', 'held' => 'neutral'];
    $amount = fn ($value) => \Modules\Construction\Support\CurrencyFormatter::parts($value)['amount'];
    $summary = $report['summary'];
    $meta = [
        ['building', $selectedProject ? $selectedProject->code . ' — ' . $selectedProject->name : __('construction::lang.statement_all_projects')],
        ['calendar', __('construction::lang.as_of_date') . ' ' . app(\App\Utils\Util::class)->format_date($toDate)],
        ['clock', __('construction::lang.retentions_without_dates') . ': ' . $summary['no_date_count']],
    ];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-report-title">
    @include('construction::dashboard.partials.rr_report_switcher', ['active' => 'retention'])

    @include('construction::dashboard.partials.rr_report_head', [
        'icon' => 'shield',
        'eyebrow' => __('construction::lang.retention_control'),
        'title' => __('construction::lang.retention_guarantees_report'),
        'description' => __('construction::lang.retention_guarantees_description'),
        'printUrl' => route('construction.reports.retention-guarantees.print', array_filter(['project_id' => $projectId, 'to_date' => $toDate, 'party_type' => $partyType, 'due_status' => $dueStatus])),
        'printLabel' => __('construction::lang.print_retention_report'),
        'meta' => $meta,
    ])

    <form method="GET" action="{{ route('construction.reports.retention-guarantees.index') }}" class="rr-filter">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-project">@lang('construction::lang.project')</label>
                <select id="ct-report-project" name="project_id" class="form-control select2">
                    <option value="">@lang('construction::lang.statement_all_projects')</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach
                </select>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-report-party">@lang('construction::lang.party_type')</label>
                <div class="rr-control">
                    <select id="ct-report-party" name="party_type" class="rr-control__input rr-control__select">
                        <option value="">@lang('construction::lang.all_parties')</option>
                        <option value="customer" @selected($partyType === 'customer')>@lang('construction::lang.customer_retentions')</option>
                        <option value="subcontractor" @selected($partyType === 'subcontractor')>@lang('construction::lang.subcontractor_retentions')</option>
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-report-due">@lang('construction::lang.due_status')</label>
                <div class="rr-control">
                    <select id="ct-report-due" name="due_status" class="rr-control__input rr-control__select">
                        <option value="">@lang('construction::lang.all_statuses')</option>
                        @foreach(array_keys($dueTones) as $status)<option value="{{ $status }}" @selected($dueStatus === $status)>@lang('construction::lang.due_status_' . $status)</option>@endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-report-as-of">@lang('construction::lang.as_of_date')</label>
                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="ct-report-as-of" name="to_date" value="{{ @format_date($toDate) }}" class="rr-control__input ct-date-picker" readonly></div>
            </div>
        </div>
        <div class="rr-filter__actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.show_report')</button>
        </div>
    </form>

    {{-- ═══ المؤشرات ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--5" style="--rr-stats: 5">
            @foreach([
                ['customer_retention', 'customer_retentions', 'download', 'rights_held_by_customers', 'rr-text--success'],
                ['subcontract_original', 'subcontract_original_retention', 'lock', 'original_retention', ''],
                ['subcontract_released', 'released_retention', 'check-circle', 'paid_to_subcontractors', ''],
                ['subcontract_remaining', 'remaining_retention', 'clock', 'liabilities_to_subcontractors', 'rr-text--danger'],
                ['net_position', 'net_retention_position', 'shield', 'rights_less_liabilities', $summary['net_position'] >= 0 ? 'rr-text--success' : 'rr-text--danger'],
            ] as [$key, $label, $icon, $sub, $tone])
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])@lang('construction::lang.' . $label)</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $summary[$key] == 0 ? 'rr-money--zero' : $tone }}">@include('construction::partials.money', ['value' => $summary[$key]])</span></div>
                    <div class="rr-stat__sub">@lang('construction::lang.' . $sub)</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ═══ التدفق المتوقع ═══ --}}
    <section class="rr-card rr-card--tint" aria-labelledby="rr-retention-outlook-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <h2 id="rr-retention-outlook-title" class="rr-card__title">@lang('construction::lang.retention_cash_outlook')</h2>
                <p class="rr-card__desc">@lang('construction::lang.next_thirty_days')</p>
            </div>
        </header>
        <div class="rr-aging">
            @foreach([
                ['due_customer', 'customer_due_now', 'success'],
                ['due_subcontractor', 'subcontract_due_now', 'danger'],
                ['upcoming_customer', 'customer_due_30', 'success'],
                ['upcoming_subcontractor', 'subcontract_due_30', 'danger'],
            ] as [$key, $label, $tone])
                <div class="rr-aging__bucket">
                    <span class="rr-aging__label">@lang('construction::lang.' . $label)</span>
                    <span class="rr-money rr-money--sm {{ $summary[$key] == 0 ? 'rr-money--zero' : 'rr-text--' . $tone }}">@include('construction::partials.money', ['value' => $summary[$key]])</span>
                </div>
            @endforeach
        </div>
        <p class="rr-card__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.customer_retention_no_release_notice')</p>
    </section>

    {{-- ═══ محتجزات عند المالك ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr-customer-retentions-title">
        <div class="rr-table-head">
            <div>
                <div class="rr-eyebrow">@lang('construction::lang.rights_held_by_customers')</div>
                <h2 id="rr-customer-retentions-title" class="rr-table-head__title">@lang('construction::lang.customer_retentions')</h2>
            </div>
            <span class="rr-money">@include('construction::partials.money', ['value' => $summary['customer_retention']])</span>
        </div>
        @if(count($report['customer_rows']) === 0)
            <div class="rr-empty"><h3 class="rr-empty__title">@lang('construction::lang.no_customer_retentions')</h3></div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.customer_retentions')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.customer')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.original_retention')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.expected_release_date')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.due_status')</th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($report['customer_rows'] as $row)
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $row['project']?->name }}</span><div class="rr-td__sub"><span class="rr-code">{{ $row['project']?->code }}</span></div></td>
                                <td class="rr-td rr-td--wrap">{{ $row['party_name'] }}</td>
                                <td class="rr-td"><span class="rr-code">{{ $row['certificate']->number }}</span><div class="rr-td__sub"><span class="rr-num">{{ @format_date($row['certificate']->certificate_date) }}</span></div></td>
                                <td class="rr-td rr-td--num"><strong class="rr-num">{{ $amount($row['remaining']) }}</strong></td>
                                <td class="rr-td">@if($row['retention_due_date'])<span class="rr-num">{{ @format_date($row['retention_due_date']) }}</span>@else<span class="rr-td--muted">@lang('construction::lang.no_due_date')</span>@endif</td>
                                <td class="rr-td">
                                    <span class="rr-badge rr-badge--{{ $dueTones[$row['due_status']] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.due_status_' . $row['due_status'])</span>
                                    @if($row['days_until_due'] !== null)<div class="rr-td__sub"><span class="rr-num">{{ abs($row['days_until_due']) }}</span> @lang($row['days_until_due'] <= 0 ? 'construction::lang.overdue_days' : 'construction::lang.days_remaining')</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ═══ محتجزات مقاولي الباطن ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr-subcontract-retentions-title">
        <div class="rr-table-head">
            <div>
                <div class="rr-eyebrow">@lang('construction::lang.liabilities_to_subcontractors')</div>
                <h2 id="rr-subcontract-retentions-title" class="rr-table-head__title">@lang('construction::lang.subcontractor_retentions')</h2>
            </div>
            <span class="rr-money">@include('construction::partials.money', ['value' => $summary['subcontract_remaining']])</span>
        </div>
        @if(count($report['subcontractor_rows']) === 0)
            <div class="rr-empty"><h3 class="rr-empty__title">@lang('construction::lang.no_subcontract_retentions')</h3></div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.subcontractor_retentions')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-col--title" scope="col">@lang('construction::lang.project')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.subcontractor')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.original_retention')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.released_retention')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.remaining_retention')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.expected_release_date')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.release_status')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.due_status')</th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($report['subcontractor_rows'] as $row)
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $row['project']?->name }}</span><div class="rr-td__sub"><span class="rr-code">{{ $row['project']?->code }}</span></div></td>
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $row['party_name'] }}</span><div class="rr-td__sub"><span class="rr-code">{{ $row['agreement_number'] }}</span></div></td>
                                <td class="rr-td"><span class="rr-code">{{ $row['certificate']->number }}</span><div class="rr-td__sub"><span class="rr-num">{{ @format_date($row['certificate']->certificate_date) }}</span></div></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($row['original']) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num {{ $row['released'] == 0 ? 'rr-td--muted' : '' }}">{{ $amount($row['released']) }}</span></td>
                                <td class="rr-td rr-td--num"><strong class="rr-num">{{ $amount($row['remaining']) }}</strong></td>
                                <td class="rr-td">@if($row['retention_due_date'])<span class="rr-num">{{ @format_date($row['retention_due_date']) }}</span>@else<span class="rr-td--muted">@lang('construction::lang.no_due_date')</span>@endif</td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $releaseTones[$row['release_status']] ?? 'neutral' }}">@lang('construction::lang.release_status_' . $row['release_status'])</span></td>
                                <td class="rr-td">
                                    <span class="rr-badge rr-badge--{{ $dueTones[$row['due_status']] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.due_status_' . $row['due_status'])</span>
                                    @if($row['days_until_due'] !== null)<div class="rr-td__sub"><span class="rr-num">{{ abs($row['days_until_due']) }}</span> @lang($row['days_until_due'] <= 0 ? 'construction::lang.overdue_days' : 'construction::lang.days_remaining')</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>
@endsection
