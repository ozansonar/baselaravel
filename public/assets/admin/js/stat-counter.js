'use strict';

/**
 * Stat Counter - Animated number counter for usr-stat-value elements.
 *
 * Usage:
 *   <h3 class="usr-stat-value" data-count="1250">0</h3>
 *   <h3 class="usr-stat-value" data-count="3500" data-suffix=" ₺">0 ₺</h3>
 *
 * Numbers are formatted with Turkish locale (dot as thousands separator).
 * Values are NEVER rounded - exact integers are displayed.
 */
(function () {
    var DURATION = 1600;
    var EASE = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };

    function formatNumber(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function animateCounter(el) {
        if (el.dataset.animated === '1') return;
        el.dataset.animated = '1';

        var target = parseInt(el.dataset.count, 10);
        if (isNaN(target)) return;

        var suffix = el.dataset.suffix || '';
        var prefix = el.dataset.prefix || '';

        if (target === 0) {
            el.textContent = prefix + '0' + suffix;
            return;
        }

        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var elapsed = timestamp - startTime;
            var progress = Math.min(elapsed / DURATION, 1);
            var easedProgress = EASE(progress);

            var current = Math.round(easedProgress * target);
            el.textContent = prefix + formatNumber(current) + suffix;

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = prefix + formatNumber(target) + suffix;
            }
        }

        requestAnimationFrame(step);
    }

    function initCounters() {
        var elements = document.querySelectorAll('.usr-stat-value[data-count], .nt-stat-value[data-count]');
        if (!elements.length) return;

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            elements.forEach(function (el) { observer.observe(el); });
        } else {
            elements.forEach(function (el) { animateCounter(el); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCounters);
    } else {
        initCounters();
    }
})();
