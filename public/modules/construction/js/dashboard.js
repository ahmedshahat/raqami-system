(function () {
    'use strict';

    /* 1. Legacy Hash Compatibility Router */
    if (window.location.hash) {
        var cleanHash = window.location.hash.replace(/^#(ct-)?/, '');
        var legacyRouteMap = {
            'quotes': '/construction/quotes',
            'projects': '/construction/projects',
            'boq': '/construction/project-items',
            'contracts': '/construction/contracts',
            'measurements': '/construction/measurements',
            'certificates': '/construction/ipcs',
            'ipcs': '/construction/ipcs',
            'overview': '/construction/overview',
            'subcontractors': '/construction/subcontractors',
            'costs': '/construction/reports',
            'reports': '/construction/reports'
        };
        if (legacyRouteMap[cleanHash] && window.location.pathname.replace(/\/+$/, '') === '/construction') {
            window.location.replace(legacyRouteMap[cleanHash]);
            return;
        }
    }

    /* 2. Mobile/Tablet Active Tab Auto-Scroll */
    document.addEventListener('DOMContentLoaded', function () {
        var activeTab = document.querySelector('.ct-tabs .ct-tab.is-active');
        if (activeTab && typeof activeTab.scrollIntoView === 'function') {
            try {
                activeTab.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' });
            } catch (e) {
                activeTab.scrollIntoView(false);
            }
        }
    });

    /* 3. Contract Row Actions Dropdown Handler */
    var activeContractMenu = null;
    function closeContractMenu() {
        if (!activeContractMenu) return;
        activeContractMenu.style.display = 'none';
        var toggle = activeContractMenu.previousElementSibling;
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        activeContractMenu = null;
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('.ct-contract-menu-toggle');
        if (toggle) {
            event.stopPropagation();
            var menu = toggle.nextElementSibling;
            if (activeContractMenu === menu) {
                closeContractMenu();
                return;
            }
            closeContractMenu();
            if (!menu) return;
            menu.style.display = 'block';
            menu.style.visibility = 'hidden';
            var button = toggle.getBoundingClientRect();
            var width = menu.offsetWidth;
            var height = menu.offsetHeight;
            var left = Math.max(8, Math.min(window.innerWidth - width - 8, button.right - width));
            var top = (button.bottom + height + 8 <= window.innerHeight || button.top < height + 8)
                ? button.bottom + 5
                : button.top - height - 5;
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
            menu.style.visibility = 'visible';
            toggle.setAttribute('aria-expanded', 'true');
            activeContractMenu = menu;
            return;
        }
        if (activeContractMenu && !event.target.closest('.ct-contract-menu')) {
            closeContractMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeContractMenu();
    });

    window.addEventListener('resize', closeContractMenu);
    window.addEventListener('scroll', closeContractMenu, true);
})();
