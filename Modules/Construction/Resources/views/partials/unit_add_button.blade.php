{{-- زرار "وحدة جديدة" جنب عنوان قائمة الوحدة؛ بيفتح partials/unit_quick_create (لازم يكون متضاف في نفس الصفحة) --}}
@if(auth()->user()->can('unit.create') || (new \App\Utils\Util())->is_admin(auth()->user(), (int) session('user.business_id')))
<button type="button" class="rr-link-btn" data-rr-unit-add>@include('construction::partials.rr_icon', ['name' => 'plus', 'size' => 14])@lang('construction::lang.add_unit')</button>
@endif
