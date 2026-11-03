@php
    $linkedExpense = $expense ?? null;
    $selectedConstructionProject = old('construction_project_id', $linkedExpense?->construction_project_id ?? request('construction_project_id'));
    $selectedConstructionItem = old('construction_boq_item_id', $linkedExpense?->construction_boq_item_id);
@endphp
<div class="col-sm-6">
    <div class="form-group">
        {!! Form::label('construction_project_id', __('construction::lang.expense_link_project').':') !!}
        {!! Form::select('construction_project_id', $construction_projects, $selectedConstructionProject, [
            'class' => 'form-control select2 construction-project-select',
            'placeholder' => __('construction::lang.expense_link_no_project'),
            'data-items-url' => url('/expenses/construction-projects'),
            'style' => 'width:100%',
        ]) !!}
        <p class="help-block">@lang('construction::lang.expense_link_project_hint')</p>
    </div>
</div>
@if($construction_project_items_enabled)
<div class="col-sm-6 construction-project-item-group {{ $selectedConstructionProject ? '' : 'hide' }}">
    <div class="form-group">
        {!! Form::label('construction_boq_item_id', __('construction::lang.expense_link_project_item').':') !!}
        {!! Form::select('construction_boq_item_id', $construction_project_items, $selectedConstructionItem, [
            'class' => 'form-control select2 construction-project-item-select',
            'placeholder' => __('construction::lang.expense_link_general_item'),
            'data-placeholder' => __('construction::lang.expense_link_general_item'),
            'style' => 'width:100%',
        ]) !!}
        <p class="help-block">@lang('construction::lang.expense_link_item_hint')</p>
    </div>
</div>
<script>
    // تحميل بنود المشروع المختار؛ موجود هنا مش في app.js علشان الربط يفضل جوه ملفات المقاولات. بيتسجل مرة واحدة حتى لو الجزء ده اتحمل أكتر من مرة (المودال)
    (function bindConstructionExpenseItems() {
        if (window.ctExpenseItemsBound) return;
        if (! window.jQuery) {
            document.addEventListener('DOMContentLoaded', bindConstructionExpenseItems);
            return;
        }
        window.ctExpenseItemsBound = true;
        jQuery(document).on('change', 'select.construction-project-select', function() {
            var $project = jQuery(this);
            var $form = $project.closest('form');
            var $group = $form.find('.construction-project-item-group');
            var $item = $form.find('select.construction-project-item-select');
            var projectId = $project.val();
            if (! $item.length) return;
            $item.empty().append(new Option($item.data('placeholder') || '', '', true, true)).trigger('change');
            if (! projectId) {
                $group.addClass('hide');
                return;
            }
            $group.removeClass('hide');
            $item.prop('disabled', true);
            jQuery.getJSON($project.data('items-url') + '/' + projectId + '/items')
                .done(function(items) {
                    jQuery.each(items, function(_, item) { $item.append(new Option(item.text, item.id, false, false)); });
                })
                .always(function() { $item.prop('disabled', false).trigger('change'); });
        });
    })();
</script>
@endif
