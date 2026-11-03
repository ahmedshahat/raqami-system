{{--
    نافذة "وحدة جديدة" لشاشات البنود. الاستخدام:
    - جنب أي قائمة وحدة: <button type="button" class="rr-link-btn" data-rr-unit-add>…</button>
      والقائمة نفسها عليها data-rr-unit-select (وكمان جوه <template> صفوف الإضافة).
    - الصفحة بتعمل @include('construction::partials.unit_quick_create') مرة واحدة برّه <main>.
    بعد الحفظ: الوحدة بتتضاف لكل قوائم الوحدات في الصفحة وفي قوالب الصفوف، وبتتختار في القائمة اللي فتحت النافذة.
--}}
@if(auth()->user()->can('unit.create') || (new \App\Utils\Util())->is_admin(auth()->user(), (int) session('user.business_id')))
<div class="modal fade rr-scope" id="rr-unit-create" tabindex="-1" role="dialog" aria-labelledby="rr-unit-create-title">
    <div class="modal-dialog rr-modal-dialog rr-modal-dialog--compact" role="document">
        <div class="modal-content rr-modal">
            <form method="POST" action="{{ route('construction.units.store') }}" data-rr-unit-form novalidate>
                @csrf
                <header class="rr-modal__head">
                    <div class="rr-modal__titles">
                        <h2 class="rr-modal__title" id="rr-unit-create-title">@lang('construction::lang.new_unit')</h2>
                        <p class="rr-modal__desc">@lang('construction::lang.new_unit_hint')</p>
                    </div>
                    <button type="button" class="rr-btn rr-btn--ghost rr-btn--sm rr-btn--icon" data-dismiss="modal" aria-label="@lang('construction::lang.close_dialog')">@include('construction::partials.rr_icon', ['name' => 'close', 'size' => 18])</button>
                </header>

                <div class="rr-modal__body">
                    <div class="rr-alert rr-alert--danger ct-quick-errors" role="alert"></div>
                    <div class="rr-form-wrap">
                        <div class="rr-form-grid">
                            <div class="rr-field">
                                <label class="rr-field__label" for="rr-unit-create-name">@lang('construction::lang.unit_name')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                <div class="rr-control">
                                    <input id="rr-unit-create-name" class="rr-control__input" name="actual_name" maxlength="190" placeholder="@lang('construction::lang.unit_name_placeholder')" required>
                                </div>
                            </div>
                            <div class="rr-field">
                                <label class="rr-field__label" for="rr-unit-create-short">@lang('construction::lang.unit_short_name')<span class="rr-field__req" aria-hidden="true">*</span></label>
                                <div class="rr-control">
                                    <input id="rr-unit-create-short" class="rr-control__input" name="short_name" maxlength="50" placeholder="@lang('construction::lang.unit_short_name_placeholder')" required>
                                </div>
                            </div>
                            <div class="rr-field rr-field--full">
                                <label class="rr-toggle"><input type="checkbox" class="rr-toggle__input" name="allow_decimal" value="1" checked><span>@lang('construction::lang.unit_allow_decimal')</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="rr-modal__foot">
                    <button class="rr-btn rr-btn--primary" type="submit">@include('construction::partials.rr_icon', ['name' => 'check'])@lang('construction::lang.save_unit')</button>
                    <button type="button" class="rr-btn rr-btn--secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                </footer>
            </form>
        </div>
    </div>
</div>

@push('ct_quick_js')
<script>
(function () {
    var modal = document.getElementById('rr-unit-create');
    var form = modal.querySelector('form');
    var errors = form.querySelector('.ct-quick-errors');
    var targetSelect = null;

    function showErrors(text) { errors.textContent = text; errors.style.display = text ? '' : 'none'; }
    function unitSelects() {
        var selects = Array.prototype.slice.call(document.querySelectorAll('select[data-rr-unit-select]'));
        document.querySelectorAll('template').forEach(function (template) {
            selects = selects.concat(Array.prototype.slice.call(template.content.querySelectorAll('select[data-rr-unit-select]')));
        });
        return selects;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-rr-unit-add]');
        if (!button) return;
        event.preventDefault();
        var holder = button.closest('.rr-field, .rr-entry-field');
        targetSelect = holder ? holder.querySelector('select[data-rr-unit-select]') : null;
        form.reset();
        showErrors('');
        $(modal).modal('show');
    });
    // ممكن تتفتح فوق نافذة تعديل بند: تطلع فوقها، ولما تتقفل الصفحة تفضل عارفة إن فيه نافذة مفتوحة
    $(modal).on('show.bs.modal', function () {
        modal.style.zIndex = 1070;
        setTimeout(function () { $('.modal-backdrop').last().css('z-index', 1065); }, 0);
    });
    $(modal).on('shown.bs.modal', function () { form.elements.actual_name.focus(); });
    $(modal).on('hidden.bs.modal', function () {
        if ($('.modal.in').length) document.body.classList.add('modal-open');
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;
        var submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        showErrors('');
        try {
            var response = await fetch(form.action, {
                method: 'POST', body: new FormData(form),
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            var data = await response.json().catch(function () { return {}; });
            if (!response.ok) {
                var messages = data.errors ? Object.values(data.errors).reduce(function (all, list) { return all.concat(list); }, []) : [];
                showErrors(messages.join('\n') || data.message || @json(__('construction::lang.validation_failed')));
                return;
            }
            unitSelects().forEach(function (select) {
                if (!select.querySelector('option[value="' + data.id + '"]')) select.appendChild(new Option(data.label, data.id));
            });
            if (targetSelect) {
                targetSelect.value = String(data.id);
                targetSelect.dispatchEvent(new Event('change', {bubbles: true}));
            }
            $(modal).modal('hide');
            if (window.toastr) toastr.success(data.message);
            if (targetSelect) targetSelect.focus();
        } catch (error) {
            showErrors(@json(__('messages.something_went_wrong')));
        } finally {
            submit.disabled = false;
        }
    });
})();
</script>
@endpush
@endif
