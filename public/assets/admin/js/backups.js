'use strict';

/**
 * /admin/yedekler — manuel yedek alma ve silme onayı.
 *
 * Yedek alma isteği uzun sürebildiği için düğme bekleme durumuna geçer ve
 * sonuç sayfanın üstündeki şeride yazılır; başarılı olursa liste tazelensin
 * diye sayfa yeniden yüklenir.
 */
(function () {
    var runBtn = document.getElementById('bkRunBtn');
    var result = document.getElementById('bkResult');

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function showResult(type, html) {
        if (!result) return;

        result.className = 'alert alert-' + type;
        result.innerHTML = html;
    }

    function setLoading(isLoading) {
        if (!runBtn) return;

        var defaultLabel = runBtn.querySelector('[data-default]');
        var loadingLabel = runBtn.querySelector('[data-loading]');

        runBtn.disabled = isLoading;

        if (defaultLabel) defaultLabel.classList.toggle('d-none', isLoading);
        if (loadingLabel) loadingLabel.classList.toggle('d-none', !isLoading);
    }

    function runBackup() {
        setLoading(true);

        if (result) {
            result.classList.add('d-none');
            result.innerHTML = '';
        }

        fetch(runBtn.dataset.createUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() }
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (response) {
                if (response.ok && response.data.success) {
                    showResult('success',
                        '<i class="bi bi-check-circle-fill me-1"></i> <strong>' + response.data.file +
                        '</strong> oluşturuldu (' + response.data.size_human + '). Liste yenileniyor…');

                    window.setTimeout(function () { window.location.reload(); }, 1500);

                    return;
                }

                showResult('danger',
                    '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' +
                    (response.data.message || 'Yedek alınamadı'));
                setLoading(false);
            })
            .catch(function (error) {
                showResult('danger',
                    '<i class="bi bi-exclamation-triangle-fill me-1"></i> İstek başarısız: ' + error.message);
                setLoading(false);
            });
    }

    if (runBtn) {
        runBtn.addEventListener('click', function () {
            AdminModal.confirm({
                title: 'Yedek Al',
                message: 'Yedek alma işlemi başlatılacak. Büyük dosyalar varsa birkaç dakika sürebilir.',
                type: 'warning',
                confirmText: 'Başlat',
                confirmIcon: 'bi bi-play-fill'
            }).then(function (confirmed) {
                if (confirmed) runBackup();
            });
        });
    }

    document.querySelectorAll('.bk-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            // Onaydan sonraki ikinci submit'i tekrar yakalamayalım.
            if (form.dataset.confirmed === '1') return;

            event.preventDefault();

            AdminModal.confirm({
                title: 'Yedek Sil',
                message: 'Bu yedek dosyası kalıcı olarak silinecek.',
                detailTitle: form.dataset.filename || '',
                warning: 'Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, Sil',
                confirmIcon: 'bi bi-trash3'
            }).then(function (confirmed) {
                if (!confirmed) return;

                form.dataset.confirmed = '1';
                form.submit();
            });
        });
    });
})();
