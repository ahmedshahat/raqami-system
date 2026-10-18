@extends('construction::layouts.module')

@section('title', $sheet->number)

@section('module_content')
<section class="content ct-materials-page ct-labor-page">
    <div class="ct-document-hero ct-labor-hero">
        <div>
            <a href="{{ route('construction.labor.index') }}" class="ct-back-link">
                <i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_labor')
            </a>
            <div class="ct-document-title">
                <span class="ct-document-title__icon ct-labor-icon"><i class="fas fa-hard-hat"></i></span>
                <div>
                    <div class="ct-document-kicker">
                        @lang('construction::lang.labor_sheet')
                        <span class="ct-status-pill {{ $sheet->status === 'approved' ? 'is-approved' : ($sheet->status === 'cancelled' ? 'is-cancelled' : 'is-draft') }}">
                            @lang('construction::lang.'.$sheet->status)
                        </span>
                    </div>
                    <h1>{{ $sheet->number }}</h1>
                    <p>{{ $sheet->project->code }} — {{ $sheet->project->name }}</p>
                </div>
            </div>
        </div>
        <div class="ct-document-actions">
            @if($sheet->accountingPosting?->mapping)
            @can('construction.accounting.view')
                    <button type="button" class="btn btn-success btn-modal" data-href="{{ route('journal-entry.show', $sheet->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal">
                        <i class="fa fa-book"></i> @lang('construction::lang.view_accounting_entry')
                    </button>
                @endcan
            @endif
            @if($sheet->cancellationAccountingPosting?->mapping)
            @can('construction.accounting.view')
                    <button type="button" class="btn btn-default btn-modal" data-href="{{ route('journal-entry.show', $sheet->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal">
                        <i class="fa fa-undo"></i> @lang('construction::lang.view_reversal_accounting_entry')
                    </button>
                @endcan
            @endif
            <a href="{{ route('construction.labor.preview', $sheet->id) }}" target="_blank" class="btn btn-default">
                <i class="fa fa-eye"></i> @lang('construction::lang.print_preview')
            </a>
            <a href="{{ route('construction.labor.print', $sheet->id) }}" target="_blank" class="btn btn-primary">
                <i class="fa fa-print"></i> @lang('construction::lang.print')
            </a>
            @if($sheet->isEditable())
                @can('construction.labor.manage')
                    <a href="{{ route('construction.labor.edit', $sheet->id) }}" class="btn btn-default">
                        <i class="fa fa-pencil"></i> @lang('construction::lang.edit_sheet')
                    </a>
                @endcan
            @endif
            @if($sheet->status === 'approved' && $canCancelApproved && $paidValue <= 0.0001)
                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#cancel-labor-sheet">
                    <i class="fa fa-ban"></i> @lang('construction::lang.cancel_approved_labor_sheet')
                </button>
            @endif
        </div>
    </div>

    <div class="ct-document-summary">
        <div><span><i class="fa fa-calendar"></i></span><small>@lang('construction::lang.period')</small><strong>{{ @format_date($sheet->period_start) }} — {{ @format_date($sheet->period_end) }}</strong></div>
        <div><span><i class="fa fa-map-marker"></i></span><small>@lang('construction::lang.work_site')</small><strong>{{ $sheet->work_site ?: '—' }}</strong></div>
        <div><span><i class="fa fa-users"></i></span><small>@lang('construction::lang.labor_lines')</small><strong>{{ $sheet->lines->count() }}</strong></div>
        <div><span><i class="fa fa-coins"></i></span><small>@lang('construction::lang.sheet_total')</small><strong>@include('construction::partials.money', ['value' => $sheet->lines->sum('total_cost')])</strong></div>
    </div>

    @if($sheet->notes)
        <div class="ct-context-banner is-note">
            <i class="fa fa-sticky-note"></i>
            <span><strong>@lang('construction::lang.notes'):</strong> {{ $sheet->notes }}</span>
        </div>
    @endif

    @if($sheet->status === 'cancelled')
        <div class="ct-cancellation-record">
            <span class="ct-cancellation-record__icon"><i class="fa fa-ban"></i></span>
            <div>
                <strong>@lang('construction::lang.labor_sheet_cancellation_record')</strong>
                <p>{{ $sheet->cancellation_reason }}</p>
                <small>
                    @lang('construction::lang.cancelled_by'):
                    {{ trim(collect([$sheet->cancelledBy?->surname, $sheet->cancelledBy?->first_name, $sheet->cancelledBy?->last_name])->filter()->implode(' ')) ?: '—' }}
                    · @lang('construction::lang.cancelled_at'): {{ $sheet->cancelled_at ? @format_datetime($sheet->cancelled_at) : '—' }}
                </small>
            </div>
        </div>
    @endif

    @if($sheet->isEditable())
        @can('construction.labor.manage')
            <div class="ct-material-panel ct-material-entry">
                <div class="ct-panel-heading">
                    <div>
                        <span>@lang('construction::lang.draft')</span>
                        <h2>@lang('construction::lang.add_labor_line')</h2>
                        <p>@lang('construction::lang.labor_cost_after_approval')</p>
                    </div>
                    <div class="ct-step-badge"><b>2</b> @lang('construction::lang.step_number', ['number' => 2])</div>
                </div>
                <form method="POST" action="{{ route('construction.labor.lines.store', $sheet->id) }}">
                    @csrf
                    <div class="ct-labor-entry-grid">
                        <div class="form-group">
                            <label>@lang('construction::lang.registered_employee')</label>
                            <select name="user_id" class="form-control select2">
                                <option value="">@lang('construction::lang.temporary_worker')</option>
                                @foreach($workers as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('construction::lang.worker_or_team_name')</label>
                            <input name="worker_name" class="form-control" placeholder="@lang('construction::lang.worker_name_placeholder')">
                        </div>
                        <div class="form-group">
                            <label>@lang('construction::lang.role_name')</label>
                            <input name="role_name" class="form-control" placeholder="@lang('construction::lang.role_name_placeholder')">
                        </div>
                        <div class="form-group">
                            <label>@lang('construction::lang.calculation_type') *</label>
                            <select name="calculation_type" class="form-control ct-labor-type" required>
                                <option value="hour">@lang('construction::lang.by_hour')</option>
                                <option value="day">@lang('construction::lang.by_day')</option>
                                <option value="linear_meter">@lang('construction::lang.by_linear_meter')</option>
                                <option value="square_meter">@lang('construction::lang.by_square_meter')</option>
                                <option value="cubic_meter">@lang('construction::lang.by_cubic_meter')</option>
                                <option value="unit">@lang('construction::lang.by_unit')</option>
                                <option value="lump_sum">@lang('construction::lang.lump_sum')</option>
                            </select>
                        </div>
                        <div class="form-group ct-labor-quantity-group">
                            <label><span class="ct-labor-quantity-label">@lang('construction::lang.quantity_hours')</span> *</label>
                            <input name="quantity" value="1" class="form-control input_number" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('construction::lang.unit_cost') *</label>
                            <input name="unit_cost" class="form-control input_number" required>
                        </div>
                        <div class="form-group ct-labor-boq">
                            <label>@lang('construction::lang.project_item_optional')</label>
                            <select name="boq_item_id" class="form-control select2">
                                <option value="">@lang('construction::lang.general_project_labor')</option>
                                @foreach($boqItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->code }} — {{ $item->description }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group ct-labor-notes">
                            <label>@lang('construction::lang.notes')</label>
                            <input name="notes" class="form-control" placeholder="@lang('construction::lang.line_notes_optional')">
                        </div>
                        <button class="btn ct-primary-action"><i class="fa fa-plus"></i> @lang('construction::lang.add_labor')</button>
                    </div>
                </form>
            </div>
        @endcan
    @endif

    <div class="ct-material-panel">
        <div class="ct-panel-heading">
            <div>
                <span>@lang('construction::lang.sheet_details')</span>
                <h2>@lang('construction::lang.labor_details')</h2>
                <p>@lang('construction::lang.labor_details_hint')</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table ct-material-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>@lang('construction::lang.worker_or_team')</th>
                        <th>@lang('construction::lang.role_name')</th>
                        <th>@lang('construction::lang.project_item')</th>
                        <th>@lang('construction::lang.calculation_type')</th>
                        <th>@lang('construction::lang.quantity')</th>
                        <th>@lang('construction::lang.unit_cost')</th>
                        <th>@lang('construction::lang.total_cost')</th>
                        @if($sheet->isEditable())<th>@lang('construction::lang.operations')</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($sheet->lines as $line)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $line->worker_name }}</strong>
                                @if($line->worker)<small class="ct-table-muted">@lang('construction::lang.registered_employee')</small>@endif
                            </td>
                            <td>{{ $line->role_name ?: '—' }}</td>
                            <td>{{ $line->boqItem ? $line->boqItem->code.' — '.$line->boqItem->description : __('construction::lang.general_project_labor') }}</td>
                            <td><span class="ct-type-pill ct-labor-type-pill">@lang('construction::lang.calc_'.$line->calculation_type)</span></td>
                            <td><span class="ct-quantity-with-unit">{{ @num_format($line->quantity) }} <small>@lang('construction::lang.calc_'.$line->calculation_type)</small></span></td>
                            <td>@include('construction::partials.money', ['value' => $line->unit_cost])</td>
                            <td><strong>@include('construction::partials.money', ['value' => $line->total_cost])</strong></td>
                            @if($sheet->isEditable())
                                <td>
                                    @can('construction.labor.manage')
                                        <button class="btn btn-xs btn-default" data-toggle="modal" data-target="#edit-labor-{{ $line->id }}"><i class="fa fa-pencil"></i></button>
                                        <form class="ct-inline-form" method="POST" action="{{ route('construction.labor.lines.destroy', [$sheet->id, $line->id]) }}" onsubmit="return confirm('@lang('messages.sure')')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="ct-empty-state is-compact"><span><i class="fas fa-user-clock"></i></span><strong>@lang('construction::lang.labor_sheet_empty')</strong></div></td></tr>
                    @endforelse
                </tbody>
                @if($sheet->lines->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="7" class="text-left">@lang('construction::lang.sheet_total')</th>
                            <th>@include('construction::partials.money', ['value' => $sheet->lines->sum('total_cost')])</th>
                            @if($sheet->isEditable())<th></th>@endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @if($sheet->isEditable())
            @can('construction.labor.approve')
                <div class="ct-approval-bar">
                    <div><i class="fa fa-shield"></i><span><strong>@lang('construction::lang.ready_for_approval')</strong><small>@lang('construction::lang.labor_approval_locks_sheet')</small></span></div>
                    <form method="POST" action="{{ route('construction.labor.approve', $sheet->id) }}" onsubmit="return confirm('@lang('construction::lang.approve_labor_confirmation')')">
                        @csrf
                        <button class="btn btn-success" @disabled($sheet->lines->isEmpty())><i class="fa fa-check-circle"></i> @lang('construction::lang.approve_labor_cost')</button>
                    </form>
                </div>
            @endcan
        @endif
    </div>

    @if(in_array($sheet->status, ['approved', 'cancelled'], true))
        <div class="ct-document-summary">
            <div><span><i class="fa fa-file-invoice-dollar"></i></span><small>@lang('construction::lang.sheet_value')</small><strong>@include('construction::partials.money', ['value' => $sheet->totalCost()])</strong></div>
            <div><span><i class="fa fa-money-check-alt"></i></span><small>@lang('construction::lang.paid_amount')</small><strong>@include('construction::partials.money', ['value' => $paidValue])</strong></div>
            <div><span><i class="fa fa-hourglass-half"></i></span><small>@lang('construction::lang.remaining_amount')</small><strong>@include('construction::partials.money', ['value' => $remainingValue])</strong></div>
            <div><span><i class="fa fa-info-circle"></i></span><small>@lang('construction::lang.payment_status')</small><strong>@lang('construction::lang.payment_status_'.$paymentStatus)</strong></div>
        </div>

        <div class="ct-material-panel ct-subcert-payments-panel">
            <div class="ct-panel-heading">
                <div>
                    <h2>@lang('construction::lang.labor_payments')</h2>
                    <p>@lang('construction::lang.labor_payment_accounting_notice')</p>
                </div>
                @if($sheet->status === 'approved' && $remainingValue > 0.0001 && $laborAccountingReady)
                    @can('construction.payment.record')
                        <button class="btn ct-primary-action" data-toggle="modal" data-target="#record-labor-payment"><i class="fa fa-money-bill-wave"></i> @lang('construction::lang.record_payment')</button>
                    @endcan
                @endif
            </div>
            @if($sheet->status === 'approved' && ! $laborAccountingReady)
                <div class="ct-context-banner is-note"><i class="fa fa-exclamation-triangle"></i><span>@lang('construction::lang.labor_accounting_setup_notice') <a href="{{ route('construction.settings.accounting.index') }}">@lang('construction::lang.manage_construction_settings')</a></span></div>
            @endif
            <div class="table-responsive">
                <table class="table ct-material-table">
                    <thead><tr><th>@lang('construction::lang.payment_number')</th><th>@lang('construction::lang.payment_date')</th><th>@lang('construction::lang.payment_amount')</th><th>@lang('construction::lang.payment_method')</th><th>@lang('construction::lang.account')</th><th>@lang('construction::lang.payment_reference')</th><th>@lang('construction::lang.operations')</th></tr></thead>
                    <tbody>
                        @forelse($sheet->payments as $payment)
                            <tr>
                                <td><strong dir="ltr">{{ $payment->number }}</strong></td>
                                <td>{{ @format_date($payment->payment_date) }}</td>
                                <td><strong>@include('construction::partials.money', ['value' => $payment->amount])</strong></td>
                                <td>{{ $paymentTypes[$payment->method] ?? $payment->method }}</td>
                                <td>{{ $payment->accountingAccount?->name ?: '—' }}</td>
                                <td>{{ $payment->reference_no ?: '—' }}</td>
                                <td>
                                    @if($payment->accountingPosting?->mapping)
                                        @can('construction.accounting.view')
                                            <button type="button" class="btn btn-xs btn-success btn-modal" data-href="{{ route('journal-entry.show', $payment->accountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_accounting_entry')"><i class="fa fa-book"></i></button>
                                        @endcan
                                    @endif
                                    @if($payment->cancellationAccountingPosting?->mapping)
                                        @can('construction.accounting.view')<button type="button" class="btn btn-xs btn-default btn-modal" data-href="{{ route('journal-entry.show', $payment->cancellationAccountingPosting->accounting_mapping_id) }}" data-container=".view_modal" title="@lang('construction::lang.view_reversal_accounting_entry')"><i class="fa fa-undo"></i></button>@endcan
                                    @endif
                                    @if($payment->isActive())
                                        @can('construction.cancel')<button type="button" class="btn btn-xs btn-danger js-cancel-payment" data-toggle="modal" data-target="#cancel-payment" data-action="{{ route('construction.labor.payments.cancel', [$sheet->id, $payment->id]) }}"><i class="fa fa-ban"></i></button>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="ct-empty-state is-compact"><span><i class="fa fa-money-check-alt"></i></span><strong>@lang('construction::lang.no_labor_payments')</strong></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>

@if($sheet->status === 'approved' && $remainingValue > 0.0001 && $laborAccountingReady && auth()->user()->can('construction.payment.record'))
    <div class="modal fade" id="record-labor-payment">
        <div class="modal-dialog">
            <div class="modal-content ct-modern-modal">
                <form method="POST" action="{{ route('construction.labor.payments.store', $sheet->id) }}">
                    @csrf
                    <div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4><i class="fa fa-money-bill-wave"></i> @lang('construction::lang.record_payment')</h4></div>
                    <div class="modal-body">
                        <div class="ct-payment-balance"><small>@lang('construction::lang.remaining_amount')</small><strong>@include('construction::partials.money', ['value' => $remainingValue])</strong></div>
                        <div class="row">
                            <div class="col-md-6 form-group"><label>@lang('construction::lang.payment_date') *</label><input name="payment_date" value="{{ old('payment_date', @format_date(now())) }}" class="form-control ct-date-picker" readonly required></div>
                            <div class="col-md-6 form-group {{ $errors->has('amount') ? 'has-error' : '' }}"><label>@lang('construction::lang.payment_amount') *</label><input name="amount" value="{{ old('amount', @num_format($remainingValue)) }}" class="form-control input_number" required>@error('amount')<small class="help-block">{{ $message }}</small>@enderror</div>
                            <div class="col-md-6 form-group"><label>@lang('construction::lang.payment_method') *</label><select name="method" class="form-control" required>@foreach($paymentTypes as $key => $label)<option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-6 form-group {{ $errors->has('accounting_account_id') ? 'has-error' : '' }}"><label>@lang('construction::lang.payment_account') *</label><select name="accounting_account_id" class="form-control select2" required><option value="">@lang('construction::lang.select_cash_bank_account')</option>@foreach($accountingPaymentAccounts as $id => $name)<option value="{{ $id }}" @selected((string) old('accounting_account_id', $accountingSettings->cash_account_id) === (string) $id)>{{ $name }}</option>@endforeach</select>@error('accounting_account_id')<small class="help-block">{{ $message }}</small>@enderror</div>
                            <div class="col-md-12 form-group"><label>@lang('construction::lang.payment_reference')</label><input name="reference_no" value="{{ old('reference_no') }}" class="form-control" maxlength="120"></div>
                            <div class="col-md-12 form-group"><label>@lang('construction::lang.notes')</label><textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea></div>
                        </div>
                        <div class="ct-context-banner"><i class="fa fa-info-circle"></i><span>@lang('construction::lang.labor_payment_accounting_notice')</span></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn ct-primary-action"><i class="fa fa-save"></i> @lang('construction::lang.record_payment')</button></div>
                </form>
            </div>
        </div>
    </div>
@endif

@if(in_array($sheet->status, ['approved', 'cancelled'], true))
    <div class="modal fade" id="cancel-payment"><div class="modal-dialog"><div class="modal-content ct-modern-modal"><form method="POST" action="">@csrf<div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4>@lang('construction::lang.cancel_posted_payment')</h4></div><div class="modal-body"><div class="form-group"><label>@lang('construction::lang.cancellation_reason') *</label><textarea name="cancellation_reason" class="form-control" rows="4" required minlength="5"></textarea></div><div class="form-group"><label>@lang('construction::lang.manager_password') *</label><input type="password" name="current_password" class="form-control" required></div><div class="alert alert-warning">@lang('construction::lang.cancellation_creates_reversal_notice')</div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn btn-danger">@lang('construction::lang.confirm_cancellation')</button></div></form></div></div></div>
@endif

@if($sheet->status === 'approved' && $canCancelApproved)
    <div class="modal fade" id="cancel-labor-sheet" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content ct-modern-modal ct-cancel-modal">
                <form method="POST" action="{{ route('construction.labor.cancel', $sheet->id) }}">
                    @csrf
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <span class="ct-cancel-modal__icon"><i class="fa fa-shield"></i></span>
                        <h4 class="modal-title">@lang('construction::lang.cancel_approved_labor_sheet')</h4>
                        <p>@lang('construction::lang.cancel_labor_sheet_warning')</p>
                    </div>
                    <div class="modal-body">
                        <div class="form-group {{ $errors->has('cancellation_reason') ? 'has-error' : '' }}">
                            <label>@lang('construction::lang.cancellation_reason') *</label>
                            <textarea name="cancellation_reason" class="form-control" rows="4" required minlength="5" maxlength="2000" placeholder="@lang('construction::lang.cancellation_reason_placeholder')">{{ old('cancellation_reason') }}</textarea>
                            @if($errors->has('cancellation_reason'))<span class="help-block">{{ $errors->first('cancellation_reason') }}</span>@endif
                        </div>
                        <div class="form-group {{ $errors->has('current_password') ? 'has-error' : '' }}">
                            <label>@lang('construction::lang.manager_password') *</label>
                            <div class="ct-input-icon"><i class="fa fa-lock"></i><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
                            @if($errors->has('current_password'))<span class="help-block">{{ $errors->first('current_password') }}</span>@endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                        <button class="btn btn-danger"><i class="fa fa-ban"></i> @lang('construction::lang.confirm_labor_cancellation')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($sheet->isEditable())
    @foreach($sheet->lines as $line)
        <div class="modal fade" id="edit-labor-{{ $line->id }}">
            <div class="modal-dialog">
                <div class="modal-content ct-modern-modal">
                    <form method="POST" action="{{ route('construction.labor.lines.update', [$sheet->id, $line->id]) }}">
                        @csrf @method('PUT')
                        <div class="modal-header">
                            <button class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">@lang('construction::lang.edit_labor_line')</h4>
                            <p>{{ $line->worker_name }}</p>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>@lang('construction::lang.registered_employee')</label>
                                <select name="user_id" class="form-control">
                                    <option value="">@lang('construction::lang.temporary_worker')</option>
                                    @foreach($workers as $id => $name)
                                        <option value="{{ $id }}" @selected($line->user_id === (int)$id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>@lang('construction::lang.worker_or_team_name')</label>
                                <input name="worker_name" value="{{ $line->worker_name }}" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>@lang('construction::lang.role_name')</label>
                                <input name="role_name" value="{{ $line->role_name }}" class="form-control">
                            </div>
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label>@lang('construction::lang.calculation_type')</label>
                                    <select name="calculation_type" class="form-control ct-labor-type">
                                        <option value="hour" @selected($line->calculation_type === 'hour')>@lang('construction::lang.by_hour')</option>
                                        <option value="day" @selected($line->calculation_type === 'day')>@lang('construction::lang.by_day')</option>
                                        <option value="linear_meter" @selected($line->calculation_type === 'linear_meter')>@lang('construction::lang.by_linear_meter')</option>
                                        <option value="square_meter" @selected($line->calculation_type === 'square_meter')>@lang('construction::lang.by_square_meter')</option>
                                        <option value="cubic_meter" @selected($line->calculation_type === 'cubic_meter')>@lang('construction::lang.by_cubic_meter')</option>
                                        <option value="unit" @selected($line->calculation_type === 'unit')>@lang('construction::lang.by_unit')</option>
                                        <option value="lump_sum" @selected($line->calculation_type === 'lump_sum')>@lang('construction::lang.lump_sum')</option>
                                    </select>
                                </div>
                                <div class="col-md-4 form-group ct-labor-quantity-group">
                                    <label><span class="ct-labor-quantity-label">@lang('construction::lang.quantity')</span> *</label>
                                    <input name="quantity" value="{{ @num_format($line->quantity) }}" class="form-control input_number">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>@lang('construction::lang.unit_cost')</label>
                                    <input name="unit_cost" value="{{ @num_format($line->unit_cost) }}" class="form-control input_number">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>@lang('construction::lang.project_item_optional')</label>
                                <select name="boq_item_id" class="form-control">
                                    <option value="">@lang('construction::lang.general_project_labor')</option>
                                    @foreach($boqItems as $item)
                                        <option value="{{ $item->id }}" @selected($line->boq_item_id === $item->id)>{{ $item->code }} — {{ $item->description }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>@lang('construction::lang.notes')</label>
                                <textarea name="notes" class="form-control">{{ $line->notes }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                            <button class="btn btn-primary"><i class="fa fa-save"></i> @lang('construction::lang.save_changes')</button>
                        </div>
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
