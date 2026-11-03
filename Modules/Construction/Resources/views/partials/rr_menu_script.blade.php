{{--
    سكريبت قائمة الإجراءات (rr-menu) — يتضمَّن مرة واحدة في أي شاشة:
    @include('construction::partials.rr_menu_script')
    الزرار: <button type="button" data-rr-menu-toggle aria-haspopup="true" aria-expanded="false" aria-controls="menu-id">
    وبعده مباشرة: <div class="rr-menu" id="menu-id" role="menu">…</div>
    القائمة بتتحط position: fixed جنب الزرار، وبتطلع لفوق لو مفيش مساحة تحت.
--}}
@once
@push('ct_quick_js')
<script>
(function () {
    var activeMenu = null, activeToggle = null;
    function closeMenu(returnFocus) {
        if (!activeMenu) return;
        activeMenu.style.display = 'none';
        activeToggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) activeToggle.focus();
        activeMenu = null;
        activeToggle = null;
    }
    function openMenu(toggle, menu) {
        menu.style.display = 'block';
        menu.style.visibility = 'hidden';
        var box = toggle.getBoundingClientRect(), width = menu.offsetWidth, height = menu.offsetHeight;
        var isRtl = getComputedStyle(toggle).direction === 'rtl';
        var left = isRtl ? box.left : box.right - width;
        menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, left)) + 'px';
        menu.style.top = (box.bottom + height + 8 <= window.innerHeight || box.top < height + 8 ? box.bottom + 4 : box.top - height - 4) + 'px';
        menu.style.visibility = 'visible';
        toggle.setAttribute('aria-expanded', 'true');
        activeMenu = menu;
        activeToggle = toggle;
        var first = menu.querySelector('[role="menuitem"]');
        if (first) first.focus();
    }
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-rr-menu-toggle]');
        if (toggle) {
            event.stopPropagation();
            var menu = document.getElementById(toggle.getAttribute('aria-controls'));
            if (!menu) return;
            if (activeMenu === menu) { closeMenu(); return; }
            closeMenu();
            openMenu(toggle, menu);
            return;
        }
        if (activeMenu && !event.target.closest('.rr-menu')) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
        if (!activeMenu) return;
        if (event.key === 'Escape') { closeMenu(true); return; }
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        var items = Array.prototype.slice.call(activeMenu.querySelectorAll('[role="menuitem"]'));
        var index = items.indexOf(document.activeElement);
        items[(index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
        event.preventDefault();
    });
    window.addEventListener('resize', function () { closeMenu(); });
    window.addEventListener('scroll', function () { closeMenu(); }, true);
})();
</script>
@endpush
@endonce
