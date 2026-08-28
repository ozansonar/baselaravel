/**
 * Dosya Yöneticisi listesi (/admin/files).
 *
 * Sorumluluk:
 *   - Izgara / liste görünüm anahtarı (tercih localStorage'da kalır)
 *   - Full URL kopyalama
 *   - Silme onayı (AdminModal) + DELETE formu
 *   - Arama kutusunda Enter ile filtre gönderimi
 *
 * Yükleme alanı ayrı dosyada: file-manager-upload.js
 *
 * CLAUDE.md uyumu: vanilla JS, alert/confirm yok, CSRF meta'dan.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var VIEW_KEY = 'fmgr.view';

    var collection = document.getElementById('fmgrCollection');
    var searchInput = document.getElementById('fmgrSearch');
    var filterForm = document.getElementById('filterForm');

    /* ---------- Görünüm anahtarı ---------- */

    function applyView(view) {
        if (!collection) {
            return;
        }

        var isList = view === 'list';

        collection.classList.toggle('fmgr-collection--list', isList);
        collection.classList.toggle('fmgr-collection--grid', !isList);

        document.querySelectorAll('[data-fmgr-view]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-fmgr-view') === (isList ? 'list' : 'grid'));
        });
    }

    if (collection) {
        var saved;

        try {
            saved = window.localStorage.getItem(VIEW_KEY);
        } catch (e) {
            saved = null;
        }

        applyView(saved === 'list' ? 'list' : 'grid');

        document.querySelectorAll('[data-fmgr-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var view = btn.getAttribute('data-fmgr-view');
                applyView(view);

                try {
                    window.localStorage.setItem(VIEW_KEY, view);
                } catch (e) {
                    // Gizli sekmede depolama kapalı olabilir — görünüm yine değişir.
                }
            });
        });
    }

    /* ---------- Arama ---------- */

    if (searchInput && filterForm) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterForm.submit();
            }
        });
    }

    /* ---------- Kopyala / Sil ---------- */

    document.addEventListener('click', function (e) {
        var copyBtn = e.target.closest('[data-fmgr-copy]');
        if (copyBtn) {
            copyUrl(copyBtn);

            return;
        }

        var deleteBtn = e.target.closest('[data-fmgr-delete]');
        if (deleteBtn) {
            confirmDelete(deleteBtn);
        }
    });

    function copyUrl(btn) {
        var url = btn.getAttribute('data-fmgr-url');
        var name = btn.getAttribute('data-fmgr-name');

        var notify = function (success) {
            if (success) {
                if (typeof showToast === 'function') {
                    showToast('URL kopyalandı: ' + name, 'success');
                }
            } else if (window.AdminModal && typeof AdminModal.status === 'function') {
                AdminModal.status({
                    title: 'Kopyalanamadı',
                    message: 'Tarayıcı izin vermedi. Detay sayfasından elle kopyalayabilirsin.',
                    type: 'warning',
                });
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () { notify(true); }, function () { notify(false); });

            return;
        }

        // Yedek yol: geçici textarea
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.classList.add('visually-hidden');
        document.body.appendChild(ta);
        ta.select();

        try {
            notify(document.execCommand('copy'));
        } catch (err) {
            notify(false);
        }

        document.body.removeChild(ta);
    }

    function confirmDelete(btn) {
        var action = btn.getAttribute('data-fmgr-action');
        var name = btn.getAttribute('data-fmgr-name');
        var csrf = document.querySelector('meta[name="csrf-token"]');

        var doSubmit = function () {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = action;
            form.innerHTML =
                '<input type="hidden" name="_token" value="' + (csrf ? csrf.content : '') + '">'
                + '<input type="hidden" name="_method" value="DELETE">';
            document.body.appendChild(form);
            form.submit();
        };

        if (window.AdminModal && typeof AdminModal.confirm === 'function') {
            AdminModal.confirm({
                title: 'Dosya Silinsin Mi?',
                message: '<strong>' + name + '</strong> kalıcı olarak silinecek (dosya + DB kaydı). Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, Sil',
                confirmIcon: 'bi bi-trash3',
            }).then(function (confirmed) {
                if (confirmed) {
                    doSubmit();
                }
            });
        } else {
            doSubmit();
        }
    }
});
