@extends('construction::layouts.module')
@section('title', __('construction::lang.cost_codes'))

@section('module_content')
<section class="content"><div class="box box-primary" id="ct-quick-list">
        <div class="box-header with-border"><h3 class="box-title">@lang('construction::lang.cost_codes')</h3><div class="box-tools">@can('construction.boq.manage')<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#ct-cost-code-create"><i class="fa fa-plus"></i> @lang('construction::lang.add_cost_code')</button>@endcan <a href="{{ route('construction.project-items.index') }}" class="btn btn-default btn-sm">@lang('construction::lang.dashboard')</a></div></div>
        <div class="box-body"><p class="text-muted">@lang('construction::lang.cost_codes_description')</p><div class="table-responsive"><table class="table table-hover"><thead><tr><th>@lang('construction::lang.cost_code')</th><th>@lang('construction::lang.cost_code_name')</th><th>@lang('construction::lang.cost_category')</th><th>@lang('construction::lang.structure_parent')</th><th>@lang('construction::lang.status')</th></tr></thead><tbody>
            @forelse($costCodes as $costCode)<tr><td><strong>{{ $costCode->code }}</strong></td><td>{{ $costCode->name }}</td><td><span class="ct-status ct-status--info">@lang('construction::lang.'.$costCode->category)</span></td><td>{{ $costCode->parent ? $costCode->parent->code.' - '.$costCode->parent->name : '—' }}</td><td><span class="ct-status ct-status--{{ $costCode->is_active ? 'active' : 'cancelled' }}">{{ $costCode->is_active ? __('construction::lang.active') : __('construction::lang.cancelled') }}</span></td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted">@lang('construction::lang.no_cost_codes')</td></tr>@endforelse
        </tbody></table></div></div>
    </div>
</section>
@can('construction.boq.manage')<div class="modal fade ct-quick-modal" id="ct-cost-code-create" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content">
        <form method="POST" action="{{ route('construction.cost-codes.store') }}" data-ct-quick-create>@csrf<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')"><span aria-hidden="true">&times;</span></button><h4 class="modal-title">@lang('construction::lang.add_cost_code')</h4></div><div class="modal-body"><div class="alert alert-danger ct-quick-errors" role="alert"></div>
            <div class="form-group"><label>@lang('construction::lang.cost_code') *</label><input name="code" class="form-control" maxlength="60" required></div>
            <div class="form-group"><label>@lang('construction::lang.cost_code_name') *</label><input name="name" class="form-control" maxlength="190" required></div>
            <div class="form-group"><label>@lang('construction::lang.cost_category') *</label><select name="category" class="form-control">@foreach(\Modules\Construction\Entities\ConstructionCostCode::CATEGORIES as $category)<option value="{{ $category }}">@lang('construction::lang.'.$category)</option>@endforeach</select></div>
            <div class="form-group"><label>@lang('construction::lang.structure_parent')</label><select name="parent_id" class="form-control"><option value="">@lang('construction::lang.root_level')</option>@foreach($costCodes as $code)<option value="{{ $code->id }}">{{ $code->code }} - {{ $code->name }}</option>@endforeach</select></div>
            <div class="form-group"><label>@lang('construction::lang.sort_order')</label><input type="number" name="sort_order" class="form-control" min="0" value="0"></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> @lang('construction::lang.add_cost_code')</button></div></form>
    </div></div></div>@endcan
@endsection
@include('construction::partials.quick_create')
