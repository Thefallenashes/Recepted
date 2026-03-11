document.addEventListener('DOMContentLoaded', function () {
    var menus = document.querySelectorAll('[data-sticky-menu]');

    menus.forEach(function (menu) {
        var toggleBtn = menu.querySelector('[data-menu-toggle]');
        var toggleIcon = menu.querySelector('[data-menu-toggle-icon]');
        var homeBtn = menu.querySelector('.menu-icon-btn[aria-label="Inicio"]');
        var stickyLinks = menu.querySelector('.sticky-links');
        var logoutBtn = menu.querySelector('.menu-icon-btn.logout-btn');
        if (!toggleBtn || !toggleIcon) {
            return;
        }

        var collapsedIcon = menu.getAttribute('data-icon-collapsed') || '../images/MostrarMenuDesplegable.PNG';
        var expandedIcon = menu.getAttribute('data-icon-expanded') || '../images/OcultarMenuDesplegable.PNG';

        function syncState() {
            var isCollapsed = menu.classList.contains('is-collapsed');
            toggleBtn.setAttribute('aria-expanded', String(!isCollapsed));

            if (stickyLinks) {
                stickyLinks.hidden = isCollapsed;
            }

            if (homeBtn) {
                homeBtn.hidden = isCollapsed;
            }

            if (logoutBtn) {
                logoutBtn.hidden = isCollapsed;
            }

            if (isCollapsed) {
                toggleIcon.src = collapsedIcon;
                toggleIcon.alt = 'Mostrar menu desplegable';
                toggleBtn.setAttribute('aria-label', 'Mostrar menu desplegable');
            } else {
                toggleIcon.src = expandedIcon;
                toggleIcon.alt = 'Ocultar menu desplegable';
                toggleBtn.setAttribute('aria-label', 'Ocultar menu desplegable');
            }
        }

        toggleBtn.addEventListener('click', function () {
            menu.classList.toggle('is-collapsed');
            syncState();
        });

        syncState();
    });
});
