document.addEventListener('DOMContentLoaded', function () {
    var DURATION = 220;
    var EASING = 'ease';
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
        var collapsibleItems = [stickyLinks, homeBtn, logoutBtn].filter(Boolean);
        var cleanupFns = [];
        var isAnimating = false;

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

        function clearStyles(el) {
            el.style.transition = '';
            el.style.overflow = '';
            el.style.maxHeight = '';
            el.style.opacity = '';
            el.style.transform = '';
        }

        function animateOut(el) {
            return new Promise(function (resolve) {
                if (el.hidden) {
                    resolve();
                    return;
                }

                if (el === stickyLinks) {
                    var currentH = el.scrollHeight;
                    el.style.overflow = 'hidden';
                    el.style.maxHeight = currentH + 'px';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                    void el.offsetHeight;
                    el.style.transition = 'max-height ' + DURATION + 'ms ' + EASING + ', opacity ' + DURATION + 'ms ' + EASING + ', transform ' + DURATION + 'ms ' + EASING;
                    el.style.maxHeight = '0px';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-6px)';
                } else {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                    void el.offsetHeight;
                    el.style.transition = 'opacity ' + DURATION + 'ms ' + EASING + ', transform ' + DURATION + 'ms ' + EASING;
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-6px)';
                }

                var done = false;
                var timer = setTimeout(finish, DURATION + 50);

                function finish() {
                    if (done) {
                        return;
                    }
                    done = true;
                    clearTimeout(timer);
                    el.removeEventListener('transitionend', onEnd);
                    el.hidden = true;
                    clearStyles(el);
                    resolve();
                }

                function onEnd(e) {
                    if (e.target !== el) {
                        return;
                    }
                    finish();
                }

                el.addEventListener('transitionend', onEnd);
            });
        }

        function animateIn(el) {
            return new Promise(function (resolve) {
                el.hidden = false;

                if (el === stickyLinks) {
                    el.style.overflow = 'hidden';
                    el.style.maxHeight = '0px';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-6px)';
                    void el.offsetHeight;
                    var targetH = el.scrollHeight;
                    el.style.transition = 'max-height ' + DURATION + 'ms ' + EASING + ', opacity ' + DURATION + 'ms ' + EASING + ', transform ' + DURATION + 'ms ' + EASING;
                    el.style.maxHeight = targetH + 'px';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                } else {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-6px)';
                    void el.offsetHeight;
                    el.style.transition = 'opacity ' + DURATION + 'ms ' + EASING + ', transform ' + DURATION + 'ms ' + EASING;
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }

                var done = false;
                var timer = setTimeout(finish, DURATION + 50);

                function finish() {
                    if (done) {
                        return;
                    }
                    done = true;
                    clearTimeout(timer);
                    el.removeEventListener('transitionend', onEnd);
                    clearStyles(el);
                    resolve();
                }

                function onEnd(e) {
                    if (e.target !== el) {
                        return;
                    }
                    finish();
                }

                el.addEventListener('transitionend', onEnd);
            });
        }

        function setCollapsed(collapsed, animate) {
            menu.classList.toggle('is-collapsed', collapsed);
            syncIcons(collapsed);

            if (!animate) {
                collapsibleItems.forEach(function (item) {
                    item.hidden = collapsed;
                    clearStyles(item);
                });
                return Promise.resolve();
            }

            if (collapsed) {
                return Promise.all(collapsibleItems.map(animateOut));
            }

            return Promise.all(collapsibleItems.map(animateIn));
        }

        function onToggleClick() {
            if (isAnimating) {
                return;
            }

            var shouldCollapse = !menu.classList.contains('is-collapsed');
            isAnimating = true;
            setCollapsed(shouldCollapse, true).finally(function () {
                isAnimating = false;
            });
        }

        on(toggleBtn, 'click', onToggleClick);

        setCollapsed(menu.classList.contains('is-collapsed'), false);

        on(window, 'pagehide', function () {
            cleanupFns.forEach(function (fn) {
                fn();
            });
            cleanupFns = [];
            delete menu.dataset.stickyMenuInit;
        });
    });
});
