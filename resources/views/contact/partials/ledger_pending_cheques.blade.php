{{-- Pending cheques (not yet collected / cleared) for the contact: they are not payments yet. --}}
@if(!empty($pending_cheques) && count($pending_cheques) > 0)
@php
	$cheque_types = $pending_cheques->pluck('type')->unique();
	$show_type = $cheque_types->count() > 1;
	$pending_total = $pending_cheques->sum('amount');
	$overall_due = $ledger_details['all_balance_due'] ?? ($ledger_details['balance_due'] - $ledger_details['ledger_discount']);
@endphp
<div class="col-md-12 col-sm-12 @if(!empty($for_pdf)) width-100 @endif" style="margin-bottom: 10px;">
	<div style="border: 1px solid #000; padding: 10px;">
		<b>@lang('cheque.pending_cheques_on_contact')</b>
		<small> - @lang('cheque.pending_cheques_note')</small>
		<table class="table table-condensed table-bordered @if(!empty($for_pdf)) table-pdf td-border @endif" style="margin-top: 6px; margin-bottom: 6px;">
			<thead>
				<tr>
					<th>@lang('cheque.cheque_no')</th>
					<th>@lang('cheque.bank_name')</th>
					@if($show_type)<th>@lang('lang_v1.type')</th>@endif
					<th>@lang('cheque.invoice')</th>
					<th>@lang('cheque.due_date')</th>
					<th class="align-right">@lang('cheque.amount')</th>
				</tr>
			</thead>
			<tbody>
				@foreach($pending_cheques as $cheque)
					<tr>
						<td>{{ $cheque->cheque_number }}</td>
						<td>{{ $cheque->bank_name }}</td>
						@if($show_type)<td>@lang('cheque.' . $cheque->type . '_short')</td>@endif
						<td>
							@if(!empty($cheque->transaction))
								{{ $cheque->type == 'receivable' ? $cheque->transaction->invoice_no : $cheque->transaction->ref_no }}
							@else
								@lang('cheque.on_account')
							@endif
						</td>
						<td>{{ @format_date($cheque->due_date) }}</td>
						<td class="align-right">@format_currency($cheque->amount)</td>
					</tr>
				@endforeach
			</tbody>
		</table>
		<table class="table table-condensed no-border text-left align-left @if(!empty($for_pdf)) table-pdf @endif" style="margin-bottom: 0;">
			@if($show_type)
				@foreach($cheque_types as $type)
					<tr>
						<td>@lang('cheque.pending_cheques_on_contact') - @lang('cheque.' . $type . '_short')</td>
						<td class="align-right">@format_currency($pending_cheques->where('type', $type)->sum('amount'))</td>
					</tr>
				@endforeach
			@else
				<tr>
					<td><strong>@lang('lang_v1.balance_due')</strong></td>
					<td class="align-right">@format_currency($overall_due)</td>
				</tr>
				<tr>
					<td>@lang('cheque.pending_cheques_on_contact')</td>
					<td class="align-right">@format_currency($pending_total)</td>
				</tr>
				<tr>
					<td><strong>@lang('cheque.uncovered_due')</strong></td>
					<td class="align-right"><strong>@format_currency($overall_due - $pending_total)</strong></td>
				</tr>
			@endif
		</table>
	</div>
</div>
@endif
