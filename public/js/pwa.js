/**
 * Servis çalışanının kaydı.
 *
 * Sayfa yüklendikten sonra kaydediliyor: kurulum sırasında kabuk varlıkları
 * indiriliyor ve bunu ilk boyamanın önüne koymak, siteyi ilk açan ziyaretçiyi
 * bekletmek olurdu.
 *
 * Kayıt başarısızsa sessizce geçiliyor — site servis çalışanı olmadan da
 * çalışıyor, konsola hata basmak ziyaretçiye bir şey kazandırmıyor.
 */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) return;

    // Güvenli bağlam şartı: HTTPS ya da localhost. Şart sağlanmıyorsa tarayıcı
    // zaten reddediyor, denemek gereksiz.
    if (!window.isSecureContext) return;

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {
            /* sessiz: servis çalışanı olmadan da her şey çalışıyor */
        });
    });
})();
