@extends('construction::layouts.module')
@section('title', __('construction::lang.measurements'))

@php
    $measurementTones = ['draft' => 'warning', 'approved' => 'success'];
    $modalErrors = collect(['number', 'measurement_date', 'period_from', 'period_to', 'notes'])->map(fn ($field) => $errors->first($field))->filter()->values();
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-measurements-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--info" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.measurements.index') }}">@lang('construction::lang.measurements')</a></li>
                    <li><a href="{{ route('construction.projects.show', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.tab_measurements')</span></li>
                </ol>
            </nav>
            <h1 id="rr-measurements-title" class="rr-head__title">@lang('construction::lang.measurements')</h1>
            <p class="rr-head__sub">{{ $project->name }}</p>
        </div>
        <div class="rr-head__actions">
            @can('construction.measurement.manage')
                <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#ct-measurement-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_measurement')</button>
            @endcan
            <a href="{{ route('construction.projects.certificates.index', $project->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.customer_certificates')</a>
            <a href="{{ route('construction.projects.show', $project->id) }}" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.project_details')</a>
        </div>
    </header>

    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-measurements-list-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-measurements-list-title" class="rr-table-head__title">@lang('construction::lang.tab_measurements')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.measurements_description')</p>
            </div>
            <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $measurements->count() }}</span></span>
        </div>
        @if($measurements->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_measurements')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.measurements')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.measurement_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.measurement_date')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.period_from')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.period_to')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.measurement_items_count')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($measurements as $measurement)
                            <tr class="rr-tr">
                                <td class="rr-td"><a class="rr-code-link" href="{{ route('construction.projects.measurements.show', [$project->id, $measurement->id]) }}"><span class="rr-code">{{ $measurement->number }}</span></a></td>
                                <td class="rr-td"><span class="rr-num">{{ @format_date($measurement->measurement_date) }}</span></td>
                                <td class="rr-td">@if($measurement->period_from)<span class="rr-num">{{ @format_date($measurement->period_from) }}</span>@else<span class="rr-td--muted">—</span>@endif</td>
                                <td class="rr-td">@if($measurement->period_to)<span class="rr-num">{{ @format_date($measurement->period_to) }}</span>@else<span class="rr-td--muted">—</span>@endif</td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ $measurement->items_count }}</span></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $measurementTones[$measurement->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $measurement->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <a href="{{ route('construction.projects.measurements.show', [$project->id, $measurement->id]) }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.open_measurement')</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>

@can('construction.measurement.manage')
<div class="modal fade rr-scope ct-quick-modal" id="ct-measurement-create" tabindex="-1" role="dialog" aria-labelledby="rr-measurement-create-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" action="{{ route('construction.projects.measurements.store', $project->id) }}" data-ct-quick-create>
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-measurement-create-title">@lang('construction::lang.new_measurement')</h2>
                        <p class="rr-modal__desc"><span class="rr-code">{{ $project->code }}</span> · {{ $project->name }}</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>
                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert">{{ $modalErrors->implode("\n") }}</div>
                    <div class="rr-form-wrap">
                        <div class="rr-form-grid">
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-measurement-number">@lang('construction::lang.measurement_number'){!! $req !!}</label>
                                <div class="rr-control"><input name="number" id="ct-measurement-number" class="rr-control__input rr-control__input--num" value="{{ old('number', $nextNumber) }}" maxlength="60" required></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-measurement-date">@lang('construction::lang.measurement_date'){!! $req !!}</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="measurement_date" id="ct-measurement-date" class="rr-control__input ct-date-picker" readonly value="{{ old('measurement_date', @format_date(now())) }}" required></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-measurement-from">@lang('construction::lang.period_from')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="period_from" id="ct-measurement-from" class="rr-control__input ct-date-picker" readonly value="{{ old('period_from') }}"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-measurement-to">@lang('construction::lang.period_to')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="period_to" id="ct-measurement-to" class="rr-control__input ct-date-picker" readonly value="{{ old('period_to') }}"></div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-measurement-notes">@lang('construction::lang.measurement_notes')</label>
                                <div class="rr-control rr-control--area"><textarea name="notes" id="ct-measurement-notes" class="rr-control__input rr-control__input--short" rows="3" maxlength="5000">{{ old('notes') }}</textarea></div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_measurement')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                </footer>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
@include('construction::partials.quick_create')

@section('module_javascript')
<script>
$(function () {
    // التقويم يفتح لفوق عشان جسم النافذة ما يقصّوش
    $('#ct-measurement-create .ct-date-picker').each(function () {
        var picker = $(this).data('DateTimePicker');
        if (picker) picker.widgetPositioning({horizontal: 'auto', vertical: 'top'});
    });
    @if(request()->boolean('open_create'))
    $('#ct-measurement-create').modal('show');
    @endif
});
</script>
@endsection
