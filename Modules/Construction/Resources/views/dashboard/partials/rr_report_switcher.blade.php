{{-- التنقل بين تقارير المقاولات. $active = مفتاح التقرير الحالي --}}
@php
    $reportTabs = [
        'financial' => ['construction.reports.index', 'financial_position_report', 'pie'],
        'profitability' => ['construction.reports.boq-cost-profitability.index', 'boq_cost_profitability', 'list'],
        'comparison' => ['construction.reports.boq-subcontract-comparison.index', 'boq_subcontract_comparison', 'building'],
        'collection' => ['construction.reports.customer-certificates-collection.index', 'customer_collection_report', 'download'],
        'retention' => ['construction.reports.retention-guarantees.index', 'retention_guarantees_report', 'shield'],
        'statement' => ['construction.reports.subcontractor-statement.index', 'subcontractor_statement', 'users'],
    ];
@endphp
<nav class="rr-tabs rr-report-tabs" aria-label="@lang('construction::lang.construction_reports')">
    @foreach($reportTabs as $key => [$routeName, $label, $icon])
        <a class="rr-tab" href="{{ route($routeName) }}" @if($active === $key) aria-current="page" @endif>@include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])@lang('construction::lang.' . $label)</a>
    @endforeach
</nav>
