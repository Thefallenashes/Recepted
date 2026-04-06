/**
 * Animation Helper Functions
 * Utility functions for triggering animations programmatically
 */

(function() {
    'use strict';

    // Animation observer for scroll-triggered animations
    const AnimationManager = {
        // Initialize scroll animations
        initScrollAnimations: function() {
            if (!('IntersectionObserver' in window)) {
                return;
            }

            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                        // Optionally unobserve after animation
                        // observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            // Observe all cards and animatable elements
            document.querySelectorAll('.card, .stat-box, .alert, .btn').forEach(function(element) {
                observer.observe(element);
            });
        },

        // Trigger animation programmatically
        animateElement: function(element, animationName, duration) {
            duration = duration || 'var(--transition-base)';
            
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (!element) return;

            element.style.animation = `${animationName} ${duration}`;
            
            element.addEventListener('animationend', function() {
                element.style.animation = '';
            }, { once: true });
        },

        // Batch animate multiple elements with stagger
        staggerElements: function(selector, animationName, delayBetween) {
            delayBetween = delayBetween || 100;
            const elements = document.querySelectorAll(selector);
            
            elements.forEach((element, index) => {
                setTimeout(() => {
                    this.animateElement(element, animationName);
                }, index * delayBetween);
            });
        },

        // Pulse animation utility
        pulse: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (!element) return;

            element.classList.add('animate-pulse');
            setTimeout(() => {
                element.classList.remove('animate-pulse');
            }, 1500);
        },

        // Bounce animation utility
        bounce: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (!element) return;

            element.classList.add('animate-bounce');
            setTimeout(() => {
                element.classList.remove('animate-bounce');
            }, 600);
        },

        // Shake animation (quick rotation)
        shake: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (!element) return;

            element.style.animation = 'shake 0.5s';
            setTimeout(() => {
                element.style.animation = '';
            }, 500);
        },

        // Add success animation
        success: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (!element) return;

            element.classList.add('animate-scale-in');
            setTimeout(() => {
                element.classList.remove('animate-scale-in');
            }, 400);
        },

        // Add error animation
        error: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (!element) return;

            this.shake(element);
        },

        // Fade in on hover
        setupHoverAnimations: function() {
            document.querySelectorAll('[data-hover-animate]').forEach(function(element) {
                const animation = element.getAttribute('data-hover-animate') || 'hover-lift';
                element.classList.add(animation);
            });
        },

        // Initialize all animations
        init: function() {
            this.initScrollAnimations();
            this.setupHoverAnimations();
        }
    };

    // Expose to global scope or as module
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = AnimationManager;
    } else {
        window.AnimationManager = AnimationManager;
    }

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            AnimationManager.init();
        });
    } else {
        AnimationManager.init();
    }

    // Add shake keyframe dynamically if not in CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    `;
    document.head.appendChild(style);
})();
