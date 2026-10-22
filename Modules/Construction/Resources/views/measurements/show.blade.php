@extends('construction::layouts.module')
@section('title', $measurement->number)

@php
    $canEditMeasurement = $measurement->status === 'draft' && auth()->user()->can('construction.measurement.manage');
    $hasMeasuredItems = $measurementSummary['measured_items_count'] > 0;
    $isApproved = $measurement->status === 'approved';
    $measurementTones = ['draft' => 'warning', 'approved' => 'success'];
@endphp

@section('module_content')
<main class="content rr-page ct-measurement-page" aria-labelledby="rr-measurement-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.measurements.index') }}">@lang('construction::lang.measurements')</a></li>
                    <li><a href="{{ route('construction.projects.measurements.index', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $measurement->number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-measurement-title" class="rr-head__title">@lang('construction::lang.measurement_number'): <span class="rr-num">{{ $measurement->number }}</span></h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $measurementTones[$measurement->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $measurement->status)</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $project->name }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])<span class="rr-num">{{ @format_date($measurement->measurement_date) }}</span></span>
                @if($measurement->period_from || $measurement->period_to)
                    <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])<span class="rr-num">{{ $measurement->period_from ? @format_date($measurement->period_from) : '—' }}</span> ← <span class="rr-num">{{ $measurement->period_to ? @format_date($measurement->period_to) : '—' }}</span></span>
                @endif
            </div>
        </div>
        <div class="rr-head__actions">
            <a href="{{ route('construction.projects.measurements.index', $project->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.back_to_measurements')</a>
            <a href="{{ route('construction.projects.show', $project->id) }}" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.back_to_project')</a>
        </div>
    </header>

    @if($errors->any())
        <div class="rr-alert rr-alert--danger ct-measurement-errors" role="alert">
            <div>
                <strong>@lang('construction::lang.fix_measurement_errors')</strong>
                <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    {{-- ═══ الحالة + الخطوة التالية ═══ --}}
    @if($isApproved)
        <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-measurement-next-title">
            <div class="rr-next">
                <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 22])</span>
                <div class="rr-next__copy">
                    <h2 id="rr-measurement-next-title" class="rr-next__title">@lang('construction::lang.approved_measurement_is_locked')</h2>
                    <p class="rr-next__text">@lang('construction::lang.ready_for_certificate')</p>
                </div>
                @if($linkedCertificateId)
                    @can('construction.certificate.view')
                        <a href="{{ route('construction.projects.certificates.show', [$project->id, $linkedCertificateId]) }}" class="rr-btn rr-btn--primary rr-btn--lg">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.open_certificate')</a>
                    @endcan
                @else
                    @can('construction.certificate.manage')
                        <a data-ct-certificate-create href="{{ route('construction.projects.certificates.index', ['project' => $project->id, 'measurement_id' => $measurement->id]) }}" class="rr-btn rr-btn--primary rr-btn--lg">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.create_certificate_from_measurement')</a>
                    @endcan
                @endif
            </div>
        </section>
    @elseif($measurement->status === 'draft')
        @can('construction.measurement.approve')
            <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-measurement-next-title">
                <div class="rr-next">
                    <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 22])</span>
                    <div class="rr-next__copy">
                        <h2 id="rr-measurement-next-title" class="rr-next__title">@lang('construction::lang.approve_measurement')</h2>
                        <p class="rr-next__text">@lang('construction::lang.approve_measurement_hint') @lang('construction::lang.save_before_approval')</p>
                    </div>
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.measurements.approve', [$project->id, $measurement->id]) }}">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit" id="approve-measurement-button" {{ !$hasMeasuredItems ? 'disabled' : '' }}>@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.approve_measurement')</button>
                    </form>
                </div>
            </section>
        @endcan
    @endif

    {{-- ═══ أرقام الحصر ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--5" style="--rr-stats: 5">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 18])@lang('construction::lang.measurement_items_count')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $measurementSummary['items_count'] }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.measured_items_count')</div>
                <div class="rr-stat__value"><span class="rr-num" id="summary-measured-count">{{ $measurementSummary['measured_items_count'] }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 18])@lang('construction::lang.current_executed_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg" id="summary-current-value">@include('construction::partials.money', ['value' => $measurementSummary['current_value']])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])@lang('construction::lang.previous_approved_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $measurementSummary['previous_value']])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.cumulative_executed_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg" id="summary-cumulative-value">@include('construction::partials.money', ['value' => $measurementSummary['cumulative_value']])</span></div>
            </div>
        </div>
    </div>

    @if($measurement->notes)
        <div class="rr-alert rr-alert--info"><div><strong>@lang('construction::lang.measurement_notes'):</strong> {{ $measurement->notes }}</div></div>
    @endif

    {{-- ═══ كميات الأعمال المنفذة ═══ --}}
    @if(!$approvedBoq)
        <div class="rr-callout rr-callout--danger">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 22])</span>
            <div class="rr-callout__copy"><strong>@lang('construction::lang.no_active_contract_for_measurement')</strong><p>@lang('construction::lang.measurement_requires_contract_hint')</p></div>
        </div>
    @else
        <section class="rr-table-wrap" aria-labelledby="rr-measurement-items-title">
            @if($canEditMeasurement)<form method="POST" action="{{ route('construction.projects.measurements.items.store', [$project->id, $measurement->id]) }}" id="measurement-items-form">@csrf @endif
            <div class="rr-table-head">
                <div>
                    <h2 id="rr-measurement-items-title" class="rr-table-head__title">@lang('construction::lang.measurement_items')</h2>
                    <p class="rr-table-head__sub">@lang('construction::lang.measurement_inline_hint')</p>
                </div>
            </div>
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.measurement_items')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.boq_code')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.boq_description')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.unit')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.contract_quantity')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.previously_executed')</th>
                            <th class="rr-th rr-th--num rr-th--focus" scope="col">@lang('construction::lang.currently_executed')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total_executed')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.remaining_quantity')</th>
                            @if($canEditMeasurement)
                                <th class="rr-th" scope="col">@lang('construction::lang.notes')</th>
                                <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                    @forelse($measurementRows as $index => $row)
                        @php
                            $item = $row->boq_item;
                            $inputValue = old("items.$index.executed_quantity", app(\App\Utils\Util::class)->num_f($row->current_quantity, false, null, true));
                            $noteValue = old("items.$index.notes", optional($row->measurement_item)->notes);
                        @endphp
                        <tr class="rr-tr ct-measurement-row {{ $row->current_quantity > 0 ? 'has-quantity' : '' }}" data-contract="{{ (float) $item->contract_quantity }}" data-previous="{{ $row->previous_quantity }}" data-price="{{ (float) $item->sales_unit_price }}">
                            <td class="rr-td">
                                <span class="rr-code">{{ $item->code }}</span>
                                @if($item->item_kind === 'variation')<div><span class="rr-badge rr-badge--info">@lang('construction::lang.additional_work')</span></div>@endif
                            </td>
                            <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $item->description }}</span></td>
                            <td class="rr-td rr-td--muted">{{ $item->unit ?: '—' }}</td>
                            <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity((float) $item->contract_quantity) }}</span></td>
                            <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity($row->previous_quantity) }}</span></td>
                            <td class="rr-td rr-td--num rr-td--focus {{ $canEditMeasurement ? 'rr-td--input' : '' }}">
                                @if($canEditMeasurement)
                                    <input type="hidden" name="items[{{ $index }}][boq_item_id]" value="{{ $item->id }}">
                                    <div class="rr-control rr-control--sm"><input type="text" name="items[{{ $index }}][executed_quantity]" class="rr-control__input rr-control__input--num input_number input_quantity ct-measurement-current" value="{{ $inputValue }}" inputmode="decimal" autocomplete="off" aria-label="@lang('construction::lang.currently_executed'): {{ $item->code }}"></div>
                                    <small class="rr-field__error ct-measurement-row-error" aria-live="polite"></small>
                                @else
                                    <strong class="rr-num">{{ @format_quantity($row->current_quantity) }}</strong>
                                @endif
                            </td>
                            <td class="rr-td rr-td--num"><strong class="rr-num ct-measurement-total">{{ @format_quantity($row->total_quantity) }}</strong></td>
                            <td class="rr-td rr-td--num"><span class="rr-num ct-measurement-remaining">{{ @format_quantity($row->remaining_quantity) }}</span></td>
                            @if($canEditMeasurement)
                                <td class="rr-td rr-td--input rr-td--note"><div class="rr-control rr-control--sm"><input type="text" name="items[{{ $index }}][notes]" class="rr-control__input ct-measurement-note" value="{{ $noteValue }}" maxlength="2000" placeholder="@lang('construction::lang.optional_note')" aria-label="@lang('construction::lang.notes'): {{ $item->code }}"></div></td>
                                <td class="rr-td rr-td--actions"><button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon ct-measurement-clear" title="@lang('construction::lang.clear_row')" aria-label="@lang('construction::lang.clear_row')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 16])</button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td class="rr-td" colspan="{{ $canEditMeasurement ? 10 : 8 }}">
                            <div class="rr-empty">
                                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'folder-open', 'size' => 22])</span>
                                <h3 class="rr-empty__title">@lang('construction::lang.no_contract_boq_items')</h3>
                            </div>
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($canEditMeasurement)
                <div class="rr-toolbar">
                    <span class="rr-toolbar__hint" id="measurement-validation-message">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.save_before_approval')</span>
                    <div class="rr-toolbar__end">
                        <button type="submit" class="rr-btn rr-btn--primary" id="save-measurement-items">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_measurement_quantities')</button>
                    </div>
                </div>
                </form>
            @endif
        </section>
    @endif
</main>
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
            $input.closest('.rr-control').toggleClass('rr-control--invalid', !!error);
            $input.attr('aria-invalid', error ? 'true' : null);
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
        $submitBtn.prop('disabled', true).text(@json(__('messages.saving')));

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
