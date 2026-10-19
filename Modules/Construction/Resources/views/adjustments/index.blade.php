@extends('construction::layouts.module')
@section('title', __('construction::lang.contract_adjustments'))

@php
    $adjustmentTones = ['draft' => 'warning', 'approved' => 'success'];
    $currencySymbol = \Modules\Construction\Support\CurrencyFormatter::parts(0)['symbol'];
    $approvedTotal = $adjustments->where('status', 'approved')->sum('total');
    $draftCount = $adjustments->where('status', 'draft')->count();
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-adjustments-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.projects.show', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><a href="{{ route('construction.projects.contracts.show', [$project->id, $contract->id]) }}" class="rr-num">{{ $contract->contract_number }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.contract_adjustments')</span></li>
                </ol>
            </nav>
            <h1 id="rr-adjustments-title" class="rr-head__title">@lang('construction::lang.contract_adjustments')</h1>
            <p class="rr-head__sub">{{ $project->name }} · @lang('construction::lang.adjustments_history_hint')</p>
        </div>
        <div class="rr-head__actions">
            @can('construction.contract.manage')
                <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#ct-adjustment-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_contract_adjustment')</button>
            @endcan
            <a class="rr-btn rr-btn--secondary" href="{{ route('construction.projects.contracts.show', [$project->id, $contract->id]) }}">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.back_to_contract')</a>
        </div>
    </header>

    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--3" style="--rr-stats: 3">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.original_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $contract->original_value])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.approved_adjustments')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $approvedTotal == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $approvedTotal])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 18])@lang('construction::lang.draft')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $draftCount }}</span></div>
            </div>
        </div>
    </div>

    <section class="rr-table-wrap" aria-labelledby="rr-adjustments-list-title">
        <div class="rr-table-head">
            <h2 id="rr-adjustments-list-title" class="rr-table-head__title">@lang('construction::lang.contract_adjustments_short')</h2>
            <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $adjustments->count() }}</span></span>
        </div>
        @if($adjustments->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_contract_adjustments')</h3>
                @can('construction.contract.manage')
                    <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#ct-adjustment-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_contract_adjustment')</button>
                @endcan
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.contract_adjustments')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.boq_code')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.boq_description')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.unit')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quantity')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.unit_price')</th>
                            <th class="rr-th rr-th--num" scope="col"><span class="rr-th__in">@lang('construction::lang.total')@if($currencySymbol !== '')<span class="rr-th__unit">({{ $currencySymbol }})</span>@endif</span></th>
                            <th class="rr-th" scope="col">@lang('construction::lang.adjustment_reason')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.date')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($adjustments as $adjustment)
                            <tr class="rr-tr">
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $adjustmentTones[$adjustment->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $adjustment->status)</span></td>
                                <td class="rr-td"><span class="rr-code">{{ $adjustment->boqItem?->code ?: ($adjustment->code ?: '—') }}</span></td>
                                <td class="rr-td rr-td--wrap">
                                    <div class="rr-entity__name">{{ $adjustment->description }}</div>
                                    @if($adjustment->originalItem)
                                        <div class="rr-td__sub">@lang('construction::lang.linked_original_item'): <span class="rr-code">{{ $adjustment->originalItem->code }}</span> — @lang('construction::lang.original_quantity'): <span class="rr-num">{{ @format_quantity((float) $adjustment->originalItem->contract_quantity) }}</span> / @lang('construction::lang.current_authorized_quantity'): <span class="rr-num">{{ @format_quantity((float) $adjustment->originalItem->contract_quantity + (float) $contract->adjustments()->where('status', 'approved')->where('original_boq_item_id', $adjustment->original_boq_item_id)->sum('quantity')) }}</span></div>
                                    @endif
                                </td>
                                <td class="rr-td rr-td--muted">{{ $adjustment->unit }}</td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity((float) $adjustment->quantity) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($adjustment->unit_price)['amount'] }}</span></td>
                                <td class="rr-td rr-td--num"><strong class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($adjustment->total)['amount'] }}</strong></td>
                                <td class="rr-td rr-td--wrap rr-td--muted">{{ $adjustment->reason }}</td>
                                <td class="rr-td"><span class="rr-num">{{ @format_date($adjustment->adjustment_date) }}</span></td>
                                <td class="rr-td rr-td--actions">
                                    @if($adjustment->status === 'draft')
                                        <div class="rr-row-actions">
                                            @can('construction.contract.approve')
                                                <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.contracts.adjustments.approve', [$project->id, $contract->id, $adjustment->id]) }}" onsubmit="return confirm(@js(__('construction::lang.approve_adjustment_confirmation')))">
                                                    @csrf
                                                    <button class="rr-btn rr-btn--secondary rr-btn--sm" type="submit">@include('construction::partials.rr_icon', ['name' => 'check', 'size' => 16])@lang('construction::lang.approve')</button>
                                                </form>
                                            @endcan
                                            @can('construction.contract.manage')
                                                <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.contracts.adjustments.destroy', [$project->id, $contract->id, $adjustment->id]) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon" type="submit" aria-label="@lang('messages.delete')" title="@lang('messages.delete')">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 18])</button>
                                                </form>
                                            @endcan
                                        </div>
                                    @else
                                        <span class="rr-td--muted" title="@lang('construction::lang.approved')">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])<span class="rr-sr">@lang('construction::lang.approved')</span></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>

@can('construction.contract.manage')
<div class="modal fade rr-scope" id="ct-adjustment-create" tabindex="-1" role="dialog" aria-labelledby="rr-adjustment-create-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" action="{{ route('construction.projects.contracts.adjustments.store', [$project->id, $contract->id]) }}">
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-adjustment-create-title">@lang('construction::lang.add_contract_adjustment')</h2>
                        <p class="rr-modal__desc"><span class="rr-code">{{ $contract->contract_number }}</span> · @lang('construction::lang.adjustments_history_hint')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>
                <div class="rr-modal__body">
                    <div class="rr-form-wrap">
                        <div class="rr-form-grid">
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-adjustment-source">@lang('construction::lang.adjustment_type')</label>
                                <div class="rr-control">
                                    <select name="original_boq_item_id" id="ct-adjustment-source" class="rr-control__input rr-control__select">
                                        <option value="">@lang('construction::lang.new_additional_item')</option>
                                        @foreach($contract->boqVersion->items as $item)<option value="{{ $item->id }}" data-code="{{ $item->code }}" data-description="{{ $item->description }}" data-unit="{{ $item->unit_id }}" data-price="{{ (float) $item->sales_unit_price }}">@lang('construction::lang.increase_existing_item'): {{ $item->code }} — {{ $item->description }}</option>@endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-adjustment-code">@lang('construction::lang.boq_code')</label>
                                <div class="rr-control"><input name="code" id="ct-adjustment-code" class="rr-control__input rr-control__input--num" maxlength="60"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-adjustment-unit">@lang('construction::lang.unit'){!! $req !!}</label>
                                <div class="rr-control">
                                    <select name="unit_id" id="ct-adjustment-unit" class="rr-control__input rr-control__select" required>
                                        <option value="">@lang('construction::lang.choose')</option>
                                        @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->actual_name }} ({{ $unit->short_name }})</option>@endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-adjustment-description">@lang('construction::lang.work_description'){!! $req !!}</label>
                                <div class="rr-control"><input name="description" id="ct-adjustment-description" class="rr-control__input" maxlength="500" required></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-adjustment-quantity">@lang('construction::lang.additional_quantity'){!! $req !!}</label>
                                <div class="rr-control"><input name="quantity" id="ct-adjustment-quantity" class="rr-control__input rr-control__input--num input_number input_quantity" required></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-adjustment-price">@lang('construction::lang.unit_price'){!! $req !!}</label>
                                <div class="rr-control"><input name="unit_price" id="ct-adjustment-price" class="rr-control__input rr-control__input--num input_number" required></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-adjustment-total">@lang('construction::lang.total')</label>
                                <div class="rr-locked"><input id="ct-adjustment-total" class="rr-control__input rr-control__input--num" readonly></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-adjustment-date">@lang('construction::lang.date'){!! $req !!}</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input name="adjustment_date" id="ct-adjustment-date" class="rr-control__input ct-date-picker" value="{{ @format_date(now()) }}" readonly required></div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-adjustment-reason">@lang('construction::lang.adjustment_reason'){!! $req !!}</label>
                                <div class="rr-control"><input name="reason" id="ct-adjustment-reason" class="rr-control__input" maxlength="500" required></div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-adjustment-notes">@lang('construction::lang.optional_notes')</label>
                                <div class="rr-control rr-control--area"><textarea name="notes" id="ct-adjustment-notes" class="rr-control__input rr-control__input--short" rows="2" maxlength="5000"></textarea></div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_as_draft')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                </footer>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection

@section('module_javascript')
<script>
$(function () {
    $('.ct-date-picker').datetimepicker({format: moment_date_format, ignoreReadonly: true});
    // تاريخ النافذة تحت، فالتقويم يفتح لفوق عشان جسم النافذة ما يقصّوش
    $('#ct-adjustment-create .ct-date-picker').each(function () {
        var picker = $(this).data('DateTimePicker');
        if (picker) picker.widgetPositioning({horizontal: 'auto', vertical: 'top'});
    });
    function total() {
        var q = __number_uf($('#ct-adjustment-quantity').val()) || 0, p = __number_uf($('#ct-adjustment-price').val()) || 0;
        $('#ct-adjustment-total').val(__currency_trans_from_en(q * p, false));
    }
    $('#ct-adjustment-source').on('change', function () {
        var o = $(this).find(':selected');
        if (o.val()) {
            $('#ct-adjustment-code').val(o.data('code') + '-V');
            $('#ct-adjustment-description').val(o.data('description'));
            $('#ct-adjustment-unit').val(String(o.data('unit')));
            __write_number($('#ct-adjustment-price'), parseFloat(o.data('price')) || 0);
        }
        total();
    });
    $('#ct-adjustment-quantity,#ct-adjustment-price').on('input change', total);
    total();
});
</script>
@endsection
