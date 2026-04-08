document.addEventListener('DOMContentLoaded', function () {
    var DURATION = 300;
    var TRANS = 'max-height ' + DURATION + 'ms ease, opacity ' + DURATION + 'ms ease, transform ' + DURATION + 'ms ease';

    var menus = document.querySelectorAll('[data-sticky-menu]');

    menus.forEach(function (menu) {
        if (menu.dataset.stickyMenuInit === '1') {
            return;
        }
        menu.dataset.stickyMenuInit = '1';

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
        var animatedItems = [stickyLinks, homeBtn, logoutBtn].filter(Boolean);
        var isAnimating = false;
        var cleanupFns = [];

        function on(el, evt, handler) {
            el.addEventListener(evt, handler);
            cleanupFns.push(function () {
                el.removeEventListener(evt, handler);
            });
        }

        function syncIcons(collapsed) {
            toggleBtn.setAttribute('aria-expanded', String(!collapsed));
            if (collapsed) {
                toggleIcon.src = collapsedIcon;
                toggleIcon.alt = 'Mostrar menu desplegable';
                toggleBtn.setAttribute('aria-label', 'Mostrar menu desplegable');
            } else {
                toggleIcon.src = expandedIcon;
                toggleIcon.alt = 'Ocultar menu desplegable';
                toggleBtn.setAttribute('aria-label', 'Ocultar menu desplegable');
            }
        }

        function clearSlideStyles(el) {
            el.style.transition = '';
            el.style.overflow = '';
            el.style.maxHeight = '';
            el.style.opacity = '';
            el.style.transform = '';
        }

        function slideIn(el) {
            return new Promise(function (resolve) {
                if (el._slideTimer) {
                    clearTimeout(el._slideTimer);
                }

                //Hace el elemento visible
                el.hidden = false;

                // Fuerza a que comienze colapsado
                el.style.transition = 'none';
                el.style.overflow = 'hidden';
                el.style.maxHeight = '0px';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                void el.offsetHeight;
                // Comprobar tamaño del contenido
                var targetH = el.scrollHeight;

                // Inicia la animacion
                el.style.transition = TRANS;
                el.style.maxHeight = targetH + 'px';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';

                var done = false;
                function finish() {
                    if (done) return;
                    done = true;
                    clearTimeout(el._slideTimer);
                    clearSlideStyles(el);
                    resolve();
                }

                function handler(e) {
                    if (e.target !== el || e.propertyName !== 'opacity') return;
                    el.removeEventListener('transitionend', handler);
                    finish();
                }
                el.addEventListener('transitionend', handler);
                el._slideTimer = setTimeout(finish, DURATION + 60);
            });
        }

        function slideOut(el) {
            return new Promise(function (resolve) {
                if (el.hidden) {
                    resolve();
                    return;
                }

                if (el._slideTimer) {
                    clearTimeout(el._slideTimer);
                }

                //Bloquea la altura actual del menu
                var currentH = el.scrollHeight;
                el.style.transition = 'none';
                el.style.overflow = 'hidden';
                el.style.maxHeight = currentH + 'px';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
                void el.offsetHeight;
                // Animacion para el cierre
                el.style.transition = TRANS;
                el.style.maxHeight = '0px';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';

                var done = false;
                function finish() {
                    if (done) return;
                    done = true;
                    clearTimeout(el._slideTimer);
                    el.hidden = true;
                    clearSlideStyles(el);
                    resolve();
                }

                function handler(e) {
                    if (e.target !== el || e.propertyName !== 'opacity') return;
                    el.removeEventListener('transitionend', handler);
                    finish();
                }
                el.addEventListener('transitionend', handler);
                el._slideTimer = setTimeout(finish, DURATION + 60);
            });
        }

        function onToggleClick() {
            if (isAnimating) {
                return;
            }

            isAnimating = true;

            if (menu.classList.contains('is-collapsed')) {
                // Abrur
                menu.classList.remove('is-collapsed');
                syncIcons(false);
                Promise.all(animatedItems.map(slideIn)).then(function () {
                    isAnimating = false;
                });
            } else {
                // Cerrar
                syncIcons(true);
                Promise.all(animatedItems.map(slideOut)).then(function () {
                    menu.classList.add('is-collapsed');
                    isAnimating = false;
                });
            }
        }
        on(toggleBtn, 'click', onToggleClick);

        // Set initial state
        var startCollapsed = menu.classList.contains('is-collapsed');
        animatedItems.forEach(function (item) {
            item.hidden = startCollapsed;
        });
        syncIcons(startCollapsed);

        on(window, 'pagehide', function () {
            animatedItems.forEach(function (el) {
                if (el && el._slideTimer) {
                    clearTimeout(el._slideTimer);
                    el._slideTimer = null;
                }
            });
            cleanupFns.forEach(function (fn) {
                fn();
            });
            cleanupFns = [];
            delete menu.dataset.stickyMenuInit;
        });
    });
});
