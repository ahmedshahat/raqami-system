@extends('construction::layouts.module')

@section('title', $certificate->number)

@section('module_content')
<section class="content ct-materials-page ct-subcert-page">
    @if($errors->any())
        <div class="alert alert-danger ct-subcert-validation-alert"><i class="fa fa-exclamation-triangle"></i><div><strong>@lang('construction::lang.subcontract_form_has_errors')</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif
    <div class="ct-document-hero ct-subcontract-hero">
        <div>
            <a href="{{ route('construction.subcontractors.show', $contract->id) }}" class="ct-back-link"><i class="fa fa-arrow-right"></i> @lang('construction::lang.back_to_subcontract')</a>
            <div class="ct-document-title">
                <span class="ct-document-title__icon"><i class="fa fa-file-invoice-dollar"></i></span>
                <div><div class="ct-document-kicker">@lang('construction::lang.subcontract_certificate') <span class="ct-status-pill {{ $certificate->status === 'approved' ? 'is-approved' : 'is-draft' }}">@lang('construction::lang.'.$certificate->status)</span></div><h1>{{ $certificate->number }}</h1><p>{{ $contract->number }} — {{ $contract->title }}</p></div>
            </div>
        </div>
        <div class="ct-document-actions">
            <a href="{{ route('construction.subcontractors.certificates.preview', [$contract->id, $certificate->id]) }}" target="_blank" class="btn btn-default"><i class="fa fa-eye"></i> @lang('construction::lang.print_preview')</a>
            <a href="{{ route('construction.subcontractors.certificates.print', [$contract->id, $certificate->id]) }}" target="_blank" class="btn btn-primary"><i class="fa fa-print"></i> @lang('construction::lang.print')</a>
        </div>
    </div>

    <div class="ct-document-summary">
        <div><span><i class="fa fa-hard-hat"></i></span><small>@lang('construction::lang.subcontractor')</small><strong>{{ $contract->subcontractor->supplier_business_name ?: $contract->subcontractor->name }}</strong></div>
        <div><span><i class="fa fa-project-diagram"></i></span><small>@lang('construction::lang.project')</small><strong>{{ $contract->project->code }} — {{ $contract->project->name }}</strong></div>
        <div><span><i class="fa fa-calendar"></i></span><small>@lang('construction::lang.certificate_date')</small><strong>{{ @format_date($certificate->certificate_date) }}</strong></div>
        <div><span><i class="fa fa-percentage"></i></span><small>@lang('construction::lang.retention_percent')</small><strong>{{ @num_format($certificate->retention_percent) }}%</strong></div>
    </div>

    @if($certificate->status === 'approved')
    @php($paidValue = $certificate->paidValue())
    @php($remainingValue = $certificate->remainingValue())
    @php($certificatePaymentStatus = $certificate->paymentStatus())
    <div class="ct-subcert-payment-overview">
        <div><span class="is-blue"><i class="fa fa-file-invoice-dollar"></i></span><small>@lang('construction::lang.net_certificate_due')</small><strong>@include('construction::partials.money',['value'=>$certificate->net_value])</strong></div>
        <div><span class="is-green"><i class="fa fa-money-check-alt"></i></span><small>@lang('construction::lang.paid_amount')</small><strong>@include('construction::partials.money',['value'=>$paidValue])</strong></div>
        <div><span class="is-amber"><i class="fa fa-hourglass-half"></i></span><small>@lang('construction::lang.remaining_amount')</small><strong>@include('construction::partials.money',['value'=>$remainingValue])</strong></div>
        <div><span class="is-violet"><i class="fa fa-info-circle"></i></span><small>@lang('construction::lang.payment_status')</small><strong class="ct-payment-status is-{{ $certificatePaymentStatus }}">@lang('construction::lang.payment_status_'.$certificatePaymentStatus)</strong></div>
    </div>
    @endif

    @if($certificate->isEditable())
    @can('construction.subcontract.manage')
    <form method="POST" action="{{ route('construction.subcontractors.certificates.update', [$contract->id, $certificate->id]) }}" id="subcontract-certificate-form">
        @csrf @method('PUT')
        <div class="ct-material-panel ct-subcert-meta">
            <div class="ct-panel-heading"><div><span>@lang('construction::lang.subcontract_certificates')</span><h2>@lang('construction::lang.certificate_progress')</h2><p>@lang('construction::lang.certificate_stage_two_hint')</p></div></div>
            <div class="ct-subcert-meta-grid">
                <div class="form-group"><label>@lang('construction::lang.certificate_date') *</label><input name="certificate_date" value="{{ old('certificate_date', @format_date($certificate->certificate_date)) }}" class="form-control ct-date-picker" readonly required></div>
                <div class="form-group"><label>@lang('construction::lang.period_from')</label><input name="period_from" value="{{ old('period_from', $certificate->period_from ? @format_date($certificate->period_from) : '') }}" class="form-control ct-date-picker" readonly></div>
                <div class="form-group"><label>@lang('construction::lang.period_to')</label><input name="period_to" value="{{ old('period_to', $certificate->period_to ? @format_date($certificate->period_to) : '') }}" class="form-control ct-date-picker" readonly></div>
                <div class="form-group"><label>@lang('construction::lang.other_deductions')</label><input name="other_deductions" value="{{ old('other_deductions', @num_format($certificate->other_deductions)) }}" class="form-control input_number ct-subcert-deduction @error('other_deductions') is-invalid @enderror">@error('other_deductions')<small class="text-danger">{{ $message }}</small>@enderror</div>
            </div>
        </div>

        <div class="ct-material-panel">
            <div class="table-responsive"><table class="table ct-material-table ct-subcert-table">
                <thead><tr><th>#</th><th>@lang('construction::lang.work_description')</th><th>@lang('construction::lang.calculation_type')</th><th>@lang('construction::lang.contract_quantity')</th><th>@lang('construction::lang.previous_quantity')</th><th>@lang('construction::lang.current_quantity')</th><th>@lang('construction::lang.cumulative_quantity')</th><th>@lang('construction::lang.current_value')</th></tr></thead>
                <tbody>
                    @foreach($certificate->items as $line)
                    <tr class="@error('items.'.$line->id) has-limit-error @enderror" data-rate="{{ (float)$line->unit_rate }}" data-type="{{ $line->calculation_type }}" data-previous="{{ (float)$line->previous_quantity }}" data-contract="{{ (float)$line->contract_quantity }}">
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $line->description }}</strong><small class="ct-table-muted">@include('construction::partials.money',['value'=>$line->unit_rate])</small></td>
                        <td><span class="ct-type-pill">@lang('construction::lang.subcalc_'.$line->calculation_type)</span></td>
                        <td>{{ @num_format($line->contract_quantity) }}</td>
                        <td>{{ @num_format($line->previous_quantity) }}</td>
                        <td><input name="items[{{ $line->id }}]" value="{{ old('items.'.$line->id, @num_format($line->current_quantity)) }}" class="form-control input_number ct-subcert-qty" required>@error('items.'.$line->id)<small class="ct-subcert-row-error">{{ $message }}</small>@enderror</td>
                        <td class="ct-subcert-cumulative">{{ @num_format($line->cumulativeQuantity()) }}</td>
                        <td><strong class="ct-subcert-line-value" data-value="{{ (float)$line->current_value }}">@include('construction::partials.money',['value'=>$line->current_value])</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table></div>
        </div>

        <div class="ct-subcert-bottom">
        <div class="ct-material-panel ct-subcert-notes"><label>@lang('construction::lang.notes')</label><textarea name="notes" class="form-control" rows="4">{{ old('notes', $certificate->notes) }}</textarea></div>
            <div class="ct-subcert-totals" data-retention="{{ (float)$certificate->retention_percent }}">
                <div><span>@lang('construction::lang.gross_certificate')</span><strong class="ct-subcert-gross">@include('construction::partials.money',['value'=>$certificate->gross_value])</strong></div>
                <div><span>@lang('construction::lang.retention_value') ({{ @num_format($certificate->retention_percent) }}%)</span><strong class="ct-subcert-retention">@include('construction::partials.money',['value'=>$certificate->retention_value])</strong></div>
                <div><span>@lang('construction::lang.other_deductions')</span><strong class="ct-subcert-other">@include('construction::partials.money',['value'=>$certificate->other_deductions])</strong></div>
                <div class="is-net"><span>@lang('construction::lang.net_certificate_due')</span><strong class="ct-subcert-net">@include('construction::partials.money',['value'=>$certificate->net_value])</strong></div>
            </div>
        </div>
        <div class="ct-subcert-savebar"><button class="btn ct-primary-action"><i class="fa fa-save"></i> @lang('construction::lang.save_certificate')</button></div>
    </form>
    @endcan
    @else
        <div class="ct-material-panel">
            <div class="table-responsive"><table class="table ct-material-table"><thead><tr><th>#</th><th>@lang('construction::lang.work_description')</th><th>@lang('construction::lang.contract_quantity')</th><th>@lang('construction::lang.previous_quantity')</th><th>@lang('construction::lang.current_quantity')</th><th>@lang('construction::lang.cumulative_quantity')</th><th>@lang('construction::lang.current_value')</th></tr></thead><tbody>@foreach($certificate->items as $line)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $line->description }}</strong></td><td>{{ @num_format($line->contract_quantity) }}</td><td>{{ @num_format($line->previous_quantity) }}</td><td>{{ @num_format($line->current_quantity) }}</td><td>{{ @num_format($line->cumulativeQuantity()) }}</td><td><strong>@include('construction::partials.money',['value'=>$line->current_value])</strong></td></tr>@endforeach</tbody></table></div>
        </div>
        <div class="ct-subcert-totals is-readonly"><div><span>@lang('construction::lang.gross_certificate')</span><strong>@include('construction::partials.money',['value'=>$certificate->gross_value])</strong></div><div><span>@lang('construction::lang.retention_value')</span><strong>@include('construction::partials.money',['value'=>$certificate->retention_value])</strong></div><div><span>@lang('construction::lang.other_deductions')</span><strong>@include('construction::partials.money',['value'=>$certificate->other_deductions])</strong></div><div class="is-net"><span>@lang('construction::lang.net_certificate_due')</span><strong>@include('construction::partials.money',['value'=>$certificate->net_value])</strong></div></div>
    @endif

    @if($certificate->isEditable())
    <div class="ct-approval-bar">
        <div><i class="fa fa-shield-alt"></i><span><strong>@lang('construction::lang.ready_for_approval')</strong><small>@lang('construction::lang.approved_subcontract_certificate_locked')</small></span></div>
        <div class="ct-subcert-approval-actions">
            @can('construction.subcontract.manage')<form method="POST" action="{{ route('construction.subcontractors.certificates.destroy', [$contract->id, $certificate->id]) }}" onsubmit="return confirm('@lang('construction::lang.delete_certificate_confirmation')')">@csrf @method('DELETE')<button class="btn btn-danger"><i class="fa fa-trash"></i> @lang('messages.delete')</button></form>@endcan
            @can('construction.subcontract.approve')<form method="POST" action="{{ route('construction.subcontractors.certificates.approve', [$contract->id, $certificate->id]) }}" onsubmit="return confirm('@lang('construction::lang.approve_subcontract_certificate_confirmation')')">@csrf<button class="btn btn-success"><i class="fa fa-check-circle"></i> @lang('construction::lang.approve_certificate')</button></form>@endcan
        </div>
    </div>
    @endif

    @if($certificate->status === 'approved')
    <div class="ct-material-panel ct-subcert-payments-panel">
        <div class="ct-panel-heading"><div><span>@lang('construction::lang.subcontract_payments')</span><h2>@lang('construction::lang.subcontract_payments')</h2><p>@lang('construction::lang.payment_stage_accounting_notice')</p></div>@if($remainingValue > 0.0001)@can('construction.subcontract.manage')<button class="btn ct-primary-action" data-toggle="modal" data-target="#record-subcontract-payment"><i class="fa fa-money-bill-wave"></i> @lang('construction::lang.record_payment')</button>@endcan @endif</div>
        <div class="table-responsive"><table class="table ct-material-table"><thead><tr><th>@lang('construction::lang.payment_number')</th><th>@lang('construction::lang.payment_date')</th><th>@lang('construction::lang.payment_amount')</th><th>@lang('construction::lang.payment_method')</th><th>@lang('construction::lang.payment_account')</th><th>@lang('construction::lang.payment_reference')</th><th>@lang('construction::lang.operations')</th></tr></thead><tbody>
            @forelse($certificate->payments as $payment)<tr><td><strong dir="ltr">{{ $payment->number }}</strong></td><td>{{ @format_date($payment->payment_date) }}</td><td><strong>@include('construction::partials.money',['value'=>$payment->amount])</strong></td><td>{{ $paymentTypes[$payment->method] ?? $payment->method }}</td><td>{{ $payment->account?->name ?: '—' }}</td><td>{{ $payment->reference_no ?: '—' }}</td><td><a href="{{ route('construction.subcontractors.certificates.payments.preview',[$contract->id,$certificate->id,$payment->id]) }}" target="_blank" class="btn btn-xs btn-default"><i class="fa fa-eye"></i></a> <a href="{{ route('construction.subcontractors.certificates.payments.print',[$contract->id,$certificate->id,$payment->id]) }}" target="_blank" class="btn btn-xs btn-primary"><i class="fa fa-print"></i></a></td></tr>@empty<tr><td colspan="7"><div class="ct-empty-state is-compact"><span><i class="fa fa-money-check-alt"></i></span><strong>@lang('construction::lang.no_subcontract_payments')</strong></div></td></tr>@endforelse
        </tbody></table></div>
    </div>
    @can('construction.subcontract.manage')
    <div class="modal fade" id="record-subcontract-payment"><div class="modal-dialog"><div class="modal-content ct-modern-modal"><form method="POST" action="{{ route('construction.subcontractors.certificates.payments.store',[$contract->id,$certificate->id]) }}">@csrf<div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4><i class="fa fa-money-bill-wave"></i> @lang('construction::lang.new_payment')</h4></div><div class="modal-body"><div class="ct-payment-balance"><small>@lang('construction::lang.remaining_amount')</small><strong>@include('construction::partials.money',['value'=>$remainingValue])</strong></div><div class="row"><div class="col-md-6 form-group"><label>@lang('construction::lang.payment_date') *</label><input name="payment_date" value="{{ old('payment_date', @format_date(now())) }}" class="form-control ct-date-picker" readonly required></div><div class="col-md-6 form-group"><label>@lang('construction::lang.payment_amount') *</label><input name="amount" value="{{ old('amount', @num_format($remainingValue)) }}" class="form-control input_number" required>@error('amount')<small class="text-danger">{{ $message }}</small>@enderror</div><div class="col-md-6 form-group"><label>@lang('construction::lang.payment_method') *</label><select name="method" class="form-control" required>@foreach($paymentTypes as $key=>$label)<option value="{{ $key }}" @selected(old('method','cash')===$key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-6 form-group"><label>@lang('construction::lang.payment_account')</label><select name="account_id" class="form-control select2"><option value="">@lang('lang_v1.none')</option>@foreach($paymentAccounts as $id=>$name)@if($id!=='')<option value="{{ $id }}" @selected((string)old('account_id')===(string)$id)>{{ $name }}</option>@endif @endforeach</select></div><div class="col-md-12 form-group"><label>@lang('construction::lang.payment_reference')</label><input name="reference_no" value="{{ old('reference_no') }}" class="form-control" maxlength="120"></div><div class="col-md-12 form-group"><label>@lang('construction::lang.notes')</label><textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea></div></div><div class="ct-context-banner"><i class="fa fa-info-circle"></i><span>@lang('construction::lang.payment_stage_accounting_notice')</span></div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button><button class="btn ct-primary-action"><i class="fa fa-save"></i> @lang('construction::lang.record_payment')</button></div></form></div></div></div>
    @endcan
    @endif
</section>
@endsection

@push('ct_quick_js')
<script>
$(function () {
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
            var $row = $(this), current = numberValue($row.find('.ct-subcert-qty'));
            var previous = parseFloat($row.data('previous')) || 0, contract = parseFloat($row.data('contract')) || 0;
            var rate = parseFloat($row.data('rate')) || 0;
            var value = $row.data('type') === 'percentage' ? rate * current / 100 : rate * current;
            $row.find('.ct-subcert-cumulative').text(money(previous + current));
            $row.find('.ct-subcert-line-value').text(money(value));
            $row.toggleClass('has-limit-error', previous + current > contract + 0.0001);
            gross += value;
        });
        var retention = gross * (parseFloat($('.ct-subcert-totals').data('retention')) || 0) / 100;
        var other = numberValue($('.ct-subcert-deduction'));
        $('.ct-subcert-gross').text(money(gross));
        $('.ct-subcert-retention').text(money(retention));
        $('.ct-subcert-other').text(money(other));
        $('.ct-subcert-net').text(money(Math.max(0, gross - retention - other)));
    }
    $(document).on('input change', '.ct-subcert-qty,.ct-subcert-deduction', recalculateCertificate);
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

@if($errors->hasAny(['amount','payment_date','method','account_id','reference_no']))
    @push('ct_quick_js')<script>$(function(){$('#record-subcontract-payment').modal('show')})</script>@endpush
@endif
