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
            ['projects', 'fa-building', 'tab_projects', route('construction.projects.index')],
            ['project-items', 'fa-list-ol', 'tab_boq', route('construction.project-items.index')],
            ['contracts', 'fa-file-signature', 'tab_contracts', route('construction.contracts.index')],
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
            ['measurements', 'fa-ruler-combined', 'tab_measurements', route('construction.measurements.index')],
            ['certificates', 'fa-file-invoice-dollar', 'customer_certificates', route('construction.ipcs.index')],
        ];
    }
@endphp

@if($sectionTabs)
    <nav class="ct-section-tabs" aria-label="@lang('construction::lang.section_navigation')">
        @foreach($sectionTabs as [$tab, $icon, $label, $url])
            <a href="{{ $url }}" class="ct-section-tab {{ $activeSectionTab === $tab ? 'is-active' : '' }}" data-section-tab="{{ $tab }}">
                <i class="fas {{ $icon }}"></i><span>@lang('construction::lang.'.$label)</span>
            </a>
        @endforeach
    </nav>
@endif
