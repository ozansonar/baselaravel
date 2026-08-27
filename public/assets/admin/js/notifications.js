'use strict';

/**
 * /admin/bildirimler — bildirim merkezi.
 *
 * Okundu/okunmadı işareti sayfayı yenilemeden değişir: liste uzun olabiliyor,
 * her tıklamada başa dönmek okumayı bozardı. Silme ve toplu işlemler ise
 * listeyi gerçekten değiştirdiği için normal form gönderimiyle yapılır.
 */
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function post(url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() }
        }).then(function (response) { return response.json(); });
    }

    // ==================== OKUNDU / OKUNMADI ====================

    document.querySelectorAll('.nt-toggle-read').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = button.closest('.nt-item');
            var icon = button.querySelector('i');
            var isUnread = item.classList.contains('unread');
            var url = isUnread ? button.dataset.readUrl : button.dataset.unreadUrl;

            button.disabled = true;

            post(url)
                .then(function (data) {
                    if (!data.success) return;

                    item.classList.toggle('unread', !isUnread);
                    button.title = isUnread ? 'Okunmadı yap' : 'Okundu yap';

                    if (icon) {
                        icon.className = isUnread ? 'bi bi-envelope' : 'bi bi-envelope-open';
                    }

                    // "Okunmadı" etiketi durumla birlikte gelip gider.
                    var tags = item.querySelector('.nt-item-tags');
                    var unreadTag = item.querySelector('.nt-tag.pending');

                    if (isUnread && unreadTag) {
                        unreadTag.remove();
                    } else if (!isUnread && tags && !unreadTag) {
                        var tag = document.createElement('span');

                        tag.className = 'nt-tag pending';
                        tag.innerHTML = '<i class="bi bi-envelope"></i> Okunmadı';
                        tags.appendChild(tag);
                    }
                })
                .finally(function () { button.disabled = false; });
        });
    });

    // ==================== TOPLU SEÇİM ====================

    var bulkBar = document.getElementById('ntBulkBar');
    var bulkCount = document.getElementById('ntBulkCount');
    var bulkReadBtn = document.getElementById('ntBulkReadBtn');
    var bulkDeleteBtn = document.getElementById('ntBulkDeleteBtn');
    var bulkReadForm = document.getElementById('ntBulkReadForm');
    var bulkDeleteForm = document.getElementById('ntBulkDeleteForm');

    function selectedIds() {
        return Array.prototype.slice.call(document.querySelectorAll('.nt-check:checked'))
            .map(function (checkbox) { return checkbox.value; });
    }

    function refreshBulkBar() {
        var count = selectedIds().length;

        if (bulkBar) bulkBar.classList.toggle('d-none', count === 0);
        if (bulkCount) bulkCount.textContent = String(count);
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.classList.contains('nt-check')) {
            refreshBulkBar();
        }
    });

    function submitWithIds(form, ids, button) {
        form.querySelectorAll('input[name="ids[]"]').forEach(function (input) { input.remove(); });

        ids.forEach(function (id) {
            var input = document.createElement('input');

            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });

        if (button) button.disabled = true;
        form.submit();
    }

    if (bulkReadBtn && bulkReadForm) {
        bulkReadBtn.addEventListener('click', function () {
            var ids = selectedIds();

            if (ids.length === 0) return;

            submitWithIds(bulkReadForm, ids, bulkReadBtn);
        });
    }

    if (bulkDeleteBtn && bulkDeleteForm) {
        bulkDeleteBtn.addEventListener('click', function () {
            var ids = selectedIds();

            if (ids.length === 0) return;

            AdminModal.confirm({
                title: 'Seçilenleri Sil',
                message: ids.length + ' bildirim kalıcı olarak silinecek.',
                warning: 'Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, Sil',
                confirmIcon: 'bi bi-trash3'
            }).then(function (confirmed) {
                if (!confirmed) return;

                submitWithIds(bulkDeleteForm, ids, bulkDeleteBtn);
            });
        });
    }

    refreshBulkBar();

    // ==================== TEKİL SİLME ====================

    document.querySelectorAll('.nt-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') return;

            event.preventDefault();

            AdminModal.confirm({
                title: 'Bildirimi Sil',
                message: 'Bu bildirim kalıcı olarak silinecek.',
                detailTitle: form.dataset.title || '',
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

    // ==================== TÜMÜ ====================

    var markAllBtn = document.getElementById('ntMarkAllRead');

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            AdminModal.confirm({
                title: 'Tümünü Okundu Yap',
                message: 'Okunmamış bütün bildirimler okundu olarak işaretlenecek.',
                type: 'warning',
                confirmText: 'Onayla',
                confirmIcon: 'bi bi-check-all'
            }).then(function (confirmed) {
                if (!confirmed) return;

                markAllBtn.disabled = true;
                post(markAllBtn.dataset.url)
                    .then(function () { window.location.reload(); });
            });
        });
    }

    var clearAllBtn = document.getElementById('ntClearAll');
    var clearAllForm = document.getElementById('ntClearAllForm');

    if (clearAllBtn && clearAllForm) {
        clearAllBtn.addEventListener('click', function () {
            AdminModal.confirm({
                title: 'Tümünü Temizle',
                message: 'Listedeki bütün bildirimler silinecek.',
                warning: 'Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, Temizle',
                confirmIcon: 'bi bi-trash3'
            }).then(function (confirmed) {
                if (!confirmed) return;

                clearAllBtn.disabled = true;
                clearAllForm.submit();
            });
        });
    }
})();
