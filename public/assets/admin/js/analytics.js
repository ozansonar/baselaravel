(function () {
    'use strict';

    var config = window.analyticsConfig || {};
    var charts = {};

    var palette = [
        '#14b8a6', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
        '#10b981', '#ec4899', '#06b6d4', '#f97316', '#6366f1'
    ];

    document.addEventListener('DOMContentLoaded', function () {
        bindDateRange();
        bindBotToggle();
        initCharts();
    });

    function bindDateRange() {
        var customBtn = document.getElementById('customRangeBtn');
        var customWrap = document.getElementById('customDateWrap');
        var applyBtn = document.getElementById('applyCustomRange');

        if (customBtn && customWrap) {
            customBtn.addEventListener('click', function () {
                customWrap.classList.toggle('d-none');
            });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var from = document.getElementById('customFrom').value;
                var to = document.getElementById('customTo').value;
                if (!from || !to) { return; }
                navigateWithParams({ range: 'custom', from: from, to: to });
            });
        }
    }

    function bindBotToggle() {
        var toggle = document.getElementById('includeBotsToggle');
        if (!toggle) { return; }
        toggle.addEventListener('change', function () {
            navigateWithParams({ include_bots: this.checked ? 1 : 0 });
        });
    }

    function navigateWithParams(params) {
        var url = new URL(window.location.href);
        Object.keys(params).forEach(function (k) {
            if (params[k] === null || params[k] === undefined) {
                url.searchParams.delete(k);
            } else {
                url.searchParams.set(k, params[k]);
            }
        });
        window.location.href = url.toString();
    }

    function initCharts() {
        if (typeof Chart === 'undefined') { return; }

        Chart.defaults.color = 'rgba(255,255,255,0.65)';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';

        var daily = config.dailyChart || { labels: [], data: [] };
        if (document.getElementById('dailyViewsChart')) {
            renderLineChart('dailyViewsChart', daily.labels, daily.data, 'Ziyaret');
        }

        if (document.getElementById('deviceChart')) {
            renderDoughnut('deviceChart', config.deviceBreakdown || []);
        }
        if (document.getElementById('browserChart')) {
            renderDoughnut('browserChart', config.browserBreakdown || []);
        }
        if (document.getElementById('referrerChart')) {
            renderDoughnut('referrerChart', config.referrers || []);
        }
        if (document.getElementById('botChart')) {
            renderDoughnut('botChart', config.botActivity || []);
        }
    }

    function renderLineChart(canvasId, labels, data, label) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) { return; }
        var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(20, 184, 166, 0.35)');
        gradient.addColorStop(1, 'rgba(20, 184, 166, 0)');

        charts[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: palette[0],
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: palette[0],
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        padding: 12,
                        titleFont: { weight: '600' },
                        borderColor: 'rgba(20, 184, 166, 0.3)',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(255,255,255,0.04)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    function renderDoughnut(canvasId, rows) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) { return; }
        var labels = rows.map(function (r) { return r.label || '—'; });
        var data = rows.map(function (r) { return r.count; });
        charts[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: palette.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: 'rgba(17, 24, 39, 0.9)',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 10,
                            font: { size: 11 },
                            color: 'rgba(255,255,255,0.75)'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        padding: 12,
                        borderColor: 'rgba(20, 184, 166, 0.3)',
                        borderWidth: 1
                    }
                }
            }
        });
    }
})();
