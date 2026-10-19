@extends('construction::layouts.module')
@section('title', $certificate->number)

@php
    $approved = in_array($certificate->status, ['approved', 'posted', 'paid', 'partially_approved']);
    $isCancelled = $certificate->status === 'cancelled';
    $current = (float) ($approved ? $certificate->current_approved_gross : $certificate->current_submitted_gross);
    $cumulative = (float) $certificate->previous_gross + $current;
    $contractValue = (float) ($certificate->contract?->original_value ?: $certificate->boqVersion?->sales_total);
    $progress = $contractValue > 0 ? 100 * $cumulative / $contractValue : 0;
    $advance = (float) ($certificate->contract?->advance_payment_value ?? 0);
    $totalDeductions = (float) $certificate->retention_value + (float) $certificate->advance_recovery_value + (float) $certificate->other_deductions_value;
    $hasInvoice = $certificate->invoice && $certificate->invoice->status === 'final';
    $certificateTones = ['draft' => 'warning', 'submitted' => 'info', 'approved' => 'success', 'partially_approved' => 'success', 'posted' => 'brand', 'paid' => 'success', 'cancelled' => 'neutral'];
    $releaseTones = ['recorded' => 'success', 'cancelled' => 'neutral'];
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-certificate-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--success" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.ipcs.index') }}">@lang('construction::lang.customer_certificates')</a></li>
                    <li><a href="{{ route('construction.projects.certificates.index', $project->id) }}" class="rr-num">{{ $project->code }}</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $certificate->number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-certificate-title" class="rr-head__title">@lang('construction::lang.certificate_number'): <span class="rr-num">{{ $certificate->number }}</span></h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $certificateTones[$certificate->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $certificate->status)</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $project->name }}</span>
                @if($certificate->contract)
                    <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'file', 'size' => 18])<span class="rr-code">{{ $certificate->contract->contract_number }}</span></span>
                @endif
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])<span class="rr-num">{{ @format_date($certificate->certificate_date) }}</span></span>
                @if($certificate->period_from || $certificate->period_to)
                    <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])<span class="rr-num">{{ $certificate->period_from ? @format_date($certificate->period_from) : '—' }}</span> ← <span class="rr-num">{{ $certificate->period_to ? @format_date($certificate->period_to) : '—' }}</span></span>
                @endif
            </div>
        </div>
        <div class="rr-head__actions">
            <a href="{{ route('construction.projects.certificates.index', $project->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.back_to_certificates')</a>
            <a href="{{ route('construction.projects.certificates.preview', [$project->id, $certificate->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.preview')</a>
            @if($approved)
                <a href="{{ route('construction.projects.certificates.print', [$project->id, $certificate->id]) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
                <a href="{{ route('construction.projects.certificates.pdf', [$project->id, $certificate->id]) }}" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'download'])PDF</a>
            @endif
        </div>
    </header>

    @if($errors->any())
        <div class="rr-alert rr-alert--danger" role="alert">
            <ul class="rr-error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ═══ الحالة + الخطوة التالية ═══ --}}
    <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-certificate-next-title">
        <div class="rr-next">
            <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => $approved || $isCancelled ? 'lock' : 'info', 'size' => 22])</span>
            <div class="rr-next__copy">
                <div class="rr-eyebrow">@lang('construction::lang.certificate_next_step')</div>
                @if(in_array($certificate->status, ['draft', 'submitted']))
                    <h2 id="rr-certificate-next-title" class="rr-next__title">@lang('construction::lang.approve_certificate')</h2>
                    <p class="rr-next__text">@lang('construction::lang.certificate_review_hint')</p>
                @elseif($approved)
                    <h2 id="rr-certificate-next-title" class="rr-next__title">{{ $hasInvoice ? __('construction::lang.invoice_created_with_number', ['number' => $certificate->invoice->invoice_no]) : __('construction::lang.create_invoice_from_certificate') }}</h2>
                    <p class="rr-next__text">@lang('construction::lang.approved_certificate_locked')@if($certificate->approved_at) · <span class="rr-num">{{ @format_datetime($certificate->approved_at) }}</span>@endif @if($certificate->approvedBy) · {{ $certificate->approvedBy->user_full_name }}@endif</p>
                @else
                    <h2 id="rr-certificate-next-title" class="rr-next__title">@lang('construction::lang.cancelled')</h2>
                @endif
            </div>
            @if(in_array($certificate->status, ['draft', 'submitted']))
                @can('construction.certificate.approve')
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.certificates.approve', [$project->id, $certificate->id]) }}">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.approve_certificate')</button>
                    </form>
                @endcan
            @elseif($approved)
                @if($hasInvoice)
                    <a class="view_invoice_url rr-btn rr-btn--primary rr-btn--lg" href="{{ url('/sells/'.$certificate->invoice->id).'?construction=1' }}">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.open_invoice')</a>
                @elseif(auth()->user()->can('construction.certificate.approve') && (auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')))
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.projects.certificates.invoice', [$project->id, $certificate->id]) }}" id="ct-create-certificate-invoice">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit">@include('construction::partials.rr_icon', ['name' => 'file'])@lang('construction::lang.create_invoice_from_certificate')</button>
                    </form>
                @endif
            @endif
            @if(!$isCancelled)
                @can('construction.cancel')
                    <button type="button" class="rr-btn rr-btn--ghost" data-toggle="modal" data-target="#cancel-customer-certificate">@include('construction::partials.rr_icon', ['name' => 'close'])@lang('construction::lang.cancel_certificate')</button>
                @endcan
            @endif
        </div>
    </section>

    {{-- ═══ القيد المحاسبي ═══ --}}
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
    @if($isCancelled && $certificate->cancellationAccountingPosting?->mapping)
        <div class="rr-callout rr-callout--danger">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 22])</span>
            <div class="rr-callout__copy">
                <strong>@lang('construction::lang.accounting_entry_reversed')</strong>
                <p>@lang('construction::lang.credit_note_created', ['number' => $certificate->creditNote?->invoice_no ?: '—'])</p>
            </div>
            @can('construction.accounting.view')
                <div class="rr-callout__actions">
                    <button type="button" class="rr-btn rr-btn--secondary btn-modal" data-href="{{ route('journal-entry.show', $certificate->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.view_reversal_accounting_entry')</button>
                </div>
            @endcan
        </div>
    @endif

    {{-- ═══ أرقام المستخلص ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--6" style="--rr-stats: 6">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])@lang('construction::lang.previous_gross')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $certificate->previous_gross == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $certificate->previous_gross])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'ruler', 'size' => 18])@lang('construction::lang.current_work_value')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $current])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'bars', 'size' => 18])@lang('construction::lang.cumulative_gross')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $cumulative])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.completion_percent')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ @num_format($progress) }}%</span></div>
                <div class="rr-progress" role="presentation"><div class="rr-progress__fill rr-progress__fill--success" style="width: {{ min(100, max(0, $progress)) }}%"></div></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 18])@lang('construction::lang.total_deductions')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $totalDeductions == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $totalDeductions])</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.net_due')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg rr-money--pos">@include('construction::partials.money', ['value' => $certificate->net_due])</span></div>
            </div>
        </div>
    </div>

    <div class="rr-cols">
        <div class="rr-cols__main">
            {{-- بنود المستخلص --}}
            <section class="rr-table-wrap" aria-labelledby="rr-certificate-items-title">
                <div class="rr-table-head">
                    <div>
                        <h2 id="rr-certificate-items-title" class="rr-table-head__title">@lang('construction::lang.certificate_items')</h2>
                        <p class="rr-table-head__sub">@lang('construction::lang.certificate_items_auto_hint')</p>
                    </div>
                    <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $certificate->items->count() }}</span></span>
                </div>
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.certificate_items')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th" scope="col">@lang('construction::lang.boq_code')</th>
                                <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.boq_description')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.unit')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.contract_quantity')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.previous_quantity')</th>
                                <th class="rr-th rr-th--num rr-th--focus" scope="col">@lang('construction::lang.current_quantity')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.cumulative_quantity')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.sales_unit_price')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.current_work_value')</th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($certificate->items as $item)
                                @php
                                    $quantity = (float) ($approved ? $item->approved_quantity : $item->submitted_quantity);
                                    $amount = (float) ($approved ? $item->approved_amount : $item->submitted_amount);
                                @endphp
                                <tr class="rr-tr">
                                    <td class="rr-td"><span class="rr-code">{{ $item->boqItem->code }}</span></td>
                                    <td class="rr-td rr-td--wrap"><span class="rr-entity__name">{{ $item->boqItem->description }}</span></td>
                                    <td class="rr-td rr-td--muted">{{ $item->boqItem->unit ?: '—' }}</td>
                                    <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity((float) $item->boqItem->contract_quantity) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity((float) $item->previous_quantity) }}</span></td>
                                    <td class="rr-td rr-td--num rr-td--focus"><strong class="rr-num">{{ @format_quantity($quantity) }}</strong></td>
                                    <td class="rr-td rr-td--num"><span class="rr-num">{{ @format_quantity((float) $item->previous_quantity + $quantity) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $item->unit_price])</span></td>
                                    <td class="rr-td rr-td--num"><strong class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $amount])</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="rr-tfoot">
                            <tr>
                                <td class="rr-td" colspan="8">@lang('construction::lang.total')</td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $current])</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <aside class="rr-cols__side">
            {{-- الاستقطاعات وصافي المستحق --}}
            <section class="rr-card" aria-labelledby="rr-certificate-deductions-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-certificate-deductions-title" class="rr-card__title">@lang('construction::lang.deductions_and_adjustments')</h2>
                    </div>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.current_work_value')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $current])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.retention_value') <span class="rr-num">{{ @num_format((float) $certificate->retention_percent) }}%</span></dt><dd><span class="rr-money rr-money--sm">@if((float) $certificate->retention_value > 0)− @endif @include('construction::partials.money', ['value' => $certificate->retention_value])</span></dd></div>
                    @if($advance > 0)
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.original_advance')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $advance])</span></dd></div>
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.previously_recovered')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => (float) $recoveredBefore])</span></dd></div>
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.advance_recovery_value')</dt><dd><span class="rr-money rr-money--sm">@if((float) $certificate->advance_recovery_value > 0)− @endif @include('construction::partials.money', ['value' => $certificate->advance_recovery_value])</span></dd></div>
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.advance_remaining')</dt><dd><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => max(0, $advance - (float) $recoveredBefore - (float) $certificate->advance_recovery_value)])</span></dd></div>
                    @endif
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.other_deductions_value')</dt><dd><span class="rr-money rr-money--sm">@if((float) $certificate->other_deductions_value > 0)− @endif @include('construction::partials.money', ['value' => $certificate->other_deductions_value])</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.tax_value') <span class="rr-num">{{ @num_format((float) $certificate->tax_percent) }}%</span></dt><dd><span class="rr-money rr-money--sm">@if((float) $certificate->tax_value > 0)+ @endif @include('construction::partials.money', ['value' => $certificate->tax_value])</span></dd></div>
                    <div class="rr-deflist__item rr-deflist__item--total"><dt>@lang('construction::lang.net_due')</dt><dd><span class="rr-money rr-money--pos">@include('construction::partials.money', ['value' => $certificate->net_due])</span></dd></div>
                </dl>
            </section>

            {{-- بيانات المستخلص --}}
            <section class="rr-card" aria-labelledby="rr-certificate-info-title">
                <header class="rr-card__head">
                    <div class="rr-card__titles">
                        <h2 id="rr-certificate-info-title" class="rr-card__title">@lang('construction::lang.certificate_information')</h2>
                    </div>
                </header>
                <dl class="rr-deflist">
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.contract_number')</dt><dd>@if($certificate->contract)<a class="rr-code-link" href="{{ route('construction.projects.contracts.show', [$project->id, $certificate->contract->id]) }}"><span class="rr-code">{{ $certificate->contract->contract_number }}</span></a>@else<span class="rr-td--muted">—</span>@endif</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.measurement_number')</dt><dd>@if($certificate->measurement)<a class="rr-code-link" href="{{ route('construction.projects.measurements.show', [$project->id, $certificate->measurement->id]) }}"><span class="rr-code">{{ $certificate->measurement->number }}</span></a>@else<span class="rr-td--muted">—</span>@endif</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.certificate_date')</dt><dd><span class="rr-num">{{ @format_date($certificate->certificate_date) }}</span></dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.period_from')</dt><dd>@if($certificate->period_from)<span class="rr-num">{{ @format_date($certificate->period_from) }}</span>@else<span class="rr-td--muted">—</span>@endif</dd></div>
                    <div class="rr-deflist__item"><dt>@lang('construction::lang.period_to')</dt><dd>@if($certificate->period_to)<span class="rr-num">{{ @format_date($certificate->period_to) }}</span>@else<span class="rr-td--muted">—</span>@endif</dd></div>
                    @if($hasInvoice)
                        <div class="rr-deflist__item"><dt>@lang('construction::lang.open_invoice')</dt><dd><a class="rr-code-link" href="{{ url('/sells/'.$certificate->invoice->id).'?construction=1' }}"><span class="rr-code">{{ $certificate->invoice->invoice_no }}</span></a></dd></div>
                    @endif
                </dl>
            </section>

            {{-- موعد الإفراج عن المحتجز --}}
            @if(!$isCancelled && (float) $certificate->retention_value > 0)
                @can('construction.certificate.manage')
                    <section class="rr-card" aria-labelledby="rr-certificate-retention-date-title">
                        <header class="rr-card__head">
                            <div class="rr-card__titles">
                                <h2 id="rr-certificate-retention-date-title" class="rr-card__title">@lang('construction::lang.retention_due_date')</h2>
                                <p class="rr-card__desc">@lang('construction::lang.retention_due_date_customer_hint')</p>
                            </div>
                        </header>
                        <form method="POST" action="{{ route('construction.projects.certificates.retention-due-date.update', [$project->id, $certificate->id]) }}" class="rr-card__body rr-form">
                            @csrf
                            @method('PATCH')
                            <div class="rr-field">
                                <label class="rr-field__label rr-sr" for="ct-certificate-retention-date">@lang('construction::lang.retention_due_date')</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="retention_due_date" id="ct-certificate-retention-date" value="{{ $certificate->retention_due_date ? @format_date($certificate->retention_due_date) : '' }}" class="rr-control__input ct-date-picker" readonly></div>
                            </div>
                            <button class="rr-btn rr-btn--secondary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_retention_due_date')</button>
                        </form>
                    </section>
                @endcan
            @endif
        </aside>
    </div>

    {{-- ═══ الإفراج عن محتجز المالك ═══ --}}
    @if($certificate->invoice_transaction_id && (float) $certificate->retention_value > 0)
        @php
            $releasedCustomerRetention = (float) $certificate->retentionReleases->where('status', 'recorded')->sum('amount');
            $remainingCustomerRetention = max(0, (float) $certificate->retention_value - $releasedCustomerRetention);
        @endphp
        <section class="rr-table-wrap" aria-labelledby="rr-certificate-releases-title">
            <div class="rr-table-head">
                <div>
                    <h2 id="rr-certificate-releases-title" class="rr-table-head__title">@lang('construction::lang.customer_retention_releases')</h2>
                    <p class="rr-table-head__sub">@lang('construction::lang.customer_retention_release_hint')</p>
                </div>
            </div>
            <div class="rr-statgroup-wrap">
                <div class="rr-statgroup rr-statgroup--3 rr-statgroup--flat" style="--rr-stats: 3">
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.original_retention')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg">@include('construction::partials.money', ['value' => $certificate->retention_value])</span></div>
                    </div>
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.released_retention')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $releasedCustomerRetention == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $releasedCustomerRetention])</span></div>
                    </div>
                    <div class="rr-stat">
                        <div class="rr-stat__label">@lang('construction::lang.remaining_retention')</div>
                        <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $remainingCustomerRetention == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $remainingCustomerRetention])</span></div>
                    </div>
                </div>
            </div>
            @if($remainingCustomerRetention > 0.0001)
                @can('construction.payment.record')
                    <form method="POST" action="{{ route('construction.projects.certificates.retention-releases.store', [$project->id, $certificate->id]) }}" class="rr-filter rr-filter--flat">
                        @csrf
                        <div class="rr-filter__fields">
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-release-date">@lang('construction::lang.release_date'){!! $req !!}</label>
                                <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" name="release_date" id="ct-release-date" value="{{ old('release_date', @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="ct-release-amount">@lang('construction::lang.release_amount'){!! $req !!}</label>
                                <div class="rr-control"><input name="retention_amount" id="ct-release-amount" value="{{ old('retention_amount', @num_format($remainingCustomerRetention)) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                            </div>
                            <div class="rr-field rr-filter__grow">
                                <label class="rr-field__label" for="ct-release-notes">@lang('construction::lang.notes')</label>
                                <div class="rr-control"><input name="notes" id="ct-release-notes" value="{{ old('notes') }}" class="rr-control__input" maxlength="1000"></div>
                            </div>
                        </div>
                        <div class="rr-filter__actions">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'lock'])@lang('construction::lang.release_retention')</button>
                        </div>
                    </form>
                @endcan
            @endif
            @if($certificate->retentionReleases->isEmpty())
                <div class="rr-empty">
                    <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 22])</span>
                    <h3 class="rr-empty__title">@lang('construction::lang.no_customer_retention_releases')</h3>
                </div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.customer_retention_releases')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th" scope="col">@lang('construction::lang.release_number')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.release_date')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.release_amount')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.status')</th>
                                <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($certificate->retentionReleases as $release)
                                <tr class="rr-tr">
                                    <td class="rr-td"><span class="rr-code">{{ $release->number }}</span></td>
                                    <td class="rr-td"><span class="rr-num">{{ @format_date($release->release_date) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $release->amount])</span></td>
                                    <td class="rr-td"><span class="rr-badge rr-badge--{{ $releaseTones[$release->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.retention_release_status_' . $release->status)</span></td>
                                    <td class="rr-td rr-td--actions">
                                        <div class="rr-row-actions">
                                            @can('construction.accounting.view')
                                                @if($release->accountingPosting?->mapping)
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm btn-modal" data-href="{{ route('journal-entry.show', $release->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_release_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'eye', 'size' => 16])@lang('construction::lang.view_release_accounting_entry')</button>
                                                @endif
                                                @if($release->cancellationAccountingPosting?->mapping)
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm btn-modal" data-href="{{ route('journal-entry.show', $release->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_reversal_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 16])@lang('construction::lang.view_reversal_accounting_entry')</button>
                                                @endif
                                            @endcan
                                            @if($release->isActive())
                                                @can('construction.cancel')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm js-cancel-customer-retention" data-toggle="modal" data-target="#cancel-customer-retention" data-action="{{ route('construction.projects.certificates.retention-releases.cancel', [$project->id, $certificate->id, $release->id]) }}">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 16])@lang('construction::lang.cancel_release')</button>
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

@can('construction.cancel')
    {{-- إلغاء المستخلص --}}
    @if(!$isCancelled)
        <div class="modal fade rr-scope" id="cancel-customer-certificate" tabindex="-1" role="dialog" aria-labelledby="rr-cancel-certificate-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.projects.certificates.cancel', [$project->id, $certificate->id]) }}">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-cancel-certificate-title">@lang('construction::lang.cancel_customer_certificate')</h2>
                                <p class="rr-modal__desc"><span class="rr-code">{{ $certificate->number }}</span></p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-alert rr-alert--warning"><div>@lang('construction::lang.cancel_customer_certificate_hint')</div></div>
                            <div class="rr-form-wrap">
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="ct-cancel-certificate-reason">@lang('construction::lang.cancellation_reason'){!! $req !!}</label>
                                    <div class="rr-control rr-control--area"><textarea name="cancellation_reason" id="ct-cancel-certificate-reason" class="rr-control__input rr-control__input--short" rows="3" required minlength="5"></textarea></div>
                                </div>
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="ct-cancel-certificate-password">@lang('construction::lang.current_password'){!! $req !!}</label>
                                    <div class="rr-control"><input type="password" name="current_password" id="ct-cancel-certificate-password" class="rr-control__input" required autocomplete="current-password"></div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--danger" type="submit">@lang('construction::lang.confirm_cancel_and_reverse')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- إلغاء إفراج المحتجز --}}
    @if($certificate->retentionReleases->isNotEmpty())
        <div class="modal fade rr-scope" id="cancel-customer-retention" tabindex="-1" role="dialog" aria-labelledby="rr-cancel-release-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST">
                        @csrf
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="rr-cancel-release-title">@lang('construction::lang.cancel_release')</h2>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="ct-cancel-release-reason">@lang('construction::lang.cancellation_reason'){!! $req !!}</label>
                                    <div class="rr-control rr-control--area"><textarea name="cancellation_reason" id="ct-cancel-release-reason" class="rr-control__input rr-control__input--short" rows="3" required></textarea></div>
                                </div>
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
    @endif
@endcan
@endsection

@section('module_javascript')
<script>
$(function () {
    $('#ct-create-certificate-invoice').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).prepend('<i class="fa fa-spinner fa-spin"></i> ');
    });
    $('.js-cancel-customer-retention').on('click', function () {
        $('#cancel-customer-retention form').attr('action', $(this).data('action'));
    });
});
</script>
@endsection
