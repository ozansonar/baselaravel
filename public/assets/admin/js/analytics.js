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
        initTables();
    });

    /**
     * Panel tabloları tarayıcıda süzülüp sayfalanıyor.
     *
     * Veri zaten sayfada: sunucuya gitmek her tuş vuruşunda tam sayfa yenilemek
     * demekti ve panelin bütün grafikleri yeniden çizilirdi. Ayrıntılı arama
     * yine "Tüm Ziyaretler" ekranında, kayıtların tamamı üzerinde yapılıyor.
     */
    function initTables() {
        createPagedList({
            rows: document.querySelectorAll('#topPagesList .anl-row'),
            pager: document.getElementById('topPagesPager'),
            search: document.getElementById('topPagesSearch'),
            perPage: document.getElementById('topPagesPerPage')
        });

        createPagedList({
            rows: document.querySelectorAll('#recentVisitsBody .anl-row'),
            pager: document.getElementById('recentPager'),
            search: document.getElementById('recentSearch'),
            perPage: document.getElementById('recentPerPage'),
            select: document.getElementById('recentDevice'),
            selectAttribute: 'device'
        });
    }

    /**
     * Süzgeç ve sayfalama: satırları gizleyip gösteriyor, sayfa düğmelerini
     * kendisi üretiyor. Süzgeç değişince ilk sayfaya dönülüyor — yoksa kullanıcı
     * üç sonuç kalan listede boş bir yedinci sayfaya bakardı.
     */
    function createPagedList(options) {
        var rows = Array.prototype.slice.call(options.rows || []);
        var pager = options.pager;

        if (!rows.length || !pager) {
            return;
        }

        var perPage = parseInt(options.perPage ? options.perPage.value : 10, 10) || 10;
        var page = 1;

        function matching() {
            var term = options.search ? options.search.value.trim().toLocaleLowerCase('tr') : '';
            var choice = options.select ? options.select.value : '';

            return rows.filter(function (row) {
                var matchesTerm = term === '' || (row.dataset.search || '').indexOf(term) !== -1;
                var matchesChoice = choice === '' || row.dataset[options.selectAttribute] === choice;

                return matchesTerm && matchesChoice;
            });
        }

        function render() {
            var visible = matching();
            var pageCount = Math.max(1, Math.ceil(visible.length / perPage));

            page = Math.min(page, pageCount);

            var start = (page - 1) * perPage;
            var shown = visible.slice(start, start + perPage);

            rows.forEach(function (row) { row.classList.add('d-none'); });
            shown.forEach(function (row) { row.classList.remove('d-none'); });

            drawPager(visible.length, pageCount, start, shown.length);
        }

        function drawPager(total, pageCount, start, shownCount) {
            pager.innerHTML = '';

            if (total === 0) {
                var empty = document.createElement('p');
                empty.className = 'anl-pager__empty mb-0';
                empty.textContent = pager.dataset.emptyText || 'Sonuç yok.';
                pager.appendChild(empty);

                return;
            }

            var info = document.createElement('span');
            info.className = 'anl-pager__info';
            info.textContent = total + ' kayıt · ' + (start + 1) + '-' + (start + shownCount) + ' arası';
            pager.appendChild(info);

            if (pageCount < 2) {
                return;
            }

            var nav = document.createElement('div');
            nav.className = 'anl-pager__nav';

            nav.appendChild(pageButton('‹', page > 1, function () { page--; render(); }, 'Önceki sayfa'));

            for (var i = 1; i <= pageCount; i++) {
                nav.appendChild(numberButton(i));
            }

            nav.appendChild(pageButton('›', page < pageCount, function () { page++; render(); }, 'Sonraki sayfa'));
            pager.appendChild(nav);
        }

        function numberButton(number) {
            var button = pageButton(String(number), number !== page, function () { page = number; render(); }, 'Sayfa ' + number);

            if (number === page) {
                button.classList.add('active');
            }

            return button;
        }

        function pageButton(text, enabled, onClick, label) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'anl-pager__btn';
            button.textContent = text;
            button.setAttribute('aria-label', label);
            button.disabled = !enabled;

            if (enabled) {
                button.addEventListener('click', onClick);
            }

            return button;
        }

        if (options.search) {
            options.search.addEventListener('input', function () { page = 1; render(); });
            options.search.addEventListener('keydown', function (event) {
                // Panelde form yok ama sayfa yenilenmesin.
                if (event.key === 'Enter') { event.preventDefault(); }
            });
        }

        if (options.select) {
            options.select.addEventListener('change', function () { page = 1; render(); });
        }

        if (options.perPage) {
            options.perPage.addEventListener('change', function () {
                perPage = parseInt(this.value, 10) || 10;
                page = 1;
                render();
            });
        }

        render();
    }

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

    /** Kullanıcı hareket azaltma istediyse grafikler de canlanmadan çizilsin. */
    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * Halkanın ortasına toplamı yazar.
     *
     * Dilimlerin oranı görünüyordu ama "kaç ziyaretin dağılımı" sorusunun
     * cevabı yoktu; sayıyı okumak için üstüne gelmek gerekiyordu.
     */
    var doughnutTotal = {
        id: 'doughnutTotal',
        afterDraw: function (chart) {
            if (chart.config.type !== 'doughnut') { return; }

            var total = chart.data.datasets[0].data.reduce(function (sum, value) {
                return sum + (Number(value) || 0);
            }, 0);

            if (!total) { return; }

            var area = chart.chartArea;
            var ctx = chart.ctx;
            var centerX = (area.left + area.right) / 2;
            var centerY = (area.top + area.bottom) / 2;

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = 'rgba(255,255,255,0.92)';
            ctx.font = '600 20px Inter, system-ui, sans-serif';
            ctx.fillText(new Intl.NumberFormat('tr-TR').format(total), centerX, centerY - 8);
            ctx.fillStyle = 'rgba(255,255,255,0.45)';
            ctx.font = '400 11px Inter, system-ui, sans-serif';
            ctx.fillText('ziyaret', centerX, centerY + 12);
            ctx.restore();
        }
    };

    function initCharts() {
        if (typeof Chart === 'undefined') { return; }

        Chart.defaults.color = 'rgba(255,255,255,0.65)';
        Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

        if (prefersReducedMotion()) {
            Chart.defaults.animation = false;
        }

        Chart.register(doughnutTotal);

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
                // İmleç noktanın tam üstünde olmasa da o günün değeri okunsun.
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        padding: 12,
                        titleFont: { weight: '600' },
                        borderColor: 'rgba(20, 184, 166, 0.3)',
                        borderWidth: 1,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                var value = new Intl.NumberFormat('tr-TR').format(context.parsed.y);

                                return value + ' ziyaret';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            // Binlik ayraç: dört haneli sayılar okunaksızdı.
                            callback: function (value) {
                                return new Intl.NumberFormat('tr-TR').format(value);
                            }
                        },
                        grid: { color: 'rgba(255,255,255,0.04)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkipPadding: 16 }
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
                        borderWidth: 1,
                        callbacks: {
                            // Dilimin payı yalnızca göz kararıydı; sayı ile
                            // birlikte oranı da yazıyor.
                            label: function (context) {
                                var total = context.dataset.data.reduce(function (sum, value) {
                                    return sum + (Number(value) || 0);
                                }, 0);
                                var value = Number(context.parsed) || 0;
                                var share = total ? Math.round((value / total) * 100) : 0;

                                return ' ' + context.label + ': '
                                    + new Intl.NumberFormat('tr-TR').format(value) + ' (%' + share + ')';
                            }
                        }
                    }
                }
            }
        });
    }
})();
