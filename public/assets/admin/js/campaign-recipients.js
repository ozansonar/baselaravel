'use strict';

/**
 * Kampanya detayında alıcı listesi: toplu seçim ve toplu işlem.
 *
 * On binlerce alıcılı bir listede satırları tek tek çıkarmak gerçekçi değil;
 * süzgeçle daraltıp topluca işlemek gerekiyor. Seçim kutuları tablonun içinde
 * ama toplu işlem formunun dışında duruyor (satır işlemleri de form, iç içe
 * form geçersiz) — HTML'in form niteliğiyle bağlanıyorlar.
 */
(function () {
    var form = document.getElementById('recipientBulkForm');

    if (!form) {
        return;
    }

    var bar = document.getElementById('recipientBulkBar');
    var sayac = document.getElementById('recipientBulkCount');
    var eylemAlani = document.getElementById('recipientBulkAction');
    var tumu = document.getElementById('recipientSelectAll');
    var birak = document.getElementById('recipientBulkClear');
    var kutular = Array.prototype.slice.call(document.querySelectorAll('.js-recipient-row'));

    function secili() {
        return kutular.filter(function (k) { return k.checked; });
    }

    function tazele() {
        var adet = secili().length;

        bar.classList.toggle('d-none', adet === 0);
        sayac.textContent = String(adet);

        if (tumu) {
            tumu.checked = adet > 0 && adet === kutular.length;
            // Kısmi seçim üçüncü bir durum: kutu ne boş ne dolu görünmeli.
            tumu.indeterminate = adet > 0 && adet < kutular.length;
        }
    }

    kutular.forEach(function (kutu) {
        kutu.addEventListener('change', tazele);
    });

    if (tumu) {
        tumu.addEventListener('change', function () {
            kutular.forEach(function (kutu) { kutu.checked = tumu.checked; });
            tazele();
        });
    }

    if (birak) {
        birak.addEventListener('click', function () {
            kutular.forEach(function (kutu) { kutu.checked = false; });
            tazele();
        });
    }

    var METINLER = {
        exclude: {
            title: 'Gönderimden Çıkar',
            message: 'Seçilen alıcılara bu kampanya gönderilmeyecek. Gönderilmiş adresler etkilenmez.',
            confirmText: 'Evet, Çıkar',
            confirmIcon: 'bi bi-person-dash',
            type: 'danger'
        },
        restore: {
            title: 'Sıraya Al',
            message: 'Çıkarılmış alıcılar yeniden gönderim sırasına eklenecek.',
            confirmText: 'Evet, Sıraya Al',
            confirmIcon: 'bi bi-arrow-counterclockwise',
            type: 'warning'
        },
        retry: {
            title: 'Yeniden Dene',
            message: 'Başarısız alıcıların deneme sayacı sıfırlanacak ve sıraya geri alınacaklar.',
            confirmText: 'Evet, Yeniden Dene',
            confirmIcon: 'bi bi-arrow-clockwise',
            type: 'warning'
        }
    };

    document.querySelectorAll('.js-bulk').forEach(function (dugme) {
        dugme.addEventListener('click', function () {
            var eylem = dugme.dataset.action;
            var adet = secili().length;

            if (adet === 0) {
                return;
            }

            var metin = METINLER[eylem];

            AdminModal.confirm({
                title: metin.title,
                message: metin.message,
                detailTitle: adet + ' alıcı seçildi',
                type: metin.type,
                confirmText: metin.confirmText,
                confirmIcon: metin.confirmIcon
            }).then(function (onay) {
                if (onay) {
                    eylemAlani.value = eylem;
                    form.submit();
                }
            });
        });
    });

    // Tüm başarısızları yeniden dene: seçimden bağımsız, kampanya geneli.
    document.querySelectorAll('.js-retry-all').forEach(function (dugme) {
        dugme.addEventListener('click', function () {
            AdminModal.confirm({
                title: 'Başarısızları Yeniden Dene',
                message: 'Bu kampanyadaki tüm başarısız alıcılar sıraya geri alınacak ve ' +
                    'deneme sayaçları sıfırlanacak. Kampanya tamamlanmışsa yeniden gönderime açılır.',
                type: 'warning',
                confirmText: 'Evet, Yeniden Dene',
                confirmIcon: 'bi bi-arrow-clockwise'
            }).then(function (onay) {
                if (onay) {
                    document.getElementById('retryAllForm').submit();
                }
            });
        });
    });

    tazele();
})();
