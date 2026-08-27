'use strict';

/**
 * Kampanya eklerini tek tek, kendi isteğiyle yükler.
 *
 * Ekler eskiden kampanya formunun içindeydi: on dosya seçilince gövde
 * post_max_size'ı aşıyor, PHP gövdeyi komple atıyor ve CSRF alanı da onunla
 * gittiği için istek 419 dönüyordu. Kullanıcı yazdığı kampanyayı, alıcı
 * listesini, hepsini kaybediyordu — üstelik FormRequest'teki nazik hata
 * mesajı çalışma fırsatı bile bulamıyordu.
 *
 * Şimdi her dosya seçilir seçilmez kendi küçük isteğiyle gidiyor:
 *   - toplam gövde sınırına hiç yaklaşılmıyor,
 *   - yavaş bağlantıda ilerleme görünüyor, kullanıcı ekranın donduğunu sanmıyor,
 *   - bir dosya başarısız olursa yalnızca o dosya kaybediliyor.
 *
 * Sunucu dosyayı geçici olarak saklayıp bir belirteç dönüyor; forma gizli alan
 * olarak yalnızca o belirteç ekleniyor. Yol istemciye hiç verilmiyor, yoksa
 * kaydederken başka bir yol gönderip sunucudaki herhangi bir dosyayı
 * kampanyaya iliştirmek mümkün olurdu.
 */
(function () {
    var input = document.getElementById('attachmentInput');
    var liste = document.getElementById('pendingAttachments');

    if (!input || !liste || !window.campaignAttachments) {
        return;
    }

    var ayar = window.campaignAttachments;
    var token = document.querySelector('meta[name="csrf-token"]');
    var csrf = token ? token.getAttribute('content') : '';

    var BIRIMLER = ['B', 'KB', 'MB', 'GB'];

    function okunurBoyut(bytes) {
        var i = 0;

        while (bytes >= 1024 && i < BIRIMLER.length - 1) {
            bytes /= 1024;
            i++;
        }

        return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + BIRIMLER[i];
    }

    function yuklenenSayisi() {
        return liste.querySelectorAll('.cmp-attachment').length;
    }

    /**
     * Zaten kaydedilmiş ekler de sayılıyor; sınır kampanyanın toplam eki için.
     * Bekleyen liste dışarıda bırakılıyor, yoksa yuklenenSayisi() ile iki kez
     * sayılır ve kullanıcı sınıra yarı yolda takılır.
     */
    function kayitliEkSayisi() {
        var tumu = document.querySelectorAll('.cmp-attachments .cmp-attachment');

        return Array.prototype.filter.call(tumu, function (satir) {
            return !liste.contains(satir);
        }).length;
    }

    function hataGoster(mesaj) {
        if (window.AdminModal && typeof window.AdminModal.status === 'function') {
            window.AdminModal.status({ title: 'Ek eklenemedi', message: mesaj, type: 'danger' });

            return;
        }

        var kutu = document.createElement('div');
        kutu.className = 'invalid-feedback d-block';
        kutu.textContent = mesaj;
        liste.appendChild(kutu);

        setTimeout(function () {
            kutu.remove();
        }, 6000);
    }

    /**
     * Yükleme sürerken satırı ilerleme çubuğuyla çizer; bitince belirteci
     * gizli alana yazıp satırı kalıcı hâle getirir.
     */
    function satirOlustur(dosya) {
        var satir = document.createElement('div');
        satir.className = 'cmp-attachment cmp-attachment--yukleniyor';

        var ikon = document.createElement('i');
        ikon.className = 'bi bi-arrow-up-circle';

        var ad = document.createElement('span');
        ad.className = 'cmp-attachment__name';
        ad.textContent = dosya.name;

        var boyut = document.createElement('span');
        boyut.className = 'cmp-attachment__size';
        boyut.textContent = okunurBoyut(dosya.size);

        var oran = document.createElement('span');
        oran.className = 'cmp-attachment__progress';
        oran.textContent = '0%';

        satir.appendChild(ikon);
        satir.appendChild(ad);
        satir.appendChild(boyut);
        satir.appendChild(oran);
        liste.appendChild(satir);

        return { satir: satir, ikon: ikon, oran: oran };
    }

    function kaldirDugmesi(satir, belirtec) {
        var dugme = document.createElement('button');
        dugme.type = 'button';
        dugme.className = 'usr-action-btn danger';
        dugme.title = 'Kaldır';
        dugme.innerHTML = '<i class="bi bi-x-lg"></i>';

        dugme.addEventListener('click', function () {
            dugme.disabled = true;

            fetch(ayar.discardUrl.replace('TOKEN', belirtec), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function () {
                satir.remove();
            }).catch(function () {
                dugme.disabled = false;
                hataGoster('Ek kaldırılamadı, tekrar deneyin.');
            });
        });

        satir.appendChild(dugme);
    }

    /**
     * XMLHttpRequest kullanılıyor çünkü fetch yükleme ilerlemesini bildirmiyor;
     * yavaş bağlantıda kullanıcının en çok ihtiyaç duyduğu şey tam olarak bu.
     */
    function yukle(dosya) {
        var ui = satirOlustur(dosya);
        var veri = new FormData();
        veri.append('file', dosya);

        var istek = new XMLHttpRequest();
        istek.open('POST', ayar.uploadUrl);
        istek.setRequestHeader('X-CSRF-TOKEN', csrf);
        istek.setRequestHeader('Accept', 'application/json');
        istek.withCredentials = true;

        istek.upload.addEventListener('progress', function (olay) {
            if (olay.lengthComputable) {
                ui.oran.textContent = Math.round((olay.loaded / olay.total) * 100) + '%';
            }
        });

        istek.addEventListener('load', function () {
            var cevap = {};

            try {
                cevap = JSON.parse(istek.responseText || '{}');
            } catch (e) {
                cevap = {};
            }

            if (istek.status >= 200 && istek.status < 300 && cevap.token) {
                ui.satir.classList.remove('cmp-attachment--yukleniyor');
                ui.ikon.className = 'bi bi-file-earmark-fill';
                ui.oran.remove();

                var gizli = document.createElement('input');
                gizli.type = 'hidden';
                gizli.name = 'attachment_tokens[]';
                gizli.value = cevap.token;
                ui.satir.appendChild(gizli);

                kaldirDugmesi(ui.satir, cevap.token);

                return;
            }

            ui.satir.remove();
            hataGoster(cevap.message || (dosya.name + ' yüklenemedi.'));
        });

        istek.addEventListener('error', function () {
            ui.satir.remove();
            hataGoster(dosya.name + ' yüklenemedi; bağlantınızı kontrol edin.');
        });

        istek.send(veri);
    }

    // Alanı boşaltmak change'i yeniden tetikliyor; bayrak olmasa dinleyici
    // kendi kendini sonsuza dek çağırır.
    var temizleniyor = false;

    input.addEventListener('change', function () {
        if (temizleniyor) {
            temizleniyor = false;

            return;
        }

        var dosyalar = Array.prototype.slice.call(input.files || []);

        dosyalar.forEach(function (dosya) {
            if (yuklenenSayisi() + kayitliEkSayisi() >= ayar.maxFiles) {
                hataGoster('En fazla ' + ayar.maxFiles + ' ek ekleyebilirsiniz.');

                return;
            }

            // Sunucunun kabul etmeyeceği bir dosyayı yola çıkarmanın anlamı yok:
            // yavaş bağlantıda dakikalarca yükleyip sonunda hata almak yerine
            // kullanıcı anında öğreniyor.
            if (dosya.size > ayar.maxBytes) {
                hataGoster(
                    dosya.name + ' çok büyük (' + okunurBoyut(dosya.size) + '). ' +
                    'Sunucunun kabul ettiği en büyük dosya ' + okunurBoyut(ayar.maxBytes) + '.'
                );

                return;
            }

            yukle(dosya);
        });

        // Alan boşaltılıyor: aynı dosya tekrar seçilebilsin ve file-input.js'in
        // kendi listesi yüklenenlerle karışmasın.
        if (dosyalar.length > 0) {
            temizleniyor = true;
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
})();
