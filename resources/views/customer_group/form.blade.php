{{-- نافذة إضافة وتعديل مجموعة عملاء. تفتح داخل .customer_groups_modal والحفظ من سكربت customer_group.index --}}
@php
    $is_edit = ! empty($customer_group);
    $calculation_type = $is_edit ? $customer_group->price_calculation_type : 'percentage';
    $form_url = $is_edit
        ? action([\App\Http\Controllers\CustomerGroupController::class, 'update'], [$customer_group->id])
        : action([\App\Http\Controllers\CustomerGroupController::class, 'store']);
@endphp
<div class="modal-dialog rr-modal-dialog rr-modal-dialog--sm" role="document">
    <div class="modal-content rr-modal">
        {!! Form::open(['url' => $form_url, 'method' => $is_edit ? 'PUT' : 'POST', 'id' => 'rr_customer_group_form']) !!}
            <header class="rr-modal__head">
                <div class="rr-modal__titles">
                    <h2 class="rr-modal__title" id="rr_cg_form_title">@lang($is_edit ? 'customer_group.edit' : 'customer_group.add')</h2>
                </div>
                <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('messages.close')">@include('partials.rr.icon', ['name' => 'close'])</button>
            </header>

            <div class="rr-modal__body">
                <div class="rr-field">
                    <label class="rr-field__label" for="name">@lang('customer_group.name') <span class="rr-field__req">*</span></label>
                    <div class="rr-control">
                        <input class="rr-control__input" type="text" id="name" name="name" value="{{ $is_edit ? $customer_group->name : '' }}" required data-msg-required="@lang('customer_group.name_required')">
                    </div>
                </div>

                <div class="rr-field">
                    <label class="rr-field__label" for="price_calculation_type">@lang('customer_group.pricing_type')</label>
                    <div class="rr-control">
                        <select class="rr-control__input rr-control__select" id="price_calculation_type" name="price_calculation_type">
                            <option value="percentage" @selected($calculation_type == 'percentage')>@lang('customer_group.type_percentage')</option>
                            <option value="selling_price_group" @selected($calculation_type == 'selling_price_group')>@lang('customer_group.type_list')</option>
                        </select>
                    </div>
                </div>

                <div class="rr-field percentage-field @if($calculation_type != 'percentage') hide @endif">
                    <label class="rr-field__label" for="amount">@lang('customer_group.percentage')</label>
                    <div class="rr-control">
                        <input class="rr-control__input rr-control__input--num input_number" type="text" id="amount" name="amount" dir="ltr" value="{{ $is_edit ? @num_format($customer_group->amount) : '' }}" placeholder="0">
                    </div>
                    <span class="rr-field__hint">@lang('customer_group.percentage_hint')</span>
                </div>

                <div class="rr-field selling_price_group-field @if($calculation_type != 'selling_price_group') hide @endif">
                    <label class="rr-field__label" for="selling_price_group_id">@lang('customer_group.price_list')</label>
                    <div class="rr-control">
                        <select class="rr-control__input rr-control__select" id="selling_price_group_id" name="selling_price_group_id">
                            @foreach($price_groups as $price_group_id => $price_group_name)
                                <option value="{{ $price_group_id }}" @selected($is_edit && $customer_group->selling_price_group_id == $price_group_id)>{{ $price_group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(count($price_groups) == 0)
                        <span class="rr-field__hint">@lang('customer_group.no_price_lists')</span>
                    @endif
                </div>
            </div>

            <footer class="rr-modal__foot">
                <button type="submit" class="rr-btn rr-btn--primary">@include('partials.rr.icon', ['name' => 'check'])@lang('customer_group.save')</button>
                <button type="button" class="rr-btn rr-btn--ghost" data-dismiss="modal">@lang('messages.close')</button>
            </footer>
        {!! Form::close() !!}
    </div>
</div>
