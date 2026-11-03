{{-- زر إظهار/إخفاء كلمة المرور داخل .rr-control — $target: محدد الحقل --}}
<button type="button" class="rr-control__toggle" data-rr-password="{{ $target }}" aria-label="@lang('guest.show_password')"
    data-label-show="@lang('guest.show_password')" data-label-hide="@lang('guest.hide_password')"
    onclick="var i=document.querySelector(this.dataset.rrPassword),v=i.type==='text';i.type=v?'password':'text';this.classList.toggle('is-visible',!v);this.setAttribute('aria-label',v?this.dataset.labelShow:this.dataset.labelHide);">
    <span class="rr-control__toggle-show">@include('partials.rr.icon', ['name' => 'eye', 'size' => 18])</span>
    <span class="rr-control__toggle-hide">@include('partials.rr.icon', ['name' => 'eye-off', 'size' => 18])</span>
</button>
