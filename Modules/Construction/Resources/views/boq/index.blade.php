@extends('construction::layouts.module')
@section('title', __('construction::lang.boq_versions'))

@section('module_content')
<section class="content">
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('construction::lang.boq_versions')</h3>
                    <div class="box-tools">
                        <a href="{{ route('construction.cost-codes.index') }}" class="btn btn-default btn-sm"><i class="fa fa-tags"></i> @lang('construction::lang.manage_cost_codes')</a>
                        <a href="{{ route('construction.projects.show', $project->id) }}" class="btn btn-default btn-sm">@lang('construction::lang.project_details')</a>
                    </div>
                </div>
                <div class="box-body">
                    <p class="text-muted">@lang('construction::lang.boq_versions_description')</p>
                    @if($versions->isEmpty())
                        <div class="callout callout-info"><p>@lang('construction::lang.no_boq_versions')</p></div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead><tr><th>@lang('construction::lang.boq_version_number')</th><th>@lang('construction::lang.boq_version_name')</th><th>@lang('construction::lang.version_status')</th><th>@lang('construction::lang.boq_items')</th><th>@lang('construction::lang.boq_sales_total')</th><th></th></tr></thead>
                                <tbody>@foreach($versions as $version)
                                    <tr>
                                        <td><strong>V{{ $version->version_number }}</strong></td>
                                        <td>{{ $version->name }}</td>
                                        <td><span class="ct-status ct-status--{{ $version->status }}">@lang('construction::lang.'.$version->status)</span></td>
                                        <td>{{ $version->items_count }}</td>
                                        <td>@include('construction::partials.money', ['value' => $version->sales_total])</td>
                                        <td><a href="{{ route('construction.projects.boq.show', [$project->id, $version->id]) }}" class="btn btn-primary btn-xs">@lang('construction::lang.open_boq')</a></td>
                                    </tr>
                                @endforeach</tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            @can('construction.boq.manage')
            <div class="box box-success">
                <div class="box-header with-border"><h3 class="box-title">@lang('construction::lang.new_boq_version')</h3></div>
                <form method="POST" action="{{ route('construction.projects.boq.store', $project->id) }}">@csrf
                    <div class="box-body">
                        <div class="form-group"><label>@lang('construction::lang.boq_version_name') *</label><input name="name" value="{{ old('name', __('construction::lang.boq_default_name')) }}" class="form-control" maxlength="190" required></div>
                        <div class="form-group"><label>@lang('construction::lang.boq_notes')</label><textarea name="notes" class="form-control" rows="4" maxlength="5000">{{ old('notes') }}</textarea></div>
                    </div>
                    <div class="box-footer"><button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> @lang('construction::lang.new_boq_version')</button></div>
                </form>
            </div>
            @endcan
        </div>
    </div>
</section>
@endsection
