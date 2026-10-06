@extends('construction::layouts.module')

@section('title', __('construction::lang.nav_costs'))

@section('module_content')
<section class="content ct-costs-page">
    <header class="ct-costs-heading">
        <div>
            <span><i class="fas fa-coins"></i> @lang('construction::lang.actual_costs')</span>
            <h1>@lang('construction::lang.costs_overview_title')</h1>
            <p>@lang('construction::lang.costs_overview_description')</p>
        </div>
        <form method="GET" action="{{ route('construction.costs.index') }}" class="ct-costs-filter">
            <label for="ct-cost-project">@lang('construction::lang.filter_by_project')</label>
            <div>
                <select id="ct-cost-project" name="project_id" class="form-control select2">
                    <option value="">@lang('construction::lang.all_projects')</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> @lang('construction::lang.apply_filter')</button>
                @if($selectedProjectId)<a href="{{ route('construction.costs.index') }}" class="btn btn-default">@lang('construction::lang.clear_filter')</a>@endif
            </div>
        </form>
    </header>

    <div class="ct-cost-cards">
        <article class="ct-cost-card ct-cost-card--total">
            <span class="ct-cost-card__icon"><i class="fas fa-chart-pie"></i></span>
            <div><small>@lang('construction::lang.total_recorded_costs')</small><strong>@include('construction::partials.money', ['value' => $summary['total']])</strong><p>@lang('construction::lang.actual_costs_note')</p></div>
        </article>
        <a href="#ct-cost-expenses" class="ct-cost-card ct-cost-card--expenses">
            <span class="ct-cost-card__icon"><i class="fas fa-receipt"></i></span>
            <div><small>@lang('construction::lang.cost_expenses')</small><strong>@include('construction::partials.money', ['value' => $summary['expenses']])</strong><p>{{ $summary['records_count'] }} @lang('construction::lang.expense_records')</p></div>
            <i class="fas fa-arrow-left ct-cost-card__arrow"></i>
        </a>
        <a href="{{ route('construction.materials.index', array_filter(['project_id' => $selectedProjectId])) }}" class="ct-cost-card ct-cost-card--materials">
            <span class="ct-cost-card__icon"><i class="fas fa-cubes"></i></span>
            <div><small>@lang('construction::lang.cost_materials')</small><strong>@include('construction::partials.money', ['value' => $summary['materials']])</strong><p>{{ $summary['material_issue_lines'] }} @lang('construction::lang.issue_lines') · {{ $summary['material_return_lines'] }} @lang('construction::lang.return_lines')</p></div>
            <i class="fas fa-arrow-left ct-cost-card__arrow"></i>
        </a>
        <a href="{{ route('construction.labor.index', array_filter(['project_id' => $selectedProjectId])) }}" class="ct-cost-card ct-cost-card--labor">
            <span class="ct-cost-card__icon"><i class="fas fa-users"></i></span>
            <div><small>@lang('construction::lang.cost_labor')</small><strong>@include('construction::partials.money', ['value' => $summary['labor']])</strong><p>{{ $summary['labor_lines'] }} @lang('construction::lang.labor_lines')</p></div>
            <i class="fas fa-arrow-left ct-cost-card__arrow"></i>
        </a>
        <a href="{{ route('construction.subcontractors.index', array_filter(['project_id' => $selectedProjectId])) }}" class="ct-cost-card ct-cost-card--subcontracts" data-cost-source="subcontracts" data-cost-value="{{ $summary['subcontracts'] }}" data-document-count="{{ $summary['subcontract_certificates'] }}">
            <span class="ct-cost-card__icon"><i class="fas fa-people-carry"></i></span>
            <div><small>@lang('construction::lang.cost_subcontracts')</small><strong>@include('construction::partials.money', ['value' => $summary['subcontracts']])</strong><p>{{ $summary['subcontract_certificates'] }} @lang('construction::lang.approved_subcontract_certificates')</p></div>
            <i class="fas fa-arrow-left ct-cost-card__arrow"></i>
        </a>
    </div>

    <section id="ct-cost-expenses" class="ct-cost-panel">
        <div class="ct-cost-panel__heading">
            <div><span>@lang('construction::lang.active_cost_source')</span><h2>@lang('construction::lang.expense_details')</h2><p>@lang('construction::lang.expense_details_description', ['projects' => $summary['projects_count']])</p></div>
            @if(auth()->user()->can('all_expense.access') || auth()->user()->can('view_own_expense'))
                <a class="btn btn-primary" href="{{ route('expenses.index', array_filter(['construction_only' => 1, 'construction_project_id' => $selectedProjectId])) }}"><i class="fa fa-external-link-alt"></i> @lang('construction::lang.open_expense_register')</a>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover ct-cost-table">
                <thead><tr><th>@lang('construction::lang.date')</th><th>@lang('construction::lang.expense_reference')</th><th>@lang('construction::lang.project')</th><th>@lang('construction::lang.project_item')</th><th>@lang('construction::lang.location')</th><th class="text-left">@lang('construction::lang.amount')</th></tr></thead>
                <tbody>
                @forelse($recentExpenses as $expense)
                    <tr data-expense-reference="{{ $expense->ref_no }}">
                        <td>{{ @format_date($expense->transaction_date) }}</td>
                        <td><strong>{{ $expense->ref_no ?: __('construction::lang.expense_without_reference') }}</strong></td>
                        <td>{{ $expense->constructionProject?->code }} — {{ $expense->constructionProject?->name }}</td>
                        <td>{{ $expense->constructionProjectItem ? $expense->constructionProjectItem->code.' — '.$expense->constructionProjectItem->description : __('construction::lang.general_project_expense') }}</td>
                        <td>{{ $expense->location?->name ?: '—' }}</td>
                        <td class="text-left"><strong class="{{ $expense->type === 'expense_refund' ? 'text-success' : '' }}">@if($expense->type === 'expense_refund')− @endif @include('construction::partials.money', ['value' => $expense->final_total])</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">@lang('construction::lang.no_cost_expenses')</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
