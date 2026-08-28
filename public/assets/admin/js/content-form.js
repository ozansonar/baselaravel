'use strict';

/**
 * İçerik formlarının ortak parçaları: karakter sayacı ve SEO önizlemesi.
 *
 * Önceden bu iki fonksiyonun content-add.js ve page-form.js içinde birebir
 * iki kopyası vardı ve ikisi de çok dilli refactor'dan (090007a) sonra
 * kırılmıştı: #title, #slug, #meta_title gibi eksiz id'leri arıyorlardı,
 * oysa alanlar title_tr / title_en biçiminde. Okudukları her alan null
 * döndüğü için önizleme hep başlangıç metninde kalıyor, sayaçlar hiç
 * değişmiyordu.
 *
 * Kural: her alan "ad_dil" biçiminde (title_tr, meta_title_en). Dil, son alt
 * çizgiden sonrası. Buna bağlı iki ad kalıbı:
 *   sayaç span'ı   → {alanId}-counter          (title_tr-counter)
 *   önizleme span'ı → seoPreview{Parça}_{dil}   (seoPreviewTitle_tr)
 */
(function () {
    /** "meta_title_tr" → "tr" */
    function dilKodu(alanId) {
        var son = String(alanId || '').lastIndexOf('_');

        return son === -1 ? '' : alanId.slice(son + 1);
    }

    function alan(ad, dil) {
        return document.getElementById(ad + '_' + dil);
    }

    function deger(ad, dil) {
        var e = alan(ad, dil);

        return e ? e.value.trim() : '';
    }

    /**
     * Yer tutucu metin span'ın kendi başlangıç içeriğinden alınıyor: blog ve
     * sayfa formları farklı metinler kullanıyor ("yeni-icerik" / "sayfa-url"),
     * ikisini JS'te ayrıca tanımlamak gerekmiyor.
     */
    function yerTutucu(span) {
        if (span.dataset.yerTutucu === undefined) {
            span.dataset.yerTutucu = span.textContent.trim();
        }

        return span.dataset.yerTutucu;
    }

    function yaz(spanAdi, dil, metin) {
        var span = document.getElementById(spanAdi + '_' + dil);

        if (!span) {
            return;
        }

        span.textContent = metin || yerTutucu(span);
    }

    /* ==================== Karakter sayacı ==================== */

    /**
     * @param {HTMLElement} girdi
     * @param {number}      enFazla
     */
    window.updateCharCounter = function (girdi, enFazla) {
        var sayac = document.getElementById(girdi.id + '-counter');

        if (!sayac) {
            return;
        }

        var uzunluk = girdi.value.length;
        sayac.textContent = uzunluk;

        // Sınırı aşan sayaç kırmızıya dönüyor; alan zaten maxlength taşıyor
        // ama yapıştırma ile aşılabiliyor.
        sayac.classList.toggle('text-danger', enFazla > 0 && uzunluk > enFazla);
    };

    /* ==================== SEO önizlemesi ==================== */

    /**
     * @param {HTMLElement|string} kaynak Tetikleyen alan ya da doğrudan dil kodu
     */
    window.updateSeoPreview = function (kaynak) {
        var dil = typeof kaynak === 'string'
            ? kaynak
            : dilKodu(kaynak && kaynak.id);

        if (!dil) {
            return;
        }

        // Meta başlık boşsa içeriğin kendi başlığı gösteriliyor: arama
        // sonucunda da öyle davranıyor.
        yaz('seoPreviewTitle', dil, deger('meta_title', dil) || deger('title', dil));
        yaz('seoPreviewDesc', dil, deger('meta_description', dil));
        yaz('seoPreviewSlug', dil, deger('slug', dil));
    };

    /* ==================== Slug değişince tazeleme ==================== */

    // slug.js başlıktan slug ürettiğinde hedefe input olayı GÖNDERMİYOR:
    // kendi dinleyicisine geri düşer ve alanı "elle düzenlendi" sayardı.
    // Bunun yerine kendi olayını yayıyor, önizleme ona bağlanıyor.
    document.addEventListener('slug:degisti', function (olay) {
        window.updateSeoPreview(olay.target);
    });

    // Açılışta bir kez: düzenleme ekranında dolu gelen alanlar önizlemeye
    // yansısın, kullanıcı bir tuşa basmadan da doğru görsün.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[id^="seoPreviewSlug_"]').forEach(function (span) {
            window.updateSeoPreview(span.id.slice('seoPreviewSlug_'.length));
        });

        // Sayaçlar da açılışta doğru başlasın.
        document.querySelectorAll('[id$="-counter"]').forEach(function (sayac) {
            var girdi = document.getElementById(sayac.id.slice(0, -'-counter'.length));

            if (girdi) {
                sayac.textContent = girdi.value.length;
            }
        });
    });
})();
