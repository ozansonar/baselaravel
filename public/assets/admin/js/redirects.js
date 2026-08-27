'use strict';

/**
 * Yönlendirme listesi: silme onayı, etkinlik anahtarı, adres kopyalama ve
 * gecikmeli arama.
 *
 * Ekleme ve düzenleme artık kendi sayfasında; bu dosyada yalnızca liste
 * üzerindeki işler var.
 */
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = meta ? meta.getAttribute('content') : '';

    // ── Silme onayı ──────────────────────────────────────────────
    // AdminModal.confirm bir Promise döndürüyor; geri çağrı seçeneği yok.
    document.querySelectorAll('.js-delete-form').forEach(function (formEl) {
        formEl.addEventListener('submit', function (event) {
            var button = formEl.querySelector('button[data-confirm]');
            var message = button ? button.dataset.confirm : 'Silmek istediğinize emin misiniz?';

            if (!window.AdminModal || typeof AdminModal.confirm !== 'function') {
                return;
            }

            event.preventDefault();

            AdminModal.confirm({
                title: 'Yönlendirmeyi sil',
                message: message,
                type: 'danger',
                confirmText: 'Evet, Sil',
                confirmIcon: 'bi bi-trash'
            }).then(function (onaylandi) {
                if (onaylandi) {
                    formEl.submit();
                }
            });
        });
    });

    // ── Etkin / kapalı anahtarı ──────────────────────────────────
    document.querySelectorAll('.js-toggle-active').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var acik = checkbox.checked;

            fetch(checkbox.dataset.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    return response.json();
                })
                .then(function (sonuc) {
                    if (!sonuc.success) {
                        throw new Error('reddedildi');
                    }

                    bildir(
                        acik ? 'Yönlendirme etkinleştirildi.' : 'Yönlendirme kapatıldı.',
                        'success'
                    );
                })
                .catch(function () {
                    // Sunucu kabul etmediyse anahtar da eski hâline dönmeli:
                    // aksi hâlde ekran kaydedilmemiş bir durumu gösterir.
                    checkbox.checked = !acik;
                    bildir('Durum güncellenemedi, tekrar deneyin.', 'danger');
                });
        });
    });

    // ── Adres kopyalama ──────────────────────────────────────────
    document.querySelectorAll('.js-copy-url').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.dataset.url || '';

            if (!navigator.clipboard || !navigator.clipboard.writeText) {
                bildir('Tarayıcı kopyalamayı desteklemiyor.', 'warning');

                return;
            }

            navigator.clipboard.writeText(value)
                .then(function () { bildir('Adres kopyalandı.', 'success'); })
                .catch(function () { bildir('Adres kopyalanamadı.', 'danger'); });
        });
    });

    // ── Yazarken arama ───────────────────────────────────────────
    var searchInput = document.getElementById('redirectSearch');
    var filterForm = document.getElementById('filterForm');

    if (searchInput && filterForm) {
        var timer = null;

        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { filterForm.submit(); }, 500);
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                clearTimeout(timer);
                filterForm.submit();
            }
        });
    }

    /**
     * AdminModal.status bir fonksiyon; success/error diye alt fonksiyonları yok.
     * Yanlış çağrı sessiz değil gürültülü bir hataydı: anahtar değişiyor ama
     * kullanıcı hiçbir şey görmüyordu.
     */
    function bildir(message, type) {
        if (window.AdminModal && typeof AdminModal.status === 'function') {
            AdminModal.status({
                title: type === 'success' ? 'Tamam' : 'Hata',
                message: message,
                type: type
            });
        }
    }
})();
