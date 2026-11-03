@if($customer_groups->isEmpty())
    <div class="rr-empty">
        <span class="rr-empty__icon">@include('partials.rr.icon', ['name' => 'users', 'size' => 24])</span>
        <h2 class="rr-empty__title">@lang('customer_group.empty_title')</h2>
        <p class="rr-empty__text">@lang('customer_group.empty_text')</p>
    </div>
@else
    <div class="rr-table-scroll">
        <table class="rr-table rr-table--fit">
            <thead>
                <tr>
                    <th class="rr-th">@lang('customer_group.col_name')</th>
                    <th class="rr-th">@lang('customer_group.col_pricing')</th>
                    <th class="rr-th"><span class="sr-only">@lang('customer_group.col_actions')</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($customer_groups as $group)
                    @php
                        $customers = (int) ($customer_counts[$group->id] ?? 0);
                        $by_percentage = $group->price_calculation_type == 'percentage';
                    @endphp
                    <tr class="rr-tr">
                        <td class="rr-td">
                            <div class="rr-cell-stack">
                                <span class="rr-cell-stack__main">{{ $group->name }}</span>
                                <span class="rr-td__sub">@lang('customer_group.customers_count', ['count' => $customers])</span>
                            </div>
                        </td>
                        <td class="rr-td">
                            @if(! $by_percentage)
                                <span class="rr-badge rr-badge--info">@lang('customer_group.pricing_list'){{ $group->selling_price_group ? ': ' . $group->selling_price_group : '' }}</span>
                            @elseif($group->amount > 0)
                                <span class="rr-badge rr-badge--warning">@lang('customer_group.pricing_increase') <span class="rr-num">{{ @num_format($group->amount) }}%</span></span>
                            @elseif($group->amount < 0)
                                <span class="rr-badge rr-badge--success">@lang('customer_group.pricing_decrease') <span class="rr-num">{{ @num_format(abs($group->amount)) }}%</span></span>
                            @else
                                <span class="rr-badge">@lang('customer_group.pricing_none')</span>
                            @endif
                        </td>
                        <td class="rr-td rr-td--actions">
                            <div class="rr-row-actions">
                                @can('customer.update')
                                    <button type="button" class="rr-btn rr-btn--secondary rr-btn--sm rr-btn--icon btn-modal" data-href="{{ action([\App\Http\Controllers\CustomerGroupController::class, 'edit'], [$group->id]) }}" data-container=".customer_groups_modal" title="@lang('messages.edit')" aria-label="@lang('messages.edit') {{ $group->name }}">@include('partials.rr.icon', ['name' => 'edit'])</button>
                                @endcan
                                @can('customer.delete')
                                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon rr-cg-delete" data-href="{{ action([\App\Http\Controllers\CustomerGroupController::class, 'destroy'], [$group->id]) }}" data-customers="{{ $customers }}" title="@lang('messages.delete')" aria-label="@lang('messages.delete') {{ $group->name }}">@include('partials.rr.icon', ['name' => 'trash'])</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
