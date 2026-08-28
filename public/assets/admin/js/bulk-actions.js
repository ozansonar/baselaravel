'use strict';

/**
 * Liste ekranlarında toplu işlem: seçim, sayaç ve onaylı gönderim.
 *
 * Önceden her liste kendi kopyasını taşıyordu ve hiçbiri çalışmıyordu —
 * "Sil" düğmesi kutuları temizliyor, sunucuya istek gitmiyordu (tema
 * dosyasındaki durağan gösterim Blade'e arka ucu kurulmadan taşınmış).
 * Kod tek yere alındı; her liste yalnız işaretleri yazıyor.
 *
 * İşaretler:
 *   [data-bulk-item]              satır kutusu, value = kayıt kimliği
 *   [data-bulk-all]               başlıktaki "tümünü seç"
 *   [data-bulk-bar]               seçim yapılınca beliren çubuk
 *   [data-bulk-count]             seçili sayısının yazıldığı yer
 *   [data-bulk-clear]             seçimi bırakma düğmesi
 *   [data-bulk-action="formId"]   işlemi başlatan düğme
 *       data-bulk-title           onay penceresinin başlığı
 *       data-bulk-message         gövdesi — :count seçili sayıyla değişir
 *       data-bulk-type            danger | warning | info (varsayılan warning)
 *       data-bulk-confirm         onay düğmesinin metni
 *       data-bulk-icon            onay düğmesinin ikonu
 *
 * Kutular listenin içinde, gönderilecek formlar dışında duruyor: satırlarda
 * kendi silme formları var, iç içe form olmaz. Kimlikler gönderim anında
 * forma buradan yazılıyor.
 */
(function () {
    function secilenler() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item]:checked'));
    }

    function hepsi() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item]'));
    }

    function tazele() {
        var secili = secilenler().length;
        var cubuk = document.querySelector('[data-bulk-bar]');

        if (!cubuk) {
            return;
        }

        cubuk.classList.toggle('d-none', secili === 0);

        var sayac = cubuk.querySelector('[data-bulk-count]');

        if (sayac) {
            sayac.textContent = secili;
        }

        // Başlıktaki kutu listenin durumunu anlatıyor: hepsi seçiliyse dolu,
        // bir kısmı seçiliyse belirsiz (üçüncü hâl), hiçbiri seçili değilse boş.
        var tumu = document.querySelector('[data-bulk-all]');

        if (tumu) {
            var toplam = hepsi().length;
            tumu.checked = toplam > 0 && secili === toplam;
            tumu.indeterminate = secili > 0 && secili < toplam;
        }
    }

    function temizle() {
        hepsi().forEach(function (kutu) { kutu.checked = false; });
        tazele();
    }

    /** Seçilen kimlikleri forma yazar ve gönderir. */
    function gonder(form) {
        // Önceki turdan kalan alanlar siliniyor: aynı sayfada iki kez
        // gönderilirse kimlikler birikirdi.
        form.querySelectorAll('input[name="ids[]"]').forEach(function (el) { el.remove(); });

        secilenler().forEach(function (kutu) {
            var alan = document.createElement('input');
            alan.type = 'hidden';
            alan.name = 'ids[]';
            alan.value = kutu.value;
            form.appendChild(alan);
        });

        form.submit();
    }

    function calistir(dugme) {
        var secili = secilenler();

        if (secili.length === 0) {
            return;
        }

        var form = document.getElementById(dugme.dataset.bulkAction);

        if (!form) {
            return;
        }

        var mesaj = (dugme.dataset.bulkMessage || ':count kayıt için işlemi onaylıyor musunuz?')
            .replace(':count', String(secili.length));

        // Onay penceresi olmadan hiçbir toplu işlem çalışmıyor: yanlış
        // tıklamayla onlarca kayıt gitmesin.
        if (window.AdminModal && typeof AdminModal.confirm === 'function') {
            AdminModal.confirm({
                title: dugme.dataset.bulkTitle || 'Toplu İşlem Onayı',
                message: mesaj,
                type: dugme.dataset.bulkType || 'warning',
                confirmText: dugme.dataset.bulkConfirm || 'Evet, Devam Et',
                confirmIcon: dugme.dataset.bulkIcon || 'bi bi-check-lg'
            }).then(function (onay) {
                if (onay) {
                    gonder(form);
                }
            });

            return;
        }

        // AdminModal yoksa işlem sessizce yapılmıyor; tarayıcının kendi
        // sorusu son çare.
        if (window.confirm(mesaj)) {
            gonder(form);
        }
    }

    document.addEventListener('change', function (olay) {
        var hedef = olay.target;

        if (hedef.matches('[data-bulk-all]')) {
            hepsi().forEach(function (kutu) { kutu.checked = hedef.checked; });
            tazele();

            return;
        }

        if (hedef.matches('[data-bulk-item]')) {
            tazele();
        }
    });

    document.addEventListener('click', function (olay) {
        var temizleDugmesi = olay.target.closest('[data-bulk-clear]');

        if (temizleDugmesi) {
            olay.preventDefault();
            temizle();

            return;
        }

        var dugme = olay.target.closest('[data-bulk-action]');

        if (dugme) {
            olay.preventDefault();
            calistir(dugme);
        }
    });

    document.addEventListener('DOMContentLoaded', tazele);
})();
