{{-- شاشة مجموعات العملاء بتصميم رقمي. الجدول بيتحمل من partials.list ويتحدث بعد كل حفظ أو حذف --}}
@extends('layouts.app')
@section('title', __('customer_group.title'))

@section('content')
<main class="content rr-page rr-list-page" aria-labelledby="rr_cg_title">
    <header class="rr-list-page__head">
        <div class="rr-list-page__titles">
            <h1 id="rr_cg_title" class="rr-head__title">@lang('customer_group.title')</h1>
            <p class="rr-head__sub">@lang('customer_group.sub')</p>
        </div>
        @can('customer.create')
            <div class="rr-head__actions">
                <button type="button" class="rr-btn rr-btn--primary btn-modal" data-href="{{ action([\App\Http\Controllers\CustomerGroupController::class, 'create']) }}" data-container=".customer_groups_modal">@include('partials.rr.icon', ['name' => 'plus', 'size' => 20])@lang('customer_group.add')</button>
            </div>
        @endcan
    </header>

    <section class="rr-table-wrap" id="rr_cg_list" aria-labelledby="rr_cg_title">
        @include('customer_group.partials.list')
    </section>
</main>

<div class="modal fade rr-scope customer_groups_modal" tabindex="-1" role="dialog" aria-labelledby="rr_cg_form_title"></div>
@stop

@section('javascript')
<script type="text/javascript">
    $(function() {
        var LIST_URL = @json(action([\App\Http\Controllers\CustomerGroupController::class, 'index']));
        var STR = @json(['delete_text' => __('customer_group.delete_text'), 'delete_with_customers' => __('customer_group.delete_with_customers')]);

        function reloadList() {
            $('#rr_cg_list').load(LIST_URL);
        }

        $(document).on('change', '#price_calculation_type', function() {
            var is_percentage = $(this).val() == 'percentage';
            $('.percentage-field').toggleClass('hide', !is_percentage);
            $('.selling_price_group-field').toggleClass('hide', is_percentage);
        });

        $('.customer_groups_modal').on('shown.bs.modal', function() {
            $(this).find('#name').trigger('focus');
        });

        $(document).on('submit', '#rr_customer_group_form', function(e) {
            e.preventDefault();
            var form = $(this);
            form.validate({
                errorPlacement: function(error, element) {
                    error.insertAfter(element.closest('.rr-control'));
                },
            });
            if (!form.valid()) return;

            var save = form.find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                method: 'POST',
                url: form.attr('action'),
                dataType: 'json',
                data: form.serialize(),
                success: function(result) {
                    if (result.success) {
                        $('.customer_groups_modal').modal('hide');
                        toastr.success(result.msg);
                        reloadList();
                    } else {
                        toastr.error(result.msg);
                    }
                },
                complete: function() {
                    save.prop('disabled', false);
                },
            });
        });

        $(document).on('click', '.rr-cg-delete', function() {
            var button = $(this);
            var customers = parseInt(button.data('customers'), 10) || 0;
            swal({
                title: LANG.sure,
                text: customers > 0 ? STR.delete_with_customers.replace(':count', customers) : STR.delete_text,
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(function(will_delete) {
                if (!will_delete) return;
                $.ajax({
                    method: 'DELETE',
                    url: button.data('href'),
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            reloadList();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            });
        });
    });
</script>
@endsection
