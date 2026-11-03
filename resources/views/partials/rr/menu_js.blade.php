{{--
    قوائم rr-menu في شاشات النظام الأساسي: الزرار data-rr-menu-toggle + aria-controls="menu-id"،
    والقائمة <div class="rr-menu" id="menu-id" role="menu">. بتتحط position: fixed جنب الزرار،
    تحته أو فوقه حسب المساحة، وبتاخد سكرول جواها لو أطول من الشاشة.
--}}
<script type="text/javascript">
    (function() {
        var activeMenu = null, activeToggle = null;
        function closeMenu(returnFocus) {
            if (!activeMenu) return;
            activeMenu.style.display = 'none';
            activeToggle.setAttribute('aria-expanded', 'false');
            if (returnFocus) activeToggle.focus();
            activeMenu = activeToggle = null;
        }
        function placeMenu(toggle, menu) {
            menu.style.maxHeight = '';
            var box = toggle.getBoundingClientRect(), width = menu.offsetWidth, height = menu.offsetHeight;
            var below = window.innerHeight - box.bottom - 12, above = box.top - 12;
            var down = height <= below || below >= above;
            var room = Math.max(160, down ? below : above);
            if (height > room) { menu.style.maxHeight = room + 'px'; height = room; }
            var left = getComputedStyle(toggle).direction === 'rtl' ? box.left : box.right - width;
            menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, left)) + 'px';
            menu.style.top = (down ? box.bottom + 4 : box.top - height - 4) + 'px';
        }
        function openMenu(toggle, menu) {
            menu.style.display = 'block';
            menu.style.visibility = 'hidden';
            placeMenu(toggle, menu);
            menu.style.visibility = 'visible';
            toggle.setAttribute('aria-expanded', 'true');
            activeMenu = menu;
            activeToggle = toggle;
            var first = menu.querySelector('[role^="menuitem"]');
            if (first) first.focus({ preventScroll: true });
        }
        document.addEventListener('click', function(event) {
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
            if (!activeMenu) return;
            if (!event.target.closest('.rr-menu') || event.target.closest('a.rr-menu__item, button.rr-menu__item')) closeMenu();
        }, true);
        document.addEventListener('keydown', function(event) {
            if (!activeMenu) return;
            if (event.key === 'Escape') { closeMenu(true); return; }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            var items = Array.prototype.slice.call(activeMenu.querySelectorAll('[role^="menuitem"]'));
            var index = items.indexOf(document.activeElement);
            items[(index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
            event.preventDefault();
        });
        window.addEventListener('resize', function() { closeMenu(); });
        // سكرول الصفحة أو الجدول بيحرّك القائمة مع زرارها؛ أي سكرول تاني (القائمة الجانبية مثلًا) ما يقفلهاش
        window.addEventListener('scroll', function(event) {
            if (!activeMenu || activeMenu.contains(event.target)) return;
            if (event.target !== document && !event.target.contains(activeToggle)) return;
            var box = activeToggle.getBoundingClientRect();
            if (box.bottom < 0 || box.top > window.innerHeight) { closeMenu(); return; }
            placeMenu(activeToggle, activeMenu);
        }, true);
    })();
</script>
