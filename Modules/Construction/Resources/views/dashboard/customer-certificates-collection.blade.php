@extends('construction::layouts.module')

@section('title', __('construction::lang.customer_collection_report'))

@php
    $statusTones = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger', 'unbilled' => 'neutral'];
    $amount = fn ($value) => \Modules\Construction\Support\CurrencyFormatter::parts($value)['amount'];
    $util = app(\App\Utils\Util::class);
    $meta = [
        ['building', $selectedProject ? $selectedProject->code . ' — ' . $selectedProject->name : __('construction::lang.statement_all_projects')],
        ['user', $selectedCustomer ? ($selectedCustomer->supplier_business_name ?: $selectedCustomer->name) : __('construction::lang.all_customers')],
        ['calendar', ($fromDate ? $util->format_date($fromDate) : __('construction::lang.account_inception')) . ' — ' . $util->format_date($toDate)],
    ];
    $summary = $report['summary'];
    $agingTotal = (float) array_sum($report['aging']);
    $agingBuckets = ['current' => 'success', 'days_1_30' => 'info', 'days_31_60' => 'warning', 'days_61_90' => 'warning', 'over_90' => 'danger'];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-report-title">
    @include('construction::dashboard.partials.rr_report_switcher', ['active' => 'collection'])

    @include('construction::dashboard.partials.rr_report_head', [
        'icon' => 'download',
        'eyebrow' => __('construction::lang.receivables_control_report'),
        'title' => __('construction::lang.customer_collection_report'),
        'description' => __('construction::lang.customer_collection_report_description'),
        'printUrl' => route('construction.reports.customer-certificates-collection.print', array_filter(['project_id' => $projectId, 'customer_id' => $customerId, 'date_range' => $dateRange, 'from_date' => $fromDate, 'to_date' => $toDate, 'collection_status' => $collectionStatus])),
        'printLabel' => __('construction::lang.print_collection_report'),
        'meta' => $meta,
    ])

    <form method="GET" action="{{ route('construction.reports.customer-certificates-collection.index') }}" class="rr-filter ct-date-range-filter {{ $dateRange === 'custom' ? 'is-custom-range' : 'is-preset-range' }}">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-project">@lang('construction::lang.project')</label>
                <select id="ct-report-project" name="project_id" class="form-control select2">
                    <option value="">@lang('construction::lang.statement_all_projects')</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach
                </select>
            </div>
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-customer">@lang('construction::lang.customer')</label>
                <select id="ct-report-customer" name="customer_id" class="form-control select2">
                    <option value="">@lang('construction::lang.all_customers')</option>
                    @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected($customerId === $customer->id)>{{ $customer->supplier_business_name ?: $customer->name }}</option>@endforeach
                </select>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-report-collection-status">@lang('construction::lang.collection_status')</label>
                <div class="rr-control">
                    <select id="ct-report-collection-status" name="collection_status" class="rr-control__input rr-control__select">
                        <option value="">@lang('construction::lang.all_statuses')</option>
                        @foreach(['unbilled', 'unpaid', 'partial', 'paid'] as $status)<option value="{{ $status }}" @selected($collectionStatus === $status)>@lang('construction::lang.collection_status_' . $status)</option>@endforeach
                    </select>
                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                </div>
            </div>
            @include('construction::dashboard.partials.date-range-filter')
        </div>
        <div class="rr-filter__actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'filter', 'size' => 16])@lang('construction::lang.show_report')</button>
        </div>
    </form>

    {{-- ═══ المؤشرات ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--3" style="--rr-stats: 3">
            @foreach([
                ['approved_work', 'approved_customer_work', 'check-circle', $summary['certificates_count'] . ' ' . __('construction::lang.approved_certificates'), ''],
                ['retention', 'customer_retention', 'lock', __('construction::lang.held_by_customer'), ''],
                ['net_due', 'customer_net_due', 'file', __('construction::lang.after_tax_and_deductions'), ''],
                ['invoiced', 'invoiced_amount', 'list', $summary['unbilled_count'] . ' ' . __('construction::lang.unbilled_certificates'), ''],
                ['collected', 'collected_amount', 'download', $summary['paid_count'] . ' ' . __('construction::lang.fully_collected_certificates'), 'rr-text--success'],
                ['outstanding', 'customer_receivable', 'clock', ($summary['partial_count'] + $summary['unpaid_count']) . ' ' . __('construction::lang.open_invoices'), $summary['outstanding'] > 0 ? 'rr-text--danger' : 'rr-text--success'],
            ] as [$key, $label, $icon, $sub, $tone])
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])@lang('construction::lang.' . $label)</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $tone }}">@include('construction::partials.money', ['value' => $summary[$key]])</span></div>
                    <div class="rr-stat__sub">{{ $sub }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ═══ أعمار المديونية ═══ --}}
    <section class="rr-card" aria-labelledby="rr-aging-title">
        <header class="rr-card__head">
            <div class="rr-card__titles">
                <div class="rr-eyebrow">@lang('construction::lang.receivable_aging')</div>
                <h2 id="rr-aging-title" class="rr-card__title">@lang('construction::lang.outstanding_by_age')</h2>
            </div>
            <span class="rr-money">@include('construction::partials.money', ['value' => $summary['outstanding']])</span>
        </header>
        <div class="rr-aging">
            @foreach($agingBuckets as $bucket => $tone)
                <div class="rr-aging__bucket">
                    <span class="rr-aging__label">@lang('construction::lang.aging_' . $bucket)</span>
                    <span class="rr-money rr-money--sm {{ $report['aging'][$bucket] == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $report['aging'][$bucket]])</span>
                    <div class="rr-progress" aria-hidden="true"><div class="rr-progress__fill rr-progress__fill--{{ $tone }}" style="width: {{ $agingTotal > 0 ? round($report['aging'][$bucket] / $agingTotal * 100, 1) : 0 }}%"></div></div>
                </div>
            @endforeach
        </div>
    </section>

    @if($summary['unbilled_count'] || $summary['invoice_differences_count'])
        <div class="rr-alert rr-alert--warning" role="status">
            <div>
                <strong>@lang('construction::lang.management_alerts')</strong>
                <ul class="rr-error-list">
                    @if($summary['unbilled_count'])<li>@lang('construction::lang.unbilled_warning', ['count' => $summary['unbilled_count'], 'amount' => \Modules\Construction\Support\CurrencyFormatter::format($summary['unbilled'])])</li>@endif
                    @if($summary['invoice_differences_count'])<li>@lang('construction::lang.invoice_difference_warning', ['count' => $summary['invoice_differences_count']])</li>@endif
                </ul>
            </div>
        </div>
    @endif

    {{-- ═══ تفاصيل المستخلصات ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr-collection-rows-title">
        <div class="rr-table-head">
            <div>
                <div class="rr-eyebrow">@lang('construction::lang.accountant_details')</div>
                <h2 id="rr-collection-rows-title" class="rr-table-head__title">@lang('construction::lang.customer_certificate_collection_details')</h2>
            </div>
            <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ count($report['rows']) }}</span></span>
        </div>
        @if(count($report['rows']) === 0)
            <div class="rr-empty"><h3 class="rr-empty__title">@lang('construction::lang.no_customer_collection_rows')</h3></div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table rr-table--wide">
                    <caption class="rr-sr">@lang('construction::lang.customer_certificate_collection_details')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.project_customer')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.approved_work')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.retention')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.deductions')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.tax')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.net_due')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.invoice')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.collected')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.outstanding')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.collection_status')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.balance_age')</th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($report['rows'] as $row)
                            <tr class="rr-tr">
                                <td class="rr-td"><span class="rr-code">{{ $row['certificate']->number }}</span><div class="rr-td__sub"><span class="rr-num">{{ @format_date($row['certificate']->certificate_date) }}</span></div></td>
                                <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $row['project']?->name }}</span><div class="rr-td__sub"><span class="rr-code">{{ $row['project']?->code }}</span> · {{ $row['customer']?->supplier_business_name ?: $row['customer']?->name }}</div></td>
                                @foreach(['approved_work', 'retention', 'deductions', 'tax'] as $key)
                                    <td class="rr-td rr-td--num"><span class="rr-num {{ $row[$key] == 0 ? 'rr-td--muted' : '' }}">{{ $amount($row[$key]) }}</span></td>
                                @endforeach
                                <td class="rr-td rr-td--num"><strong class="rr-num">{{ $amount($row['net_due']) }}</strong></td>
                                <td class="rr-td">
                                    @if($row['invoice'])
                                        <span class="rr-code">{{ $row['invoice']->invoice_no }}</span><div class="rr-td__sub"><span class="rr-num">{{ $amount($row['invoice_amount']) }}</span></div>
                                    @else
                                        <span class="rr-badge">@lang('construction::lang.not_invoiced')</span>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($row['collected']) }}</span></td>
                                <td class="rr-td rr-td--num"><strong class="rr-num {{ $row['outstanding'] > 0 ? 'rr-text--danger' : '' }}">{{ $amount($row['outstanding']) }}</strong></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $statusTones[$row['status']] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.collection_status_' . $row['status'])</span></td>
                                <td class="rr-td">
                                    @if($row['invoice'] && $row['outstanding'] > 0)
                                        <strong class="rr-num">{{ $row['age_days'] }}</strong> @lang('construction::lang.days')
                                        <div class="rr-td__sub">@lang('construction::lang.due_date'): <span class="rr-num">{{ @format_date($row['due_date']) }}</span></div>
                                    @else
                                        <span class="rr-td--muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="rr-tfoot">
                        <tr>
                            <td class="rr-td" colspan="2">@lang('construction::lang.total')</td>
                            @foreach(['approved_work', 'retention', 'deductions', 'tax', 'net_due', 'invoiced', 'collected', 'outstanding'] as $key)
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($summary[$key]) }}</span></td>
                            @endforeach
                            <td class="rr-td" colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>
</main>
@endsection
