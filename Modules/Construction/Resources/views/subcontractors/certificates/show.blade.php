@extends('construction::layouts.module')

@section('title', $certificate->number)

@php
    $certificateTones = ['draft' => 'warning', 'approved' => 'success', 'cancelled' => 'neutral'];
    $paymentTones = ['unpaid' => 'warning', 'partially_paid' => 'info', 'paid' => 'success'];
    $isApproved = $certificate->status === 'approved';
    $isEditable = $certificate->isEditable();
    $canEditCertificate = $isEditable && auth()->user()->can('construction.subcontract.manage');
    $subcontractorName = $contract->subcontractor->supplier_business_name ?: $contract->subcontractor->name;
    $canCancelCertificate = $isApproved && ! $certificate->activePayments()->exists() && ! $certificate->activeRetentionReleases()->exists();
    if ($isApproved) {
        $paidValue = $certificate->paidValue();
        $releasedRetentionValue = $certificate->releasedRetentionValue();
        $retentionRemainingValue = $certificate->retentionRemainingValue();
        $payableValue = $certificate->payableValue();
        $remainingValue = $certificate->remainingValue();
        $certificatePaymentStatus = $certificate->paymentStatus();
    }
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-subcert-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.subcontractors.index') }}">@lang('construction::lang.subcontract_agreements')</a></li>
                    <li><a href="{{ route('construction.subcontractors.show', $contract->id) }}" class="rr-num">{{ $contract->number }}</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $certificate->number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-subcert-title" class="rr-head__title">@lang('construction::lang.subcontract_certificate'): <span class="rr-num">{{ $certificate->number }}</span></h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $certificateTones[$certificate->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $certificate->status)</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'user', 'size' => 18]){{ $subcontractorName }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $contract->project->name }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])<span class="rr-num">{{ @format_date($certificate->certificate_date) }}</span></span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 18])@lang('construction::lang.retention_percent') <span class="rr-num">{{ @num_format($certificate->retention_percent) }}%</span></span>
            </div>
        </div>
        <div class="rr-head__actions">
            <a href="{{ route('construction.subcontractors.show', $contract->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.back_to_subcontract')</a>
            <a href="{{ route('construction.subcontractors.certificates.preview', [$contract->id, $certificate->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.print_preview')</a>
            <a href="{{ route('construction.subcontractors.certificates.print', [$contract->id, $certificate->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
        </div>
    </header>

    @if($errors->any())
        <div class="rr-alert rr-alert--danger" role="alert">
            <div>
                <strong>@lang('construction::lang.subcontract_form_has_errors')</strong>
                <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    {{-- ═══ الخطوة التالية ═══ --}}
    @if($isEditable)
        <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-subcert-next-title">
            <div class="rr-next">
                <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 22])</span>
                <div class="rr-next__copy">
                    <div class="rr-eyebrow">@lang('construction::lang.next_action')</div>
                    <h2 id="rr-subcert-next-title" class="rr-next__title">@lang('construction::lang.ready_for_approval')</h2>
                    <p class="rr-next__text">@lang('construction::lang.approved_subcontract_certificate_locked')</p>
                </div>
                @can('construction.subcontract.approve')
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.subcontractors.certificates.approve', [$contract->id, $certificate->id]) }}" onsubmit="return confirm(@js(__('construction::lang.approve_subcontract_certificate_confirmation')))">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.approve_certificate')</button>
                    </form>
                @endcan
                @can('construction.subcontract.manage')
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.subcontractors.certificates.destroy', [$contract->id, $certificate->id]) }}" onsubmit="return confirm(@js(__('construction::lang.delete_certificate_confirmation')))">
                        @csrf
                        @method('DELETE')
                        <button class="rr-btn rr-btn--ghost" type="submit">@include('construction::partials.rr_icon', ['name' => 'trash'])@lang('messages.delete')</button>
                    </form>
                @endcan
            </div>
        </section>
    @elseif($isApproved)
        <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-subcert-next-title">
            <div class="rr-next">
                <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 22])</span>
                <div class="rr-next__copy">
                    <div class="rr-eyebrow">@lang('construction::lang.payment_status')</div>
                    <h2 id="rr-subcert-next-title" class="rr-next__title"><span class="rr-badge rr-badge--lg rr-badge--{{ $paymentTones[$certificatePaymentStatus] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.payment_status_' . $certificatePaymentStatus)</span></h2>
                    <p class="rr-next__text">@lang('construction::lang.remaining_amount'): <span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $remainingValue])</span></p>
                </div>
                @if($remainingValue > 0.0001)
                    @can('construction.payment.record')
                        <button type="button" class="rr-btn rr-btn--primary rr-btn--lg" data-toggle="modal" data-target="#record-subcontract-payment">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.record_payment')</button>
                    @endcan
                @endif
                @if($canCancelCertificate)
                    @can('construction.cancel')
                        <button type="button" class="rr-btn rr-btn--ghost" data-toggle="modal" data-target="#cancel-subcontract-certificate">@include('construction::partials.rr_icon', ['name' => 'close'])@lang('construction::lang.cancel_posted_certificate')</button>
                    @endcan
                @endif
            </div>
        </section>
    @endif

    {{-- ═══ القيود المحاسبية ═══ --}}
    @if($certificate->accountingPosting?->mapping)
        <div class="rr-callout rr-callout--success">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 22])</span>
            <div class="rr-callout__copy">
                <strong>@lang('construction::lang.accounting_entry_created')</strong>
                <p>@lang('construction::lang.accounting_entry_reference', ['reference' => $certificate->accountingPosting->mapping->ref_no])</p>
            </div>
            @can('construction.accounting.view')
                <div class="rr-callout__actions">
                    <button type="button" class="rr-btn rr-btn--secondary btn-modal" data-href="{{ route('journal-entry.show', $certificate->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.view_accounting_entry')</button>
                </div>
            @endcan
        </div>
    @endif
    @if($certificate->cancellationAccountingPosting?->mapping)
        <div class="rr-callout rr-callout--danger">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 22])</span>
            <div class="rr-callout__copy">
                <strong>@lang('construction::lang.reversal_entry_created')</strong>
                <p>{{ $certificate->cancellation_reason }}</p>
            </div>
            @can('construction.accounting.view')
                <div class="rr-callout__actions">
                    <button type="button" class="rr-btn rr-btn--secondary btn-modal" data-href="{{ route('journal-entry.show', $certificate->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.view_reversal_accounting_entry')</button>
                </div>
            @endcan
        </div>
    @endif

    {{-- ═══ موقف السداد ═══ --}}
    @if($isApproved)
        <div class="rr-statgroup-wrap">
            <div class="rr-statgroup rr-statgroup--5" style="--rr-stats: 5">
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])@lang('construction::lang.net_certificate_due')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $certificate->net_value])</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 18])@lang('construction::lang.released_retention')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $releasedRetentionValue == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $releasedRetentionValue])</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.total_payable_after_release')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $payableValue])</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.paid_amount')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $paidValue == 0 ? 'rr-money--zero' : 'rr-money--pos' }}">@include('construction::partials.money', ['value' => $paidValue])</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])@lang('construction::lang.remaining_amount')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $remainingValue == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $remainingValue])</span></div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ بنود المستخلص والإجماليات ═══ --}}
    @if($canEditCertificate)<form method="POST" action="{{ route('construction.subcontractors.certificates.update', [$contract->id, $certificate->id]) }}" id="subcontract-certificate-form" class="rr-form">@csrf @method('PUT')@endif

    @if($canEditCertificate)
        <section class="rr-card" aria-labelledby="rr-subcert-meta-title">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <h2 id="rr-subcert-meta-title" class="rr-card__title">@lang('construction::lang.certificate_progress')</h2>
                    <p class="rr-card__desc">@lang('construction::lang.certificate_stage_two_hint')</p>
                </div>
            </header>
            <div class="rr-card__body rr-form-wrap">
                <div class="rr-form-grid rr-form-grid--3">
                    <div class="rr-field">
                        <label class="rr-field__label" for="ct-subcert-date">@lang('construction::lang.certificate_date'){!! $req !!}</label>
                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-date" name="certificate_date" value="{{ old('certificate_date', @format_date($certificate->certificate_date)) }}" class="rr-control__input ct-date-picker" readonly required></div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="ct-subcert-from">@lang('construction::lang.period_from')</label>
                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-from" name="period_from" value="{{ old('period_from', $certificate->period_from ? @format_date($certificate->period_from) : '') }}" class="rr-control__input ct-date-picker" readonly></div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="ct-subcert-to">@lang('construction::lang.period_to')</label>
                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-to" name="period_to" value="{{ old('period_to', $certificate->period_to ? @format_date($certificate->period_to) : '') }}" class="rr-control__input ct-date-picker" readonly></div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="ct-subcert-retention-due">@lang('construction::lang.retention_due_date')</label>
                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-retention-due" name="retention_due_date" value="{{ old('retention_due_date', $certificate->retention_due_date ? @format_date($certificate->retention_due_date) : '') }}" class="rr-control__input ct-date-picker" readonly></div>
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="ct-subcert-other">@lang('construction::lang.other_deductions')</label>
                        <div class="rr-control @error('other_deductions') rr-control--invalid @enderror"><input id="ct-subcert-other" name="other_deductions" value="{{ old('other_deductions', @num_format($certificate->other_deductions)) }}" class="rr-control__input rr-control__input--num input_number ct-subcert-deduction" inputmode="decimal"></div>
                        @error('other_deductions')<p class="rr-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="ct-subcert-tax">@lang('construction::lang.input_vat_percent')</label>
                        <div class="rr-control @error('tax_percent') rr-control--invalid @enderror"><input id="ct-subcert-tax" name="tax_percent" value="{{ old('tax_percent', @num_format($certificate->tax_percent)) }}" class="rr-control__input rr-control__input--num input_number ct-subcert-tax-percent" inputmode="decimal"><span class="rr-control__affix">%</span></div>
                        @error('tax_percent')<p class="rr-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="rr-field">
                        <label class="rr-field__label" for="ct-subcert-advance">@lang('construction::lang.advance_recovery')</label>
                        <div class="rr-control @error('advance_recovery_value') rr-control--invalid @enderror"><input id="ct-subcert-advance" name="advance_recovery_value" value="{{ old('advance_recovery_value', @num_format($certificate->advance_recovery_value)) }}" class="rr-control__input rr-control__input--num input_number ct-subcert-advance" inputmode="decimal"></div>
                        <p class="rr-field__hint">@lang('construction::lang.advance_available'): <span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $availableAdvance])</span></p>
                        @error('advance_recovery_value')<p class="rr-field__error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </section>
    @endif

    <div class="rr-cols">
        <div class="rr-cols__main">
            <section class="rr-table-wrap" aria-labelledby="rr-subcert-items-title">
                <div class="rr-table-head">
                    <div>
                        <h2 id="rr-subcert-items-title" class="rr-table-head__title">@lang('construction::lang.certificate_items')</h2>
                        @if($canEditCertificate)<p class="rr-table-head__sub">@lang('construction::lang.certificate_stage_two_hint')</p>@endif
                    </div>
                    <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $certificate->items->count() }}</span></span>
                </div>
                <div class="rr-table-scroll">
                    <table class="rr-table ct-subcert-table">
                        <caption class="rr-sr">@lang('construction::lang.certificate_items')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th rr-col--index" scope="col">#</th>
                                <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.work_description')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.calculation_type')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.contract_quantity')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.previous_quantity')</th>
                                <th class="rr-th rr-th--num rr-th--focus" scope="col">@lang('construction::lang.current_quantity')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.cumulative_quantity')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.current_value')</th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($certificate->items as $line)
                                <tr class="rr-tr @error('items.' . $line->id) has-limit-error @enderror" data-rate="{{ (float) $line->unit_rate }}" data-type="{{ $line->calculation_type }}" data-previous="{{ (float) $line->previous_quantity }}" data-contract="{{ (float) $line->contract_quantity }}">
                                    <td class="rr-td rr-td--muted"><span class="rr-num">{{ $loop->iteration }}</span></td>
                                    <td class="rr-td rr-td--wrap">
                                        <span class="rr-entity__name">{{ $line->description }}</span>
                                        <div class="rr-td__sub"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $line->unit_rate])</span></div>
                                    </td>
                                    <td class="rr-td"><span class="rr-badge">@lang('construction::lang.subcalc_' . $line->calculation_type)</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($line->contract_quantity) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($line->previous_quantity) }}</span></td>
                                    <td class="rr-td rr-td--num rr-td--focus {{ $canEditCertificate ? 'rr-td--input' : '' }}">
                                        @if($canEditCertificate)
                                            <div class="rr-control rr-control--sm"><input name="items[{{ $line->id }}]" value="{{ old('items.' . $line->id, @num_format($line->current_quantity)) }}" class="rr-control__input rr-control__input--num input_number ct-subcert-qty" inputmode="decimal" required aria-label="@lang('construction::lang.current_quantity'): {{ $line->description }}"></div>
                                            @error('items.' . $line->id)<small class="rr-field__error">{{ $message }}</small>@enderror
                                        @else
                                            <strong class="rr-num">{{ @num_format($line->current_quantity) }}</strong>
                                        @endif
                                    </td>
                                    <td class="rr-td rr-td--num"><span class="rr-num ct-subcert-cumulative">{{ @num_format($line->cumulativeQuantity()) }}</span></td>
                                    <td class="rr-td rr-td--num"><strong class="rr-money rr-money--sm ct-subcert-line-value" data-value="{{ (float) $line->current_value }}">@include('construction::partials.money', ['value' => $line->current_value])</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @if($canEditCertificate)
                <section class="rr-card" aria-labelledby="rr-subcert-notes-title">
                    <div class="rr-card__body rr-field">
                        <label id="rr-subcert-notes-title" class="rr-field__label" for="ct-subcert-notes">@lang('construction::lang.notes')</label>
                        <div class="rr-control rr-control--area"><textarea id="ct-subcert-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3">{{ old('notes', $certificate->notes) }}</textarea></div>
                    </div>
                </section>
            @elseif($certificate->notes)
                <div class="rr-alert rr-alert--info"><div><strong>@lang('construction::lang.notes'):</strong> {{ $certificate->notes }}</div></div>
            @endif
        </div>

        <aside class="rr-cols__side">
            <section class="rr-card" aria-labelledby="rr-subcert-totals-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-subcert-totals-title" class="rr-card__title">@lang('construction::lang.deductions_and_adjustments')</h2>
                    </div>
                </header>
                <dl class="rr-deflist ct-subcert-totals" data-retention="{{ (float) $certificate->retention_percent }}">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.gross_certificate')</dt><dd><span class="rr-money rr-money--sm ct-subcert-gross">@include('construction::partials.money', ['value' => $certificate->gross_value])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.input_vat')</dt><dd><span class="rr-money rr-money--sm ct-subcert-tax">@include('construction::partials.money', ['value' => $certificate->tax_value])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.retention_value') <span class="rr-num">{{ @num_format($certificate->retention_percent) }}%</span></dt><dd><span class="rr-money rr-money--sm ct-subcert-retention">@include('construction::partials.money', ['value' => $certificate->retention_value])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.other_deductions')</dt><dd><span class="rr-money rr-money--sm ct-subcert-other">@include('construction::partials.money', ['value' => $certificate->other_deductions])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.advance_recovery')</dt><dd><span class="rr-money rr-money--sm ct-subcert-advance-value">@include('construction::partials.money', ['value' => $certificate->advance_recovery_value])</span></dd></div>
                    <div class="rr-deflist__item rr-deflist__item--total"><dt>@lang('construction::lang.net_certificate_due')</dt><dd><span class="rr-money ct-subcert-net">@include('construction::partials.money', ['value' => $certificate->net_value])</span></dd></div>
                </dl>
            </section>

            @if($isApproved)
                @can('construction.subcontract.manage')
                    <section class="rr-card" aria-labelledby="rr-subcert-retention-date-title">
                        <header class="rr-card__head">
                            <div class="rr-card__titles">
                                <h2 id="rr-subcert-retention-date-title" class="rr-card__title">@lang('construction::lang.retention_due_date')</h2>
                                <p class="rr-card__desc">@lang('construction::lang.retention_due_date_subcontractor_hint')</p>
                            </div>
                        </header>
                        <form method="POST" action="{{ route('construction.subcontractors.certificates.retention-due-date.update', [$contract->id, $certificate->id]) }}" class="rr-card__body rr-form">
                            @csrf
                            @method('PATCH')
                            <div class="rr-field">
                                <label class="rr-field__label rr-sr" for="ct-subcert-retention-date">@lang('construction::lang.retention_due_date')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-subcert-retention-date" name="retention_due_date" value="{{ $certificate->retention_due_date ? @format_date($certificate->retention_due_date) : '' }}" class="rr-control__input ct-date-picker" readonly></div>
                            </div>
                            <button class="rr-btn rr-btn--secondary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_retention_due_date')</button>
                        </form>
                    </section>
                @endcan
            @endif
        </aside>
    </div>

    @if($canEditCertificate)
        <div class="rr-form-actions">
            <button type="submit" class="rr-btn rr-btn--primary">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_certificate')</button>
        </div>
        </form>
    @endif

    @if($isApproved)
        {{-- ═══ الإفراج عن المحتجز ═══ --}}
        <section class="rr-table-wrap" aria-labelledby="rr-subcert-releases-title">
            <div class="rr-table-head">
                <div>
                    <h2 id="rr-subcert-releases-title" class="rr-table-head__title">@lang('construction::lang.retention_releases')</h2>
                    <p class="rr-table-head__sub">@lang('construction::lang.retention_release_hint')</p>
                </div>
                @if($retentionRemainingValue > 0.0001)
                    @can('construction.payment.record')
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-toggle="modal" data-target="#record-retention-release">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 16])@lang('construction::lang.release_retention')</button>
                    @endcan
                @endif
            </div>
            <div class="rr-statgroup-wrap">
                <div class="rr-statgroup rr-statgroup--3 rr-statgroup--flat" style="--rr-stats: 3">
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.original_retention')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $certificate->retention_value])</span></div>
                    </div>
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.released_retention')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $releasedRetentionValue == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $releasedRetentionValue])</span></div>
                    </div>
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.remaining_retention')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $retentionRemainingValue == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $retentionRemainingValue])</span></div>
                    </div>
                </div>
            </div>
            @if($certificate->retentionReleases->isEmpty())
                <div class="rr-empty">
                    <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 22])</span>
                    <h3 class="rr-empty__title">@lang('construction::lang.no_retention_releases')</h3>
                </div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.retention_releases')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th" scope="col">@lang('construction::lang.release_number')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.release_date')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.release_amount')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                                <th class="rr-th rr-col--notes" scope="col">@lang('construction::lang.notes')</th>
                                <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($certificate->retentionReleases as $release)
                                <tr class="rr-tr">
                                    <td class="rr-td"><span class="rr-code">{{ $release->number }}</span></td>
                                    <td class="rr-td"><span class="rr-num">{{ @format_date($release->release_date) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $release->status === 'cancelled' ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $release->amount])</span></td>
                                    <td class="rr-td"><span class="rr-badge rr-badge--{{ $release->status === 'recorded' ? 'success' : 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.retention_release_status_' . $release->status)</span></td>
                                    <td class="rr-td rr-td--wrap rr-td--muted">{{ $release->status === 'cancelled' ? $release->cancellation_reason : ($release->notes ?: '—') }}</td>
                                    <td class="rr-td rr-td--actions">
                                        <div class="rr-row-actions">
                                            @can('construction.accounting.view')
                                                @if($release->accountingPosting?->mapping)
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ route('journal-entry.show', $release->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_release_accounting_entry')" aria-label="@lang('construction::lang.view_release_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 16])</button>
                                                @endif
                                                @if($release->cancellationAccountingPosting?->mapping)
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ route('journal-entry.show', $release->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_reversal_accounting_entry')" aria-label="@lang('construction::lang.view_reversal_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 16])</button>
                                                @endif
                                            @endcan
                                            @if($release->isActive())
                                                @can('construction.cancel')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm js-cancel-retention-release" data-toggle="modal" data-target="#cancel-retention-release" data-action="{{ route('construction.subcontractors.certificates.retention-releases.cancel', [$contract->id, $certificate->id, $release->id]) }}">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 16])@lang('construction::lang.cancel_release')</button>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ═══ المدفوعات ═══ --}}
        <section class="rr-table-wrap" aria-labelledby="rr-subcert-payments-title">
            <div class="rr-table-head">
                <div>
                    <h2 id="rr-subcert-payments-title" class="rr-table-head__title">@lang('construction::lang.subcontract_payments')</h2>
                    <p class="rr-table-head__sub">@lang('construction::lang.payment_stage_accounting_notice')</p>
                </div>
                @if($remainingValue > 0.0001)
                    @can('construction.payment.record')
                        <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm" data-toggle="modal" data-target="#record-subcontract-payment">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 16])@lang('construction::lang.record_payment')</button>
                    @endcan
                @endif
            </div>
            @if($certificate->payments->isEmpty())
                <div class="rr-empty">
                    <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 22])</span>
                    <h3 class="rr-empty__title">@lang('construction::lang.no_subcontract_payments')</h3>
                </div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.subcontract_payments')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_number')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_date')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.payment_amount')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_method')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_account')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_reference')</th>
                                <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($certificate->payments as $payment)
                                <tr class="rr-tr">
                                    <td class="rr-td">
                                        <span class="rr-code">{{ $payment->number }}</span>
                                        @if($payment->status !== 'recorded')<div><span class="rr-badge"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.cancelled')</span></div>@endif
                                    </td>
                                    <td class="rr-td"><span class="rr-num">{{ @format_date($payment->payment_date) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $payment->status !== 'recorded' ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $payment->amount])</span></td>
                                    <td class="rr-td">{{ $paymentTypes[$payment->method] ?? $payment->method }}</td>
                                    <td class="rr-td rr-td--wrap">{{ $payment->accountingAccount?->name ?: ($payment->account?->name ?: '—') }}</td>
                                    <td class="rr-td rr-td--muted">{{ $payment->reference_no ?: '—' }}</td>
                                    <td class="rr-td rr-td--actions">
                                        <div class="rr-row-actions">
                                            <a href="{{ route('construction.subcontractors.certificates.payments.preview', [$contract->id, $certificate->id, $payment->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" title="@lang('construction::lang.print_preview')" aria-label="@lang('construction::lang.print_preview'): {{ $payment->number }}">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 16])</a>
                                            <a href="{{ route('construction.subcontractors.certificates.payments.print', [$contract->id, $certificate->id, $payment->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" title="@lang('construction::lang.print')" aria-label="@lang('construction::lang.print'): {{ $payment->number }}">@include('construction::partials.rr_icon', ['name' => 'print', 'size' => 16])</a>
                                            @if($payment->accountingPosting?->mapping)
                                                @can('construction.accounting.view')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ route('journal-entry.show', $payment->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_accounting_entry')" aria-label="@lang('construction::lang.view_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 16])</button>
                                                @endcan
                                            @endif
                                            @if($payment->status === 'recorded')
                                                @can('construction.cancel')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm js-cancel-subcontract-payment" data-toggle="modal" data-target="#cancel-subcontract-payment" data-action="{{ route('construction.subcontractors.certificates.payments.cancel', [$contract->id, $certificate->id, $payment->id]) }}">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 16])@lang('construction::lang.cancel_posted_payment')</button>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</main>

{{-- ═══ النوافذ ═══ --}}
@if($isApproved)
    @can('construction.payment.record')
        <div class="modal fade rr-scope" id="record-retention-release" tabindex="-1" role="dialog" aria-labelledby="rr-record-release-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.subcontractors.certificates.retention-releases.store', [$contract->id, $certificate->id]) }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-record-release-title">@lang('construction::lang.release_retention')</h2>
                                <p class="rr-modal__desc">@lang('construction::lang.remaining_retention'): <span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $retentionRemainingValue])</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-release-date">@lang('construction::lang.release_date'){!! $req !!}</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-release-date" name="release_date" value="{{ old('release_date', @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-release-amount">@lang('construction::lang.release_amount'){!! $req !!}</label>
                                        <div class="rr-control @error('retention_amount') rr-control--invalid @enderror"><input id="ct-release-amount" name="retention_amount" value="{{ old('retention_amount', @num_format($retentionRemainingValue)) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                                        @error('retention_amount')<p class="rr-field__error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="ct-release-notes">@lang('construction::lang.notes')</label>
                                        <div class="rr-control rr-control--area"><textarea id="ct-release-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3">{{ old('notes') }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                            <p class="rr-modal__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.retention_release_no_cost_notice')</p>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.confirm_release')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade rr-scope" id="record-subcontract-payment" tabindex="-1" role="dialog" aria-labelledby="rr-record-payment-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.subcontractors.certificates.payments.store', [$contract->id, $certificate->id]) }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-record-payment-title">@lang('construction::lang.new_payment')</h2>
                                <p class="rr-modal__desc">@lang('construction::lang.remaining_amount'): <span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $remainingValue])</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-payment-date">@lang('construction::lang.payment_date'){!! $req !!}</label>
                                        <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="ct-payment-date" name="payment_date" value="{{ old('payment_date', @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-payment-amount">@lang('construction::lang.payment_amount'){!! $req !!}</label>
                                        <div class="rr-control @error('amount') rr-control--invalid @enderror"><input id="ct-payment-amount" name="amount" value="{{ old('amount', @num_format($remainingValue)) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                                        @error('amount')<p class="rr-field__error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-payment-method">@lang('construction::lang.payment_method'){!! $req !!}</label>
                                        <div class="rr-control">
                                            <select id="ct-payment-method" name="method" class="rr-control__input rr-control__select" required>
                                                @foreach($paymentTypes as $key => $label)<option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="ct-payment-account">@lang('construction::lang.payment_account')@if($accountingSettings){!! $req !!}@endif</label>
                                        @if($accountingSettings)
                                            <select id="ct-payment-account" name="accounting_account_id" class="form-control select2" required>
                                                <option value="">@lang('construction::lang.select_cash_bank_account')</option>
                                                @foreach($accountingPaymentAccounts as $id => $name)<option value="{{ $id }}" @selected((string) old('accounting_account_id', $accountingSettings->cash_account_id) === (string) $id)>{{ $name }}</option>@endforeach
                                            </select>
                                            @error('accounting_account_id')<p class="rr-field__error">{{ $message }}</p>@enderror
                                        @else
                                            <select id="ct-payment-account" name="account_id" class="form-control select2">
                                                <option value="">@lang('lang_v1.none')</option>
                                                @foreach($paymentAccounts as $id => $name)@if($id !== '')<option value="{{ $id }}" @selected((string) old('account_id') === (string) $id)>{{ $name }}</option>@endif @endforeach
                                            </select>
                                        @endif
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="ct-payment-reference">@lang('construction::lang.payment_reference')</label>
                                        <div class="rr-control"><input id="ct-payment-reference" name="reference_no" value="{{ old('reference_no') }}" class="rr-control__input" maxlength="120"></div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="ct-payment-notes">@lang('construction::lang.notes')</label>
                                        <div class="rr-control rr-control--area"><textarea id="ct-payment-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3">{{ old('notes') }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                            <p class="rr-modal__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.payment_stage_accounting_notice')</p>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.record_payment')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endif

@can('construction.cancel')
    @foreach([
        ['cancel-retention-release', 'cancel_release', '', false, 'retention_release_cancel_notice'],
        ['cancel-subcontract-payment', 'cancel_posted_payment', '', true, 'cancellation_creates_reversal_notice'],
        ['cancel-subcontract-certificate', 'cancel_posted_certificate', route('construction.subcontractors.certificates.cancel', [$contract->id, $certificate->id]), true, 'cancellation_creates_reversal_notice'],
    ] as [$modalId, $modalTitle, $modalAction, $needsPassword, $modalNotice])
        <div class="modal fade rr-scope" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ $modalAction }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="{{ $modalId }}-title">@lang('construction::lang.' . $modalTitle)</h2>
                                <p class="rr-modal__desc"><span class="rr-code">{{ $certificate->number }}</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-alert rr-alert--warning"><div>@lang('construction::lang.' . $modalNotice)</div></div>
                            <div class="rr-form-wrap">
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="{{ $modalId }}-reason">@lang('construction::lang.cancellation_reason'){!! $req !!}</label>
                                    <div class="rr-control rr-control--area"><textarea id="{{ $modalId }}-reason" name="cancellation_reason" class="rr-control__input rr-control__input--short" rows="3" required @if($needsPassword) minlength="5" @endif></textarea></div>
                                </div>
                                @if($needsPassword)
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="{{ $modalId }}-password">@lang('construction::lang.manager_password'){!! $req !!}</label>
                                        <div class="rr-control"><input type="password" id="{{ $modalId }}-password" name="current_password" class="rr-control__input" required autocomplete="current-password"></div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--danger" type="submit">@lang('construction::lang.confirm_cancellation')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endcan
@endsection

@push('ct_quick_js')
<script>
$(function () {
    $('.js-cancel-subcontract-payment').on('click', function () {
        $('#cancel-subcontract-payment form').attr('action', $(this).data('action'));
    });
    $('.js-cancel-retention-release').on('click', function () {
        $('#cancel-retention-release form').attr('action', $(this).data('action'));
    });
    function numberValue($field) {
        var value = String($field.val() || '0').replace(/,/g, '');
        return isNaN(parseFloat(value)) ? 0 : parseFloat(value);
    }
    function money(value) {
        return value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function recalculateCertificate() {
        var gross = 0;
        $('.ct-subcert-table tbody tr').each(function () {
            var $row = $(this), $input = $row.find('.ct-subcert-qty');
            if (!$input.length) return;
            var current = numberValue($input);
            var previous = parseFloat($row.data('previous')) || 0, contract = parseFloat($row.data('contract')) || 0;
            var rate = parseFloat($row.data('rate')) || 0;
            var value = $row.data('type') === 'percentage' ? rate * current / 100 : rate * current;
            var overLimit = previous + current > contract + 0.0001;
            $row.find('.ct-subcert-cumulative').text(money(previous + current));
            $row.find('.ct-subcert-line-value').text(money(value));
            $row.toggleClass('has-limit-error', overLimit);
            $input.closest('.rr-control').toggleClass('rr-control--invalid', overLimit);
            gross += value;
        });
        if (!$('.ct-subcert-qty').length) return;
        var retention = gross * (parseFloat($('.ct-subcert-totals').data('retention')) || 0) / 100;
        var other = numberValue($('.ct-subcert-deduction'));
        var tax = gross * numberValue($('.ct-subcert-tax-percent')) / 100;
        var advance = numberValue($('.ct-subcert-advance'));
        $('.ct-subcert-gross').text(money(gross));
        $('.ct-subcert-retention').text(money(retention));
        $('.ct-subcert-other').text(money(other));
        $('.ct-subcert-tax').text(money(tax));
        $('.ct-subcert-advance-value').text(money(advance));
        $('.ct-subcert-net').text(money(Math.max(0, gross + tax - retention - other - advance)));
    }
    $(document).on('input change', '.ct-subcert-qty, .ct-subcert-deduction, .ct-subcert-tax-percent, .ct-subcert-advance', recalculateCertificate);
    $('#subcontract-certificate-form').on('submit', function (event) {
        recalculateCertificate();
        if ($('.ct-subcert-table tbody tr.has-limit-error').length) {
            event.preventDefault();
            toastr.error(@json(__('construction::lang.subcontract_certificate_fix_quantity_errors')));
            $('.ct-subcert-table tbody tr.has-limit-error').first().find('.ct-subcert-qty').focus();
        }
    });
    recalculateCertificate();
});
</script>
@endpush

@if($errors->hasAny(['amount', 'payment_date', 'method', 'account_id', 'reference_no']))
    @push('ct_quick_js')<script>$(function(){$('#record-subcontract-payment').modal('show')})</script>@endpush
@endif
@if($errors->hasAny(['retention_amount', 'release_date']))
    @push('ct_quick_js')<script>$(function(){$('#record-retention-release').modal('show')})</script>@endpush
@endif
