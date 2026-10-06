@extends('construction::layouts.module')
@section('title', $version->name)

@section('module_content')
<section class="content ct-boq-page">
    <div class="ct-boq-workspace">
        <header class="ct-boq-head">
            <div class="ct-boq-head__copy">
                <span class="ct-boq-kicker"><i class="fa fa-clipboard-list"></i> @lang('construction::lang.boq_workspace')</span>
                <h2>{{ $version->name }}</h2>
                <p>{{ $project->name }} · @lang('construction::lang.basic_boq_hint')</p>
            </div>
            <div class="ct-boq-head__total">
                <small>@lang('construction::lang.boq_total_value')</small>
                <strong>@include('construction::partials.money', ['value' => $version->sales_total])</strong>
                <span class="ct-boq-state ct-boq-state--{{ $version->status }}">@lang('construction::lang.'.$version->status)</span>
            </div>
        </header>

        <div class="ct-boq-actions">
            <div class="ct-boq-actions__main">
                @if($canEditItems)
                    @can('construction.boq.manage')<a href="#add-boq-item" class="ct-boq-button ct-boq-button--primary"><i class="fa fa-plus"></i> @lang('construction::lang.add_item')</a>@endcan
                    @if($version->status === 'draft')@can('construction.boq.approve')
                        <form method="POST" action="{{ route('construction.projects.boq.approve', [$project->id, $version->id]) }}" onsubmit="return confirm('{{ __('construction::lang.approve_boq_confirmation') }}')">@csrf<button class="ct-boq-button ct-boq-button--success" type="submit"><i class="fa fa-check"></i> @lang('construction::lang.approve_boq')</button></form>
                    @endcan @endif
                    @if($version->status === 'approved' && !$project->primaryContract)@can('construction.contract.manage')<a href="{{ route('construction.projects.contracts.create', ['project' => $project->id, 'boq_version_id' => $version->id]) }}" class="ct-boq-button ct-boq-button--success"><i class="fa fa-file-signature"></i> @lang('construction::lang.create_contract_from_boq')</a>@endcan @endif
                @elseif($version->status === 'approved' && !$project->primaryContract)
                    @can('construction.contract.manage')<a href="{{ route('construction.projects.contracts.create', ['project' => $project->id, 'boq_version_id' => $version->id]) }}" class="ct-boq-button ct-boq-button--success"><i class="fa fa-file-signature"></i> @lang('construction::lang.create_contract_from_boq')</a>@endcan
                @elseif($project->primaryContract)
                    @can('construction.contract.view')<a href="{{ route('construction.projects.contracts.show', [$project->id,$project->primaryContract->id]) }}" class="ct-boq-button ct-boq-button--success"><i class="fa fa-file-signature"></i> @lang('construction::lang.open_contract')</a>@endcan
                @endif
            </div>
            <div class="ct-boq-actions__secondary">
                <a href="{{ route('construction.projects.boq.export', [$project->id,$version->id]) }}"><i class="fa fa-file-excel"></i> @lang('construction::lang.export_excel')</a>
                @if($canEditItems)@can('construction.boq.manage')<a href="#boq-import"><i class="fa fa-upload"></i> @lang('construction::lang.import_boq_items')</a>@endcan @endif
            </div>
        </div>

        @if($version->status === 'approved' && !$canEditItems)
            <div class="ct-boq-notice ct-boq-notice--success"><i class="fa fa-check-circle"></i><div><strong>@lang('construction::lang.boq_approved')</strong><span>@lang('construction::lang.approved_next_contract')</span></div></div>
        @elseif($version->status === 'superseded')
            <div class="ct-boq-notice"><i class="fa fa-history"></i><div><strong>@lang('construction::lang.superseded')</strong><span>@lang('construction::lang.old_boq_revision_hint')</span></div></div>
        @endif

        <div class="ct-boq-table-wrap">
            <table class="ct-boq-table">
                <thead><tr><th>@lang('construction::lang.item_name')</th><th>@lang('construction::lang.unit')</th><th>@lang('construction::lang.quantity')</th><th>@lang('construction::lang.unit_price')</th><th>@lang('construction::lang.total')</th>@if($canEditItems)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse($version->items as $item)
                        @if($item->row_type === 'section')
                            <tr class="ct-boq-section-row"><td colspan="{{ $canEditItems ? 6 : 5 }}"><i class="fa fa-folder-open"></i> {{ $item->description }}</td></tr>
                        @else
                            <tr>
                                <td data-label="@lang('construction::lang.item_name')"><b>{{ $item->description }}</b><small>{{ $item->code }}</small></td>
                                <td data-label="@lang('construction::lang.unit')">{{ $item->unit ?: '—' }}</td>
                                <td data-label="@lang('construction::lang.quantity')">{{ @format_quantity((float) $item->contract_quantity) }}</td>
                                <td data-label="@lang('construction::lang.unit_price')">@include('construction::partials.money', ['value' => $item->sales_unit_price])</td>
                                <td data-label="@lang('construction::lang.total')"><strong>@include('construction::partials.money', ['value' => $item->sales_total])</strong></td>
                                @if($canEditItems)<td class="ct-boq-table__action">@can('construction.boq.manage')<button type="button" data-toggle="modal" data-target="#ct-edit-item-{{ $item->id }}"><i class="fa fa-edit"></i></button> <form method="POST" action="{{ route('construction.projects.boq.items.destroy', [$project->id, $version->id, $item->id]) }}" onsubmit="return confirm('{{ __('construction::lang.delete_item_confirmation') }}')">@csrf @method('DELETE')<button type="submit"><i class="fa fa-trash"></i></button></form>@endcan</td>@endif
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="{{ $canEditItems ? 6 : 5 }}"><div class="ct-boq-empty"><i class="fa fa-list-ul"></i><strong>@lang('construction::lang.simple_boq_empty')</strong><span>@lang('construction::lang.simple_boq_empty_hint')</span></div></td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr><td colspan="4">@lang('construction::lang.boq_total_value')</td><td><strong>@include('construction::partials.money', ['value' => $version->sales_total])</strong></td>@if($canEditItems)<td></td>@endif</tr></tfoot>
            </table>
        </div>
    </div>

    @if($canEditItems)@can('construction.boq.manage')
        <div class="ct-boq-panel" id="add-boq-item">
            <div class="ct-boq-panel__head">
                <div><span>@lang('construction::lang.boq_step_items')</span><h3>@lang('construction::lang.add_multiple_items')</h3><p>@lang('construction::lang.add_multiple_items_hint')</p></div>
                <i class="fa fa-layer-group"></i>
            </div>
            <form method="POST" action="{{ route('construction.projects.boq.items.store', [$project->id, $version->id]) }}" id="ct-boq-batch-form">@csrf
                @php($formRows = old('items', [['description' => '', 'unit_id' => '', 'contract_quantity' => 0, 'sales_unit_price' => 0]]))
                <div class="ct-boq-entry-wrap">
                    <table class="ct-boq-entry-table">
                        <thead><tr><th>#</th><th>@lang('construction::lang.item_name')</th><th>@lang('construction::lang.unit')</th><th>@lang('construction::lang.quantity')</th><th>@lang('construction::lang.unit_price')</th><th>@lang('construction::lang.row_total')</th><th></th></tr></thead>
                        <tbody id="ct-boq-entry-rows">
                            @foreach($formRows as $rowIndex => $row)
                                <tr class="ct-boq-entry-row">
                                    <td class="ct-boq-entry-number">{{ $loop->iteration }}</td>
                                    <td><input type="hidden" name="items[{{ $rowIndex }}][row_type]" value="item"><input type="hidden" name="items[{{ $rowIndex }}][item_kind]" value="standard"><input name="items[{{ $rowIndex }}][description]" class="form-control" maxlength="500" value="{{ $row['description'] ?? '' }}" placeholder="@lang('construction::lang.item_name_example')" required></td>
                                    <td><select name="items[{{ $rowIndex }}][unit_id]" class="form-control" required><option value="">@lang('construction::lang.choose')</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)($row['unit_id'] ?? '') === (string)$unit->id)>{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach</select></td>
                                    <td><input type="text" name="items[{{ $rowIndex }}][contract_quantity]" class="form-control input_number input_quantity ct-boq-quantity" value="{{ @format_quantity($row['contract_quantity'] ?? 0) }}" required></td>
                                    <td><input type="text" name="items[{{ $rowIndex }}][sales_unit_price]" class="form-control input_number ct-boq-price" value="{{ @num_format($row['sales_unit_price'] ?? 0) }}" required></td>
                                    <td><output class="ct-boq-row-total">0.00</output></td>
                                    <td><button class="ct-boq-remove-row" type="button" title="@lang('construction::lang.remove_row')"><i class="fa fa-times"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ct-boq-panel__footer ct-boq-panel__footer--batch">
                    <button class="ct-boq-button ct-boq-button--soft" type="button" id="ct-add-boq-row"><i class="fa fa-plus"></i> @lang('construction::lang.add_row')</button>
                    <button class="ct-boq-button ct-boq-button--primary" type="submit"><i class="fa fa-save"></i> @lang('construction::lang.save_all_items')</button>
                </div>
            </form>
        </div>
    @endcan @endif

    <details class="ct-boq-tools" id="boq-import">
        <summary><span><i class="fa fa-file-excel"></i><b>@lang('construction::lang.import_export_tools')</b><small>@lang('construction::lang.import_export_tools_hint')</small></span><i class="fa fa-chevron-down"></i></summary>
        <div class="ct-boq-tools__body">
            <a href="{{ route('construction.boq.import-template') }}" class="ct-boq-button ct-boq-button--soft"><i class="fa fa-download"></i> @lang('construction::lang.download_import_template')</a>
            <a href="{{ route('construction.projects.boq.export', [$project->id,$version->id]) }}" class="ct-boq-button ct-boq-button--soft"><i class="fa fa-file-export"></i> @lang('construction::lang.export_excel')</a>
            @if($canEditItems)@can('construction.boq.manage')<form class="ct-boq-import-form" method="POST" action="{{ route('construction.projects.boq.import.preview', [$project->id,$version->id]) }}" enctype="multipart/form-data">@csrf<input type="file" name="boq_file" class="form-control" accept=".xlsx,.xls,.csv" required><button class="ct-boq-button ct-boq-button--primary" type="submit"><i class="fa fa-search"></i> @lang('construction::lang.preview_before_import')</button></form>@endcan @else <p class="text-muted">@lang('construction::lang.import_requires_draft_version')</p> @endif
        </div>
    </details>

    <details class="ct-boq-tools">
        <summary><span><i class="fa fa-history"></i><b>@lang('construction::lang.revision_history')</b><small>@lang('construction::lang.revision_history_hint')</small></span><i class="fa fa-chevron-down"></i></summary>
        <div class="ct-boq-tools__body ct-boq-history">
            @can('construction.boq.manage')<button type="button" class="ct-boq-button ct-boq-button--soft" data-toggle="modal" data-target="#ct-boq-version-create"><i class="fa fa-plus"></i> @lang('construction::lang.new_boq_version')</button>@endcan
            @foreach($versions as $historyVersion)
                <a class="{{ $historyVersion->id === $version->id ? 'is-current' : '' }}" href="{{ route('construction.projects.boq.show', [$project->id, $historyVersion->id]) }}"><span><b>V{{ $historyVersion->version_number }} · {{ $historyVersion->name }}</b><small>{{ $historyVersion->items_count }} @lang('construction::lang.items_count_label')</small></span><em class="ct-boq-state ct-boq-state--{{ $historyVersion->status }}">@lang('construction::lang.'.$historyVersion->status)</em></a>
            @endforeach
            @if($version->status !== 'draft')@can('construction.boq.manage')<form method="POST" action="{{ route('construction.projects.boq.revise', [$project->id, $version->id]) }}">@csrf<button class="ct-boq-button ct-boq-button--soft" type="submit"><i class="fa fa-copy"></i> @lang('construction::lang.create_revision')</button></form>@endcan @endif
        </div>
    </details>
</section>
@can('construction.boq.manage')<div class="modal fade ct-quick-modal" id="ct-boq-version-create" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content"><form method="POST" action="{{ route('construction.projects.boq.store', $project->id) }}" data-ct-quick-create data-ct-open-mode="redirect">@csrf<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')"><span aria-hidden="true">&times;</span></button><h4 class="modal-title">@lang('construction::lang.new_boq_version')</h4></div><div class="modal-body"><div class="alert alert-danger ct-quick-errors" role="alert"></div><div class="form-group"><label>@lang('construction::lang.boq_version_name') *</label><input name="name" value="{{ old('name', __('construction::lang.boq_default_name')) }}" class="form-control" maxlength="190" required></div><div class="form-group"><label>@lang('construction::lang.boq_notes')</label><textarea name="notes" class="form-control" rows="4" maxlength="5000">{{ old('notes') }}</textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-success" type="submit"><i class="fa fa-plus"></i> @lang('construction::lang.new_boq_version')</button></div></form></div></div></div>@endcan
@if($canEditItems)@can('construction.boq.manage')
@foreach($version->items->where('row_type', 'item') as $item)
<div class="modal fade ct-quick-modal" id="ct-edit-item-{{ $item->id }}" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content">
<form method="POST" action="{{ route('construction.projects.boq.items.update', [$project->id, $version->id, $item->id]) }}">@csrf @method('PUT')
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">@lang('construction::lang.edit_project_item')</h4></div>
<div class="modal-body"><div class="row"><div class="col-md-4 form-group"><label>@lang('construction::lang.boq_code') *</label><input name="code" class="form-control" value="{{ $item->code }}" required></div><div class="col-md-8 form-group"><label>@lang('construction::lang.boq_description') *</label><input name="description" class="form-control" value="{{ $item->description }}" required></div></div>
<div class="row"><div class="col-md-4 form-group"><label>@lang('construction::lang.unit') *</label><select name="unit_id" class="form-control" required>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected($item->unit_id == $unit->id)>{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach</select></div><div class="col-md-4 form-group"><label>@lang('construction::lang.quantity') *</label><input name="contract_quantity" class="form-control input_number input_quantity" value="{{ @format_quantity((float)$item->contract_quantity) }}" required></div><div class="col-md-4 form-group"><label>@lang('construction::lang.unit_price') *</label><input name="sales_unit_price" class="form-control input_number" value="{{ @num_format((float)$item->sales_unit_price) }}" required></div></div>
<div class="form-group"><label>@lang('construction::lang.notes')</label><textarea name="notes" class="form-control" rows="3">{{ $item->notes }}</textarea></div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('messages.save')</button></div></form>
</div></div></div>
@endforeach
@endcan @endif
@endsection
@include('construction::partials.quick_create')

@section('module_javascript')
@if($canEditItems)@can('construction.boq.manage')
<template id="ct-boq-row-template">
    <tr class="ct-boq-entry-row">
        <td class="ct-boq-entry-number"></td>
        <td><input type="hidden" data-name="row_type" value="item"><input type="hidden" data-name="item_kind" value="standard"><input data-name="description" class="form-control" maxlength="500" placeholder="@lang('construction::lang.item_name_example')" required></td>
        <td><select data-name="unit_id" class="form-control" required><option value="">@lang('construction::lang.choose')</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach</select></td>
        <td><input type="text" data-name="contract_quantity" class="form-control input_number input_quantity ct-boq-quantity" value="{{ @format_quantity(0) }}" required></td>
        <td><input type="text" data-name="sales_unit_price" class="form-control input_number ct-boq-price" value="{{ @num_format(0) }}" required></td>
        <td><output class="ct-boq-row-total">0.00</output></td>
        <td><button class="ct-boq-remove-row" type="button" title="@lang('construction::lang.remove_row')"><i class="fa fa-times"></i></button></td>
    </tr>
</template>
<script>
(function () {
    var rows = document.getElementById('ct-boq-entry-rows');
    var addButton = document.getElementById('ct-add-boq-row');
    var template = document.getElementById('ct-boq-row-template');
    if (!rows || !addButton || !template) return;

    function refreshRows() {
        var allRows = rows.querySelectorAll('.ct-boq-entry-row');
        allRows.forEach(function (row, index) {
            row.querySelector('.ct-boq-entry-number').textContent = index + 1;
            row.querySelectorAll('[data-name]').forEach(function (input) {
                input.name = 'items[' + index + '][' + input.dataset.name + ']';
            });
            row.querySelector('.ct-boq-remove-row').disabled = allRows.length === 1;
            updateTotal(row);
        });
    }
    function updateTotal(row) {
        var quantity = __number_uf(row.querySelector('.ct-boq-quantity').value) || 0;
        var price = __number_uf(row.querySelector('.ct-boq-price').value) || 0;
        row.querySelector('.ct-boq-row-total').textContent = __currency_trans_from_en(quantity * price, false);
    }
    addButton.addEventListener('click', function () {
        rows.appendChild(template.content.cloneNode(true));
        refreshRows();
        rows.lastElementChild.querySelector('[data-name="description"]').focus();
    });
    rows.addEventListener('click', function (event) {
        var button = event.target.closest('.ct-boq-remove-row');
        if (button && rows.children.length > 1) { button.closest('tr').remove(); refreshRows(); }
    });
    rows.addEventListener('input', function (event) {
        if (event.target.matches('.ct-boq-quantity,.ct-boq-price')) updateTotal(event.target.closest('tr'));
    });
    rows.querySelectorAll('input,select').forEach(function (input) { if (!input.dataset.name) input.dataset.name = input.name.match(/\[([^\]]+)\]$/)[1]; });
    refreshRows();
})();
</script>
@endcan @endif
@endsection
