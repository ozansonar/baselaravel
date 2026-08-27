'use strict';

/**
 * Dosya alanlarını panelin diline çeviren sarmalayıcı.
 *
 * Tarayıcının kendi kontrolü ("Choose File / No file chosen") ne biçimlenebiliyor
 * ne de Türkçeleşebiliyor; koyu temada da yamalı duruyordu. Burada alanın
 * etrafına bir kutu örülüyor ve asıl <input> saydam olarak o kutunun üstüne
 * yayılıyor.
 *
 * Girdinin kendisi yerinde kaldığı için:
 *   - name, accept, multiple ve doğrulama nitelikleri aynen çalışır,
 *   - tıklama ve sürükle-bırak tarayıcının kendi davranışıdır, taklit değil,
 *   - change olayı doğal olarak tetiklenir; ona bağlı kodlar (Excel önizlemesi,
 *     avatar önizlemesi) değişmeden çalışmaya devam eder.
 *
 * Ayarlardaki logo alanları gibi kendi düğmesi olan gizli girdiler atlanır.
 */
(function () {
    var BOYUT_BIRIMLERI = ['B', 'KB', 'MB', 'GB'];

    function okunurBoyut(bytes) {
        var i = 0;

        while (bytes >= 1024 && i < BOYUT_BIRIMLERI.length - 1) {
            bytes /= 1024;
            i++;
        }

        return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + BOYUT_BIRIMLERI[i];
    }

    /**
     * accept niteliğini okunur bir ipucuna çevirir: "image/jpeg,image/png" →
     * "JPEG, PNG". Kullanıcı hangi dosyayı seçebileceğini kutunun üstünde görür.
     */
    function turIpucu(input) {
        var accept = (input.getAttribute('accept') || '').trim();

        if (accept === '' || accept === '*/*') {
            return '';
        }

        var parcalar = accept.split(',').map(function (parca) {
            parca = parca.trim();

            if (parca === 'image/*') return 'Görsel';
            if (parca.indexOf('/') !== -1) return parca.split('/')[1].replace('x-icon', 'ico').toUpperCase();

            return parca.replace('.', '').toUpperCase();
        });

        // Aynı uzantı iki kez yazılmış olabilir (ör. image/jpeg ve .jpg).
        return parcalar.filter(function (deger, i) {
            return parcalar.indexOf(deger) === i;
        }).join(', ');
    }

    function dosyaListesi(input) {
        return Array.prototype.slice.call(input.files || []);
    }

    function ciz(kutu, input) {
        var liste = kutu.querySelector('.fu__files');
        var dosyalar = dosyaListesi(input);

        kutu.classList.toggle('fu--dolu', dosyalar.length > 0);
        liste.innerHTML = '';

        dosyalar.forEach(function (dosya) {
            var satir = document.createElement('span');
            satir.className = 'fu__file';

            var ad = document.createElement('span');
            ad.className = 'fu__file-name';
            ad.textContent = dosya.name;

            var boyut = document.createElement('span');
            boyut.className = 'fu__file-size';
            boyut.textContent = okunurBoyut(dosya.size);

            satir.appendChild(ad);
            satir.appendChild(boyut);
            liste.appendChild(satir);
        });
    }

    /**
     * Sarmalayıcının uzak durması gereken alanlar.
     *
     * Üçüncü parti bileşenler kendi dosya girdilerini kendileri yönetiyor:
     * TinyMCE'nin görsel yükleme diyalogu (.tox) ve Dropzone (.dropzone)
     * girdiyi kendi kutusuna yerleştiriyor, seçilen dosyayı kendi arayüzünde
     * gösteriyor. Bunların üstüne bir de bizim kutumuz örülünce diyalogun
     * yerleşimi bozuluyor ve seçilen dosyalar bileşenin dışına, sayfanın
     * altına taşıyordu.
     *
     * data-fu-skip ile tek bir alan da elle dışarıda bırakılabilir.
     */
    var YABANCI_KAPSAYICILAR = '.tox, .dropzone, [data-fu-skip]';

    function bizeAitMi(input) {
        // Dropzone gizli girdisini kendi kutusuna değil doğrudan <body>'ye
        // ekliyor, bu yüzden kapsayıcı listesine takılmıyor. Bizim hiçbir
        // alanımız body'nin doğrudan çocuğu değil: sayfanın kendi işaretlemesi
        // içinde durur. Oraya iliştirilmiş bir girdi bir bileşenin yardımcısıdır.
        if (input.parentElement === document.body) {
            return false;
        }

        return !input.closest(YABANCI_KAPSAYICILAR);
    }

    function kur(input) {
        if (input.dataset.fuReady === '1' || input.hasAttribute('hidden')) {
            return;
        }

        if (!bizeAitMi(input)) {
            // Bir daha bakılmasın: bileşen girdiyi taşırsa tekrar denenmesin.
            input.dataset.fuReady = 'yabanci';

            return;
        }

        input.dataset.fuReady = '1';

        var kutu = document.createElement('div');
        kutu.className = 'fu';

        var ipucu = turIpucu(input);
        var coklu = input.multiple;

        kutu.innerHTML =
            '<span class="fu__icon"><i class="bi bi-cloud-arrow-up"></i></span>' +
            '<span class="fu__text">' +
                '<strong class="fu__label">' + (coklu ? 'Dosyaları seçin' : 'Dosya seçin') + '</strong>' +
                '<small class="fu__hint">' +
                    'ya da buraya sürükleyin' + (ipucu ? ' · ' + ipucu : '') +
                '</small>' +
            '</span>' +
            '<span class="fu__files"></span>' +
            '<button type="button" class="fu__clear" title="Seçimi kaldır" aria-label="Seçimi kaldır">' +
                '<i class="bi bi-x-lg"></i>' +
            '</button>';

        input.parentNode.insertBefore(kutu, input);
        kutu.appendChild(input);
        input.classList.add('fu__native');

        // Sürükleme sırasında kutu vurgulanıyor; bırakma işini tarayıcı yapıyor.
        ['dragenter', 'dragover'].forEach(function (olay) {
            input.addEventListener(olay, function () {
                kutu.classList.add('fu--over');
            });
        });

        ['dragleave', 'drop'].forEach(function (olay) {
            input.addEventListener(olay, function () {
                kutu.classList.remove('fu--over');
            });
        });

        input.addEventListener('change', function () {
            ciz(kutu, input);
        });

        kutu.querySelector('.fu__clear').addEventListener('click', function (event) {
            // Düğme saydam girdinin üstünde duruyor; tıklama alta geçerse
            // dosya seçme penceresi açılır.
            event.preventDefault();
            event.stopPropagation();

            input.value = '';
            // Seçime bağlı kodlar (önizleme) temizlendiğini duymalı.
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        ciz(kutu, input);
    }

    function tara(kok) {
        (kok || document).querySelectorAll('input[type="file"]').forEach(kur);
    }

    document.addEventListener('DOMContentLoaded', function () {
        tara();

        // Sonradan gelen alanlar: pencerede açılan formlar, tekrarlanan satırlar.
        new MutationObserver(function (kayitlar) {
            kayitlar.forEach(function (kayit) {
                kayit.addedNodes.forEach(function (dugum) {
                    if (dugum.nodeType !== 1) {
                        return;
                    }

                    if (dugum.matches && dugum.matches('input[type="file"]')) {
                        kur(dugum);
                    } else {
                        tara(dugum);
                    }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    });
})();
