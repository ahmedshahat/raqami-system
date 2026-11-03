{{--
    كشف الحساب على شاشة show_v2 للنموذجين التفصيلي (format_1) وبالرصيد (format_3).
    الـ PDF والإرسال بيفضلوا على contact.ledger و contact.ledger_format_3.
    الـ id ledger_table والكلاسات delete_ledger_discount و btn-modal الجافاسكربت في show_js بيعتمد عليهم.
--}}
@php
    $with_balance = $format == 'format_3';
    $rows = collect($ledger_details['ledger'])->filter(function ($row) {
        return ! ($row['type'] == __('lang_v1.opening_balance') && empty((float) $row['debit']) && empty((float) $row['credit']));
    });
    $has_location = $rows->pluck('location')->filter()->unique()->count() > 1;
    $kind_tones = ['sell' => 'brand', 'purchase' => 'brand', 'sell_return' => 'warning', 'purchase_return' => 'warning', 'ledger_discount' => 'info'];
    $status_tones = [__('lang_v1.paid') => 'success', __('lang_v1.partial') => 'info', __('lang_v1.due') => 'warning'];
    $doc_urls = [
        'sell' => fn ($id) => action([\App\Http\Controllers\SellController::class, 'show'], [$id]),
        'purchase' => fn ($id) => action([\App\Http\Controllers\PurchaseController::class, 'show'], [$id]),
    ];
    $money = fn ($value) => view('partials.rr.money', ['value' => $value])->render();
    $is_customer = in_array($contact->type, ['customer', 'both']);
    $is_supplier = in_array($contact->type, ['supplier', 'both']);
    // الأخضر للعمود اللي بيقلل المستحق: الدائن عند العميل والمدين عند المورد
    $is_supplier_only = $contact->type == 'supplier';
    $period_due = $ledger_details['balance_due'] - $ledger_details['ledger_discount'];
@endphp

<section class="rr-cs__period" aria-label="@lang('contact_show.period_summary')">
    <p class="rr-cs__period-title"><strong>@lang('contact_show.period_summary')</strong> <span>@lang('contact_show.period_from_to', ['start' => $ledger_details['start_date'], 'end' => $ledger_details['end_date']])</span></p>
    <dl class="rr-cs__period-grid">
        <div><dt>@lang('contact_show.opening_balance')</dt><dd>{!! $money($ledger_details['beginning_balance']) !!}</dd></div>
        @if($is_customer)
            <div><dt>@lang('contact_show.invoices')</dt><dd>{!! $money($ledger_details['total_invoice']) !!}</dd></div>
        @endif
        @if($is_supplier)
            <div><dt>@lang('contact_show.purchases')</dt><dd>{!! $money($ledger_details['total_purchase']) !!}</dd></div>
        @endif
        <div class="is-success"><dt>@lang('contact_show.payments')</dt><dd>{!! $money($ledger_details['total_paid']) !!}</dd></div>
        @if($ledger_details['ledger_discount'] > 0)
            <div class="is-info"><dt>@lang('contact_show.discounts')</dt><dd>{!! $money($ledger_details['ledger_discount']) !!}</dd></div>
        @endif
        <div class="{{ $period_due > 0 ? 'is-warning' : '' }} is-total"><dt>@lang('contact_show.period_due')</dt><dd>{!! $money($period_due) !!}</dd></div>
    </dl>
</section>

@include('contact.partials.ledger_pending_cheques')

@if($rows->isEmpty())
    <div class="rr-empty">
        <span class="rr-empty__icon">@include('partials.rr.icon', ['name' => 'list', 'size' => 22])</span>
        <strong>@lang('contact_show.no_moves')</strong>
        <p class="rr-empty__text">@lang('contact_show.no_moves_hint')</p>
    </div>
@else
    <div class="rr-table-scroll rr-cs__ledger-wrap">
        <table class="rr-table rr-cs__ledger-table" id="ledger_table">
            <thead>
                <tr>
                    <th class="rr-th">@lang('contact_show.col_date')</th>
                    <th class="rr-th">@lang('contact_show.col_type')</th>
                    <th class="rr-th">@lang('contact_show.col_ref')</th>
                    @if($has_location)<th class="rr-th">@lang('contact_show.location')</th>@endif
                    <th class="rr-th">@lang('contact_show.col_status')</th>
                    <th class="rr-th rr-th--num">@lang('contact_show.col_debit')</th>
                    <th class="rr-th rr-th--num">@lang('contact_show.col_credit')</th>
                    @if($with_balance)<th class="rr-th rr-th--num">@lang('contact_show.col_balance')</th>@endif
                    <th class="rr-th">@lang('contact_show.col_method')</th>
                    <th class="rr-th">@lang('contact_show.col_note')</th>
                    @if(! empty($can_manage_discount))<th class="rr-th"><span class="rr-sr">@lang('messages.action')</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $data)
                    @php
                        $kind = $data['transaction_type'] ?? null;
                        $is_payment = array_key_exists('payment_method_key', $data);
                        $tone = $is_payment ? 'success' : ($kind_tones[$kind] ?? null);
                        $label = $is_payment ? __('contact_show.kind_payment') : (isset($kind_tones[$kind]) ? __('contact_show.kind_' . $kind) : $data['type']);
                        $is_discount = $kind == 'ledger_discount' && ! empty($data['transaction_id']);
                        $note = trim(strip_tags(str_replace('<small>', ' ', (string) $data['others'])));
                    @endphp
                    <tr class="rr-tr">
                        <td class="rr-td rr-td--muted"><span class="rr-num">{{ @format_datetime($data['date']) }}</span></td>
                        <td class="rr-td"><span class="rr-badge {{ $tone ? 'rr-badge--' . $tone : '' }}">{{ $label }}</span></td>
                        <td class="rr-td">
                            @if(! empty($data['ref_no']) && isset($doc_urls[$kind]))
                                <a href="#" class="btn-modal rr-code" data-href="{{ $doc_urls[$kind]($data['transaction_id']) }}" data-container=".view_modal">{{ $data['ref_no'] }}</a>
                            @else
                                <span class="rr-code">{{ $data['ref_no'] }}</span>
                            @endif
                        </td>
                        @if($has_location)<td class="rr-td rr-td--muted">{{ $data['location'] }}</td>@endif
                        <td class="rr-td">
                            @if(! empty($data['payment_status']))
                                <span class="rr-badge {{ isset($status_tones[$data['payment_status']]) ? 'rr-badge--' . $status_tones[$data['payment_status']] : '' }}">{{ $data['payment_status'] }}</span>
                            @endif
                        </td>
                        <td class="rr-td rr-td--num">@if($data['debit'] != '')<span class="rr-num {{ $is_supplier_only ? 'rr-amount--success' : '' }}">{!! @num_format($data['debit']) !!}</span>@endif</td>
                        <td class="rr-td rr-td--num">@if($data['credit'] != '')<span class="rr-num {{ $is_supplier_only ? '' : 'rr-amount--success' }}">{!! @num_format($data['credit']) !!}</span>@endif</td>
                        @if($with_balance)<td class="rr-td rr-td--num"><span class="rr-num">{{ str_replace([' ' . __('lang_v1.dr'), ' ' . __('lang_v1.cr')], [' ' . __('contact_statement.dr'), ' ' . __('contact_statement.cr')], $data['balance']) }}</span></td>@endif
                        <td class="rr-td rr-td--muted">{{ $data['payment_method'] }}</td>
                        <td class="rr-td rr-td--muted rr-cs__ledger-note">{{ $note }}</td>
                        @if(! empty($can_manage_discount))
                            <td class="rr-td rr-td--actions">
                                @if($is_discount)
                                    <span class="rr-row-actions">
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon btn-modal" data-href="{{ action([\App\Http\Controllers\LedgerDiscountController::class, 'edit'], ['ledger_discount' => $data['transaction_id']]) }}" data-container="#edit_ledger_discount_modal" aria-label="@lang('contact_show.edit_discount')" title="@lang('contact_show.edit_discount')">@include('partials.rr.icon', ['name' => 'edit', 'size' => 16])</button>
                                        <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon delete_ledger_discount" data-href="{{ action([\App\Http\Controllers\LedgerDiscountController::class, 'destroy'], ['ledger_discount' => $data['transaction_id']]) }}" aria-label="@lang('contact_show.delete_discount')" title="@lang('contact_show.delete_discount')">@include('partials.rr.icon', ['name' => 'trash', 'size' => 16])</button>
                                    </span>
                                @endif
                            </td>
                        @endif
                    </tr>
                    @if($with_balance && in_array($kind, ['sell', 'purchase']))
                        <tr class="rr-cs__ledger-lines">
                            <td colspan="{{ 8 + ($has_location ? 1 : 0) + 1 + (! empty($can_manage_discount) ? 1 : 0) }}">
                                @if($kind == 'sell')
                                    @include('sale_pos.partials.sale_line_details', ['sell' => (object) $data, 'enabled_modules' => [], 'is_warranty_enabled' => false, 'for_ledger' => true])
                                @else
                                    @include('contact.partials.ledger_purchase_lines_details', ['purchase' => (object) $data])
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif
