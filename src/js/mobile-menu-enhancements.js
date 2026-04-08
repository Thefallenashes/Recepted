/**
 * Mobile Menu Enhancement
 * Improves responsive behavior on small screens
 */

(function() {
    'use strict';

    const teardownCallbacks = [];

    // Detect if we're on mobile
    function isMobileViewport() {
        return window.innerWidth <= 480;
    }

    // Handle menu responsiveness
    function initMobileMenuEnhancements() {
        const menus = document.querySelectorAll('[data-sticky-menu]');
        if (!menus.length) return;

        menus.forEach(menu => {
            if (menu.dataset.mobileMenuInit === '1') {
                return;
            }
            menu.dataset.mobileMenuInit = '1';

            const stickyMenu = menu;
            const toggleBtn = menu.querySelector('[data-menu-toggle]');
            const stickyLinks = menu.querySelector('.sticky-links');

            if (!toggleBtn || !stickyLinks) {
                return;
            }

            // Close menu when clicking outside
            const onDocumentClick = function(e) {
                if (!stickyMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
                    if (isMobileViewport() && !stickyMenu.classList.contains('is-collapsed')) {
                        toggleBtn.click();
                    }
                }
            };
            document.addEventListener('click', onDocumentClick);
            teardownCallbacks.push(() => document.removeEventListener('click', onDocumentClick));

            // Close menu when clicking a link
            const links = stickyLinks.querySelectorAll('a');
            links.forEach(link => {
                const onLinkClick = function() {
                    if (isMobileViewport() && !stickyMenu.classList.contains('is-collapsed')) {
                        toggleBtn.click();
                    }
                };
                link.addEventListener('click', onLinkClick);
                teardownCallbacks.push(() => link.removeEventListener('click', onLinkClick));
            });

            // Handle window resize
            let resizeTimer;
            const onResize = function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (isMobileViewport() && !stickyMenu.classList.contains('is-collapsed')) {
                        toggleBtn.click();
                    }
                }, 250);
            };

            window.addEventListener('resize', onResize);
            teardownCallbacks.push(() => {
                clearTimeout(resizeTimer);
                window.removeEventListener('resize', onResize);
            });
        });
    }

    // Handle touch interactions for better mobile UX
    function initTouchEnhancements() {
        const menuButtons = document.querySelectorAll('.menu-icon-btn');
        
        menuButtons.forEach(btn => {
            if (btn.dataset.touchEnhanceInit === '1') {
                return;
            }
            btn.dataset.touchEnhanceInit = '1';

            const onTouchStart = function() {
                this.style.backgroundColor = 'var(--color-bg-hover)';
            };
            
            const onTouchEnd = function() {
                this.style.backgroundColor = '';
            };

            btn.addEventListener('touchstart', onTouchStart);
            btn.addEventListener('touchend', onTouchEnd);
            teardownCallbacks.push(() => {
                btn.removeEventListener('touchstart', onTouchStart);
                btn.removeEventListener('touchend', onTouchEnd);
                delete btn.dataset.touchEnhanceInit;
            });
        });
    }

    // Prevent body scroll when menu is open on mobile
    function initScrollLock() {
        const menus = document.querySelectorAll('[data-sticky-menu]');
        if (!menus.length) return;

        menus.forEach(menu => {
            if (menu.dataset.scrollLockInit === '1') {
                return;
            }
            menu.dataset.scrollLockInit = '1';

            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        const isCollapsed = menu.classList.contains('is-collapsed');
                        if (isMobileViewport()) {
                            if (isCollapsed) {
                                document.body.style.overflow = 'auto';
                            } else {
                                document.body.style.overflow = 'hidden';
                            }
                        }
                    }
                });
            });

            observer.observe(menu, { attributes: true });
            teardownCallbacks.push(() => {
                observer.disconnect();
                delete menu.dataset.scrollLockInit;
            });
        });
    }

    function registerGlobalCleanup() {
        const onPageHide = function() {
            teardownCallbacks.splice(0).forEach(fn => fn());
            document.body.style.overflow = 'auto';
        };

        window.addEventListener('pagehide', onPageHide, { once: true });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initMobileMenuEnhancements();
            initTouchEnhancements();
            initScrollLock();
            registerGlobalCleanup();
        });
    } else {
        initMobileMenuEnhancements();
        initTouchEnhancements();
        initScrollLock();
        registerGlobalCleanup();
    }
})();
