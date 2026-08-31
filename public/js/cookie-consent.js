'use strict';

/**
 * Çerez rıza bandı — yalnızca ilerlemeli iyileştirme.
 *
 * Bandın kendisi betiksiz de çalışıyor: düğmeler gerçek submit, hangi
 * kategorilere izin verildiğini sunucu `choice` alanından çözüyor ve tercihi
 * değiştirme bağlantısı `:target` ile CSS'ten açılıyor.
 *
 * Buradaki tek iş, "Ayarla" düğmesinin ayrıntıları açıp düğme takımını
 * değiştirmesi. Betik yüklenmezse ayrıntılar kapalı kalır ve ziyaretçi yine
 * "tümünü kabul et" ile "yalnızca zorunlu" arasında seçim yapabilir — yani
 * hiçbir hak kaybı olmuyor.
 */
(function () {
    var banner = document.getElementById('cookieConsent');

    if (!banner) return;

    var details = document.getElementById('cookieConsentDetails');
    var customise = document.getElementById('cookieConsentCustomise');
    var save = document.getElementById('cookieConsentSave');

    if (!details || !customise || !save) return;

    function open() {
        details.hidden = false;
        customise.setAttribute('aria-expanded', 'true');
        customise.hidden = true;

        // Ayrıntı açıkken "tümünü kabul et" duruyor; kaydet düğmesi
        // işaretlenenleri gönderiyor, "yalnızca zorunlu" da yerinde kalıyor.
        save.hidden = false;

        var first = details.querySelector('.cc-check:not(:disabled)');

        if (first) first.focus();
    }

    customise.addEventListener('click', open);
}());
