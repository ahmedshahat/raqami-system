@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('modules/construction/css/dashboard.css?v=' . config('construction.module_version')) }}">
    @yield('module_css')
    <link rel="stylesheet" href="{{ asset('modules/construction/css/refinement.css?v=' . config('construction.module_version')) }}">
    @stack('ct_quick_css')
@endsection

@section('content')
<div class="ct-dashboard ct-module-page" dir="rtl">
    @if(request()->routeIs('construction.overview.*') || request()->routeIs('construction.dashboard'))
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
        });
    </script>
    @yield('module_javascript')
    @stack('ct_quick_js')
@endsection
