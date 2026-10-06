@extends('construction::layouts.module')
@section('title', __('construction::lang.projects'))

@section('module_content')
<section class="content">
    <div class="ct-kpi-grid" id="ct-project-summary" style="margin-bottom: 20px;">
        @foreach([
            ['all', 'all_projects', 'blue', 'fas fa-city', 'portfolio'],
            ['active', 'active_projects', 'green', 'fas fa-hammer', 'execution'],
            ['draft', 'draft_projects', 'amber', 'fas fa-pencil-ruler', 'planning'],
            ['completed', 'completed_projects', 'slate', 'fas fa-check-circle', 'closed']
        ] as [$key, $label, $tone, $icon, $tag])
            <article class="ct-kpi ct-kpi--{{ $tone }}">
                <div class="ct-kpi__top">
                    <span class="ct-kpi__icon"><i class="{{ $icon }}"></i></span>
                    <span class="ct-kpi__tag">@lang('construction::lang.'.$tag)</span>
                </div>
                <small>@lang('construction::lang.'.$label)</small>
                <strong>{{ $summary[$key] }}</strong>
                <p>{{ $summary['all'] > 0 ? @num_format(($summary[$key] / $summary['all']) * 100) : 0 }}% @lang('construction::lang.out_of') {{ $summary['all'] }}</p>
            </article>
        @endforeach
    </div>
    <div class="box box-solid" id="ct-quick-list">
        <div class="box-header with-border"><h3 class="box-title">@lang('construction::lang.projects')</h3><div class="box-tools">@can('construction.project.create')<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#ct-project-create"><i class="fa fa-plus"></i> @lang('construction::lang.new_project')</button> <a href="{{ route('construction.quotes.index', ['open_create' => 1]) }}" class="btn btn-default btn-sm"><i class="fa fa-file-invoice"></i> @lang('construction::lang.new_quote')</a>@endcan</div></div>
        <div class="box-body">
            <form method="GET" class="row ct-filter-form">
                <div class="col-md-6 col-sm-6 col-xs-12" style="margin-bottom:10px;"><input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="@lang('construction::lang.search_projects')"></div>
                <div class="col-md-3 col-sm-3 col-xs-8" style="margin-bottom:10px;"><select name="status" class="form-control"><option value="">@lang('construction::lang.all_statuses')</option>@foreach(\Modules\Construction\Entities\ConstructionProject::STATUSES as $projectStatus)<option value="{{ $projectStatus }}" @selected($status === $projectStatus)>@lang('construction::lang.'.$projectStatus)</option>@endforeach</select></div>
                <div class="col-md-3 col-sm-3 col-xs-4" style="margin-bottom:10px;"><button class="btn btn-default btn-block" type="submit"><i class="fa fa-search"></i> @lang('construction::lang.search')</button></div>
            </form>
            <div class="table-responsive"><table class="table table-bordered table-striped">
                <thead><tr><th>@lang('construction::lang.project_code')</th><th>@lang('construction::lang.project_name')</th><th>@lang('construction::lang.customer')</th><th>@lang('construction::lang.project_manager')</th><th>@lang('construction::lang.contract_value')</th><th>@lang('construction::lang.status')</th><th>@lang('construction::lang.actions')</th></tr></thead>
                <tbody>@forelse($projects as $project)<tr>
                    <td>{{ $project->code }}</td><td><a href="{{ route('construction.projects.show',$project->id) }}">{{ $project->name }}</a></td>
                    <td>{{ $project->customer->supplier_business_name ?: $project->customer->name }}</td><td>{{ $project->manager?->user_full_name ?: __('construction::lang.not_set') }}</td>
                    <td>@include('construction::partials.money', ['value' => (float) optional($project->primaryContract)->original_value])</td><td><span class="ct-status ct-status--{{ $project->status }}">@lang('construction::lang.'.$project->status)</span></td>
                    <td><a href="{{ route('construction.projects.show',$project->id) }}" class="btn btn-xs btn-info">@lang('construction::lang.view')</a> @can('construction.project.update')<a href="{{ route('construction.projects.edit',$project->id) }}" class="btn btn-xs btn-primary">@lang('construction::lang.edit')</a>@endcan</td>
                </tr>@empty<tr><td colspan="7" class="text-center text-muted">@lang('construction::lang.no_projects_yet')</td></tr>@endforelse</tbody>
            </table></div>{{ $projects->links() }}
        </div>
    </div>
    <div id="ct-quick-workspace"></div>
</section>
@can('construction.project.create')@include('construction::projects.partials.create_modal')@endcan
@endsection
@include('construction::partials.quick_create')
