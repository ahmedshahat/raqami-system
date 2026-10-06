@extends('construction::layouts.module')
@section('title', __('construction::lang.measurements'))
@section('module_content')
<section class="content"><div class="box box-primary" id="ct-quick-list"><div class="box-header with-border"><h3 class="box-title">@lang('construction::lang.measurements')</h3><div class="box-tools">@can('construction.measurement.manage')<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#ct-measurement-create"><i class="fa fa-plus"></i> @lang('construction::lang.new_measurement')</button>@endcan <a href="{{ route('construction.projects.certificates.index', $project->id) }}" class="btn btn-success btn-sm"><i class="fa fa-file-invoice-dollar"></i> @lang('construction::lang.customer_certificates')</a> <a href="{{ route('construction.projects.show', $project->id) }}" class="btn btn-default btn-sm">@lang('construction::lang.project_details')</a></div></div>
        <div class="box-body"><p class="text-muted">@lang('construction::lang.measurements_description')</p><div class="table-responsive"><table class="table table-hover"><thead><tr><th>@lang('construction::lang.measurement_number')</th><th>@lang('construction::lang.measurement_date')</th><th>@lang('construction::lang.period_from')</th><th>@lang('construction::lang.period_to')</th><th>@lang('construction::lang.measurement_items')</th><th>@lang('construction::lang.status')</th><th></th></tr></thead><tbody>
            @forelse($measurements as $measurement)<tr><td><strong>{{ $measurement->number }}</strong></td><td>{{ @format_date($measurement->measurement_date) }}</td><td>{{ $measurement->period_from ? @format_date($measurement->period_from) : '—' }}</td><td>{{ $measurement->period_to ? @format_date($measurement->period_to) : '—' }}</td><td>{{ $measurement->items_count }}</td><td><span class="ct-status ct-status--{{ $measurement->status }}">@lang('construction::lang.'.$measurement->status)</span></td><td><a data-ct-workspace href="{{ route('construction.projects.measurements.show', [$project->id, $measurement->id]) }}" class="btn btn-primary btn-xs">@lang('construction::lang.open_measurement')</a></td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted">@lang('construction::lang.no_measurements')</td></tr>@endforelse
        </tbody></table></div></div>
    </div>
    <div id="ct-quick-workspace"></div>
</section>
@can('construction.measurement.manage')<div class="modal fade ct-quick-modal" id="ct-measurement-create" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content">
        <form method="POST" action="{{ route('construction.projects.measurements.store', $project->id) }}" data-ct-quick-create>@csrf<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')"><span aria-hidden="true">&times;</span></button><h4 class="modal-title">@lang('construction::lang.new_measurement')</h4></div><div class="modal-body"><div class="alert alert-danger ct-quick-errors" role="alert"></div>
            <div class="form-group"><label>@lang('construction::lang.measurement_number') *</label><input name="number" class="form-control" value="{{ old('number', $nextNumber) }}" required></div>
            <div class="form-group"><label>@lang('construction::lang.measurement_date') *</label><input type="text" name="measurement_date" class="form-control ct-date-picker" readonly value="{{ old('measurement_date', @format_date(now())) }}" required></div>
            <div class="row"><div class="col-xs-6 form-group"><label>@lang('construction::lang.period_from')</label><input type="text" name="period_from" class="form-control ct-date-picker" readonly value="{{ old('period_from') }}"></div><div class="col-xs-6 form-group"><label>@lang('construction::lang.period_to')</label><input type="text" name="period_to" class="form-control ct-date-picker" readonly value="{{ old('period_to') }}"></div></div>
            <div class="form-group"><label>@lang('construction::lang.measurement_notes')</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> @lang('construction::lang.new_measurement')</button></div></form>
    </div></div></div>@endcan
@endsection
@include('construction::partials.quick_create')

@section('module_javascript')
@if(request()->boolean('open_create'))
<script>$(function () { $('#ct-measurement-create').modal('show'); });</script>
@endif
@endsection
