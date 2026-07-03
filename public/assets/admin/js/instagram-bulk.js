/**
 * Bulk Schedule (Toplu Plan) — progress polling.
 *
 * - Sayfa yüklenince otomatik AJAX polling başlar (3sn)
 * - BulkImport finished durumuna geçince polling durur, "tamamlandı" mesajı çıkar
 * - Hata listesi dinamik güncellenir
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var wrap = document.querySelector('[data-bulk-status-url]');
        if (!wrap) return;

        var statusUrl = wrap.getAttribute('data-bulk-status-url');
        if (!statusUrl) return;

        var statusBadge   = document.getElementById('bulkStatusBadge');
        var processedEl   = document.getElementById('bulkProcessedCount');
        var totalEl       = document.getElementById('bulkTotalCount');
        var successEl     = document.getElementById('bulkSuccessCount');
        var failEl        = document.getElementById('bulkFailCount');
        var progressBar   = document.getElementById('bulkProgressBar');
        var progressPct   = document.getElementById('bulkProgressPct');
        var finishedMsg   = document.getElementById('bulkFinishedMsg');
        var errorsCard    = document.getElementById('bulkErrorsCard');
        var errorsList    = document.getElementById('bulkErrorsList');
        var errorsCount   = document.getElementById('bulkErrorsCount');

        // İlk yüklemede progress bar genişliğini set et
        if (progressBar) {
            var initialPct = parseInt(progressBar.getAttribute('data-pct') || '0', 10);
            progressBar.style.width = initialPct + '%';
        }

        var pollInterval = null;
        var statusLabels = {
            'pending':        'Bekliyor',
            'processing':     'İşleniyor',
            'completed':      'Tamamlandı',
            'partial_failed': 'Kısmen Başarılı',
            'failed':         'Başarısız',
        };

        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function updateUi(data) {
            if (statusBadge) {
                statusBadge.textContent = statusLabels[data.status] || data.status;
                statusBadge.className = 'bulk-status bulk-status-' + data.status;
            }
            if (processedEl) processedEl.textContent = data.processed_rows;
            if (totalEl)     totalEl.textContent = data.total_rows;
            if (successEl)   successEl.textContent = data.success_count;
            if (failEl)      failEl.textContent = data.fail_count;
            if (progressBar) progressBar.style.width = data.progress_pct + '%';
            if (progressPct) progressPct.textContent = data.progress_pct + '%';

            // Hata listesi
            if (data.errors && data.errors.length > 0) {
                if (errorsCard) errorsCard.classList.remove('d-none');
                if (errorsCount) errorsCount.textContent = data.errors.length;
                if (errorsList) {
                    errorsList.innerHTML = '';
                    data.errors.forEach(function (err) {
                        var li = document.createElement('li');
                        li.innerHTML = '<strong>Satır ' + escapeHtml(err.row || '?') + ':</strong> ' + escapeHtml(err.message || '');
                        errorsList.appendChild(li);
                    });
                }
            }

            // Bittiğinde
            if (data.finished) {
                if (finishedMsg) finishedMsg.classList.remove('d-none');
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            }
        }

        function poll() {
            fetch(statusUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(updateUi)
            .catch(function (err) {
                console.warn('Bulk status polling failed:', err);
            });
        }

        // İlk poll'ü hemen yap (sayfa açılışında initial state DB'den geliyor ama
        // job'lar arada işleyip ilerleyebilir)
        poll();

        // 3 saniyede bir polling
        pollInterval = setInterval(poll, 3000);

        // 10 dakika sonra otomatik durdur (timeout safety)
        setTimeout(function () {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }, 10 * 60 * 1000);
    });
})();
