@extends('construction::layouts.module')
@section('title', __('construction::lang.customer_certificates'))

@php
    $certificateTones = ['draft' => 'warning', 'submitted' => 'info', 'approved' => 'success', 'partially_approved' => 'success', 'posted' => 'brand', 'paid' => 'success', 'cancelled' => 'neutral'];
    $hasActiveContract = $contract && $contract->status === 'active';
    $canCreateCertificate = $hasActiveContract && $unbilledCount > 0;
    $liveCertificates = $certificates->where('status', '!=', 'cancelled');
    $grossTotal = $liveCertificates->sum(fn ($certificate) => (float) $certificate->current_approved_gross ?: (float) $certificate->current_submitted_gross);
    $netTotal = $liveCertificates->sum(fn ($certificate) => (float) $certificate->net_due);
    $modalErrors = collect(['measurement_id', 'certificate_date', 'period_from', 'period_to', 'retention_due_date', 'advance_recovery_value', 'other_deductions_value'])->map(fn ($field) => $errors->first($field))->filter()->values();
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-certificates-title">

    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--success" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.ipcs.index') }}">@lang('construction::lang.customer_certificates')</a></li>
                    <li><a href="{{ route('construction.projects.show', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><span aria-current="page">@lang('construction::lang.tab_certificates')</span></li>
                </ol>
            </nav>
            <h1 id="rr-certificates-title" class="rr-head__title">@lang('construction::lang.customer_certificates')</h1>
            <p class="rr-head__sub">{{ $project->name }}</p>
        </div>
        <div class="rr-head__actions">
            @can('construction.certificate.manage')
                <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#ct-certificate-create" {{ $canCreateCertificate ? '' : 'disabled' }}>@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_certificate')</button>
            @endcan
            <a href="{{ route('construction.projects.measurements.index', $project->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'ruler'])@lang('construction::lang.measurements')</a>
            <a href="{{ route('construction.projects.show', $project->id) }}" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'building'])@lang('construction::lang.project_details')</a>
        </div>
    </header>

    @if($errors->any())
        <div class="rr-alert rr-alert--danger" role="alert">
            <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ═══ الخطوة التالية ═══ --}}
    @if(!$hasActiveContract)
        <div class="rr-callout rr-callout--danger">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 22])</span>
            <div class="rr-callout__copy"><strong>@lang('construction::lang.certificate_requires_active_contract')</strong></div>
        </div>
    @elseif($unbilledCount > 0)
        @can('construction.certificate.manage')
            <div class="rr-callout rr-callout--info">
                <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 22])</span>
                <div class="rr-callout__copy">
                    <strong>@lang('construction::lang.unbilled_measurements'): <span class="rr-num">{{ $unbilledCount }}</span></strong>
                    <p>@lang('construction::lang.certificates_description')</p>
                </div>
                <div class="rr-callout__actions">
                    <button type="button" class="rr-btn rr-btn--primary" data-toggle="modal" data-target="#ct-certificate-create">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_certificate')</button>
                </div>
            </div>
        @endcan
    @endif

    {{-- ═══ الأرقام ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--3" style="--rr-stats: 3">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.tab_certificates')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $liveCertificates->count() }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 18])@lang('construction::lang.current_approved_gross')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $grossTotal == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $grossTotal])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.net_due')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $netTotal == 0 ? 'rr-money--zero' : 'rr-money--pos' }}">@include('construction::partials.money', ['value' => $netTotal])</span></div>
            </div>
        </div>
    </div>

    <section class="rr-table-wrap" id="ct-quick-list" aria-labelledby="rr-certificates-list-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-certificates-list-title" class="rr-table-head__title">@lang('construction::lang.tab_certificates')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.certificates_description')</p>
            </div>
            <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $certificates->count() }}</span></span>
        </div>
        @if($certificates->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.no_certificates_yet')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.customer_certificates')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate_number')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate_date')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.period_from')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.period_to')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.current_approved_gross')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.net_due')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.certificate_status')</th>
                            <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.actions')</span></th>
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($certificates as $certificate)
                            @php $certificateUrl = route('construction.projects.certificates.show', [$project->id, $certificate->id]); @endphp
                            <tr class="rr-tr">
                                <td class="rr-td"><a class="rr-code-link" href="{{ $certificateUrl }}"><span class="rr-code">{{ $certificate->number }}</span></a></td>
                                <td class="rr-td"><span class="rr-num">{{ @format_date($certificate->certificate_date) }}</span></td>
                                <td class="rr-td">@if($certificate->period_from)<span class="rr-num">{{ @format_date($certificate->period_from) }}</span>@else<span class="rr-td--muted">—</span>@endif</td>
                                <td class="rr-td">@if($certificate->period_to)<span class="rr-num">{{ @format_date($certificate->period_to) }}</span>@else<span class="rr-td--muted">—</span>@endif</td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => (float) $certificate->current_approved_gross ?: (float) $certificate->current_submitted_gross])</span></td>
                                <td class="rr-td rr-td--num"><strong class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $certificate->net_due])</strong></td>
                                <td class="rr-td"><span class="rr-badge rr-badge--{{ $certificateTones[$certificate->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $certificate->status)</span></td>
                                <td class="rr-td rr-td--actions">
                                    <a href="{{ $certificateUrl }}" class="rr-btn rr-btn--secondary rr-btn--sm">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 18])@lang('construction::lang.open_certificate')</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</main>

@can('construction.certificate.manage')
<div class="modal fade rr-scope ct-quick-modal" id="ct-certificate-create" tabindex="-1" role="dialog" aria-labelledby="rr-certificate-create-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" action="{{ route('construction.projects.certificates.store', $project->id) }}" data-ct-quick-create>
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-certificate-create-title">@lang('construction::lang.new_certificate')</h2>
                        <p class="rr-modal__desc"><span class="rr-code">{{ $nextNumber }}</span> · {{ $project->name }} · <span class="rr-code">{{ $contract?->contract_number ?: '—' }}</span></p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>
                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert">{{ $modalErrors->implode("\n") }}</div>
                    <div class="rr-form-wrap">
                        <div class="rr-form-grid">
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="ct-certificate-measurement">@lang('construction::lang.measurement_number'){!! $req !!}</label>
                                <div class="rr-control">
                                    <select name="measurement_id" id="ct-certificate-measurement" class="rr-control__input rr-control__select" required>
                                        <option value="">@lang('construction::lang.select_approved_measurement')</option>
                                        @foreach($measurements as $measurement)
                                            <option value="{{ $measurement->id }}" {{ (string) old('measurement_id', $selectedMeasurement?->id) === (string) $measurement->id ? 'selected' : '' }}>{{ $measurement->number }} · {{ @format_date($measurement->measurement_date) }}</option>
                                        @endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-certificate-date">@lang('construction::lang.certificate_date'){!! $req !!}</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="certificate_date" id="ct-certificate-date" class="rr-control__input ct-date-picker" readonly required value="{{ old('certificate_date', @format_date(now())) }}"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-certificate-retention-due">@lang('construction::lang.retention_due_date')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="retention_due_date" id="ct-certificate-retention-due" class="rr-control__input ct-date-picker" readonly value="{{ old('retention_due_date') }}"></div>
                                <p class="rr-field__hint">@lang('construction::lang.retention_due_date_hint')</p>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-certificate-from">@lang('construction::lang.period_from')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="period_from" id="ct-certificate-from" class="rr-control__input ct-date-picker" readonly value="{{ old('period_from') }}"></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-certificate-to">@lang('construction::lang.period_to')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="period_to" id="ct-certificate-to" class="rr-control__input ct-date-picker" readonly value="{{ old('period_to') }}"></div>
                            </div>
                            @if((float) ($contract?->advance_payment_value ?? 0) > 0)
                                <div class="rr-field">
                                    <label class="rr-field__label" for="ct-certificate-advance">@lang('construction::lang.advance_recovery_value')</label>
                                    <div class="rr-control"><input name="advance_recovery_value" id="ct-certificate-advance" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" value="{{ old('advance_recovery_value', @num_format(0)) }}"></div>
                                    <p class="rr-field__hint">@lang('construction::lang.advance_remaining'): <span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => max(0, (float) $contract->advance_payment_value - $previousRecovered)])</span></p>
                                </div>
                            @endif
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-certificate-deductions">@lang('construction::lang.other_deductions_value')</label>
                                <div class="rr-control"><input name="other_deductions_value" id="ct-certificate-deductions" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" value="{{ old('other_deductions_value', @num_format(0)) }}"></div>
                            </div>
                        </div>
                        <dl class="rr-deflist rr-card">
                            <div class="rr-deflist__item"><dt>@lang('construction::lang.retention_percent')</dt><dd><span class="rr-num">{{ @num_format($retentionPercent) }}%</span></dd></div>
                            @if((float) ($contract?->advance_payment_value ?? 0) > 0)
                                <div class="rr-deflist__item"><dt>@lang('construction::lang.original_advance')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => (float) $contract->advance_payment_value])</span></dd></div>
                                <div class="rr-deflist__item"><dt>@lang('construction::lang.previously_recovered')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $previousRecovered])</span></dd></div>
                            @endif
                        </dl>
                    </div>
                </div>
                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit" {{ $canCreateCertificate ? '' : 'disabled' }}>@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.new_certificate')</button>
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
    $('#ct-certificate-create .ct-date-picker').each(function () {
        var picker = $(this).data('DateTimePicker');
        if (picker) picker.widgetPositioning({horizontal: 'auto', vertical: 'top'});
    });
    @if(($selectedMeasurement || request()->boolean('open_create')) && $canCreateCertificate)
    $('#ct-certificate-create').modal('show');
    @endif
});
</script>
@endsection
