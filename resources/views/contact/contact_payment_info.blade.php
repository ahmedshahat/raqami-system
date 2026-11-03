@if( $contact->type == 'supplier' || $contact->type == 'both')
    <strong>@lang('report.total_purchase')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->total_purchase }}</span>
    </p>
    <strong>@lang('contact.total_purchase_paid')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->purchase_paid }}</span>
    </p>
    <strong>@lang('contact.total_purchase_due')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->total_purchase - $contact->purchase_paid }}</span>
    </p>
@endif
@if( $contact->type == 'customer' || $contact->type == 'both')
    <strong>@lang('report.total_sell')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->total_invoice }}</span>
    </p>
    <strong>@lang('contact.total_sale_paid')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->invoice_received }}</span>
    </p>
    <strong>@lang('contact.total_sale_due')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->total_invoice - $contact->invoice_received }}</span>
    </p>
@endif
@if(!empty($contact->opening_balance) && $contact->opening_balance != '0.00')
    <strong>@lang('lang_v1.opening_balance')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->opening_balance }}</span>
    </p>
    <strong>@lang('lang_v1.opening_balance_due')</strong>
    <p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->opening_balance - $contact->opening_balance_paid }}</span>
    </p>
@endif
<strong>@lang('lang_v1.advance_balance')</strong>
<p class="text-muted">
    <span class="display_currency" data-currency_symbol="true">
    {{ $contact->balance }}</span>
</p>
@php
    $contact_pending_cheques = \App\Cheque::where('business_id', $contact->business_id)
        ->where('contact_id', $contact->id)
        ->where('status', \App\Cheque::STATUS_PENDING)
        ->selectRaw('type, SUM(amount) as total, MIN(due_date) as next_due')
        ->groupBy('type')
        ->get()
        ->keyBy('type');
@endphp
@foreach(['receivable' => ['customer', 'both', 'total_invoice', 'invoice_received'], 'payable' => ['supplier', 'both', 'total_purchase', 'purchase_paid']] as $cheque_type => $cfg)
    @if(in_array($contact->type, [$cfg[0], $cfg[1]]) && !empty($contact_pending_cheques[$cheque_type]))
        <strong>@lang('cheque.pending_cheques_on_contact') ({{ __('cheque.' . $cheque_type . '_short') }})</strong>
        <p class="text-muted">
            <span class="display_currency" data-currency_symbol="true">{{ $contact_pending_cheques[$cheque_type]->total }}</span>
            <br><small>@lang('cheque.next_due'): {{ @format_date($contact_pending_cheques[$cheque_type]->next_due) }}</small>
        </p>
        <strong>@lang('cheque.uncovered_due')</strong>
        <p class="text-muted">
            <span class="display_currency" data-currency_symbol="true">{{ $contact->{$cfg[2]} - $contact->{$cfg[3]} - $contact_pending_cheques[$cheque_type]->total }}</span>
        </p>
    @endif
@endforeach
