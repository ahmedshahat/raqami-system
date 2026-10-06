@extends('construction::layouts.module')
@section('title', __('construction::lang.quotes'))
@section('module_css')@include('construction::quotes.styles')@endsection

@section('module_content')
<section class="ct-quote-page">
    <header class="ct-quote-heading"><div><span class="ct-quote-eyebrow">@lang('construction::lang.quote_first_step')</span><h2>@lang('construction::lang.quotes')</h2><p>@lang('construction::lang.quotes_intro')</p></div><div class="ct-quote-heading__actions">@can('construction.project.create')<button class="ct-quote-btn ct-quote-btn--primary" type="button" data-toggle="modal" data-target="#ct-quote-create"><i class="fa fa-plus"></i> @lang('construction::lang.new_quote')</button>@endcan</div></header>
    <div class="ct-quote-card"><div class="ct-quote-list-head"><h3>@lang('construction::lang.all_quotes')</h3><form class="ct-quote-search" method="GET"><input class="form-control" name="q" value="{{ $search }}" placeholder="@lang('construction::lang.search_quotes')"><button class="ct-quote-btn" type="submit"><i class="fa fa-search"></i></button></form></div>
        <div class="ct-quote-table-wrap" id="ct-quick-list"><table class="ct-quote-table"><thead><tr><th>@lang('construction::lang.quote_number')</th><th>@lang('construction::lang.quote_title')</th><th>@lang('construction::lang.customer')</th><th>@lang('construction::lang.quote_date')</th><th>@lang('construction::lang.quote_status')</th><th>@lang('construction::lang.items_count_label')</th><th class="ct-quote-num">@lang('construction::lang.total')</th><th>@lang('construction::lang.project')</th><th class="ct-quote-actions-heading">@lang('construction::lang.actions')</th></tr></thead><tbody>
        @forelse($quotes as $quote)
        <tr data-quote-id="{{ $quote->id }}">
            <td><a href="{{ route('construction.quotes.show', $quote->id) }}">{{ $quote->number }}</a></td>
            <td>{{ $quote->title }}</td>
            <td>{{ $quote->customer?->supplier_business_name ?: $quote->customer?->name }}</td>
            <td>{{ @format_date($quote->quote_date) }}</td>
            <td><span class="ct-quote-badge ct-quote-badge--{{ $quote->project_id ? 'converted' : $quote->status }}">@lang('construction::lang.quote_status_'.($quote->project_id ? 'converted' : $quote->status))</span></td>
            <td>{{ $quote->items_count }}</td>
            <td class="ct-quote-num">@include('construction::partials.money', ['value' => $quote->total])</td>
            <td>@if($quote->project)<a href="{{ route('construction.projects.show', $quote->project->id) }}">{{ $quote->project->code }}</a>@else—@endif</td>
            <td class="ct-quote-actions-cell">
                <div class="ct-quote-row-actions">
                    <a class="ct-quote-view" href="{{ route('construction.quotes.show', $quote->id) }}">@lang('construction::lang.view')</a>
                    <button type="button" class="ct-quote-menu-toggle" aria-label="@lang('construction::lang.quote_more_actions')" aria-haspopup="true" aria-expanded="false" aria-controls="ct-quote-menu-{{ $quote->id }}">&#8942;</button>
                    <div class="ct-quote-menu" id="ct-quote-menu-{{ $quote->id }}" role="menu">
                        @if(in_array($quote->status, ['draft', 'sent'], true) && !$quote->project_id)
                            @can('construction.project.create')<button type="button" role="menuitem" data-toggle="modal" data-target="#ct-quote-edit" data-quote-id="{{ $quote->id }}" data-quote-date="{{ $quote->quote_date->toDateString() }}" data-customer-id="{{ $quote->customer_id }}" data-quote-title="{{ $quote->title }}" data-validity-days="{{ $quote->validity_days }}" data-notes="{{ $quote->notes }}"><i class="fa fa-pen"></i> @lang('construction::lang.edit')</button>@endcan
                        @endif
                        <a role="menuitem" href="{{ route('construction.quotes.preview', $quote->id) }}" target="_blank" rel="noopener"><i class="fa fa-eye"></i> @lang('construction::lang.preview')</a>
                        <a role="menuitem" href="{{ route('construction.quotes.print', $quote->id) }}" target="_blank" rel="noopener"><i class="fa fa-print"></i> @lang('construction::lang.print')</a>
                        <a role="menuitem" href="{{ route('construction.quotes.pdf', $quote->id) }}"><i class="fa fa-file-pdf"></i> PDF</a>
                        @if($quote->project)
                            <a role="menuitem" href="{{ route('construction.projects.show', $quote->project->id) }}"><i class="fa fa-folder-open"></i> @lang('construction::lang.open_project')</a>
                        @elseif($quote->status === 'accepted')
                            @can('construction.project.create')<button type="button" role="menuitem" data-toggle="modal" data-target="#ct-quote-convert" data-quote-id="{{ $quote->id }}" data-quote-title="{{ $quote->title }}"><i class="fa fa-building"></i> @lang('construction::lang.convert_to_project')</button>@endcan
                        @endif
                        @if($quote->status === 'draft' && !$quote->project_id)
                            @can('construction.project.delete')<button type="button" role="menuitem" class="ct-quote-menu-danger" data-toggle="modal" data-target="#ct-quote-delete" data-quote-id="{{ $quote->id }}" data-confirmation="{{ __('construction::lang.quote_delete_confirmation', ['number' => $quote->number]) }}"><i class="fa fa-trash"></i> @lang('construction::lang.delete')</button>@endcan
                        @endif
                    </div>
                </div>
            </td>
        </tr>
        @empty<tr><td colspan="9"><div class="ct-quote-empty"><i class="fa fa-file-invoice"></i>@lang('construction::lang.no_quotes')</div></td></tr>@endforelse
        </tbody></table></div>{{ $quotes->links() }}</div>
</section>
@can('construction.project.create')<div class="modal fade ct-quote-modal" id="ct-quote-create" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content"><form method="POST" action="{{ route('construction.quotes.store') }}" data-ct-quick-create data-ct-open-mode="redirect">@csrf<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">@lang('construction::lang.new_quote')</h4></div><div class="modal-body"><div class="alert alert-danger ct-quick-errors" role="alert"></div>
    <div class="form-group"><label>@lang('construction::lang.quote_date') *</label><input type="date" name="quote_date" class="form-control" value="{{ old('quote_date', now()->toDateString()) }}" required></div>
    <div class="form-group"><label>@lang('construction::lang.customer') *</label><select name="customer_id" class="form-control" required><option value="">@lang('construction::lang.choose')</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->supplier_business_name ?: $customer->name }}</option>@endforeach</select></div>
    <div class="form-group"><label>@lang('construction::lang.quote_title') *</label><input name="title" class="form-control" maxlength="190" value="{{ old('title') }}" required></div>
    <div class="form-group"><label>@lang('construction::lang.quote_validity_days') *</label><input type="number" name="validity_days" class="form-control" min="1" max="365" value="{{ old('validity_days', 30) }}" required></div>
    <div class="form-group"><label>@lang('construction::lang.quote_notes')</label><textarea name="notes" class="form-control" rows="3" maxlength="5000">{{ old('notes') }}</textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-primary" type="submit">@lang('construction::lang.create_quote')</button></div></form></div></div></div>@endcan
@can('construction.project.create')
<div class="modal fade ct-quote-modal" id="ct-quote-edit" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content">
    <form method="POST" data-ct-quick-create data-update-url="{{ route('construction.quotes.update', ['quote' => '__QUOTE__']) }}">@csrf @method('PUT')
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">@lang('construction::lang.edit_quote')</h4></div>
        <div class="modal-body"><div class="alert alert-danger ct-quick-errors" role="alert"></div>
            <div class="form-group"><label>@lang('construction::lang.quote_date') *</label><input type="date" name="quote_date" class="form-control" required></div>
            <div class="form-group"><label>@lang('construction::lang.customer') *</label><select name="customer_id" class="form-control" required>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->supplier_business_name ?: $customer->name }}</option>@endforeach</select></div>
            <div class="form-group"><label>@lang('construction::lang.quote_title') *</label><input name="title" class="form-control" maxlength="190" required></div>
            <div class="form-group"><label>@lang('construction::lang.quote_validity_days') *</label><input type="number" name="validity_days" class="form-control" min="1" max="365" required></div>
            <div class="form-group"><label>@lang('construction::lang.quote_notes')</label><textarea name="notes" class="form-control" rows="3" maxlength="5000"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-primary" type="submit">@lang('construction::lang.save')</button></div>
    </form>
</div></div></div>
<div class="modal fade ct-quote-modal" id="ct-quote-convert" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form method="POST" data-ct-quick-create data-ct-open-mode="redirect" data-convert-url="{{ route('construction.quotes.convert', ['quote' => '__QUOTE__']) }}">@csrf
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">@lang('construction::lang.convert_to_project')</h4></div>
        <div class="modal-body"><div class="alert alert-danger ct-quick-errors" role="alert"></div>@include('construction::quotes.partials.conversion_fields')</div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-success" type="submit">@lang('construction::lang.convert_to_project')</button></div>
    </form>
</div></div></div>
@endcan
@can('construction.project.delete')
<div class="modal fade ct-quote-modal" id="ct-quote-delete" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content">
    <form method="POST" data-ct-quick-create data-delete-url="{{ route('construction.quotes.destroy', ['quote' => '__QUOTE__']) }}">@csrf @method('DELETE')
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">@lang('construction::lang.delete') @lang('construction::lang.quote_number')</h4></div>
        <div class="modal-body"><div class="alert alert-danger ct-quick-errors" role="alert"></div><p class="ct-quote-delete-warning" id="ct-quote-delete-confirmation"></p></div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-danger" type="submit">@lang('construction::lang.delete')</button></div>
    </form>
</div></div></div>
@endcan
@endsection
@include('construction::partials.quick_create')
@section('module_javascript')
<script>
$(function () {
    if (new URLSearchParams(location.search).has('open_create')) $('#ct-quote-create').modal('show');

    var activeMenu = null;
    function closeMenu() {
        if (!activeMenu) return;
        activeMenu.style.display = 'none';
        activeMenu.previousElementSibling.setAttribute('aria-expanded', 'false');
        activeMenu = null;
    }
    $(document).on('click', '.ct-quote-menu-toggle', function (event) {
        event.stopPropagation();
        var menu = this.nextElementSibling;
        if (activeMenu === menu) { closeMenu(); return; }
        closeMenu();
        menu.style.display = 'block';
        menu.style.visibility = 'hidden';
        var button = this.getBoundingClientRect(), width = menu.offsetWidth, height = menu.offsetHeight;
        menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, button.right - width)) + 'px';
        menu.style.top = (button.bottom + height + 8 <= window.innerHeight || button.top < height + 8 ? button.bottom + 5 : button.top - height - 5) + 'px';
        menu.style.visibility = 'visible';
        this.setAttribute('aria-expanded', 'true');
        activeMenu = menu;
    });
    $(document).on('click', function (event) { if (!$(event.target).closest('.ct-quote-menu').length) closeMenu(); });
    $(document).on('keydown', function (event) { if (event.key === 'Escape') closeMenu(); });
    $(window).on('resize scroll', closeMenu);
    $('.ct-quote-table-wrap').on('scroll', closeMenu);

    $('#ct-quote-edit').on('show.bs.modal', function (event) {
        var trigger = event.relatedTarget, form = this.querySelector('form');
        if (!trigger) return;
        form.action = form.dataset.updateUrl.replace('__QUOTE__', trigger.dataset.quoteId);
        form.elements.quote_date.value = trigger.dataset.quoteDate;
        form.elements.customer_id.value = trigger.dataset.customerId;
        form.elements.title.value = trigger.dataset.quoteTitle;
        form.elements.validity_days.value = trigger.dataset.validityDays;
        form.elements.notes.value = trigger.dataset.notes || '';
    });
    $('#ct-quote-convert').on('show.bs.modal', function (event) {
        var trigger = event.relatedTarget, form = this.querySelector('form');
        if (!trigger) return;
        form.action = form.dataset.convertUrl.replace('__QUOTE__', trigger.dataset.quoteId);
        form.elements.project_name.value = trigger.dataset.quoteTitle;
    });
    $('#ct-quote-delete').on('show.bs.modal', function (event) {
        var trigger = event.relatedTarget, form = this.querySelector('form');
        if (!trigger) return;
        form.action = form.dataset.deleteUrl.replace('__QUOTE__', trigger.dataset.quoteId);
        this.querySelector('#ct-quote-delete-confirmation').textContent = trigger.dataset.confirmation;
    });
});
</script>
@endsection
