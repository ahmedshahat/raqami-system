@php
    $is_receivable = $cheque->type == 'receivable';
    if ($action == 'collect') {
        $title = $is_receivable ? __('cheque.collect_cheque') : __('cheque.clear_cheque');
        $date_label = $is_receivable ? __('cheque.collection_date') : __('cheque.clearing_date');
        $btn_class = 'tw-dw-btn-success';
    } elseif ($action == 'bounce') {
        $title = __('cheque.bounce_cheque');
        $date_label = __('messages.date');
        $btn_class = 'tw-dw-btn-error';
    } else {
        $title = __('cheque.cancel_cheque');
        $date_label = __('messages.date');
        $btn_class = 'tw-dw-btn-primary';
    }
@endphp
<div class="modal-dialog" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => action([\App\Http\Controllers\ChequeController::class, 'performAction'], [$cheque->id, $action]), 'method' => 'post', 'id' => 'cheque_action_form']) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">{{ $title }}</h4>
        </div>
        <div class="modal-body">
            <table class="table table-condensed table-bordered">
                <tr>
                    <th>{{ $is_receivable ? __('cheque.customer') : __('cheque.supplier') }}</th>
                    <td>{{ $cheque->contact->name ?? '' }}</td>
                </tr>
                <tr>
                    <th>{{ __('cheque.cheque_no') }}</th>
                    <td>{{ $cheque->cheque_number }} @if($cheque->bank_name) - {{ $cheque->bank_name }} @endif</td>
                </tr>
                <tr>
                    <th>{{ __('cheque.amount') }}</th>
                    <td><strong><span class="display_currency" data-currency_symbol="true">{{ $cheque->amount }}</span></strong></td>
                </tr>
                <tr>
                    <th>{{ __('cheque.due_date') }}</th>
                    <td>{{ @format_date($cheque->due_date) }}</td>
                </tr>
                <tr>
                    <th>{{ __('cheque.invoice') }}</th>
                    <td>
                        @if(!empty($cheque->transaction))
                            {{ $is_receivable ? $cheque->transaction->invoice_no : $cheque->transaction->ref_no }}
                        @else
                            {{ __('cheque.on_account') }}
                        @endif
                    </td>
                </tr>
            </table>

            @if($action == 'collect' && !is_null($due_amount) && $cheque->amount - $due_amount > 0.0001)
                <div class="alert alert-warning">{{ __('cheque.cheque_amount_exceeds') }}</div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="action_date">{{ $date_label }}:*</label>
                        <input type="date" name="action_date" id="action_date" class="form-control" required value="{{ \Carbon::now()->format('Y-m-d') }}">
                    </div>
                </div>
                @if($action == 'collect' && !empty($accounts))
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('account_id', __('cheque.payment_account') . ':') !!}
                            {!! Form::select('account_id', $accounts, $cheque->account_id, ['class' => 'form-control', 'style' => 'width:100%']); !!}
                        </div>
                    </div>
                @endif
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('status_note', ($action == 'collect' ? __('cheque.note') : __('cheque.reason')) . ':') !!}
                        {!! Form::textarea('status_note', null, ['class' => 'form-control', 'rows' => 2]); !!}
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn {{ $btn_class }} tw-text-white">{{ __('cheque.confirm_action') }}</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">{{ __('messages.close') }}</button>
        </div>
        {!! Form::close() !!}
    </div>
</div>
<script>
    __currency_convert_recursively($('.cheque_modal'));
</script>
