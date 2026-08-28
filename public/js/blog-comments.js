'use strict';

/**
 * Blog yazısındaki yorum formu.
 *
 * Sayfa yenilenmeden gönderiliyor: yorumunu yazan kişi okuduğu yazının başına
 * dönmemeli. Bu yüzden doğrulama motorunun kendi submit kancasına
 * güvenilemiyor — kanca yalnız tarayıcının gönderimini durdurabiliyor, fetch
 * çağrısını değil — ve istek atılmadan hemen önce burada soruluyor.
 *
 * Metinler ve reCAPTCHA'nın açık olup olmadığı script etiketinin data
 * nitelikleriyle sunucudan geliyor; dosyanın içinde gömülü Türkçe metin yok.
 */
(function () {
    var script = document.currentScript;

    function label(name, fallback) {
        return (script && script.dataset[name]) || fallback;
    }

    var METIN = {
        gonderiliyor: label('sending', 'Gönderiliyor...'),
        genelHata: label('errorGeneric', 'Bir hata oluştu.'),
        baglantiHatasi: label('errorRetry', 'Bir hata oluştu. Lütfen tekrar deneyin.'),
        recaptchaGerekli: label('recaptchaRequired', 'Lütfen robot olmadığınızı doğrulayın.')
    };

    var recaptchaAcik = label('recaptchaEnabled', '0') === '1';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('blogCommentForm');

        if (!form) {
            return;
        }

        var button = document.getElementById('blogCommentSubmit');
        var csrf = document.querySelector('meta[name="csrf-token"]');

        function sonucGoster(tur, mesaj) {
            if (typeof window.showResultModal === 'function') {
                window.showResultModal(tur, mesaj);
            }
        }

        /** Robot kutusu işaretlendi mi? Kapalıysa soru zaten yok. */
        function recaptchaYaniti() {
            if (!recaptchaAcik || typeof grecaptcha === 'undefined') {
                return '';
            }

            try {
                return grecaptcha.getResponse();
            } catch (e) {
                return '';
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            // Alan kuralları doğrulama motorunun; hata balonları alanların
            // altında beliriyor, kutuya taşınmıyor.
            if (window.FormValidation && !window.FormValidation.isValid(form)) {
                return;
            }

            // Kutu işaretlenmeden istek atmanın anlamı yok: sunucu zaten
            // reddedecek, ziyaretçi bir tur bekledikten sonra aynı şeyi
            // duyacaktı.
            if (recaptchaAcik && recaptchaYaniti() === '') {
                sonucGoster('warning', METIN.recaptchaGerekli);

                return;
            }

            var originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + METIN.gonderiliyor;

            var veri = {};
            new FormData(form).forEach(function (value, key) {
                if (key !== '_token') {
                    veri[key] = value;
                }
            });

            if (!veri['g-recaptcha-response']) {
                veri['g-recaptcha-response'] = recaptchaYaniti();
            }

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.content : '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(veri)
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (sonuc) {
                    button.disabled = false;
                    button.innerHTML = originalHtml;

                    if (sonuc.success) {
                        form.reset();

                        if (recaptchaAcik && typeof grecaptcha !== 'undefined') {
                            grecaptcha.reset();
                        }

                        sonucGoster('success', sonuc.message);

                        return;
                    }

                    if (sonuc.errors) {
                        // Satırlar dizi olarak veriliyor: kutu her birini kendi
                        // düğümü olarak basıyor. '<br>' ile birleştirilseydi
                        // etiketin kendisi ekranda görünürdü.
                        sonucGoster('error', Object.keys(sonuc.errors).map(function (alan) {
                            return sonuc.errors[alan][0];
                        }));

                        return;
                    }

                    sonucGoster('error', sonuc.message || METIN.genelHata);
                })
                .catch(function () {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                    sonucGoster('error', METIN.baglantiHatasi);
                });
        });
    });
})();
