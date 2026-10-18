@extends('layouts.app')
@section('title', __('accounting::lang.edit_voucher'))
@section('content')
@include('accounting::layouts.nav')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold">@lang('accounting::lang.edit_voucher') — {{ $voucher->ref_no }}</h1></section>
<section class="content">
    @component('components.widget', ['class' => 'box-solid'])
        {!! Form::open(['url' => action([\Modules\Accounting\Http\Controllers\VoucherController::class, 'update'], [$voucher->id]), 'method' => 'put', 'id' => 'voucher_form']) !!}
            @include('accounting::voucher.form')
            <div class="text-center">
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white"><i class="fas fa-save"></i> @lang('messages.update')</button>
                <a href="{{ action([\Modules\Accounting\Http\Controllers\VoucherController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">@lang('messages.cancel')</a>
            </div>
        {!! Form::close() !!}
    @endcomponent
</section>
@stop
@section('javascript')
@include('accounting::accounting.common_js')
<script>$(function(){ $('#operation_date').datetimepicker({format: moment_date_format + ' ' + moment_time_format, ignoreReadonly: true}); $('#voucher_form').validate(); });</script>
@endsection

