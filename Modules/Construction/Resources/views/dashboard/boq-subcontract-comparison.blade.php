@extends('construction::layouts.module')

@section('title', __('construction::lang.boq_subcontract_comparison'))

@php
    $marginTones = ['healthy' => 'success', 'low' => 'warning', 'loss' => 'danger'];
    $amount = fn ($value) => \Modules\Construction\Support\CurrencyFormatter::parts($value)['amount'];
    $meta = $report ? [
        ['building', $selectedProject->code . ' — ' . $selectedProject->name],
        ['user', $selectedProject->customer?->name ?: '—'],
        ['calendar', __('construction::lang.as_of_date') . ' ' . app(\App\Utils\Util::class)->format_date($toDate)],
    ] : [];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-report-title">
    @include('construction::dashboard.partials.rr_report_switcher', ['active' => 'comparison'])

    @include('construction::dashboard.partials.rr_report_head', [
        'icon' => 'building',
        'eyebrow' => __('construction::lang.owner_profitability_report'),
        'title' => __('construction::lang.boq_subcontract_comparison'),
        'description' => __('construction::lang.boq_subcontract_comparison_description'),
        'printUrl' => $report ? route('construction.reports.boq-subcontract-comparison.print', ['project_id' => $selectedProject->id, 'to_date' => $toDate]) : null,
        'printLabel' => __('construction::lang.print_comparison_report'),
        'meta' => $meta,
    ])

    <form method="GET" action="{{ route('construction.reports.boq-subcontract-comparison.index') }}" class="rr-filter">
        <div class="rr-filter__fields">
            <div class="rr-field rr-filter__grow">
                <label class="rr-field__label" for="ct-report-project">@lang('construction::lang.project')<span class="rr-field__req" aria-hidden="true">*</span></label>
                <select id="ct-report-project" name="project_id" class="form-control select2" required>
                    <option value="">@lang('construction::lang.select_project')</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach
                </select>
            </div>
            <div class="rr-field">
                <label class="rr-field__label" for="ct-report-as-of">@lang('construction::lang.as_of_date')</label>
                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input id="ct-report-as-of" name="to_date" value="{{ @format_date($toDate) }}" class="rr-control__input ct-date-picker" readonly></div>
            </div>
        </div>
        <div class="rr-filter__actions">
            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 16])@lang('construction::lang.show_report')</button>
        </div>
    </form>

    @if(! $report)
        <div class="rr-card">
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 22])</span>
                <h2 class="rr-empty__title">@lang('construction::lang.comparison_select_project')</h2>
                <p class="rr-empty__text">@lang('construction::lang.comparison_select_project_hint')</p>
            </div>
        </div>
    @else
        @include('construction::dashboard.partials.rr_report_warnings', ['warnings' => $report['warnings']])

        {{-- المتوقع تعاقديًا مقابل الفعلي حتى تاريخه --}}
        <div class="rr-report-grid">
            @foreach([
                'contract' => ['contractual_expected', 'customer_sale_value', 'subcontract_assignment_value', 'expected_margin'],
                'actual' => ['actual_to_date', 'customer_certified_value', 'subcontract_certified_value', 'actual_margin'],
            ] as $side => [$title, $salesLabel, $assignmentLabel, $marginLabel])
                @php $block = $report[$side]; @endphp
                <section class="rr-card" aria-labelledby="rr-compare-{{ $side }}">
                    <header class="rr-card__head">
                        <div class="rr-card__titles">
                            <h2 id="rr-compare-{{ $side }}" class="rr-card__title">@lang('construction::lang.' . $title)</h2>
                            <p class="rr-card__desc"><span class="rr-num">{{ $block['loss_items'] }}</span> @lang('construction::lang.loss_making_items')</p>
                        </div>
                        <span class="rr-badge rr-badge--{{ $block['margin'] < 0 ? 'danger' : ($block['margin_percent'] < 10 ? 'warning' : 'success') }}"><span class="rr-num">{{ @num_format($block['margin_percent']) }}%</span></span>
                    </header>
                    <dl class="rr-deflist">
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.' . $salesLabel)</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $block['sales']])</span></dd></div>
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.' . $assignmentLabel)</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $block['assignment']])</span></dd></div>
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.' . $marginLabel)</dt><dd><span class="rr-money rr-money--sm {{ $block['margin'] < 0 ? 'rr-text--danger' : 'rr-text--success' }}">@include('construction::partials.money', ['value' => $block['margin']])</span></dd></div>
                    </dl>
                </section>
            @endforeach
        </div>

        <section class="rr-table-wrap" aria-labelledby="rr-compare-items-title">
            <div class="rr-table-head rr-table-head--toolbar">
                <div class="rr-table-head__titles">
                    <div class="rr-eyebrow">@lang('construction::lang.item_level_analysis')</div>
                    <h2 id="rr-compare-items-title" class="rr-table-head__title">@lang('construction::lang.sale_assignment_margin_by_item')</h2>
                </div>
                <ul class="rr-legend rr-legend--inline">
                    <li class="rr-legend__item"><span class="rr-badge rr-badge--success">@lang('construction::lang.margin_healthy')</span></li>
                    <li class="rr-legend__item"><span class="rr-badge rr-badge--warning">@lang('construction::lang.margin_low')</span></li>
                    <li class="rr-legend__item"><span class="rr-badge rr-badge--danger">@lang('construction::lang.margin_loss')</span></li>
                </ul>
            </div>
            @if($report['rows']->isEmpty())
                <div class="rr-empty"><h3 class="rr-empty__title">@lang('construction::lang.no_comparison_items')</h3></div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.sale_assignment_margin_by_item')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th rr-col--text" scope="col" rowspan="2">@lang('construction::lang.boq_item')</th>
                                <th class="rr-th" scope="col" rowspan="2">@lang('construction::lang.subcontractor')</th>
                                <th class="rr-th rr-th--group" scope="colgroup" colspan="3">@lang('construction::lang.contractual_expected')</th>
                                <th class="rr-th rr-th--group" scope="colgroup" colspan="3">@lang('construction::lang.actual_to_date')</th>
                            </tr>
                            <tr>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.customer_sale')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.subcontract_assignment')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.margin')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.customer_certified')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.subcontract_certified')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.margin')</th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($report['rows'] as $row)
                                <tr class="rr-tr">
                                    <td class="rr-td rr-td--wrap"><span class="rr-code">{{ $row['code'] }}</span> <span class="rr-entity__name">{{ $row['description'] }}</span><div class="rr-td__sub"><span class="rr-num">{{ @num_format($row['contract_quantity']) }}</span> {{ $row['unit'] }}</div></td>
                                    <td class="rr-td rr-td--wrap rr-td--muted">{{ $row['subcontractors'] ?: '—' }}</td>
                                    @foreach(['contract', 'actual'] as $side)
                                        <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($row[$side]['sales']) }}</span></td>
                                        <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($row[$side]['assignment']) }}</span></td>
                                        <td class="rr-td rr-td--num"><span class="rr-badge rr-badge--{{ $marginTones[$row[$side]['status']] ?? 'neutral' }}"><span class="rr-num">{{ $amount($row[$side]['margin']) }}</span></span><div class="rr-td__sub"><span class="rr-num">{{ @num_format($row[$side]['margin_percent']) }}%</span></div></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="rr-tfoot">
                            <tr>
                                <td class="rr-td" colspan="2">@lang('construction::lang.total')</td>
                                @foreach(['contract', 'actual'] as $side)
                                    @foreach(['sales', 'assignment', 'margin'] as $key)
                                        <td class="rr-td rr-td--num"><span class="rr-num">{{ $amount($report[$side][$key]) }}</span></td>
                                    @endforeach
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    @endif
</main>
@endsection
