@php
    $rrIconSize = $size ?? 20;
    $rrIconFlip = in_array($name, ['chevron-forward', 'chevron-back', 'arrow-forward', 'arrow-back'], true);
@endphp
<svg class="rr-icon{{ $rrIconFlip ? ' rr-icon--flip' : '' }}" width="{{ $rrIconSize }}" height="{{ $rrIconSize }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('calendar')<rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M4 10h16M8 3v4M16 3v4"></path>@break
    @case('plus')<path d="M12 5v14M5 12h14"></path>@break
    @case('building')<rect x="5" y="3" width="14" height="18" rx="1"></rect><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"></path>@break
    @case('arrow-forward')<path d="M5 12h14M13 6l6 6-6 6"></path>@break
    @case('arrow-back')<path d="M19 12H5M11 6l-6 6 6 6"></path>@break
    @case('folder-open')<path d="M3 19V6a1 1 0 0 1 1-1h5l2 2h7a1 1 0 0 1 1 1v2"></path><path d="M3 19l2.6-8.2a1 1 0 0 1 1-.8H21l-2.4 8.2a1 1 0 0 1-1 .8H3z"></path>@break
    @case('download')<path d="M12 4v11M7 11l5 5 5-5M5 20h14"></path>@break
    @case('check')<path d="M5 12.5l4.5 4.5L19 7.5"></path>@break
    @case('file')<path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path><path d="M14 3v5h5M9 13h6M9 17h6"></path>@break
    @case('lock')<rect x="5" y="11" width="14" height="9" rx="2"></rect><path d="M8 11V8a4 4 0 0 1 8 0v3"></path>@break
    @case('check-circle')<circle cx="12" cy="12" r="9"></circle><path d="M8 12.5l3 3 5-6"></path>@break
    @case('chevron-forward')<path d="M9 6l6 6-6 6"></path>@break
    @case('list')<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"></path>@break
    @case('ruler')<path d="M3 17L17 3l4 4L7 21l-4-4z"></path><path d="M7 13l2 2M10 10l2 2M13 7l2 2"></path>@break
    @case('pie')<path d="M12 3v9h9"></path><path d="M20.5 15A9 9 0 1 1 9 3.5"></path>@break
    @case('bars')<path d="M5 20v-8M12 20V5M19 20v-5M3 21h18"></path>@break
    @case('edit')<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17v3z"></path><path d="M14 8l3 3"></path>@break
    @case('print')<path d="M7 9V4h10v5"></path><path d="M7 17H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2"></path><path d="M7 14h10v6H7z"></path>@break
    @case('trash')<path d="M4 7h16M10 11v6M14 11v6"></path><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"></path>@break
    @case('search')<circle cx="11" cy="11" r="6.5"></circle><path d="M20 20l-4.2-4.2"></path>@break
    @case('chevron-down')<path d="M6 9l6 6 6-6"></path>@break
    @case('chevron-back')<path d="M15 6l-6 6 6 6"></path>@break
    @case('more')<circle cx="5" cy="12" r="1.3" fill="currentColor"></circle><circle cx="12" cy="12" r="1.3" fill="currentColor"></circle><circle cx="19" cy="12" r="1.3" fill="currentColor"></circle>@break
    @case('close')<path d="M6 6l12 12M18 6L6 18"></path>@break
    @case('info')<circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path>@break
    @case('filter')<path d="M4 5h16l-6 7.5V19l-4 2v-8.5L4 5z"></path>@break
    @case('eye')<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"></path><circle cx="12" cy="12" r="3"></circle>@break
@endswitch
</svg>
