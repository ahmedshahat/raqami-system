@php
    $linkedExpense = $expense ?? null;
    $selectedConstructionProject = old('construction_project_id', $linkedExpense?->construction_project_id ?? request('construction_project_id'));
    $selectedConstructionItem = old('construction_boq_item_id', $linkedExpense?->construction_boq_item_id);
@endphp
<div class="col-sm-6">
    <div class="form-group">
        {!! Form::label('construction_project_id', __('expense.construction_project').':') !!}
        {!! Form::select('construction_project_id', $construction_projects, $selectedConstructionProject, [
            'class' => 'form-control select2 construction-project-select',
            'placeholder' => __('expense.no_construction_project'),
            'data-items-url' => url('/expenses/construction-projects'),
            'style' => 'width:100%',
        ]) !!}
        <p class="help-block">@lang('expense.construction_project_optional_hint')</p>
    </div>
</div>
@if($construction_project_items_enabled)
<div class="col-sm-6 construction-project-item-group {{ $selectedConstructionProject ? '' : 'hide' }}">
    <div class="form-group">
        {!! Form::label('construction_boq_item_id', __('expense.construction_project_item').':') !!}
        {!! Form::select('construction_boq_item_id', $construction_project_items, $selectedConstructionItem, [
            'class' => 'form-control select2 construction-project-item-select',
            'placeholder' => __('expense.general_project_expense'),
            'data-placeholder' => __('expense.general_project_expense'),
            'style' => 'width:100%',
        ]) !!}
        <p class="help-block">@lang('expense.construction_project_item_optional_hint')</p>
    </div>
</div>
@endif
