@extends('construction::layouts.module')

@section('title', __('construction::lang.tab_subcontractors'))

@section('module_content')
<section class="content ct-materials-page ct-subcontract-page">
    <div class="ct-materials-hero ct-subcontract-hero">
        <div>
            <span class="ct-eyebrow"><i class="fas fa-people-carry"></i> @lang('construction::lang.subcontract_management')</span>
            <h1>@lang('construction::lang.subcontract_agreements')</h1>
            <p>@lang('construction::lang.subcontract_phase_one_hint')</p>
        </div>
        @can('construction.subcontract.manage')
            <button class="btn ct-primary-action" data-toggle="modal" data-target="#create-subcontract"><i class="fa fa-plus"></i> @lang('construction::lang.new_subcontract')</button>
        @endcan
    </div>

    <div class="ct-material-stat-grid">
        <a href="{{ route('construction.subcontractors.index') }}" class="ct-material-stat {{ !$status ? 'is-active' : '' }}"><span class="ct-material-stat__icon is-blue"><i class="fa fa-file-contract"></i></span><span><small>@lang('construction::lang.all_agreements')</small><strong>{{ $stats['all'] }}</strong></span></a>
        <a href="{{ route('construction.subcontractors.index',['status'=>'draft']) }}" class="ct-material-stat {{ $status === 'draft' ? 'is-active' : '' }}"><span class="ct-material-stat__icon is-amber"><i class="fa fa-pencil-alt"></i></span><span><small>@lang('construction::lang.draft_agreements')</small><strong>{{ $stats['draft'] }}</strong></span></a>
        <a href="{{ route('construction.subcontractors.index',['status'=>'approved']) }}" class="ct-material-stat {{ $status === 'approved' ? 'is-active' : '' }}"><span class="ct-material-stat__icon is-green"><i class="fa fa-check-circle"></i></span><span><small>@lang('construction::lang.approved_agreements')</small><strong>{{ $stats['approved'] }}</strong></span></a>
        <div class="ct-material-stat"><span class="ct-material-stat__icon is-violet"><i class="fa fa-coins"></i></span><span><small>@lang('construction::lang.approved_subcontract_value')</small><strong class="ct-stat-money">@include('construction::partials.money',['value'=>$stats['approved_total']])</strong></span></div>
    </div>

    <div class="ct-material-panel">
        <form method="GET" class="ct-material-filter ct-subcontract-filter">
            <div class="ct-material-filter__search"><label>@lang('construction::lang.search')</label><div class="ct-input-icon"><i class="fa fa-search"></i><input name="q" value="{{ $search }}" class="form-control" placeholder="@lang('construction::lang.search_subcontracts')"></div></div>
            <div><label>@lang('construction::lang.project')</label><select name="project_id" class="form-control select2"><option value="">@lang('construction::lang.all_projects')</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></div>
            <div><label>@lang('construction::lang.status')</label><select name="status" class="form-control"><option value="">@lang('construction::lang.all_statuses')</option>@foreach(['draft','approved','completed','cancelled'] as $value)<option value="{{ $value }}" @selected($status===$value)>@lang('construction::lang.'.$value)</option>@endforeach</select></div>
            <div class="ct-material-filter__actions"><button class="btn btn-primary"><i class="fa fa-filter"></i> @lang('construction::lang.apply_filter')</button><a href="{{ route('construction.subcontractors.index') }}" class="btn btn-default"><i class="fa fa-times"></i></a></div>
        </form>
        <div class="table-responsive">
            <table class="table ct-material-table">
                <thead><tr><th>@lang('construction::lang.agreement_number')</th><th>@lang('construction::lang.subcontractor')</th><th>@lang('construction::lang.project')</th><th>@lang('construction::lang.assigned_items')</th><th>@lang('construction::lang.retention_percent')</th><th>@lang('construction::lang.agreement_value')</th><th>@lang('construction::lang.status')</th><th>@lang('construction::lang.operations')</th></tr></thead>
                <tbody>
                    @forelse($subcontracts as $contract)
                        <tr>
                            <td><a class="ct-document-link" href="{{ route('construction.subcontractors.show',$contract->id) }}"><span class="ct-document-type-icon"><i class="fa fa-file-signature"></i></span><span><strong>{{ $contract->number }}</strong><small>{{ $contract->title }}</small></span></a></td>
                            <td><strong>{{ $contract->subcontractor->supplier_business_name ?: $contract->subcontractor->name }}</strong></td>
                            <td><strong>{{ $contract->project->name }}</strong><small class="ct-table-muted">{{ $contract->project->code }}</small></td>
                            <td>{{ $contract->items_count }}</td><td>{{ @num_format($contract->retention_percent) }}%</td>
                            <td><strong>@include('construction::partials.money',['value'=>$contract->total_value])</strong></td>
                            <td><span class="ct-status-pill {{ $contract->status === 'approved' ? 'is-approved' : 'is-draft' }}">@lang('construction::lang.'.$contract->status)</span></td>
                            <td><div class="btn-group ct-actions-menu"><button class="btn dropdown-toggle" data-toggle="dropdown"><i class="fa fa-ellipsis-h"></i><span>@lang('construction::lang.operations')</span></button><ul class="dropdown-menu dropdown-menu-left"><li><a href="{{ route('construction.subcontractors.show',$contract->id) }}"><i class="fa fa-eye"></i> @lang('construction::lang.open_agreement')</a></li><li><a href="{{ route('construction.subcontractors.preview',$contract->id) }}" target="_blank"><i class="fa fa-print"></i> @lang('construction::lang.print_preview')</a></li>@if($contract->isEditable())@can('construction.subcontract.manage')<li class="divider"></li><li><form method="POST" action="{{ route('construction.subcontractors.destroy',$contract->id) }}" onsubmit="return confirm('@lang('construction::lang.delete_subcontract_confirmation')')">@csrf @method('DELETE')<button class="ct-dropdown-danger"><i class="fa fa-trash"></i> @lang('construction::lang.delete_draft')</button></form></li>@endcan @endif</ul></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="ct-empty-state"><span><i class="fa fa-people-carry"></i></span><strong>@lang('construction::lang.no_subcontracts')</strong><p>@lang('construction::lang.no_subcontracts_hint')</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ct-panel-pagination">{{ $subcontracts->links() }}</div>
    </div>
</section>

@can('construction.subcontract.manage')
<div class="modal fade ct-subcontract-create-modal" id="create-subcontract" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('construction.subcontractors.store') }}">
                @csrf
                <div class="ct-subcontract-modal-head">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <span class="ct-subcontract-modal-icon"><i class="fa fa-file-signature"></i></span>
                    <div><span class="ct-subcontract-modal-kicker">@lang('construction::lang.subcontract_phase_one')</span><h3>@lang('construction::lang.new_subcontract')</h3><p>@lang('construction::lang.subcontract_create_hint')</p></div>
                </div>

                <div class="modal-body ct-subcontract-modal-body">
                    @if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                    <section class="ct-subcontract-form-section ct-subcontract-form-section--parties">
                        <header><span><i class="fa fa-link"></i></span><div><h4>@lang('construction::lang.project_and_subcontractor')</h4><p>@lang('construction::lang.project_and_subcontractor_hint')</p></div></header>
                        <div class="ct-subcontract-form-grid">
                            <div class="form-group">
                                <label>@lang('construction::lang.project') <em>*</em></label>
                                <div class="ct-modern-select"><i class="fa fa-project-diagram"></i><select name="project_id" class="form-control select2" required><option value="">@lang('construction::lang.select_project')</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id')==$project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></div>
                            </div>
                            <div class="form-group">
                                <label>@lang('construction::lang.subcontractor') <em>*</em></label>
                                <div class="ct-modern-select"><i class="fa fa-hard-hat"></i><select id="subcontractor_id" name="subcontractor_id" class="form-control select2" required><option value="">@lang('construction::lang.select_subcontractor')</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('subcontractor_id')==$supplier->id)>{{ $supplier->supplier_business_name ?: $supplier->name }}</option>@endforeach</select></div>
                                @can('supplier.create')
                                    <button type="button" class="ct-add-supplier-link btn-modal" data-href="{{ action([\App\Http\Controllers\ContactController::class, 'create'], ['type' => 'supplier']) }}" data-container=".ct-subcontract-contact-modal"><i class="fa fa-plus-circle"></i> @lang('construction::lang.create_supplier_first')</button>
                                @endcan
                            </div>
                        </div>
                    </section>

                    <section class="ct-subcontract-form-section ct-subcontract-form-section--identity">
                        <header><span><i class="fa fa-file-alt"></i></span><div><h4>@lang('construction::lang.agreement_data')</h4><p>@lang('construction::lang.agreement_data_hint')</p></div></header>
                        <div class="ct-subcontract-form-grid">
                            <div class="form-group ct-subcontract-number-field"><label>@lang('construction::lang.agreement_number')</label><div class="ct-readonly-field"><i class="fa fa-lock"></i><input class="form-control" value="{{ $nextNumber }}" readonly></div></div>
                            <div class="form-group ct-subcontract-title-field"><label>@lang('construction::lang.agreement_title') <em>*</em></label><input name="title" value="{{ old('title') }}" class="form-control" required maxlength="190" placeholder="@lang('construction::lang.agreement_title_placeholder')"></div>
                        </div>
                    </section>

                    <section class="ct-subcontract-form-section ct-subcontract-form-section--terms">
                        <header><span><i class="fa fa-calendar-alt"></i></span><div><h4>@lang('construction::lang.period_and_retention')</h4><p>@lang('construction::lang.period_and_retention_hint')</p></div></header>
                        <div class="ct-subcontract-form-grid ct-subcontract-form-grid--three">
                            <div class="form-group"><label>@lang('construction::lang.start_date')</label><div class="ct-field-icon"><i class="fa fa-calendar"></i><input name="start_date" value="{{ old('start_date') }}" class="form-control ct-date-picker" readonly placeholder="@lang('construction::lang.select_date')"></div></div>
                            <div class="form-group"><label>@lang('construction::lang.end_date')</label><div class="ct-field-icon"><i class="fa fa-calendar-check"></i><input name="end_date" value="{{ old('end_date') }}" class="form-control ct-date-picker" readonly placeholder="@lang('construction::lang.select_date')"></div></div>
                            <div class="form-group"><label>@lang('construction::lang.retention_percent')</label><div class="ct-field-suffix"><input name="retention_percent" value="{{ old('retention_percent',5) }}" class="form-control input_number" required><span>%</span></div><small>@lang('construction::lang.retention_simple_hint')</small></div>
                        </div>
                    </section>

                    <div class="form-group ct-subcontract-notes"><label><i class="fa fa-sticky-note"></i> @lang('construction::lang.notes') <small>@lang('construction::lang.optional')</small></label><textarea name="notes" class="form-control" rows="3" placeholder="@lang('construction::lang.subcontract_notes_placeholder')">{{ old('notes') }}</textarea></div>
                    <div class="ct-after-save-hint"><i class="fa fa-arrow-left"></i><span><strong>@lang('construction::lang.after_saving_agreement')</strong><small>@lang('construction::lang.after_saving_agreement_hint')</small></span></div>
                </div>

                <div class="modal-footer ct-subcontract-modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                    <button class="btn ct-primary-action"><i class="fa fa-save"></i> @lang('construction::lang.create_agreement')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@can('supplier.create')
    <div class="modal fade contact_modal ct-subcontract-contact-modal" tabindex="-1" role="dialog" aria-hidden="true"></div>
@endcan
@endcan
@endsection

@push('ct_quick_js')
<script>
$(function () {
    var supplierPopupPending = false;

    $(document).on('click', '.ct-add-supplier-link', function () {
        supplierPopupPending = true;
    });

    $(document).on('show.bs.modal', '.ct-subcontract-contact-modal', function () {
        $(this).css('z-index', 1070);
        setTimeout(function () {
            $('.modal-backdrop').last().css('z-index', 1060);
        }, 0);
    });

    $(document).on('hidden.bs.modal', '.ct-subcontract-contact-modal', function () {
        if ($('#create-subcontract').hasClass('in')) {
            $('body').addClass('modal-open');
        }
    });

    window.addEventListener('contactAdded', function (event) {
        if (!supplierPopupPending || !event.detail) {
            return;
        }

        var supplier = event.detail;
        var supplierName = supplier.supplier_business_name || supplier.name;
        var supplierSelect = $('#subcontractor_id');

        if (supplier.id && supplierName) {
            if (!supplierSelect.find('option[value="' + supplier.id + '"]').length) {
                supplierSelect.append(new Option(supplierName, supplier.id, true, true));
            }
            supplierSelect.val(supplier.id).trigger('change');
        }

        supplierPopupPending = false;
    });
});
</script>
@endpush

@if($errors->any())
    @push('ct_quick_js')<script>$(function(){$('#create-subcontract').modal('show')})</script>@endpush
@endif
