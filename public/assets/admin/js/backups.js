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

    // ==================== TOPLU SEÇİM ====================

    var selectAll = document.getElementById('bkSelectAll');
    var bulkBar = document.getElementById('bkBulkActions');
    var bulkCount = document.getElementById('bkSelectedCount');
    var bulkBtn = document.getElementById('bkBulkDeleteBtn');
    var bulkForm = document.getElementById('bkBulkForm');

    function rowCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.bk-checkbox'));
    }

    function selectedNames() {
        return rowCheckboxes()
            .filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return checkbox.value; });
    }

    function refreshBulkBar() {
        var selected = selectedNames();

        if (bulkBar) bulkBar.classList.toggle('d-none', selected.length === 0);
        if (bulkCount) bulkCount.textContent = String(selected.length);

        if (selectAll) {
            var all = rowCheckboxes();

            selectAll.checked = all.length > 0 && selected.length === all.length;
            // Bir kısmı seçiliyken kutu ne boş ne dolu görünsün.
            selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes().forEach(function (checkbox) { checkbox.checked = selectAll.checked; });
            refreshBulkBar();
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.classList.contains('bk-checkbox')) {
            refreshBulkBar();
        }
    });

    if (bulkBtn && bulkForm) {
        bulkBtn.addEventListener('click', function () {
            var names = selectedNames();

            if (names.length === 0) return;

            AdminModal.confirm({
                title: 'Seçilen Yedekleri Sil',
                message: names.length + ' yedek dosyası kalıcı olarak silinecek.',
                detailTitle: names.length > 3
                    ? names.slice(0, 3).join(', ') + ' ve ' + (names.length - 3) + ' dosya daha'
                    : names.join(', '),
                warning: 'Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, Sil',
                confirmIcon: 'bi bi-trash3'
            }).then(function (confirmed) {
                if (!confirmed) return;

                // Seçilenler forma taşınıyor: kutular tablonun içinde, form ise
                // satırlardaki tekil silme formlarıyla iç içe geçmesin diye
                // tablonun dışında duruyor.
                bulkForm.querySelectorAll('input[name="files[]"]').forEach(function (input) {
                    input.remove();
                });

                names.forEach(function (name) {
                    var input = document.createElement('input');

                    input.type = 'hidden';
                    input.name = 'files[]';
                    input.value = name;
                    bulkForm.appendChild(input);
                });

                bulkBtn.disabled = true;
                bulkForm.submit();
            });
        });
    }

    refreshBulkBar();

    // ==================== TEKİL SİLME ====================

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

// ==================== GERİ YÜKLEME ====================
//
// Geri yükleme geri alınamaz bir işlem: veritabanı yedekteki hâline döner ve
// aradaki her değişiklik gider. Bu yüzden önce arşive bakılıyor ve onay
// penceresi tam olarak neyin uygulanacağını yazıyor — "emin misiniz?" tek
// başına bu kararı vermeye yetmez.
(function () {
    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function summarise(info) {
        var parts = [];

        if (info.has_database) parts.push('veritabanının tamamı');
        if (info.has_uploads) parts.push(info.upload_count + ' yüklenen dosya');

        return parts.length ? parts.join(' ve ') : 'içerik yok';
    }

    function runRestore(button) {
        fetch(button.getAttribute('data-restore-url'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                AdminModal.status({
                    title: data.success ? 'Geri yüklendi' : 'Geri yüklenemedi',
                    message: data.message + (data.safety_backup
                        ? '\n\nÖnceki durumun yedeği: ' + data.safety_backup
                        : ''),
                    type: data.success ? 'success' : 'danger'
                });

                if (data.success) setTimeout(function () { window.location.reload(); }, 2500);
            })
            .catch(function (error) {
                AdminModal.status({ title: 'Geri yüklenemedi', message: error.message, type: 'danger' });
            });
    }

    function confirmRestore(button) {
        fetch(button.getAttribute('data-inspect-url'), { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (info) {
                if (!info.ok) {
                    AdminModal.status({ title: 'Bu yedek kullanılamıyor', message: info.message, type: 'danger' });

                    return;
                }

                AdminModal.confirm({
                    title: 'Yedeği geri yükle',
                    message: button.getAttribute('data-filename') + ' uygulanacak: ' + summarise(info) + '.\n\n'
                        + 'Bu yedekten sonra yapılan tüm değişiklikler kaybolur ve kullanıcı '
                        + 'hesapları da yedekteki hâline döner — oturumunuz kapanabilir.\n\n'
                        + 'İşlemden önce mevcut durumun yedeği otomatik alınır.',
                    type: 'danger',
                    confirmText: 'Geri Yükle',
                    confirmIcon: 'bi bi-arrow-counterclockwise'
                }).then(function (confirmed) {
                    if (confirmed) runRestore(button);
                });
            })
            .catch(function (error) {
                AdminModal.status({ title: 'Yedek okunamadı', message: error.message, type: 'danger' });
            });
    }

    function bindRestore() {
        document.querySelectorAll('.bk-restore').forEach(function (button) {
            button.addEventListener('click', function () { confirmRestore(button); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindRestore);
    } else {
        bindRestore();
    }
}());
