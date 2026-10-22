@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('modules/construction/css/dashboard.css?v=' . config('construction.module_version')) }}">
    @yield('module_css')
    <link rel="stylesheet" href="{{ asset('modules/construction/css/refinement.css?v=' . config('construction.module_version')) }}">
    {{-- نظام التصميم المشترك للموديول (متغيرات + مكوّنات rr-*) — انظر CLAUDE.md --}}
    <link rel="stylesheet" href="{{ asset('css/raqmi.css?v=' . config('construction.module_version')) }}">
    @stack('ct_quick_css')
@endsection

@section('content')
<div class="ct-dashboard ct-module-page" dir="rtl">
    @if(request()->routeIs('construction.dashboard'))
        @include('construction::partials.module_header')
    @endif
    @include('construction::partials.section_tabs')
    <div class="ct-module-content">@yield('module_content')</div>
</div>
@endsection

@section('javascript')
    <script>
        $(document).ready(function () {
            $('.ct-date-picker').datetimepicker({
                format: moment_date_format,
                ignoreReadonly: true
            });
            // التقويم جوه نافذة rr-modal: يطلع برّه النافذة بدل ما يعمل سكرول جوّاها
            // (لو جسم النافذة نفسه محتاج سكرول على شاشة صغيرة بنسيبه زي ما هو)
            $(document).on('dp.show', '.rr-modal .ct-date-picker', function () {
                var body = $(this).closest('.rr-modal__body')[0];
                var pickerHeight = $(body).find('.bootstrap-datetimepicker-widget').outerHeight(true) || 0;
                if (body && body.scrollHeight - pickerHeight <= body.clientHeight + 1) {
                    $(this).closest('.rr-modal').addClass('rr-modal--picker-open');
                }
            }).on('dp.hide', '.rr-modal .ct-date-picker', function () {
                $(this).closest('.rr-modal').removeClass('rr-modal--picker-open');
            });
        });
    </script>
    @yield('module_javascript')
    @stack('ct_quick_js')
@endsection
