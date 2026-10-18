<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('accounting::lang.journal_entry_details') — {{ $journal->ref_no }}</h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>@lang('accounting::lang.journal_date'):</strong>
                    {{ @format_date($journal->operation_date) }}
                </div>
                <div class="col-md-8">
                    <strong>@lang('lang_v1.additional_notes'):</strong>
                    {{ $journal->note ?: __('accounting::lang.no_notes') }}
                </div>
            </div>

            <br>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('accounting::lang.account')</th>
                            <th class="text-center">@lang('accounting::lang.debit')</th>
                            <th class="text-center">@lang('accounting::lang.credit')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalDebit = 0;
                            $totalCredit = 0;
                        @endphp
                        @foreach($lines as $line)
                            @php
                                $debit = $line->type === 'debit' ? $line->amount : 0;
                                $credit = $line->type === 'credit' ? $line->amount : 0;
                                $totalDebit += $debit;
                                $totalCredit += $credit;
                            @endphp
                            <tr>
                                <td>{{ optional($line->account)->name }}</td>
                                <td class="text-center">{{ $debit ? @num_format($debit) : '' }}</td>
                                <td class="text-center">{{ $credit ? @num_format($credit) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>@lang('accounting::lang.total')</th>
                            <th class="text-center">{{ @num_format($totalDebit) }}</th>
                            <th class="text-center">{{ @num_format($totalCredit) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
                @lang('messages.close')
            </button>
        </div>
    </div>
</div>
