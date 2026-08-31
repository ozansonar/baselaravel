/**
 * Rapor merkezi.
 *
 * Üç iş yapıyor: grafiği çizmek, önizleme penceresini doldurmak ve zamanlama
 * penceresini açmak. Rapor verisinin kendisi sunucudan geliyor; burada
 * hesaplanan hiçbir sayı yok — iki yerde hesaplanan sayı, er ya da geç iki
 * farklı sayıdır.
 */
(function () {
    'use strict';

    /* ---- Sayaç animasyonu (tema davranışı) ---- */
    function animateCounters() {
        document.querySelectorAll('.usr-stat-value[data-count]').forEach(function (el) {
            const target = parseInt(el.dataset.count || '0', 10);
            const duration = 700;
            const start = performance.now();

            function step(now) {
                const progress = Math.min((now - start) / duration, 1);
                el.textContent = Math.round(target * progress).toLocaleString('tr-TR');
                if (progress < 1) requestAnimationFrame(step);
            }

            requestAnimationFrame(step);
        });
    }

    /* ---- Eğri ---- */
    function drawChart() {
        const canvas = document.getElementById('reportTrendChart');
        if (!canvas || typeof Chart === 'undefined' || !window.reportChartData) return;

        const data = window.reportChartData;
        const context = canvas.getContext('2d');
        const gradient = context.createLinearGradient(0, 0, 0, 320);
        gradient.addColorStop(0, 'rgba(45, 212, 191, 0.35)');
        gradient.addColorStop(1, 'rgba(45, 212, 191, 0)');

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: data.label,
                    data: data.values,
                    borderColor: '#2dd4bf',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', maxTicksLimit: 12 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: '#94a3b8', precision: 0 } },
                },
            },
        });
    }

    /* ---- Önizleme ---- */
    window.openPreviewModal = function (type) {
        const url = window.reportPreviewUrl.replace('__TYPE__', type) + '?range=' + encodeURIComponent(window.reportRange);

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                document.getElementById('previewModalTitle').textContent = data.title;
                document.getElementById('previewRange').textContent = data.range;

                const metrics = document.getElementById('previewMetrics');
                metrics.innerHTML = '';
                (data.metrics || []).forEach(function (metric) {
                    const col = document.createElement('div');
                    col.className = 'col-6 col-md-3';
                    col.innerHTML = '<div class="rpr-preview-metric">'
                        + '<span class="rpr-preview-metric-label"></span>'
                        + '<strong class="rpr-preview-metric-value"></strong></div>';
                    col.querySelector('.rpr-preview-metric-label').textContent = metric.label;
                    col.querySelector('.rpr-preview-metric-value').textContent = metric.value;
                    metrics.appendChild(col);
                });

                const head = document.getElementById('previewTableHead');
                head.innerHTML = '';
                const headRow = document.createElement('tr');
                (data.columns || []).forEach(function (column) {
                    const th = document.createElement('th');
                    th.textContent = column;
                    headRow.appendChild(th);
                });
                head.appendChild(headRow);

                const body = document.getElementById('previewTableBody');
                body.innerHTML = '';
                (data.rows || []).forEach(function (row) {
                    const tr = document.createElement('tr');
                    row.forEach(function (cell) {
                        const td = document.createElement('td');
                        td.textContent = cell;
                        tr.appendChild(td);
                    });
                    body.appendChild(tr);
                });

                document.getElementById('previewTotal').textContent = data.total > (data.rows || []).length
                    ? 'Toplam ' + data.total + ' satırın ilk ' + data.rows.length + ' tanesi gösteriliyor.'
                    : '';

                bootstrap.Modal.getOrCreateInstance(document.getElementById('previewModal')).show();
            })
            .catch(function () {
                if (window.AdminModal) {
                    window.AdminModal.alert('Önizleme yüklenemedi.');
                }
            });
    };

    /* ---- Zamanlama penceresi ---- */
    window.openScheduleModal = function (schedule, presetType) {
        const modalEl = document.getElementById('scheduleModal');
        if (!modalEl) return;

        const form = document.getElementById('scheduleForm');
        const method = document.getElementById('scheduleMethod');

        if (schedule) {
            form.action = window.reportScheduleUpdateUrl.replace('__ID__', schedule.id);
            method.value = 'PUT';
            document.getElementById('scheduleModalTitle').textContent = 'Zamanlanmış Raporu Düzenle';
            document.getElementById('schedType').value = schedule.type;
            document.getElementById('schedFrequency').value = schedule.frequency;
            document.getElementById('schedRange').value = schedule.range;
            document.getElementById('schedFormat').value = schedule.format;
            document.getElementById('schedRecipients').value = schedule.recipients;
            document.getElementById('schedActive').checked = !!schedule.is_active;
        } else {
            form.action = window.reportScheduleStoreUrl;
            method.value = 'POST';
            document.getElementById('scheduleModalTitle').textContent = 'Yeni Zamanlanmış Rapor';
            form.reset();
            document.getElementById('schedActive').checked = true;
            if (presetType) document.getElementById('schedType').value = presetType;
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    window.openDeleteScheduleModal = function (id, name) {
        const modalEl = document.getElementById('deleteScheduleModal');
        if (!modalEl) return;

        document.getElementById('deleteScheduleName').textContent = name;
        document.getElementById('deleteScheduleForm').action = window.reportScheduleDeleteUrl.replace('__ID__', id);

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    document.addEventListener('DOMContentLoaded', function () {
        animateCounters();
        drawChart();
    });
})();
