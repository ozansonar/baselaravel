'use strict';

/**
 * /admin/kuyruk — başarısız işlerin ayrıntısı, yeniden denemesi ve silinmesi.
 *
 * Yeniden deneme ve silme birer form; düğmeler formu doğrudan göndermek yerine
 * önce onay penceresini açıyor. Yığın izi listeye sığmadığı için ayrı bir
 * pencerede, istendiğinde çekiliyor: sayfanın her satırında tam metni taşımak
 * listeyi gereksiz şişirirdi.
 */
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    // ==================== HATA AYRINTISI ====================

    var detailModal = document.getElementById('qDetailModal');
    var detailTitle = document.getElementById('qDetailTitle');
    var detailMeta = document.getElementById('qDetailMeta');
    var detailBody = document.getElementById('qDetailException');

    function openDetail(url) {
        if (!detailModal || !detailBody) return;

        detailTitle.textContent = 'Hata Ayrıntısı';
        detailMeta.textContent = '';
        detailBody.textContent = 'Yükleniyor…';

        new bootstrap.Modal(detailModal).show();

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    detailBody.textContent = data.message || 'Kayıt okunamadı.';

                    return;
                }

                detailTitle.textContent = data.job;
                detailMeta.textContent = data.queue + ' kuyruğu · ' + data.failed_at + ' · ' + data.uuid;
                detailBody.textContent = data.exception || '(hata metni boş)';
            })
            .catch(function (error) {
                detailBody.textContent = 'İstek başarısız: ' + error.message;
            });
    }

    // ==================== ONAY GEREKTİREN İŞLEMLER ====================

    /**
     * Düğmenin sarmalayıcı formunu onaydan sonra gönderir.
     */
    function confirmThenSubmit(button, config) {
        var form = button.closest('form');

        if (!form) return;

        AdminModal.confirm(config).then(function (confirmed) {
            if (confirmed) form.submit();
        });
    }

    function bind() {
        document.querySelectorAll('.qs-detail').forEach(function (button) {
            button.addEventListener('click', function () {
                openDetail(button.getAttribute('data-url'));
            });
        });

        document.querySelectorAll('.qs-retry').forEach(function (button) {
            button.addEventListener('click', function () {
                confirmThenSubmit(button, {
                    title: 'Yeniden dene',
                    message: button.getAttribute('data-job') + ' işi kuyruğa geri konacak ve '
                        + 'sıradaki turda yeniden çalıştırılacak.',
                    type: 'warning',
                    confirmText: 'Kuyruğa Al',
                    confirmIcon: 'bi bi-arrow-clockwise'
                });
            });
        });

        document.querySelectorAll('.qs-delete').forEach(function (button) {
            button.addEventListener('click', function () {
                confirmThenSubmit(button, {
                    title: 'Kaydı sil',
                    message: button.getAttribute('data-job') + ' işinin kaydı silinecek. '
                        + 'Hata metni de gider, iş bir daha çalıştırılamaz.',
                    type: 'danger',
                    confirmText: 'Sil',
                    confirmIcon: 'bi bi-trash'
                });
            });
        });

        var flushBtn = document.getElementById('qFlushBtn');

        if (flushBtn) {
            flushBtn.addEventListener('click', function () {
                confirmThenSubmit(flushBtn, {
                    title: 'Listeyi temizle',
                    message: 'Başarısız iş kayıtlarının tamamı silinecek. Hata metinleri de '
                        + 'gider ve hiçbiri yeniden çalıştırılamaz.',
                    type: 'danger',
                    confirmText: 'Hepsini Sil',
                    confirmIcon: 'bi bi-trash'
                });
            });
        }

        // Kuyruğu işlemek saniyeler sürebiliyor; düğme iki kez basılmasın.
        var runForm = document.getElementById('qRunForm');
        var runBtn = document.getElementById('qRunBtn');

        if (runForm && runBtn) {
            runForm.addEventListener('submit', function () {
                runBtn.disabled = true;
                runBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> İşleniyor…';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
}());
