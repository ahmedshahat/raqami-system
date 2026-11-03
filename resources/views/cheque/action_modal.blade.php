@php
    $is_receivable = $cheque->type == 'receivable';
    if ($action == 'collect') {
        $title = $is_receivable ? __('cheque.collect_cheque') : __('cheque.clear_cheque');
        $date_label = $is_receivable ? __('cheque.collection_date') : __('cheque.clearing_date');
        $btn_class = 'rr-btn--primary';
    } elseif ($action == 'bounce') {
        $title = __('cheque.bounce_cheque');
        $date_label = __('messages.date');
        $btn_class = 'rr-btn--danger';
    } else {
        $title = __('cheque.cancel_cheque');
        $date_label = __('messages.date');
        $btn_class = 'rr-btn--primary';
    }
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm rr-modal-dialog--compact" role="document">
    <div class="modal-content rr-modal">
        {!! Form::open(['url' => action([\App\Http\Controllers\ChequeController::class, 'performAction'], [$cheque->id, $action]), 'method' => 'post', 'id' => 'cheque_action_form']) !!}
        <header class="rr-modal__head">
            <div class="rr-modal__titles">
                <h2 class="rr-modal__title">{{ $title }}</h2>
                <p class="rr-modal__desc">{{ $cheque->contact->name ?? '' }}</p>
            </div>
            <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="{{ __('messages.close') }}"><i class="fa fa-times"></i></button>
        </header>
        <div class="rr-modal__body">
            <dl class="rr-deflist rr-card">
                <div class="rr-deflist__item"><dt>{{ __('cheque.cheque_no') }}</dt><dd><span class="rr-code">{{ $cheque->cheque_number }}</span>@if($cheque->bank_name) · {{ $cheque->bank_name }}@endif</dd></div>
                <div class="rr-deflist__item"><dt>{{ __('cheque.amount') }}</dt><dd><span class="rr-num">{{ @num_format($cheque->amount) }}</span></dd></div>
                <div class="rr-deflist__item"><dt>{{ __('cheque.due_date') }}</dt><dd><span class="rr-num">{{ @format_date($cheque->due_date) }}</span></dd></div>
                <div class="rr-deflist__item"><dt>{{ __('cheque.invoice') }}</dt><dd>
                    @if(!empty($cheque->transaction))
                        <span class="rr-code">{{ $is_receivable ? $cheque->transaction->invoice_no : $cheque->transaction->ref_no }}</span>
                    @else
                        {{ __('cheque.on_account') }}
                    @endif
                </dd></div>
            </dl>

            @if($action == 'collect' && !is_null($due_amount) && $cheque->amount - $due_amount > 0.0001)
                <div class="rr-alert rr-alert--warning" role="alert">{{ __('cheque.cheque_amount_exceeds') }}</div>
            @endif
            @if($action == 'bounce')
                <div class="rr-alert rr-alert--warning" role="alert">{{ __('cheque.bounce_hint') }}</div>
            @endif

            <div class="rr-form-wrap">
                <div class="rr-form-grid">
                    <div class="rr-field @if(!($action == 'collect' && !empty($accounts))) rr-field--full @endif">
                        <label class="rr-field__label" for="action_date">{{ $date_label }} <span class="rr-field__req">*</span></label>
                        <div class="rr-control">
                            <input type="date" name="action_date" id="action_date" class="rr-control__input" required value="{{ \Carbon::now()->format('Y-m-d') }}">
                        </div>
                    </div>
                    @if($action == 'collect' && !empty($accounts))
                        <div class="rr-field">
                            <label class="rr-field__label" for="account_id">{{ __('cheque.payment_account') }}</label>
                            <div class="rr-control">
                                <select name="account_id" id="account_id" class="rr-control__input rr-control__select">
                                    @foreach($accounts as $id => $name)
                                        <option value="{{ $id }}" @selected($cheque->account_id == $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                <span class="rr-control__chev"><i class="fa fa-chevron-down"></i></span>
                            </div>
                        </div>
                    @endif
                    <div class="rr-field rr-field--full">
                        <label class="rr-field__label" for="status_note">{{ $action == 'collect' ? __('cheque.note') : __('cheque.reason') }} <span class="rr-field__opt">({{ __('cheque.optional') }})</span></label>
                        <div class="rr-control rr-control--area">
                            <textarea name="status_note" id="status_note" class="rr-control__input rr-control__input--short" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <footer class="rr-modal__foot">
            <button type="submit" class="rr-btn {{ $btn_class }}">{{ __('cheque.confirm_action') }}</button>
            <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">{{ __('messages.close') }}</button>
        </footer>
        {!! Form::close() !!}
    </div>
</div>
