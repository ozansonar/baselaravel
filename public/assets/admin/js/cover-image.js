'use strict';

/**
 * Kapak görseli alanı: önizleme, kaldırma ve galeri.
 *
 * Önceden alanda yalnız boş bir dosya girdisi vardı. Kayıtlı bir kapak olsa
 * bile ekranda görünmüyor, dolayısıyla kaldırmanın da yolu yoktu.
 *
 * Blade tarafındaki kancalar (her dil sekmesi için bir kutu):
 *   [data-cover="tr"]   sarmalayıcı
 *   [data-cover-box]    önizleme kutusu, görsel yoksa d-none
 *   [data-cover-img]    <img>
 *   [data-cover-link]   galeriyi açan <a class="glightbox">
 *   [data-cover-remove] kaldır düğmesi
 *   [data-cover-flag]   remove_image gizli girdisi
 *   input[type=file]    yeni dosya
 */
(function () {
    var galeri = null;

    /** Kutu içindeki parçaları tek yerden topla. */
    function parcalar(kutu) {
        return {
            onizleme: kutu.querySelector('[data-cover-box]'),
            gorsel:   kutu.querySelector('[data-cover-img]'),
            baglanti: kutu.querySelector('[data-cover-link]'),
            ad:       kutu.querySelector('[data-cover-name]'),
            bayrak:   kutu.querySelector('[data-cover-flag]'),
            dosya:    kutu.querySelector('input[type="file"]')
        };
    }

    function galeriyiTazele() {
        if (typeof GLightbox === 'undefined') {
            return;
        }

        // Kaynak değiştiğinde eski örnek eski adresi açmaya devam ediyor.
        if (galeri) {
            galeri.destroy();
        }

        galeri = GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true });
    }

    /* ---------- Kaldırma ---------- */

    function kaldir(kutu) {
        var p = parcalar(kutu);

        p.bayrak.value = '1';
        p.onizleme.classList.add('d-none');

        // Seçilmiş yeni dosya varsa o da gitsin: kullanıcı "kaldır" dedi.
        if (p.dosya) {
            p.dosya.value = '';
            p.dosya.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (typeof showToast === 'function') {
            showToast('Kapak görseli kaydedince kaldırılacak.', 'warning');
        }
    }

    document.addEventListener('click', function (olay) {
        var dugme = olay.target.closest('[data-cover-remove]');

        if (!dugme) {
            return;
        }

        olay.preventDefault();

        var kutu = dugme.closest('[data-cover]');

        if (!kutu) {
            return;
        }

        if (window.AdminModal && typeof AdminModal.confirm === 'function') {
            AdminModal.confirm({
                title: 'Kapak Görseli Kaldırılsın Mı?',
                message: 'Kaydettiğinizde görsel sunucudan da silinecek. Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, Kaldır',
                confirmIcon: 'bi bi-trash3'
            }).then(function (onay) {
                if (onay) {
                    kaldir(kutu);
                }
            });

            return;
        }

        kaldir(kutu);
    });

    /* ---------- Yeni dosya seçilince önizleme ---------- */

    document.addEventListener('change', function (olay) {
        var girdi = olay.target;

        if (!girdi || girdi.type !== 'file') {
            return;
        }

        var kutu = girdi.closest('[data-cover]');

        if (!kutu) {
            return;
        }

        var p = parcalar(kutu);
        var dosya = girdi.files && girdi.files[0];

        if (!dosya) {
            return;
        }

        var adres = URL.createObjectURL(dosya);

        p.gorsel.src = adres;
        p.baglanti.href = adres;
        p.onizleme.classList.remove('d-none');

        if (p.ad) {
            p.ad.textContent = dosya.name;
        }

        // Yeni dosya seçmek kaldırma isteğini geçersiz kılıyor.
        p.bayrak.value = '0';

        galeriyiTazele();
    });

    document.addEventListener('DOMContentLoaded', galeriyiTazele);
})();
