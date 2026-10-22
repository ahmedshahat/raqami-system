@extends('construction::layouts.module')

@section('title', $sheet->number)

@php
    $sheetTones = ['draft' => 'warning', 'approved' => 'success', 'cancelled' => 'neutral'];
    $isDraft = $sheet->isEditable();
    $isCancelled = $sheet->status === 'cancelled';
    $isPosted = in_array($sheet->status, ['approved', 'cancelled'], true);
    $sheetTotal = (float) $sheet->lines->sum('total_cost');
    $canManage = auth()->user()->can('construction.labor.manage');
    $canRecordPayment = $sheet->status === 'approved' && $remainingValue > 0.0001 && $laborAccountingReady && auth()->user()->can('construction.payment.record');
    $req = '<span class="rr-field__req" aria-hidden="true">*</span>';
    $calculationTypes = [
        'hour' => 'by_hour', 'day' => 'by_day', 'linear_meter' => 'by_linear_meter', 'square_meter' => 'by_square_meter',
        'cubic_meter' => 'by_cubic_meter', 'unit' => 'by_unit', 'lump_sum' => 'lump_sum',
    ];
    $paymentTones = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'neutral'];
@endphp

@section('module_content')
<main class="content rr-page" aria-labelledby="rr-labor-sheet-title">

    {{-- ═══ رأس الصفحة ═══ --}}
    <header class="rr-head">
        <span class="rr-chip rr-chip--lg rr-chip--warning" aria-hidden="true">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 24])</span>
        <div class="rr-head__titles">
            <nav aria-label="@lang('construction::lang.breadcrumb_nav')">
                <ol class="rr-crumbs">
                    <li><a href="{{ route('construction.labor.index') }}">@lang('construction::lang.labor_sheets')</a></li>
                    <li><a href="{{ route('construction.projects.show', $sheet->project_id) }}" class="rr-num">{{ $sheet->project->code }}</a></li>
                    <li><span aria-current="page" class="rr-num">{{ $sheet->number }}</span></li>
                </ol>
            </nav>
            <h1 id="rr-labor-sheet-title" class="rr-head__title">@lang('construction::lang.labor_sheet') <span class="rr-num">{{ $sheet->number }}</span></h1>
            <div class="rr-head__meta">
                <span class="rr-badge rr-badge--{{ $sheetTones[$sheet->status] ?? 'neutral' }}"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.' . $sheet->status)</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'building', 'size' => 18]){{ $sheet->project->name }}</span>
                <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])<span class="rr-num">{{ @format_date($sheet->period_start) }}</span> — <span class="rr-num">{{ @format_date($sheet->period_end) }}</span></span>
                @if($sheet->work_site)
                    <span class="rr-head__meta-item">@include('construction::partials.rr_icon', ['name' => 'map-pin', 'size' => 18]){{ $sheet->work_site }}</span>
                @endif
            </div>
        </div>
        <div class="rr-head__actions">
            @if($isDraft && $canManage)
                <a href="{{ route('construction.labor.edit', $sheet->id) }}" class="rr-btn rr-btn--secondary">@include('construction::partials.rr_icon', ['name' => 'edit'])@lang('construction::lang.edit_sheet')</a>
            @endif
            @if($sheet->accountingPosting?->mapping)
                @can('construction.accounting.view')
                    <button type="button" class="rr-btn rr-btn--secondary btn-modal" data-href="{{ route('journal-entry.show', $sheet->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal">@include('construction::partials.rr_icon', ['name' => 'list'])@lang('construction::lang.view_accounting_entry')</button>
                @endcan
            @endif
            @if($sheet->cancellationAccountingPosting?->mapping)
                @can('construction.accounting.view')
                    <button type="button" class="rr-btn rr-btn--secondary btn-modal" data-href="{{ route('journal-entry.show', $sheet->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal">@include('construction::partials.rr_icon', ['name' => 'arrow-back'])@lang('construction::lang.view_reversal_accounting_entry')</button>
                @endcan
            @endif
            <a href="{{ route('construction.labor.preview', $sheet->id) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'eye'])@lang('construction::lang.print_preview')</a>
            <a href="{{ route('construction.labor.print', $sheet->id) }}" target="_blank" rel="noopener" class="rr-btn rr-btn--ghost">@include('construction::partials.rr_icon', ['name' => 'print'])@lang('construction::lang.print')</a>
            @if($sheet->status === 'approved' && $canCancelApproved && $paidValue <= 0.0001)
                <button type="button" class="rr-btn rr-btn--danger-ghost" data-toggle="modal" data-target="#cancel-labor-sheet">@include('construction::partials.rr_icon', ['name' => 'close'])@lang('construction::lang.cancel_approved_labor_sheet')</button>
            @endif
        </div>
    </header>

    {{-- ═══ الحالة + الخطوة التالية ═══ --}}
    @if($isCancelled)
        <div class="rr-callout rr-callout--danger">
            <span class="rr-callout__icon">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 22])</span>
            <div class="rr-callout__copy">
                <strong>@lang('construction::lang.labor_sheet_cancellation_record')</strong>
                <p>{{ $sheet->cancellation_reason }}</p>
                <p>
                    @lang('construction::lang.cancelled_by'): {{ trim(collect([$sheet->cancelledBy?->surname, $sheet->cancelledBy?->first_name, $sheet->cancelledBy?->last_name])->filter()->implode(' ')) ?: '—' }}
                    · @lang('construction::lang.cancelled_at'): <span class="rr-num">{{ $sheet->cancelled_at ? @format_datetime($sheet->cancelled_at) : '—' }}</span>
                </p>
            </div>
        </div>
    @elseif($isDraft)
        @can('construction.labor.approve')
            <section class="rr-card rr-card--tint rr-card__body" aria-labelledby="rr-labor-next-title">
                <div class="rr-next">
                    <span class="rr-next__icon">@include('construction::partials.rr_icon', ['name' => 'shield', 'size' => 22])</span>
                    <div class="rr-next__copy">
                        <h2 id="rr-labor-next-title" class="rr-next__title">@lang('construction::lang.ready_for_approval')</h2>
                        <p class="rr-next__text">@lang('construction::lang.labor_approval_locks_sheet')</p>
                    </div>
                    <form class="rr-inline-form" method="POST" action="{{ route('construction.labor.approve', $sheet->id) }}" onsubmit="return confirm(@js(__('construction::lang.approve_labor_confirmation')))">
                        @csrf
                        <button class="rr-btn rr-btn--primary rr-btn--lg" type="submit" @disabled($sheet->lines->isEmpty())>@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.approve_labor_cost')</button>
                    </form>
                </div>
            </section>
        @endcan
    @endif

    {{-- ═══ الأرقام ═══ --}}
    <div class="rr-statgroup-wrap">
        <div class="rr-statgroup rr-statgroup--{{ $isPosted ? 4 : 2 }}" style="--rr-stats: {{ $isPosted ? 4 : 2 }}">
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 18])@lang('construction::lang.labor_lines')</div>
                <div class="rr-stat__value"><span class="rr-num">{{ $sheet->lines->count() }}</span></div>
            </div>
            <div class="rr-stat">
                <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'pie', 'size' => 18])@lang('construction::lang.sheet_total')</div>
                <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ $sheetTotal == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $sheetTotal])</span></div>
            </div>
            @if($isPosted)
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'check-circle', 'size' => 18])@lang('construction::lang.paid_amount')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $paidValue == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $paidValue])</span></div>
                </div>
                <div class="rr-stat">
                    <div class="rr-stat__label">@include('construction::partials.rr_icon', ['name' => 'clock', 'size' => 18])@lang('construction::lang.remaining_amount')</div>
                    <div class="rr-stat__value"><span class="rr-money rr-money--lg {{ (float) $remainingValue == 0 ? 'rr-money--zero' : '' }}">@include('construction::partials.money', ['value' => $remainingValue])</span></div>
                    <div class="rr-stat__sub"><span class="rr-badge rr-badge--{{ $paymentTones[$paymentStatus] ?? 'neutral' }}">@lang('construction::lang.payment_status_' . $paymentStatus)</span></div>
                </div>
            @endif
        </div>
    </div>

    @if($sheet->notes)
        <div class="rr-alert rr-alert--info"><div><strong>@lang('construction::lang.notes'):</strong> {{ $sheet->notes }}</div></div>
    @endif

    {{-- ═══ إضافة عامل / فريق ═══ --}}
    @if($isDraft && $canManage)
        <section class="rr-card" aria-labelledby="rr-labor-add-title">
            <header class="rr-card__head">
                <div class="rr-card__titles">
                    <h2 id="rr-labor-add-title" class="rr-card__title">@lang('construction::lang.add_labor_line')</h2>
                    <p class="rr-card__desc">@lang('construction::lang.labor_cost_after_approval')</p>
                </div>
            </header>
            <form method="POST" action="{{ route('construction.labor.lines.store', $sheet->id) }}">
                @csrf
                <div class="rr-entry">
                    <div class="rr-entry-row">
                        <div class="rr-entry-row__fields">
                            <div class="rr-entry-field rr-entry-field--grow rr-field">
                                <label class="rr-entry-field__label" for="labor-line-user">@lang('construction::lang.registered_employee')</label>
                                <select id="labor-line-user" name="user_id" class="form-control select2">
                                    <option value="">@lang('construction::lang.temporary_worker')</option>
                                    @foreach($workers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="rr-entry-field rr-entry-field--grow">
                                <label class="rr-entry-field__label" for="labor-line-name">@lang('construction::lang.worker_or_team_name')</label>
                                <div class="rr-control rr-control--sm"><input id="labor-line-name" name="worker_name" class="rr-control__input" placeholder="@lang('construction::lang.worker_name_placeholder')"></div>
                            </div>
                            <div class="rr-entry-field rr-entry-field--unit">
                                <label class="rr-entry-field__label" for="labor-line-role">@lang('construction::lang.role_name')</label>
                                <div class="rr-control rr-control--sm"><input id="labor-line-role" name="role_name" class="rr-control__input" placeholder="@lang('construction::lang.role_name_placeholder')"></div>
                            </div>
                            <span class="rr-entry-row__break"></span>
                            <div class="rr-entry-field rr-entry-field--unit">
                                <label class="rr-entry-field__label" for="labor-line-type">@lang('construction::lang.calculation_type'){!! $req !!}</label>
                                <div class="rr-control rr-control--sm">
                                    <select id="labor-line-type" name="calculation_type" class="rr-control__input rr-control__select ct-labor-type" required>
                                        @foreach($calculationTypes as $value => $label)<option value="{{ $value }}">@lang('construction::lang.' . $label)</option>@endforeach
                                    </select>
                                    <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                </div>
                            </div>
                            <div class="rr-entry-field rr-entry-field--num ct-labor-quantity-group">
                                <label class="rr-entry-field__label" for="labor-line-qty"><span class="ct-labor-quantity-label">@lang('construction::lang.quantity_hours')</span>{!! $req !!}</label>
                                <div class="rr-control rr-control--sm"><input id="labor-line-qty" name="quantity" value="1" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                            </div>
                            <div class="rr-entry-field rr-entry-field--num">
                                <label class="rr-entry-field__label" for="labor-line-cost">@lang('construction::lang.unit_cost'){!! $req !!}</label>
                                <div class="rr-control rr-control--sm"><input id="labor-line-cost" name="unit_cost" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                            </div>
                            <div class="rr-entry-field rr-entry-field--grow rr-field">
                                <label class="rr-entry-field__label" for="labor-line-item">@lang('construction::lang.project_item_optional')</label>
                                <select id="labor-line-item" name="boq_item_id" class="form-control select2">
                                    <option value="">@lang('construction::lang.general_project_labor')</option>
                                    @foreach($boqItems as $item)<option value="{{ $item->id }}">{{ $item->code }} — {{ $item->description }}</option>@endforeach
                                </select>
                            </div>
                            <div class="rr-entry-field rr-entry-field--note">
                                <label class="rr-entry-field__label" for="labor-line-notes">@lang('construction::lang.notes')</label>
                                <div class="rr-control rr-control--sm"><input id="labor-line-notes" name="notes" class="rr-control__input" placeholder="@lang('construction::lang.line_notes_optional')"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rr-toolbar">
                    <span class="rr-toolbar__hint">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.add_labor_hint')</span>
                    <div class="rr-toolbar__end">
                        <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'plus'])@lang('construction::lang.add_labor')</button>
                    </div>
                </div>
            </form>
        </section>
    @endif

    {{-- ═══ تفاصيل العمالة ═══ --}}
    <section class="rr-table-wrap" aria-labelledby="rr-labor-lines-title">
        <div class="rr-table-head">
            <div>
                <h2 id="rr-labor-lines-title" class="rr-table-head__title">@lang('construction::lang.labor_details')</h2>
                <p class="rr-table-head__sub">@lang('construction::lang.labor_details_hint')</p>
            </div>
            <span class="rr-badge rr-badge--brand"><span class="rr-num">{{ $sheet->lines->count() }}</span></span>
        </div>
        @if($sheet->lines->isEmpty())
            <div class="rr-empty">
                <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'users', 'size' => 22])</span>
                <h3 class="rr-empty__title">@lang('construction::lang.labor_sheet_empty')</h3>
            </div>
        @else
            <div class="rr-table-scroll">
                <table class="rr-table">
                    <caption class="rr-sr">@lang('construction::lang.labor_details')</caption>
                    <thead>
                        <tr>
                            <th class="rr-th rr-th--num" scope="col">#</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.worker_or_team')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.role_name')</th>
                            <th class="rr-th rr-col--text" scope="col">@lang('construction::lang.project_item')</th>
                            <th class="rr-th" scope="col">@lang('construction::lang.calculation_type')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.quantity')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.unit_cost')</th>
                            <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.total_cost')</th>
                            @if($isDraft && $canManage)<th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>@endif
                        </tr>
                    </thead>
                    <tbody class="rr-tbody">
                        @foreach($sheet->lines as $line)
                            <tr class="rr-tr">
                                <td class="rr-td rr-td--num rr-td--muted"><span class="rr-num">{{ $loop->iteration }}</span></td>
                                <td class="rr-td rr-td--wrap">
                                    <span class="rr-entity__name">{{ $line->worker_name }}</span>
                                    @if($line->worker)<div class="rr-td__sub">@lang('construction::lang.registered_employee')</div>@endif
                                </td>
                                <td class="rr-td rr-td--muted">{{ $line->role_name ?: '—' }}</td>
                                <td class="rr-td rr-td--wrap">
                                    @if($line->boqItem)
                                        <span class="rr-code">{{ $line->boqItem->code }}</span> {{ $line->boqItem->description }}
                                    @else
                                        <span class="rr-td--muted">@lang('construction::lang.general_project_labor')</span>
                                    @endif
                                </td>
                                <td class="rr-td"><span class="rr-badge">@lang('construction::lang.calc_' . $line->calculation_type)</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ @num_format($line->quantity) }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-num">{{ \Modules\Construction\Support\CurrencyFormatter::parts($line->unit_cost)['amount'] }}</span></td>
                                <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $line->total_cost])</span></td>
                                @if($isDraft && $canManage)
                                    <td class="rr-td rr-td--actions">
                                        <div class="rr-row-actions">
                                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-toggle="modal" data-target="#edit-labor-{{ $line->id }}" title="@lang('construction::lang.edit_labor_line')" aria-label="@lang('construction::lang.edit_labor_line'): {{ $line->worker_name }}">@include('construction::partials.rr_icon', ['name' => 'edit', 'size' => 16])</button>
                                            <form class="rr-inline-form" method="POST" action="{{ route('construction.labor.lines.destroy', [$sheet->id, $line->id]) }}" onsubmit="return confirm(@js(__('messages.sure')))">
                                                @csrf @method('DELETE')
                                                <button class="rr-btn rr-btn--danger-ghost rr-btn--sm rr-btn--icon" type="submit" title="@lang('messages.delete')" aria-label="@lang('messages.delete'): {{ $line->worker_name }}">@include('construction::partials.rr_icon', ['name' => 'trash', 'size' => 16])</button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="rr-tfoot">
                        <tr>
                            <td class="rr-td" colspan="7">@lang('construction::lang.sheet_total')</td>
                            <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $sheetTotal])</span></td>
                            @if($isDraft && $canManage)<td class="rr-td"></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    {{-- ═══ مدفوعات العمالة ═══ --}}
    @if($isPosted)
        <section class="rr-table-wrap" aria-labelledby="rr-labor-payments-title">
            <div class="rr-table-head">
                <div>
                    <h2 id="rr-labor-payments-title" class="rr-table-head__title">@lang('construction::lang.labor_payments')</h2>
                    <p class="rr-table-head__sub">@lang('construction::lang.labor_payment_accounting_notice')</p>
                </div>
                @if($canRecordPayment)
                    <button type="button" class="rr-btn rr-btn--primary rr-btn--sm" data-toggle="modal" data-target="#record-labor-payment">@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 16])@lang('construction::lang.record_payment')</button>
                @endif
            </div>
            @if($sheet->status === 'approved' && ! $laborAccountingReady)
                <div class="rr-alert rr-alert--warning"><div>@lang('construction::lang.labor_accounting_setup_notice') <a href="{{ route('construction.settings.accounting.index') }}">@lang('construction::lang.manage_construction_settings')</a></div></div>
            @endif
            @if($sheet->payments->isEmpty())
                <div class="rr-empty">
                    <span class="rr-empty__icon">@include('construction::partials.rr_icon', ['name' => 'download', 'size' => 22])</span>
                    <h3 class="rr-empty__title">@lang('construction::lang.no_labor_payments')</h3>
                </div>
            @else
                <div class="rr-table-scroll">
                    <table class="rr-table">
                        <caption class="rr-sr">@lang('construction::lang.labor_payments')</caption>
                        <thead>
                            <tr>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_number')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_date')</th>
                                <th class="rr-th rr-th--num" scope="col">@lang('construction::lang.payment_amount')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_method')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.account')</th>
                                <th class="rr-th" scope="col">@lang('construction::lang.payment_reference')</th>
                                <th class="rr-th" scope="col"><span class="rr-sr">@lang('construction::lang.operations')</span></th>
                            </tr>
                        </thead>
                        <tbody class="rr-tbody">
                            @foreach($sheet->payments as $payment)
                                <tr class="rr-tr {{ $payment->isActive() ? '' : 'rr-tr--muted' }}">
                                    <td class="rr-td">
                                        <span class="rr-code">{{ $payment->number }}</span>
                                        @unless($payment->isActive())<div><span class="rr-badge"><span class="rr-badge__dot" aria-hidden="true"></span>@lang('construction::lang.cancelled')</span></div>@endunless
                                    </td>
                                    <td class="rr-td"><span class="rr-num">{{ @format_date($payment->payment_date) }}</span></td>
                                    <td class="rr-td rr-td--num"><span class="rr-money rr-money--sm {{ $payment->isActive() ? '' : 'rr-money--zero' }}">@include('construction::partials.money', ['value' => $payment->amount])</span></td>
                                    <td class="rr-td">{{ $paymentTypes[$payment->method] ?? $payment->method }}</td>
                                    <td class="rr-td rr-td--wrap">{{ $payment->accountingAccount?->name ?: '—' }}</td>
                                    <td class="rr-td rr-td--muted">{{ $payment->reference_no ?: '—' }}</td>
                                    <td class="rr-td rr-td--actions">
                                        <div class="rr-row-actions">
                                            @if($payment->accountingPosting?->mapping)
                                                @can('construction.accounting.view')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ route('journal-entry.show', $payment->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_accounting_entry')" aria-label="@lang('construction::lang.view_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'list', 'size' => 16])</button>
                                                @endcan
                                            @endif
                                            @if($payment->cancellationAccountingPosting?->mapping)
                                                @can('construction.accounting.view')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ route('journal-entry.show', $payment->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_reversal_accounting_entry')" aria-label="@lang('construction::lang.view_reversal_accounting_entry')">@include('construction::partials.rr_icon', ['name' => 'arrow-back', 'size' => 16])</button>
                                                @endcan
                                            @endif
                                            @if($payment->isActive())
                                                @can('construction.cancel')
                                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm js-cancel-payment" data-toggle="modal" data-target="#cancel-payment" data-action="{{ route('construction.labor.payments.cancel', [$sheet->id, $payment->id]) }}">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 16])@lang('construction::lang.cancel_posted_payment')</button>
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
@if($canRecordPayment)
    <div class="modal fade rr-scope" id="record-labor-payment" tabindex="-1" role="dialog" aria-labelledby="rr-labor-payment-title">
        <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
            <div class="modal-content rr-modal">
                <form method="POST" action="{{ route('construction.labor.payments.store', $sheet->id) }}">
                    @csrf
                    <header class="rr-modal__head">
                        <div class="rr-modal__titles">
                            <h2 class="rr-modal__title" id="rr-labor-payment-title">@lang('construction::lang.record_payment')</h2>
                            <p class="rr-modal__desc">@lang('construction::lang.remaining_amount'): <span class="rr-money rr-money--sm">@include('construction::partials.money', ['value' => $remainingValue])</span></p>
                        </div>
                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                    </header>
                    <div class="rr-modal__body">
                        <div class="rr-form-wrap">
                            <div class="rr-form-grid">
                                <div class="rr-field">
                                    <label class="rr-field__label" for="labor-payment-date">@lang('construction::lang.payment_date'){!! $req !!}</label>
                                    <div class="rr-control rr-control--visible"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'calendar', 'size' => 18])</span><input type="text" id="labor-payment-date" name="payment_date" value="{{ old('payment_date', @format_date(now())) }}" class="rr-control__input ct-date-picker" readonly required></div>
                                </div>
                                <div class="rr-field">
                                    <label class="rr-field__label" for="labor-payment-amount">@lang('construction::lang.payment_amount'){!! $req !!}</label>
                                    <div class="rr-control @error('amount') rr-control--invalid @enderror"><input id="labor-payment-amount" name="amount" value="{{ old('amount', @num_format($remainingValue)) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal" required></div>
                                    @error('amount')<p class="rr-field__error">{{ $message }}</p>@enderror
                                </div>
                                <div class="rr-field">
                                    <label class="rr-field__label" for="labor-payment-method">@lang('construction::lang.payment_method'){!! $req !!}</label>
                                    <div class="rr-control">
                                        <select id="labor-payment-method" name="method" class="rr-control__input rr-control__select" required>
                                            @foreach($paymentTypes as $key => $label)<option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>@endforeach
                                        </select>
                                        <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                    </div>
                                </div>
                                <div class="rr-field">
                                    <label class="rr-field__label" for="labor-payment-account">@lang('construction::lang.payment_account'){!! $req !!}</label>
                                    <select id="labor-payment-account" name="accounting_account_id" class="form-control select2" required>
                                        <option value="">@lang('construction::lang.select_cash_bank_account')</option>
                                        @foreach($accountingPaymentAccounts as $id => $name)<option value="{{ $id }}" @selected((string) old('accounting_account_id', $accountingSettings->cash_account_id) === (string) $id)>{{ $name }}</option>@endforeach
                                    </select>
                                    @error('accounting_account_id')<p class="rr-field__error">{{ $message }}</p>@enderror
                                </div>
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="labor-payment-reference">@lang('construction::lang.payment_reference')</label>
                                    <div class="rr-control"><input id="labor-payment-reference" name="reference_no" value="{{ old('reference_no') }}" class="rr-control__input" maxlength="120"></div>
                                </div>
                                <div class="rr-field rr-field--full">
                                    <label class="rr-field__label" for="labor-payment-notes">@lang('construction::lang.notes')</label>
                                    <div class="rr-control rr-control--area"><textarea id="labor-payment-notes" name="notes" class="rr-control__input rr-control__input--short" rows="3">{{ old('notes') }}</textarea></div>
                                </div>
                            </div>
                        </div>
                        <p class="rr-modal__note">@include('construction::partials.rr_icon', ['name' => 'info', 'size' => 16])@lang('construction::lang.labor_payment_accounting_notice')</p>
                    </div>
                    <footer class="rr-modal__foot">
                        <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.record_payment')</button>
                        <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                    </footer>
                </form>
            </div>
        </div>
    </div>
@endif

{{-- إلغاء دفعة (الكل) وإلغاء اليومية المعتمدة: سبب + كلمة سر المدير --}}
@php
    $cancelModals = [];
    if ($isPosted) {
        $cancelModals[] = ['cancel-payment', 'cancel_posted_payment', '', 'cancellation_creates_reversal_notice', 'confirm_cancellation'];
    }
    if ($sheet->status === 'approved' && $canCancelApproved) {
        $cancelModals[] = ['cancel-labor-sheet', 'cancel_approved_labor_sheet', route('construction.labor.cancel', $sheet->id), 'cancel_labor_sheet_warning', 'confirm_labor_cancellation'];
    }
@endphp
@foreach($cancelModals as [$modalId, $modalTitle, $modalAction, $modalNotice, $confirmLabel])
    @php $isSheetCancel = $modalId === 'cancel-labor-sheet'; @endphp
    <div class="modal fade rr-scope" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}-title">
        <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
            <div class="modal-content rr-modal">
                <form method="POST" action="{{ $modalAction }}">
                    @csrf
                    <header class="rr-modal__head">
                        <div class="rr-modal__titles">
                            <h2 class="rr-modal__title" id="{{ $modalId }}-title">@lang('construction::lang.' . $modalTitle)</h2>
                            <p class="rr-modal__desc"><span class="rr-code">{{ $sheet->number }}</span></p>
                        </div>
                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                    </header>
                    <div class="rr-modal__body">
                        <div class="rr-alert rr-alert--warning"><div>@lang('construction::lang.' . $modalNotice)</div></div>
                        <div class="rr-form-wrap">
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="{{ $modalId }}-reason">@lang('construction::lang.cancellation_reason'){!! $req !!}</label>
                                <div class="rr-control rr-control--area {{ $isSheetCancel && $errors->has('cancellation_reason') ? 'rr-control--invalid' : '' }}"><textarea id="{{ $modalId }}-reason" name="cancellation_reason" class="rr-control__input rr-control__input--short" rows="3" required minlength="5" maxlength="2000" placeholder="@lang('construction::lang.cancellation_reason_placeholder')">{{ $isSheetCancel ? old('cancellation_reason') : '' }}</textarea></div>
                                @if($isSheetCancel)@error('cancellation_reason')<p class="rr-field__error">{{ $message }}</p>@enderror @endif
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-field__label" for="{{ $modalId }}-password">@lang('construction::lang.manager_password'){!! $req !!}</label>
                                <div class="rr-control {{ $isSheetCancel && $errors->has('current_password') ? 'rr-control--invalid' : '' }}"><span class="rr-control__lead">@include('construction::partials.rr_icon', ['name' => 'lock', 'size' => 18])</span><input type="password" id="{{ $modalId }}-password" name="current_password" class="rr-control__input" required autocomplete="current-password"></div>
                                @if($isSheetCancel)@error('current_password')<p class="rr-field__error">{{ $message }}</p>@enderror @endif
                            </div>
                        </div>
                    </div>
                    <footer class="rr-modal__foot">
                        <button class="rr-btn rr-btn--danger" type="submit">@lang('construction::lang.' . $confirmLabel)</button>
                        <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                    </footer>
                </form>
            </div>
        </div>
    </div>
@endforeach

@if($isDraft && $canManage)
    @foreach($sheet->lines as $line)
        <div class="modal fade rr-scope" id="edit-labor-{{ $line->id }}" tabindex="-1" role="dialog" aria-labelledby="edit-labor-{{ $line->id }}-title">
            <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
                <div class="modal-content rr-modal">
                    <form method="POST" action="{{ route('construction.labor.lines.update', [$sheet->id, $line->id]) }}">
                        @csrf @method('PUT')
                        <header class="rr-modal__head">
                            <div class="rr-modal__titles">
                                <h2 class="rr-modal__title" id="edit-labor-{{ $line->id }}-title">@lang('construction::lang.edit_labor_line')</h2>
                                <p class="rr-modal__desc">{{ $line->worker_name }}</p>
                            </div>
                            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                        </header>
                        <div class="rr-modal__body">
                            <div class="rr-form-wrap">
                                <div class="rr-form-grid">
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-user">@lang('construction::lang.registered_employee')</label>
                                        <div class="rr-control">
                                            <select id="edit-labor-{{ $line->id }}-user" name="user_id" class="rr-control__input rr-control__select">
                                                <option value="">@lang('construction::lang.temporary_worker')</option>
                                                @foreach($workers as $id => $name)<option value="{{ $id }}" @selected($line->user_id === (int) $id)>{{ $name }}</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-name">@lang('construction::lang.worker_or_team_name')</label>
                                        <div class="rr-control"><input id="edit-labor-{{ $line->id }}-name" name="worker_name" value="{{ $line->worker_name }}" class="rr-control__input"></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-role">@lang('construction::lang.role_name')</label>
                                        <div class="rr-control"><input id="edit-labor-{{ $line->id }}-role" name="role_name" value="{{ $line->role_name }}" class="rr-control__input"></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-type">@lang('construction::lang.calculation_type')</label>
                                        <div class="rr-control">
                                            <select id="edit-labor-{{ $line->id }}-type" name="calculation_type" class="rr-control__input rr-control__select ct-labor-type">
                                                @foreach($calculationTypes as $value => $label)<option value="{{ $value }}" @selected($line->calculation_type === $value)>@lang('construction::lang.' . $label)</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field ct-labor-quantity-group">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-qty"><span class="ct-labor-quantity-label">@lang('construction::lang.quantity')</span>{!! $req !!}</label>
                                        <div class="rr-control"><input id="edit-labor-{{ $line->id }}-qty" name="quantity" value="{{ @num_format($line->quantity) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal"></div>
                                    </div>
                                    <div class="rr-field">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-cost">@lang('construction::lang.unit_cost')</label>
                                        <div class="rr-control"><input id="edit-labor-{{ $line->id }}-cost" name="unit_cost" value="{{ @num_format($line->unit_cost) }}" class="rr-control__input rr-control__input--num input_number" inputmode="decimal"></div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-item">@lang('construction::lang.project_item_optional')</label>
                                        <div class="rr-control">
                                            <select id="edit-labor-{{ $line->id }}-item" name="boq_item_id" class="rr-control__input rr-control__select">
                                                <option value="">@lang('construction::lang.general_project_labor')</option>
                                                @foreach($boqItems as $item)<option value="{{ $item->id }}" @selected($line->boq_item_id === $item->id)>{{ $item->code }} — {{ $item->description }}</option>@endforeach
                                            </select>
                                            <span class="rr-control__chev">@include('construction::partials.rr_icon', ['name' => 'chevron-down', 'size' => 16])</span>
                                        </div>
                                    </div>
                                    <div class="rr-field rr-field--full">
                                        <label class="rr-field__label" for="edit-labor-{{ $line->id }}-notes">@lang('construction::lang.notes')</label>
                                        <div class="rr-control rr-control--area"><textarea id="edit-labor-{{ $line->id }}-notes" name="notes" class="rr-control__input rr-control__input--short" rows="2">{{ $line->notes }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="rr-modal__foot">
                            <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_changes')</button>
                            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection

@push('ct_quick_js')
    <script>
        $(function () {
            var laborQuantityLabels = {
                hour: @json(__('construction::lang.quantity_hours')),
                day: @json(__('construction::lang.quantity_days')),
                linear_meter: @json(__('construction::lang.quantity_linear_meters')),
                square_meter: @json(__('construction::lang.quantity_square_meters')),
                cubic_meter: @json(__('construction::lang.quantity_cubic_meters')),
                unit: @json(__('construction::lang.quantity_units')),
                lump_sum: @json(__('construction::lang.quantity_lump_sum'))
            };

            function syncLaborQuantityLabel($select) {
                var calculationType = $select.val();
                var $form = $select.closest('form');
                var $quantityGroup = $form.find('.ct-labor-quantity-group').first();
                var $quantityInput = $quantityGroup.find('input[name="quantity"]');

                $quantityGroup.find('.ct-labor-quantity-label').text(
                    laborQuantityLabels[calculationType] || @json(__('construction::lang.quantity'))
                );

                if (calculationType === 'lump_sum') {
                    $quantityInput.val('1').prop('readonly', true);
                } else {
                    $quantityInput.prop('readonly', false);
                }
            }

            $('.ct-labor-type').each(function () {
                syncLaborQuantityLabel($(this));
            });

            $(document).on('change', '.ct-labor-type', function () {
                syncLaborQuantityLabel($(this));
            });
            $(document).on('click', '.js-cancel-payment', function () {
                $('#cancel-payment form').attr('action', $(this).data('action'));
            });
        });
    </script>
@endpush

@if(($sheet->status === 'approved' && $canCancelApproved && request()->boolean('cancel')) || $errors->has('current_password') || $errors->has('cancellation_reason'))
    @push('ct_quick_js')
        <script>$(function () { $('#cancel-labor-sheet').modal('show'); });</script>
    @endpush
@endif

@if($errors->has('amount') || $errors->has('accounting_account_id') || $errors->has('payment_date') || $errors->has('method'))
    @push('ct_quick_js')
        <script>$(function () { $('#record-labor-payment').modal('show'); });</script>
    @endpush
@endif
