/**
 * Dosya Yöneticisi listesi (/admin/files).
 *
 * Sorumluluk:
 *   - Izgara / liste görünüm anahtarı (tercih localStorage'da kalır)
 *   - Full URL kopyalama
 *   - Silme onayı (AdminModal) + DELETE formu
 *   - Arama kutusunda Enter ile filtre gönderimi
 *   - Yükleme bitince listeyi sayfayı yenilemeden tazeleme
 *
 * Tazeleme neden fetch ile: yükleme kuyruğunda hata satırları duruyor olabilir,
 * sayfa yenilenirse kullanıcı hangi dosyanın neden düştüğünü kaybediyor. Bu
 * yüzden yalnız liste gövdesi ve istatistik kartları sunucudan gelen taze HTML
 * ile değiştiriliyor. Kart işaretlemesi tek kaynakta — Blade'de — kalır.
 *
 * Yükleme alanı ayrı dosyada: file-manager-upload.js
 *
 * CLAUDE.md uyumu: vanilla JS, alert/confirm yok, CSRF meta'dan.
 */
'use strict';

window.FileManagerList = (function () {
    var VIEW_KEY = 'fmgr.view';

    /* ---------- Görünüm anahtarı ---------- */

    function storedView() {
        try {
            return window.localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'grid';
        } catch (e) {
            return 'grid';
        }
    }

    function applyView(view) {
        var collection = document.getElementById('fmgrCollection');
        var isList = view === 'list';

        if (collection) {
            collection.classList.toggle('fmgr-collection--list', isList);
            collection.classList.toggle('fmgr-collection--grid', !isList);
        }

        document.querySelectorAll('[data-fmgr-view]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-fmgr-view') === view);
        });
    }

    function selectView(view) {
        applyView(view);

        try {
            window.localStorage.setItem(VIEW_KEY, view);
        } catch (e) {
            // Gizli sekmede depolama kapalı olabilir — görünüm yine değişir.
        }
    }

    /* ---------- Listeyi sunucudan tazeleme ---------- */

    /**
     * Sayfanın kendisini yeniden çeker; liste gövdesini ve istatistik
     * kartlarını değiştirir. Sayfa yenilenmediği için yükleme kuyruğundaki
     * hata satırları ekranda kalır.
     *
     * @returns {Promise<boolean>} tazeleme başarılıysa true
     */
    function refreshList() {
        var body = document.getElementById('fmgrListBody');

        if (!body) {
            return Promise.resolve(false);
        }

        body.classList.add('fmgr-list-busy');

        return fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.getElementById('fmgrListBody');

                // Oturum düşmüşse giriş sayfası gelir; o HTML'de liste yok.
                if (!fresh) {
                    throw new Error('Liste gövdesi bulunamadı');
                }

                body.innerHTML = fresh.innerHTML;

                // AOS bir kez çalıştığı için sonradan gelen düğümler saydam
                // kalırdı; animasyonu bitmiş kabul ediyoruz.
                body.querySelectorAll('[data-aos]').forEach(function (el) {
                    el.classList.add('aos-animate');
                });

                syncStats(doc);
                applyView(storedView());

                return true;
            })
            .catch(function () {
                return false;
            })
            .then(function (ok) {
                body.classList.remove('fmgr-list-busy');

                return ok;
            });
    }

    /** stat-counter.js sayıları nokta ayraçla yazar; tazelerken aynı biçim. */
    function formatCount(value) {
        return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function syncStats(doc) {
        doc.querySelectorAll('[data-fmgr-stat]').forEach(function (fresh) {
            var key = fresh.getAttribute('data-fmgr-stat');
            var current = document.querySelector('[data-fmgr-stat="' + key + '"]');

            if (!current) {
                return;
            }

            if (fresh.hasAttribute('data-count')) {
                // Sayaç animasyonu bir kez çalıştı; taze değeri doğrudan yaz.
                current.setAttribute('data-count', fresh.getAttribute('data-count'));
                current.dataset.animated = '1';
                current.textContent = formatCount(fresh.getAttribute('data-count'));

                return;
            }

            current.textContent = fresh.textContent;
        });
    }

    /* ---------- Kopyala ---------- */

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

    /* ---------- Sil ---------- */

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

    /* ---------- Bağlama ---------- */

    document.addEventListener('DOMContentLoaded', function () {
        applyView(storedView());

        var searchInput = document.getElementById('fmgrSearch');
        var filterForm = document.getElementById('filterForm');

        if (searchInput && filterForm) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filterForm.submit();
                }
            });
        }
    });

    // Liste gövdesi tazelenince düğümler değiştiği için dinleyiciler
    // document üzerinde: yeniden bağlamaya gerek kalmıyor.
    document.addEventListener('click', function (e) {
        var viewBtn = e.target.closest('[data-fmgr-view]');
        if (viewBtn) {
            selectView(viewBtn.getAttribute('data-fmgr-view'));

            return;
        }

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

    return {
        refresh: refreshList,
        applyView: applyView,
    };
})();
