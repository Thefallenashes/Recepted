/**
 * Mobile Menu Enhancement
 * Improves responsive behavior on small screens
 */

(function() {
    'use strict';

    // Detect if we're on mobile
    function isMobileViewport() {
        return window.innerWidth <= 480;
    }

    // Handle menu responsiveness
    function initMobileMenuEnhancements() {
        const menu = document.querySelector('[data-sticky-menu]');
        if (!menu) return;

        const stickyMenu = menu;
        const toggleBtn = menu.querySelector('[data-menu-toggle]');
        const stickyLinks = menu.querySelector('.sticky-links');

        if (!toggleBtn || !stickyLinks) return;

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!stickyMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
                // Only close if not collapsed and we're on mobile
                if (isMobileViewport() && !stickyMenu.classList.contains('is-collapsed')) {
                    toggleBtn.click();
                }
            }
        });

        // Close menu when clicking a link
        const links = stickyLinks.querySelectorAll('a');
        links.forEach(link => {
            link.addEventListener('click', function() {
                if (isMobileViewport() && !stickyMenu.classList.contains('is-collapsed')) {
                    toggleBtn.click();
                }
            });
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Close expanded menu if switching from tablet to shrinking below 480px
                if (isMobileViewport() && !stickyMenu.classList.contains('is-collapsed')) {
                    toggleBtn.click();
                }
            }, 250);
        });
    }

    // Handle touch interactions for better mobile UX
    function initTouchEnhancements() {
        const menuButtons = document.querySelectorAll('.menu-icon-btn');
        
        menuButtons.forEach(btn => {
            btn.addEventListener('touchstart', function() {
                this.style.backgroundColor = 'var(--color-bg-hover)';
            });
            
            btn.addEventListener('touchend', function() {
                this.style.backgroundColor = '';
            });
        });
    }

    // Prevent body scroll when menu is open on mobile
    function initScrollLock() {
        const menu = document.querySelector('[data-sticky-menu]');
        if (!menu) return;

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
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initMobileMenuEnhancements();
            initTouchEnhancements();
            initScrollLock();
        });
    } else {
        initMobileMenuEnhancements();
        initTouchEnhancements();
        initScrollLock();
    }
})();
