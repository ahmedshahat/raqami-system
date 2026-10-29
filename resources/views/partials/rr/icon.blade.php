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
    @case('chevron-up')<path d="M6 15l6-6 6 6"></path>@break
    @case('chevron-back')<path d="M15 6l-6 6 6 6"></path>@break
    @case('more')<circle cx="5" cy="12" r="1.3" fill="currentColor"></circle><circle cx="12" cy="12" r="1.3" fill="currentColor"></circle><circle cx="19" cy="12" r="1.3" fill="currentColor"></circle>@break
    @case('user')<circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path>@break
    @case('users')<circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20a6.5 6.5 0 0 1 13 0"></path><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.2a6.5 6.5 0 0 1 3.5 5.8"></path>@break
    @case('clock')<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>@break
    @case('shield')<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3z"></path>@break
    @case('map-pin')<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"></path><circle cx="12" cy="9.5" r="2.5"></circle>@break
    @case('close')<path d="M6 6l12 12M18 6L6 18"></path>@break
    @case('info')<circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path>@break
    @case('filter')<path d="M4 5h16l-6 7.5V19l-4 2v-8.5L4 5z"></path>@break
    @case('eye')<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"></path><circle cx="12" cy="12" r="3"></circle>@break
    @case('eye-off')<path d="M3 3l18 18M10.6 6a9.7 9.7 0 0 1 1.4-.1c6 0 9.5 6.1 9.5 6.1a17 17 0 0 1-2.6 3.4M6.6 6.6A16.5 16.5 0 0 0 2.5 12S6 18.5 12 18.5a9.3 9.3 0 0 0 4.9-1.4"></path><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>@break
    @case('mail')<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3.5 6.5l8.5 6.5 8.5-6.5"></path>@break
    @case('phone')<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"></path>@break
    @case('cloud')<path d="M7 18a4.5 4.5 0 0 1-.6-9A6 6 0 0 1 18 9.5a4.3 4.3 0 0 1-.5 8.5H7z"></path>@break
    @case('gift')<rect x="3" y="8" width="18" height="4" rx="1"></rect><path d="M5 12v8h14v-8M12 8v12M12 8S10.5 3.5 8 4.5 9 8 12 8zM12 8s1.5-4.5 4-3.5S15 8 12 8z"></path>@break
    @case('trend')<path d="M3 17l6-6 4 4 8-8"></path><path d="M15 7h6v6"></path>@break
    @case('coins')<ellipse cx="9" cy="7" rx="6" ry="3"></ellipse><path d="M3 7v4c0 1.7 2.7 3 6 3s6-1.3 6-3V7"></path><path d="M9 14v3c0 1.7 2.7 3 6 3s6-1.3 6-3v-4c0-1.7-2.7-3-6-3"></path>@break
    @case('wallet')<path d="M4 7a2 2 0 0 1 2-2h12v4"></path><rect x="4" y="7" width="17" height="13" rx="2"></rect><path d="M16 13.5h2"></path>@break
    @case('receipt')<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"></path><path d="M9 8h6M9 12h6M9 16h3"></path>@break
    @case('cart')<circle cx="9" cy="20" r="1.3"></circle><circle cx="18" cy="20" r="1.3"></circle><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.4a1 1 0 0 0 1-.8L21 8H6.2"></path>@break
    @case('undo')<path d="M9 14L4 9l5-5"></path><path d="M4 9h11a5 5 0 0 1 0 10h-3"></path>@break
    @case('box')<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"></path><path d="M3 8l9 5 9-5M12 13v8"></path>@break
    @case('truck')<path d="M2 6h11v10H2zM13 10h4l3 3v3h-7"></path><circle cx="6" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle>@break
    @case('cheque')<rect x="2.5" y="6" width="19" height="12" rx="2"></rect><path d="M6 10h6M6 14h4M14.5 14.5c1-.2 1.7-1.8 2.5-1.8s.8 1.6 2 1.3"></path>@break
    @case('clipboard')<rect x="5" y="4" width="14" height="17" rx="2"></rect><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"></path>@break
    @case('arrow-up')<path d="M12 19V5M6 11l6-6 6 6"></path>@break
    @case('arrow-down')<path d="M12 5v14M6 13l6 6 6-6"></path>@break
    @case('monitor')<rect x="3" y="4" width="18" height="12" rx="2"></rect><path d="M8 20h8M12 16v4"></path>@break
    @case('sliders')<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"></path><circle cx="16" cy="6" r="2"></circle><circle cx="10" cy="12" r="2"></circle><circle cx="18" cy="18" r="2"></circle>@break
    @case('moon')<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"></path>@break
    @case('sun')<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>@break
@endswitch
</svg>
