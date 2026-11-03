{{-- تنبيهات التقرير: $warnings = مجموعة نصوص --}}
@if($warnings->isNotEmpty())
    <div class="rr-alert rr-alert--warning" role="status">
        <div>
            <strong>@lang('construction::lang.management_alerts')</strong>
            <ul class="rr-error-list">@foreach($warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul>
        </div>
    </div>
@endif
