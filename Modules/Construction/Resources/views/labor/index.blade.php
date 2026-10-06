@extends('construction::layouts.module')

@section('title', __('construction::lang.labor_sheets'))

@section('module_content')
<section class="content ct-materials-page ct-labor-page">
    <div class="ct-materials-hero ct-labor-hero">
        <div>
            <span class="ct-eyebrow"><i class="fas fa-hard-hat"></i> @lang('construction::lang.labor_cost_center')</span>
            <h1>@lang('construction::lang.labor_sheets')</h1>
            <p>@lang('construction::lang.labor_sheets_hint')</p>
        </div>
        @can('construction.labor.manage')
            <a href="{{ route('construction.labor.create') }}" class="btn ct-primary-action"><i class="fa fa-plus"></i> @lang('construction::lang.new_labor_sheet')</a>
        @endcan
    </div>

    <div class="ct-material-stat-grid ct-labor-stats">
        <a href="{{ route('construction.labor.index') }}" class="ct-material-stat {{ !$status ? 'is-active' : '' }}"><span class="ct-material-stat__icon is-blue"><i class="fas fa-file-alt"></i></span><span><small>@lang('construction::lang.all_sheets')</small><strong>{{ $stats['all'] }}</strong></span></a>
        <a href="{{ route('construction.labor.index', ['status' => 'draft']) }}" class="ct-material-stat {{ $status === 'draft' ? 'is-active' : '' }}"><span class="ct-material-stat__icon is-amber"><i class="fas fa-pencil-alt"></i></span><span><small>@lang('construction::lang.draft_sheets')</small><strong>{{ $stats['draft'] }}</strong></span></a>
        <a href="{{ route('construction.labor.index', ['status' => 'approved']) }}" class="ct-material-stat {{ $status === 'approved' ? 'is-active' : '' }}"><span class="ct-material-stat__icon is-green"><i class="fas fa-check-circle"></i></span><span><small>@lang('construction::lang.approved_sheets')</small><strong>{{ $stats['approved'] }}</strong></span></a>
        <a href="{{ route('construction.labor.index', ['status' => 'cancelled']) }}" class="ct-material-stat {{ $status === 'cancelled' ? 'is-active' : '' }}"><span class="ct-material-stat__icon is-red"><i class="fas fa-ban"></i></span><span><small>@lang('construction::lang.cancelled_labor_sheets')</small><strong>{{ $stats['cancelled'] }}</strong></span></a>
        <div class="ct-material-stat"><span class="ct-material-stat__icon is-violet"><i class="fas fa-coins"></i></span><span><small>@lang('construction::lang.approved_labor_cost')</small><strong class="ct-stat-money">@include('construction::partials.money', ['value' => $stats['approved_total']])</strong></span></div>
    </div>

    <div class="ct-material-panel">
        <form method="GET" class="ct-material-filter ct-labor-filter">
            <div class="ct-material-filter__search">
                <label>@lang('construction::lang.search')</label>
                <div class="ct-input-icon"><i class="fa fa-search"></i><input name="q" value="{{ $search }}" class="form-control" placeholder="@lang('construction::lang.search_labor_sheets')"></div>
            </div>
            <div>
                <label>@lang('construction::lang.project')</label>
                <select name="project_id" class="form-control select2">
                    <option value="">@lang('construction::lang.all_projects')</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>@lang('construction::lang.status')</label>
                <select name="status" class="form-control">
                    <option value="">@lang('construction::lang.all_statuses')</option>
                    <option value="draft" @selected($status === 'draft')>@lang('construction::lang.draft')</option>
                    <option value="approved" @selected($status === 'approved')>@lang('construction::lang.approved')</option>
                    <option value="cancelled" @selected($status === 'cancelled')>@lang('construction::lang.cancelled')</option>
                </select>
            </div>
            <div class="ct-material-filter__actions">
                <button class="btn btn-primary"><i class="fa fa-filter"></i> @lang('construction::lang.apply_filter')</button>
                <a class="btn btn-default" href="{{ route('construction.labor.index') }}"><i class="fa fa-times"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table ct-material-table">
                <thead>
                    <tr>
                        <th>@lang('construction::lang.sheet_number')</th>
                        <th>@lang('construction::lang.project')</th>
                        <th>@lang('construction::lang.period')</th>
                        <th>@lang('construction::lang.work_site')</th>
                        <th>@lang('construction::lang.status')</th>
                        <th>@lang('construction::lang.labor_lines')</th>
                        <th>@lang('construction::lang.total_cost')</th>
                        <th class="text-center">@lang('construction::lang.operations')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sheets as $sheet)
                        <tr class="{{ $sheet->status === 'cancelled' ? 'ct-row-cancelled' : '' }}">
                            <td><a class="ct-document-link" href="{{ route('construction.labor.show', $sheet->id) }}"><span class="ct-document-type-icon ct-labor-icon"><i class="fas fa-hard-hat"></i></span><span><strong>{{ $sheet->number }}</strong><small>@lang('construction::lang.labor_sheet')</small></span></a></td>
                            <td><strong class="ct-project-name">{{ $sheet->project->name }}</strong><small class="ct-table-muted">{{ $sheet->project->code }}</small></td>
                            <td>{{ @format_date($sheet->period_start) }} — {{ @format_date($sheet->period_end) }}</td>
                            <td>{{ $sheet->work_site ?: '—' }}</td>
                            <td><span class="ct-status-pill {{ $sheet->status === 'approved' ? 'is-approved' : ($sheet->status === 'cancelled' ? 'is-cancelled' : 'is-draft') }}">@lang('construction::lang.'.$sheet->status)</span></td>
                            <td>{{ $sheet->lines_count }}</td>
                            <td><strong>@include('construction::partials.money', ['value' => $sheet->lines_sum_total_cost ?: 0])</strong></td>
                            <td class="text-center">
                                <div class="btn-group ct-actions-menu">
                                    <button class="btn dropdown-toggle" data-toggle="dropdown"><i class="fa fa-ellipsis-h"></i><span>@lang('construction::lang.operations')</span></button>
                                    <ul class="dropdown-menu dropdown-menu-left">
                                        <li><a href="{{ route('construction.labor.show', $sheet->id) }}"><i class="fa fa-eye"></i> @lang('construction::lang.open_sheet')</a></li>
                                        <li><a href="{{ route('construction.labor.preview', $sheet->id) }}" target="_blank"><i class="fa fa-print"></i> @lang('construction::lang.print_preview')</a></li>
                                        @if($sheet->status === 'draft')
                                            @can('construction.labor.manage')
                                                <li><a href="{{ route('construction.labor.edit', $sheet->id) }}"><i class="fa fa-pencil"></i> @lang('construction::lang.edit_sheet')</a></li>
                                                <li class="divider"></li>
                                                <li><form method="POST" action="{{ route('construction.labor.destroy', $sheet->id) }}" onsubmit="return confirm('@lang('construction::lang.delete_labor_sheet_confirmation')')">@csrf @method('DELETE')<button class="ct-dropdown-danger"><i class="fa fa-trash"></i> @lang('construction::lang.delete_draft')</button></form></li>
                                            @endcan
                                        @elseif($sheet->status === 'approved' && $canCancelApproved)
                                            <li class="divider"></li>
                                            <li><a class="ct-dropdown-danger" href="{{ route('construction.labor.show', ['sheet' => $sheet->id, 'cancel' => 1]) }}"><i class="fa fa-ban"></i> @lang('construction::lang.cancel_approved_labor_sheet')</a></li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="ct-empty-state"><span><i class="fas fa-users"></i></span><strong>@lang('construction::lang.no_labor_sheets')</strong><p>@lang('construction::lang.no_labor_sheets_hint')</p>@can('construction.labor.manage')<a href="{{ route('construction.labor.create') }}" class="btn btn-primary">@lang('construction::lang.new_labor_sheet')</a>@endcan</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ct-panel-pagination">{{ $sheets->links() }}</div>
    </div>
</section>
@endsection
