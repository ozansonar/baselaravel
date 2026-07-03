'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // Counter Animation
    let counterStarted = false;
    const statsSection = document.getElementById('aboutStatsSection');

    function animateCounter(element) {
        const target = parseInt(element.getAttribute('data-target'), 10);
        const suffix = element.getAttribute('data-suffix') || '';
        const format = element.getAttribute('data-format');
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;

        const timer = setInterval(function () {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }

            let displayValue = Math.floor(current);
            if (format === 'K') {
                displayValue = Math.floor(current / 1000) + 'K';
            }
            element.textContent = displayValue + suffix;
        }, 16);
    }

    if (statsSection) {
        const statsObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !counterStarted) {
                    counterStarted = true;
                    const counters = document.querySelectorAll('.about-stat-number[data-target]');
                    counters.forEach(function (counter) {
                        animateCounter(counter);
                    });
                }
            });
        }, { threshold: 0.5 });

        statsObserver.observe(statsSection);
    }
});
