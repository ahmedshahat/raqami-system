@extends('construction::layouts.module')
@section('title', $measurement->number)

@section('module_content')
@php
    $canEditMeasurement = $measurement->status === 'draft' && auth()->user()->can('construction.measurement.manage');
    $hasMeasuredItems = $measurementSummary['measured_items_count'] > 0;
@endphp
<section class="content ct-measurement-page">
    <div class="ct-measurement-heading">
        <div>
            <span><a href="{{ route('construction.projects.show', $project->id) }}">{{ $project->name }}</a> / <a href="{{ route('construction.projects.measurements.index', $project->id) }}">@lang('construction::lang.measurements')</a> / {{ $measurement->number }}</span>
            <h2>@lang('construction::lang.measurement_number'): {{ $measurement->number }}</h2>
            <p>@lang('construction::lang.measurement_workspace_hint')</p>
        </div>
        <div class="ct-measurement-heading__actions">
            <a href="{{ route('construction.projects.measurements.index', $project->id) }}" class="ct-measurement-button ct-measurement-button--soft"><i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_measurements')</a>
            <a href="{{ route('construction.projects.show', $project->id) }}" class="ct-measurement-button ct-measurement-button--soft"><i class="fa fa-building"></i> @lang('construction::lang.back_to_project')</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger ct-measurement-errors"><strong>@lang('construction::lang.fix_measurement_errors')</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <article class="ct-measurement-card">
        <header class="ct-measurement-card__header">
            <span class="ct-measurement-card__icon"><i class="fa fa-clipboard-list"></i></span>
            <div><h3>@lang('construction::lang.measurement_information')</h3><p>@lang('construction::lang.measurement_information_hint')</p></div>
            <span class="ct-measurement-status ct-measurement-status--{{ $measurement->status }}">@lang('construction::lang.'.$measurement->status)</span>
        </header>
        <div class="ct-measurement-info-grid">
            <div><small>@lang('construction::lang.measurement_number')</small><strong>{{ $measurement->number }}</strong></div>
            <div><small>@lang('construction::lang.measurement_date')</small><strong>{{ @format_date($measurement->measurement_date) }}</strong></div>
            <div><small>@lang('construction::lang.period_from')</small><strong>{{ $measurement->period_from ? @format_date($measurement->period_from) : '—' }}</strong></div>
            <div><small>@lang('construction::lang.period_to')</small><strong>{{ $measurement->period_to ? @format_date($measurement->period_to) : '—' }}</strong></div>
            <div><small>@lang('construction::lang.status')</small><strong>@lang('construction::lang.'.$measurement->status)</strong></div>
            <div class="ct-measurement-info-grid__notes"><small>@lang('construction::lang.measurement_notes')</small><strong>{{ $measurement->notes ?: '—' }}</strong></div>
        </div>
    </article>

    <div class="ct-measurement-summary">
        <div><span><i class="fa fa-list"></i></span><small>@lang('construction::lang.measurement_items_count')</small><strong>{{ $measurementSummary['items_count'] }}</strong></div>
        <div><span><i class="fa fa-check-circle"></i></span><small>@lang('construction::lang.measured_items_count')</small><strong id="summary-measured-count">{{ $measurementSummary['measured_items_count'] }}</strong></div>
        <div><span><i class="fa fa-coins"></i></span><small>@lang('construction::lang.current_executed_value')</small><strong id="summary-current-value">@include('construction::partials.money', ['value' => $measurementSummary['current_value']])</strong></div>
        <div><span><i class="fa fa-history"></i></span><small>@lang('construction::lang.previous_approved_value')</small><strong>@include('construction::partials.money', ['value' => $measurementSummary['previous_value']])</strong></div>
        <div><span><i class="fa fa-chart-line"></i></span><small>@lang('construction::lang.cumulative_executed_value')</small><strong id="summary-cumulative-value">@include('construction::partials.money', ['value' => $measurementSummary['cumulative_value']])</strong></div>
    </div>

    @if($measurement->status === 'approved')
        <div class="ct-measurement-state ct-measurement-state--approved">
            <i class="fa fa-lock"></i><div><strong>@lang('construction::lang.approved_measurement_is_locked')</strong><span>@lang('construction::lang.ready_for_certificate')</span></div>
            @if($linkedCertificateId)
                @can('construction.certificate.view')<a href="{{ route('construction.projects.certificates.show', [$project->id, $linkedCertificateId]) }}" class="ct-measurement-button ct-measurement-button--primary"><i class="fa fa-file-invoice-dollar"></i> @lang('construction::lang.open_certificate')</a>@endcan
            @else
                @can('construction.certificate.manage')<a data-ct-certificate-create href="{{ route('construction.projects.certificates.index', ['project' => $project->id, 'measurement_id' => $measurement->id]) }}" class="ct-measurement-button ct-measurement-button--success"><i class="fa fa-file-invoice-dollar"></i> @lang('construction::lang.create_certificate_from_measurement')</a>@endcan
            @endif
        </div>
    @endif

    @if(!$approvedBoq)
        <div class="ct-measurement-state ct-measurement-state--warning"><i class="fa fa-exclamation-triangle"></i><div><strong>@lang('construction::lang.no_active_contract_for_measurement')</strong><span>@lang('construction::lang.measurement_requires_contract_hint')</span></div></div>
    @else
        <article class="ct-measurement-card ct-measurement-card--table">
            <header class="ct-measurement-card__header"><span class="ct-measurement-card__icon"><i class="fa fa-list-ol"></i></span><div><h3>@lang('construction::lang.measurement_items')</h3><p>@lang('construction::lang.measurement_inline_hint')</p></div></header>
            @if($canEditMeasurement)<form method="POST" action="{{ route('construction.projects.measurements.items.store', [$project->id, $measurement->id]) }}" id="measurement-items-form">@csrf @endif
            <div class="ct-measurement-table-wrap">
                <table class="ct-measurement-table">
                    <thead><tr><th>@lang('construction::lang.boq_code')</th><th>@lang('construction::lang.boq_description')</th><th>@lang('construction::lang.unit')</th><th>@lang('construction::lang.contract_quantity')</th><th>@lang('construction::lang.previously_executed')</th><th>@lang('construction::lang.currently_executed')</th><th>@lang('construction::lang.total_executed')</th><th>@lang('construction::lang.remaining_quantity')</th>@if($canEditMeasurement)<th>@lang('construction::lang.notes')</th><th>@lang('construction::lang.actions')</th>@endif</tr></thead>
                    <tbody>
                    @forelse($measurementRows as $index => $row)
                        @php
                            $item = $row->boq_item;
                            $inputValue = old("items.$index.executed_quantity", app(\App\Utils\Util::class)->num_f($row->current_quantity, false, null, true));
                            $noteValue = old("items.$index.notes", optional($row->measurement_item)->notes);
                        @endphp
                        <tr class="ct-measurement-row {{ $row->current_quantity > 0 ? 'has-quantity' : '' }}" data-contract="{{ (float) $item->contract_quantity }}" data-previous="{{ $row->previous_quantity }}" data-price="{{ (float) $item->sales_unit_price }}">
                            <td><strong class="ct-measurement-code">{{ $item->code }}</strong>@if($item->item_kind === 'variation')<small class="label label-info">@lang('construction::lang.additional_work')</small>@endif</td><td><strong class="ct-measurement-description">{{ $item->description }}</strong></td><td><span class="ct-measurement-unit">{{ $item->unit ?: '—' }}</span></td>
                            <td class="ct-number">{{ @format_quantity((float) $item->contract_quantity) }}</td><td class="ct-number">{{ @format_quantity($row->previous_quantity) }}</td>
                            <td class="ct-measurement-current-cell">
                                @if($canEditMeasurement)
                                    <input type="hidden" name="items[{{ $index }}][boq_item_id]" value="{{ $item->id }}">
                                    <input type="text" name="items[{{ $index }}][executed_quantity]" class="form-control input_number input_quantity ct-measurement-current" value="{{ $inputValue }}" inputmode="decimal" autocomplete="off">
                                    <small class="ct-measurement-row-error"></small>
                                @else<strong>{{ @format_quantity($row->current_quantity) }}</strong>@endif
                            </td>
                            <td class="ct-number"><strong class="ct-measurement-total">{{ @format_quantity($row->total_quantity) }}</strong></td><td class="ct-number"><strong class="ct-measurement-remaining">{{ @format_quantity($row->remaining_quantity) }}</strong></td>
                            @if($canEditMeasurement)<td><input type="text" name="items[{{ $index }}][notes]" class="form-control ct-measurement-note" value="{{ $noteValue }}" maxlength="2000" placeholder="@lang('construction::lang.optional_note')"></td>
                            <td class="ct-measurement-actions"><button type="button" class="ct-measurement-clear" title="@lang('construction::lang.clear_row')"><i class="fa fa-eraser"></i></button></td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canEditMeasurement ? 10 : 8 }}"><div class="ct-measurement-empty"><i class="fa fa-inbox"></i><strong>@lang('construction::lang.no_contract_boq_items')</strong></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($canEditMeasurement)
                <footer class="ct-measurement-footer"><span id="measurement-validation-message"><i class="fa fa-info-circle"></i> @lang('construction::lang.save_before_approval')</span><button type="submit" class="ct-measurement-button ct-measurement-button--primary" id="save-measurement-items"><i class="fa fa-save"></i> @lang('construction::lang.save_measurement_quantities')</button></footer>
                </form>
            @endif
        </article>
    @endif

    @if($measurement->status === 'draft')
        @can('construction.measurement.approve')
            <div class="ct-measurement-approve"><div><strong>@lang('construction::lang.approve_measurement')</strong><span>@lang('construction::lang.approve_measurement_hint')</span></div><form method="POST" action="{{ route('construction.projects.measurements.approve', [$project->id, $measurement->id]) }}">@csrf<button class="ct-measurement-button ct-measurement-button--success" id="approve-measurement-button" {{ !$hasMeasuredItems ? 'disabled' : '' }}><i class="fa fa-check"></i> @lang('construction::lang.approve_measurement')</button></form></div>
        @endcan
    @endif
</section>
<div id="ct-quick-workspace"></div>
@endsection
@include('construction::partials.quick_create')

@section('module_javascript')
@if($canEditMeasurement)
<script id="ct-measurement-detail-script">
$(function () {
    var $form = $('#measurement-items-form');
    var $approveButton = $('#approve-measurement-button');
    var initialState = $form.serialize();
    var limitMessage = @json(__('construction::lang.measurement_exceeds_contract_quantity_plain'));
    var negativeMessage = @json(__('construction::lang.current_quantity_non_negative'));
    function quantityFormat(value) { return __number_f(value, false, false, __quantity_precision); }
    function moneyFormat(value) { return __currency_trans_from_en(value, true); }
    function refreshMeasurement() {
        var measuredCount = 0, currentValue = 0, cumulativeValue = 0, invalid = false;
        $('.ct-measurement-row').each(function () {
            var $row = $(this), $input = $row.find('.ct-measurement-current');
            var current = $input.length ? __read_number($input) : 0;
            var previous = parseFloat($row.data('previous')) || 0, contract = parseFloat($row.data('contract')) || 0, price = parseFloat($row.data('price')) || 0;
            var error = '';
            if (!isFinite(current)) current = 0;
            if (current < 0) error = negativeMessage;
            if (previous + current > contract + 0.0001) error = limitMessage;
            $row.toggleClass('has-quantity', current > 0).toggleClass('has-error', !!error);
            $row.find('.ct-measurement-row-error').text(error);
            $row.find('.ct-measurement-total').text(quantityFormat(previous + current));
            $row.find('.ct-measurement-remaining').text(quantityFormat(Math.max(0, contract - previous - current)));
            if (current > 0) measuredCount++;
            currentValue += Math.max(0, current) * price;
            cumulativeValue += (previous + Math.max(0, current)) * price;
            invalid = invalid || !!error;
        });
        $('#summary-measured-count').text(measuredCount);
        $('#summary-current-value').text(moneyFormat(currentValue));
        $('#summary-cumulative-value').text(moneyFormat(cumulativeValue));
        $('#save-measurement-items').prop('disabled', invalid);
        $approveButton.prop('disabled', invalid || initialState !== $form.serialize() || measuredCount < 1);
        $('#measurement-validation-message').toggleClass('text-danger', invalid);
    }
    $form.on('input change', '.ct-measurement-current, .ct-measurement-note', refreshMeasurement);
    $form.on('click', '.ct-measurement-clear', function () { var $row = $(this).closest('tr'); __write_number($row.find('.ct-measurement-current'), 0); $row.find('.ct-measurement-note').val(''); refreshMeasurement(); });
    $form.on('submit', function (event) {
        event.preventDefault();
        refreshMeasurement();
        if ($(this).find('.has-error').length) {
            return false;
        }

        var $submitBtn = $('#save-measurement-items');
        var originalHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> @lang("messages.saving")');

        $.ajax({
            method: 'POST',
            url: $form.attr('action'),
            data: $form.serialize(),
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (result) {
                if (result.success) {
                    if (window.toastr) {
                        toastr.success(result.msg || @json(__('construction::lang.measurement_quantities_saved')));
                    }
                    initialState = $form.serialize();
                    refreshMeasurement();
                    if (window.ctRefreshList) {
                        window.ctRefreshList();
                    }
                } else {
                    if (window.toastr) {
                        toastr.error(result.msg || @json(__('messages.something_went_wrong')));
                    }
                }
            },
            error: function (xhr) {
                var msg = @json(__('messages.something_went_wrong'));
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        var errs = [];
                        $.each(xhr.responseJSON.errors, function (k, v) {
                            errs.push($.isArray(v) ? v.join('<br>') : v);
                        });
                        msg = errs.join('<br>');
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.msg) {
                        msg = xhr.responseJSON.msg;
                    }
                }
                if (window.toastr) {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            },
            complete: function () {
                $submitBtn.prop('disabled', false).html(originalHtml);
                refreshMeasurement();
            }
        });
    });
    refreshMeasurement();
});
</script>
@endif
@endsection
