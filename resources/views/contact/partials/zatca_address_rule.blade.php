{{--
    موديول زاتكا (app.js بتاعه) بيخلي حقول العنوان إجبارية لأي عميل له رقم ضريبي، ومتحمّل لكل الأنشطة مع كاش عام،
    فالقاعدة بتتطبق هنا حسب النشاط: اللي مش مربوط بزاتكا بتتشال عنده، والمربوط بتفضل برسالة توضح السبب.
--}}
@php
    $zatca_active = \App\BusinessLocation::where('business_id', session('user.business_id'))
        ->whereNotNull('zatca_response')->where('zatca_response', '!=', '')->exists();
@endphp
<script data-rr-zatca-active="{{ $zatca_active ? 1 : 0 }}">
    (function() {
        var active = @json($zatca_active), msg = @json(__('contact_form.zatca_address_required'));
        var fields = '#street_name, #building_number, #city, #zip_code, #additional_number, #state, #country';

        function bind() {
            // setTimeout عشان يشتغل بعد معالج الموديول على نفس الحدث
            $(document).off('shown.bs.modal.rrZatca').on('shown.bs.modal.rrZatca', '.contact_modal', function() {
                var $fields = $(this).find(fields);
                setTimeout(function() {
                    active ? $fields.attr('data-msg-required', msg) : $fields.removeAttr('required');
                }, 0);
            });
        }
        // النوافذ بتتحمّل بعد jQuery، والفورم القديم في صفحات البيع والشراء بيتحمّل قبله
        window.jQuery ? bind() : document.addEventListener('DOMContentLoaded', bind);
    })();
</script>
