@php
    $sectionTabs = [];
    $activeSectionTab = null;

    if (
        (request()->routeIs('construction.projects.*')
        && ! request()->routeIs('construction.projects.measurements.*')
        && ! request()->routeIs('construction.projects.certificates.*'))
        || request()->routeIs('construction.project-items.*')
        || request()->routeIs('construction.contracts.*')
    ) {
        $activeSectionTab = match (true) {
            request()->routeIs('construction.projects.boq.*') || request()->routeIs('construction.project-items.*') => 'project-items',
            request()->routeIs('construction.projects.contracts.*') || request()->routeIs('construction.contracts.*') => 'contracts',
            default => 'projects',
        };
        $sectionTabs = [
            ['projects', 'building', 'tab_projects', route('construction.projects.index')],
            ['project-items', 'list', 'tab_boq', route('construction.project-items.index')],
            ['contracts', 'file', 'tab_contracts', route('construction.contracts.index')],
        ];
    } elseif (
        request()->routeIs('construction.measurements.*')
        || request()->routeIs('construction.ipcs.*')
        || request()->routeIs('construction.projects.measurements.*')
        || request()->routeIs('construction.projects.certificates.*')
    ) {
        $activeSectionTab = request()->routeIs('construction.ipcs.*') || request()->routeIs('construction.projects.certificates.*')
            ? 'certificates'
            : 'measurements';
        $sectionTabs = [
            ['measurements', 'ruler', 'tab_measurements', route('construction.measurements.index')],
            ['certificates', 'check-circle', 'customer_certificates', route('construction.ipcs.index')],
        ];
    }
@endphp

@if($sectionTabs)
    {{-- تصميم rr؛ ct-section-tabs و is-active و data-section-tab متسابين عشان الاختبارات والسكريبتات --}}
    <nav class="ct-section-tabs rr-scope rr-tabs" aria-label="@lang('construction::lang.section_navigation')">
        @foreach($sectionTabs as [$tab, $icon, $label, $url])
            <a href="{{ $url }}" class="ct-section-tab rr-tab {{ $activeSectionTab === $tab ? 'is-active' : '' }}" @if($activeSectionTab === $tab) aria-current="page" @endif data-section-tab="{{ $tab }}">
                @include('construction::partials.rr_icon', ['name' => $icon, 'size' => 18])<span>@lang('construction::lang.'.$label)</span>
            </a>
        @endforeach
    </nav>
@endif
