/**
 * Galeriye toplu fotoğraf yükleme.
 *
 * Dropzone kütüphanesi admin layout'ta zaten yüklü
 * (assets/vendor/dropzone/dropzone.min.js).
 *
 * Neden dosyalar formla gitmiyor: yüz dosya tek POST'ta gitseydi gövde
 * post_max_size'ı aşar, PHP gövdeyi komple atar ve CSRF alanı da onunla gittiği
 * için istek 419 dönerdi. Her dosya kendi küçük isteğiyle gidiyor; yavaş
 * bağlantıda ilerleme görünüyor ve bir dosyanın başarısızlığı ötekileri
 * etkilemiyor.
 *
 * Kayıt yüklemeyle birlikte doğuyor, "Hepsini Kaydet"i beklemiyor: bekletilseydi
 * tarayıcı kapandığında yüz yükleme çöpe giderdi. Alttaki ızgara yalnızca
 * başlıkları düzeltmek için.
 *
 * CLAUDE.md uyumu: vanilla JS, alert/confirm yerine AdminModal, CSRF meta'dan.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var kapsayici = document.querySelector('[data-gbu]');

    if (!kapsayici || typeof Dropzone === 'undefined') {
        return;
    }

    Dropzone.autoDiscover = false;

    var alan     = kapsayici.querySelector('[data-gbu-dropzone]');
    var izgara   = kapsayici.querySelector('[data-gbu-grid]');
    var panel    = kapsayici.querySelector('[data-gbu-panel]');
    var ozet     = kapsayici.querySelector('[data-gbu-summary]');
    var sayacOk  = kapsayici.querySelector('[data-gbu-count-ok]');
    var sayacErr = kapsayici.querySelector('[data-gbu-count-err]');
    var hataKutu = kapsayici.querySelector('.gbu-summary__item--err');
    var ilerleme = kapsayici.querySelector('[data-gbu-progress]');
    var kaydetDugmesi = kapsayici.querySelector('[data-gbu-save]');

    var dilSecimi      = kapsayici.querySelector('[data-gbu-locale]');
    var kategoriSecimi = kapsayici.querySelector('[data-gbu-category]');
    var durumSecimi    = kapsayici.querySelector('[data-gbu-active]');
    var siraBaslangici = kapsayici.querySelector('[data-gbu-sort-start]');

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = meta ? meta.getAttribute('content') : '';

    var ayar = {
        uploadUrl:  kapsayici.dataset.uploadUrl,
        saveUrl:    kapsayici.dataset.saveUrl,
        destroyUrl: kapsayici.dataset.destroyUrl,
        maxBytes:   parseInt(kapsayici.dataset.maxBytes, 10) || 0,
        maxLabel:   kapsayici.dataset.maxLabel || '',
        accept:     kapsayici.dataset.accept || ''
    };

    var basarili = 0;
    var basarisiz = 0;

    // Sıra numarası istemcide veriliyor: yüklemeler paralel gittiği için sunucuda
    // "en büyük + 1" demek iki dosyaya aynı numarayı verirdi. Dosyanın bırakılma
    // sırası burada belli.
    var siraSayaci = 0;

    // ==================== ORTAK ALANLAR ====================

    /**
     * Bütün kategorilerin kopyası. Dil değişince liste buradan yeniden kuruluyor.
     *
     * @type {Array<{id: string, ad: string, dil: string}>}
     */
    var tumKategoriler = [];

    if (kategoriSecimi) {
        Array.prototype.forEach.call(kategoriSecimi.options, function (secenek) {
            if (secenek.value) {
                tumKategoriler.push({
                    id: secenek.value,
                    ad: secenek.textContent,
                    dil: secenek.dataset.locale || ''
                });
            }
        });
    }

    /**
     * Kategoriler de çevrilmiş; yalnızca seçili dilin kategorileri listeleniyor.
     *
     * Seçenekleri gizlemek/pasifleştirmek yetmiyordu: panelde her <select>
     * Select2 ile sarılıyor ve Select2 kendi listesini option'lardan kuruyor —
     * gizli seçeneği yine gösteriyordu. Bu yüzden seçenekler DOM'dan silinip
     * yeniden kuruluyor.
     */
    function kategorileriSuz() {
        if (!kategoriSecimi || !dilSecimi) {
            return;
        }

        var dil = dilSecimi.value;
        var onceki = kategoriSecimi.value;
        var korundu = false;

        // İlk seçenek yer tutucu ("Seçiniz"); duruyor.
        while (kategoriSecimi.options.length > 1) {
            kategoriSecimi.remove(1);
        }

        tumKategoriler.forEach(function (kategori) {
            if (kategori.dil !== dil) {
                return;
            }

            var secenek = document.createElement('option');
            secenek.value = kategori.id;
            secenek.textContent = kategori.ad;
            secenek.dataset.locale = kategori.dil;

            if (kategori.id === onceki) {
                secenek.selected = true;
                korundu = true;
            }

            kategoriSecimi.appendChild(secenek);
        });

        // Önceki seçim başka dilin kategorisiyse düşüyor: Türkçe öğe İngilizce
        // kategoriye bağlanmamalı (sunucu da bunu reddediyor).
        if (!korundu) {
            kategoriSecimi.value = '';
        }

        // Select2 görünümünü tazelesin. jQuery yazmıyoruz; Select2 native change
        // olayını da dinliyor.
        kategoriSecimi.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (dilSecimi) {
        dilSecimi.addEventListener('change', kategorileriSuz);
    }

    kategorileriSuz();

    // ==================== BİLDİRİM ====================

    /**
     * Sorunlar biriktirilip tek pencerede bildiriliyor.
     *
     * Her dosya için ayrı pencere açmak işe yaramıyordu: pencere bir öncekini
     * eziyor ve kullanıcı yalnızca son dosyanın mesajını görüyordu. Yüz dosya
     * bırakıp doksanını listede bulan biri, onunun neden düştüğünü öğrenemezdi.
     */
    var sorunlar = [];
    var bildirimZamani = null;

    function sorunEkle(mesaj) {
        sorunlar.push(mesaj);

        window.clearTimeout(bildirimZamani);
        bildirimZamani = window.setTimeout(sorunlariBildir, 600);
    }

    function sorunlariBildir() {
        if (sorunlar.length === 0) {
            return;
        }

        var bildirilecek = sorunlar.slice();
        sorunlar = [];

        var baslik = bildirilecek.length === 1
            ? 'Dosya yüklenemedi'
            : bildirilecek.length + ' dosya yüklenemedi';

        // Yüz dosyalık bir yüklemede hata listesi ekranı taşırabilir; ilk onu
        // gösterilip gerisi sayılıyor.
        var gosterilecek = bildirilecek.slice(0, 10);
        var kalan = bildirilecek.length - gosterilecek.length;
        var mesaj = gosterilecek.join('\n') + (kalan > 0 ? '\n… ve ' + kalan + ' dosya daha' : '');

        if (window.AdminModal && typeof window.AdminModal.status === 'function') {
            window.AdminModal.status({ title: baslik, message: mesaj, type: 'danger' });

            return;
        }

        window.console.error(baslik, mesaj);
    }

    function bilgiVer(baslik, mesaj, tur) {
        if (window.AdminModal && typeof window.AdminModal.status === 'function') {
            window.AdminModal.status({ title: baslik, message: mesaj, type: tur || 'success' });
        }
    }

    // ==================== IZGARA ====================

    function satirSayisi() {
        return izgara.querySelectorAll('[data-gbu-item]').length;
    }

    function panelTazele() {
        panel.classList.toggle('d-none', satirSayisi() === 0);
    }

    function ozetTazele() {
        ozet.classList.remove('d-none');
        sayacOk.textContent = String(basarili);
        sayacErr.textContent = String(basarisiz);
        hataKutu.classList.toggle('d-none', basarisiz === 0);
    }

    /**
     * Kaydedilmiş öğe için ızgara satırı: küçük önizleme, başlık kutusu,
     * yeni sekmede açma ve kaldırma.
     */
    function satirEkle(cevap) {
        var satir = document.createElement('div');
        satir.className = 'gbu-item';
        satir.dataset.gbuItem = '';
        satir.dataset.itemId = String(cevap.id);

        var gorsel = document.createElement('img');
        gorsel.className = 'gbu-item__thumb';
        gorsel.src = cevap.thumb_url;
        gorsel.alt = cevap.title;
        gorsel.loading = 'lazy';

        var govde = document.createElement('div');
        govde.className = 'gbu-item__body';

        var etiket = document.createElement('label');
        etiket.className = 'gbu-item__label';
        etiket.textContent = 'Başlık';
        etiket.htmlFor = 'gbuTitle' + cevap.id;

        var kutu = document.createElement('input');
        kutu.type = 'text';
        kutu.className = 'form-control form-control-sm';
        kutu.id = 'gbuTitle' + cevap.id;
        kutu.value = cevap.title;
        kutu.maxLength = 255;
        kutu.setAttribute('data-gbu-title', '');
        // Kural motoruna bağlı bir form değil; alan bilerek kuralsız.
        kutu.setAttribute('data-fv-ignore', '');

        govde.appendChild(etiket);
        govde.appendChild(kutu);

        var eylemler = document.createElement('div');
        eylemler.className = 'gbu-item__actions';

        var ac = document.createElement('a');
        ac.className = 'usr-action-btn';
        ac.href = cevap.edit_url;
        ac.target = '_blank';
        ac.rel = 'noopener';
        ac.title = 'Öğeyi yeni sekmede aç';
        ac.innerHTML = '<i class="bi bi-box-arrow-up-right"></i>';

        var sil = document.createElement('button');
        sil.type = 'button';
        sil.className = 'usr-action-btn danger';
        sil.title = 'Kaldır';
        sil.innerHTML = '<i class="bi bi-trash3"></i>';
        sil.addEventListener('click', function () { kaldir(satir, sil, kutu.value); });

        eylemler.appendChild(ac);
        eylemler.appendChild(sil);

        satir.appendChild(gorsel);
        satir.appendChild(govde);
        satir.appendChild(eylemler);
        izgara.appendChild(satir);

        panelTazele();
    }

    function kaldir(satir, dugme, ad) {
        onayAl(ad).then(function (onaylandi) {
            if (!onaylandi) {
                return;
            }

            dugme.disabled = true;

            fetch(ayar.destroyUrl.replace('ITEM_ID', satir.dataset.itemId), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (cevap) {
                if (!cevap.ok) {
                    throw new Error('silinemedi');
                }

                satir.remove();
                basarili = Math.max(0, basarili - 1);
                ozetTazele();
                panelTazele();
            }).catch(function () {
                dugme.disabled = false;
                sorunEkle('"' + ad + '" kaldırılamadı, tekrar deneyin.');
            });
        });
    }

    function onayAl(ad) {
        if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
            return window.AdminModal.confirm({
                title: 'Öğeyi kaldır',
                message: '"' + ad + '" galeriden silinecek.',
                type: 'danger',
                confirmText: 'Evet, kaldır',
                confirmIcon: 'bi bi-trash3'
            });
        }

        return Promise.resolve(true);
    }

    // ==================== YÜKLEME ====================

    var dz = new Dropzone(alan, {
        url: ayar.uploadUrl,
        method: 'POST',
        paramName: 'image',
        maxFiles: null,                 // sayı sınırı yok
        maxFilesize: ayar.maxBytes > 0 ? ayar.maxBytes / 1048576 : null,
        acceptedFiles: ayar.accept || null,
        parallelUploads: 3,
        uploadMultiple: false,          // her dosya ayrı istek — ZORUNLU
        autoProcessQueue: true,
        addRemoveLinks: false,
        // Önizlemeler bırakma alanının içine düşmesin: yüz dosyada alan sayfa
        // boyunca uzuyordu. Satırları başlık ızgarası çiziyor.
        previewsContainer: false,
        createImageThumbnails: false,
        timeout: 180000,
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },

        dictFileTooBig: 'Dosya çok büyük ({{filesize}} MB). En fazla ' + ayar.maxLabel + '.',
        dictInvalidFileType: 'Yalnızca JPG, PNG ve WebP yükleyebilirsin.',
        dictResponseError: 'Sunucu hatası: {{statusCode}}'
    });

    dz.on('sending', function (dosya, xhr, veri) {
        veri.append('_token', csrf);
        veri.append('locale', dilSecimi ? dilSecimi.value : '');
        veri.append('gallery_category_id', kategoriSecimi ? kategoriSecimi.value : '');
        veri.append('is_active', durumSecimi ? durumSecimi.value : '1');

        var baslangic = parseInt(siraBaslangici ? siraBaslangici.value : '0', 10);
        veri.append('sort_order', String((isNaN(baslangic) ? 0 : baslangic) + siraSayaci));
        siraSayaci++;
    });

    dz.on('totaluploadprogress', function (oran) {
        if (ilerleme) {
            ilerleme.style.width = oran + '%';
        }
    });

    dz.on('addedfile', ozetTazele);

    dz.on('success', function (dosya, cevap) {
        if (!cevap || !cevap.id) {
            return;
        }

        basarili++;
        satirEkle(cevap);
        ozetTazele();

        // Kuyruk şişmesin: kayıt oluştu, dosyanın bellekte durmasına gerek yok.
        dz.removeFile(dosya);
    });

    dz.on('error', function (dosya, mesaj) {
        // Sunucu doğrulama hatasını {message, errors} olarak döner; nesne olduğu
        // gibi basılsaydı kullanıcı "[object Object]" görürdü.
        var metin = mesaj;

        if (mesaj && typeof mesaj === 'object') {
            metin = (mesaj.errors && mesaj.errors.image && mesaj.errors.image[0])
                || mesaj.message
                || 'Yüklenemedi.';
        }

        basarisiz++;
        sorunEkle(dosya.name + ': ' + metin);
        ozetTazele();
        dz.removeFile(dosya);
    });

    // ==================== BAŞLIKLARI KAYDET ====================

    kaydetDugmesi.addEventListener('click', function () {
        var kutular = izgara.querySelectorAll('[data-gbu-title]');

        if (kutular.length === 0) {
            return;
        }

        var govde = { titles: {} };
        var bos = [];

        Array.prototype.forEach.call(kutular, function (kutu) {
            var satir = kutu.closest('[data-gbu-item]');
            var deger = kutu.value.trim();

            if (deger === '') {
                bos.push(kutu);

                return;
            }

            govde.titles[satir.dataset.itemId] = deger;
        });

        // Başlık zorunlu bir alan; boş bırakılanı sunucuya götürüp toptan
        // reddedilmektense burada gösteriliyor.
        if (bos.length > 0) {
            bos.forEach(function (kutu) { kutu.classList.add('is-invalid'); });
            bos[0].focus();
            bilgiVer('Başlık boş olamaz', bos.length + ' satırda başlık boş. Doldurup tekrar kaydet.', 'warning');

            return;
        }

        Array.prototype.forEach.call(kutular, function (kutu) { kutu.classList.remove('is-invalid'); });

        kaydetDugmesi.disabled = true;
        var eskiIcerik = kaydetDugmesi.innerHTML;
        kaydetDugmesi.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Kaydediliyor...';

        fetch(ayar.saveUrl, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(govde)
        }).then(function (cevap) {
            if (!cevap.ok) {
                throw new Error('kaydedilemedi');
            }

            return cevap.json();
        }).then(function (sonuc) {
            bilgiVer(
                'Başlıklar kaydedildi',
                sonuc.updated > 0
                    ? sonuc.updated + ' başlık güncellendi.'
                    : 'Değişen başlık yoktu.',
                'success'
            );
        }).catch(function () {
            sorunEkle('Başlıklar kaydedilemedi, tekrar deneyin.');
        }).finally(function () {
            kaydetDugmesi.disabled = false;
            kaydetDugmesi.innerHTML = eskiIcerik;
        });
    });
});
