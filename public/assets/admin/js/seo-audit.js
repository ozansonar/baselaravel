/**
 * SEO denetim paneli.
 *
 * Formdaki alanları toplayıp sunucuya gönderiyor, dönen bulguları listeliyor.
 * Denetim kaydedilmiş kayda değil, formun o anki hâline bakıyor — asıl değeri
 * bu: kaydettikten sonra söylenen bir uyarı, düzeltmesi bir tur daha
 * gerektiren bir uyarıdır.
 *
 * Panel formun bir parçası değil: hiçbir alanı yok, hiçbir şey göndermiyor ve
 * kaydetmeyi engellemiyor.
 */
(function () {
    'use strict';

    /* Yazarken her tuşta istek atmamak için bekleme. Sunucu tarafında da hız
       sınırı var; burası onu zorlamamak için. */
    var GECIKME = 900;

    var zamanlayicilar = new WeakMap();
    var sonIstekler = new WeakMap();

    /* ---- Alan okuma ---- */

    /**
     * Panelin ait olduğu dil sekmesindeki alanı bulur.
     *
     * Çok dilli formda her dilin kendi alanı var ve adları dizi biçiminde
     * yazılıyor: translations[tr][meta_title]. Panel yalnız kendi dilini
     * denetlemeli — Türkçesi tam, İngilizcesi eksik bir yazının tek bir "tamam"
     * alması yanıltıcı olurdu.
     */
    function alan(panel, ad) {
        var dil = panel.dataset.seoLocale;
        var form = panel.closest('form');
        if (!form) return null;

        var secici = '[name="translations[' + dil + '][' + ad + ']"]';
        return form.querySelector(secici) || form.querySelector('[name="' + ad + '"]');
    }

    function deger(panel, ad) {
        var el = alan(panel, ad);
        if (!el) return '';

        /* Zengin metin alanının gerçek içeriği editörde; textarea kaydetme
           anına kadar boş kalabiliyor. */
        if (typeof tinymce !== 'undefined' && el.id) {
            var editor = tinymce.get(el.id);
            if (editor) return editor.getContent();
        }

        return el.value || '';
    }

    function govdeAlanAdi(panel) {
        return panel.dataset.seoType === 'page' ? 'content' : 'body';
    }

    /**
     * Kapak görseli seçili mi?
     *
     * Görsel alanı x-image-field bileşeninden geliyor ve dile bağlı:
     * translations[tr][image]. İki durumda dolu sayılıyor — ya bu oturumda
     * yeni bir dosya seçilmiş, ya da kayıtlı bir görsel duruyor. İkincisini
     * bileşenin önizleme kutusu ele veriyor (görsel yokken d-none ile gizli),
     * çünkü kayıtlı yol formda bir alan olarak taşınmıyor.
     *
     * "Kaldır" işaretlenmişse görsel gidiyor demektir; o durumda boş sayılıyor.
     */
    function kapakGorseli(panel) {
        var form = panel.closest('form');
        if (!form) return '';

        var dil = panel.dataset.seoLocale;

        var kaldir = form.querySelector('[name="translations[' + dil + '][remove_image]"]');
        if (kaldir && kaldir.checked) return '';

        var dosya = form.querySelector('[name="translations[' + dil + '][image]"]')
            || form.querySelector('[name="image"]');

        if (dosya && dosya.type === 'file' && dosya.files && dosya.files.length) {
            return dosya.files[0].name;
        }

        /* Kayıtlı görsel: önizleme kutusu açıksa bir görsel var. */
        var kutu = dosya && dosya.closest('[data-cover]');
        var onizleme = kutu && kutu.querySelector('[data-cover-box]');

        if (onizleme && !onizleme.classList.contains('d-none')) {
            var ad = kutu.querySelector('[data-cover-name]');
            return ad && ad.textContent.trim() !== '' ? ad.textContent.trim() : 'kapak';
        }

        return '';
    }

    /* ---- İstek ---- */

    function denetle(panel) {
        var onceki = sonIstekler.get(panel);
        if (onceki) onceki.abort();

        var kontrol = new AbortController();
        sonIstekler.set(panel, kontrol);

        durumGoster(panel, 'busy');

        var govde = new FormData();
        govde.append('locale', panel.dataset.seoLocale);
        govde.append('type', panel.dataset.seoType);
        govde.append('title', deger(panel, 'title'));
        govde.append('slug', deger(panel, 'slug'));
        govde.append('meta_title', deger(panel, 'meta_title'));
        govde.append('meta_description', deger(panel, 'meta_description'));
        govde.append(govdeAlanAdi(panel), deger(panel, govdeAlanAdi(panel)));
        govde.append('image', kapakGorseli(panel));

        fetch(panel.dataset.seoUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: govde,
            credentials: 'same-origin',
            signal: kontrol.signal
        })
            .then(function (yanit) {
                if (!yanit.ok) throw new Error('HTTP ' + yanit.status);
                return yanit.json();
            })
            .then(function (veri) {
                if (!veri || !veri.report) throw new Error('Beklenmeyen yanıt');
                ciz(panel, veri.report);
            })
            .catch(function (hata) {
                /* İptal edilen istek hata değil: kullanıcı yazmaya devam etti. */
                if (hata.name === 'AbortError') return;
                durumGoster(panel, 'failed');
            });
    }

    /* ---- Çizim ---- */

    function durumGoster(panel, durum) {
        ['idle', 'busy', 'clean', 'failed'].forEach(function (ad) {
            var el = panel.querySelector('[data-seo-' + ad + ']');
            if (el) el.hidden = ad !== durum;
        });

        var liste = panel.querySelector('[data-seo-list]');
        if (liste) liste.hidden = durum !== 'issues';

        if (durum === 'issues') {
            ['idle', 'busy', 'clean', 'failed'].forEach(function (ad) {
                var el = panel.querySelector('[data-seo-' + ad + ']');
                if (el) el.hidden = true;
            });
        }
    }

    function ciz(panel, rapor) {
        var puan = panel.querySelector('[data-seo-score]');
        var puanDeger = panel.querySelector('[data-seo-score-value]');

        if (puan && puanDeger) {
            puanDeger.textContent = rapor.score;
            puanDeger.className = 'seo-score seo-score--' + rapor.grade;
            puan.hidden = false;
        }

        var liste = panel.querySelector('[data-seo-list]');
        if (!liste) return;

        liste.textContent = '';

        if (!rapor.issues.length) {
            durumGoster(panel, 'clean');
            return;
        }

        rapor.issues.forEach(function (bulgu) {
            liste.appendChild(satir(panel, bulgu));
        });

        durumGoster(panel, 'issues');
    }

    function satir(panel, bulgu) {
        var li = document.createElement('li');
        li.className = 'seo-issue seo-issue--' + bulgu.level;

        var rozet = document.createElement('span');
        rozet.className = 'seo-issue__level';
        rozet.textContent = bulgu.level;
        li.appendChild(rozet);

        var govde = document.createElement('div');
        govde.className = 'seo-issue__body';

        var mesaj = document.createElement('p');
        mesaj.className = 'seo-issue__message';
        mesaj.textContent = bulgu.message;
        govde.appendChild(mesaj);

        if (bulgu.hint) {
            var ipucu = document.createElement('p');
            ipucu.className = 'seo-issue__hint';
            ipucu.textContent = bulgu.hint;
            govde.appendChild(ipucu);
        }

        li.appendChild(govde);

        /* Bulgu bir alana bağlıysa oraya götüren düğme: yazarın aradığı şey
           uyarının kendisi değil, düzelteceği alan. */
        var hedef = bulgu.field ? alan(panel, bulgu.field) : null;

        if (hedef) {
            var git = document.createElement('button');
            git.type = 'button';
            git.className = 'seo-issue__goto';
            git.textContent = '↗';
            git.setAttribute('aria-label', bulgu.message);
            git.addEventListener('click', function () {
                hedef.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (typeof hedef.focus === 'function') hedef.focus({ preventScroll: true });
            });
            li.appendChild(git);
        }

        return li;
    }

    /* ---- Bağlama ---- */

    function geciktir(panel) {
        clearTimeout(zamanlayicilar.get(panel));
        zamanlayicilar.set(panel, setTimeout(function () {
            denetle(panel);
        }, GECIKME));
    }

    document.addEventListener('click', function (olay) {
        var dugme = olay.target.closest('[data-seo-run]');
        if (!dugme) return;

        var panel = dugme.closest('[data-seo-panel]');
        if (panel) denetle(panel);
    });

    document.addEventListener('input', function (olay) {
        var form = olay.target.closest('form');
        if (!form) return;

        var ad = olay.target.getAttribute('name') || '';
        if (!/\[(title|slug|meta_title|meta_description|body|content)\]|^(title|slug|meta_title|meta_description|body|content)$/.test(ad)) {
            return;
        }

        form.querySelectorAll('[data-seo-panel]').forEach(function (panel) {
            /* Yalnız o dilin paneli: başka sekmedeki panel boşuna koşmasın. */
            if (ad.indexOf('[' + panel.dataset.seoLocale + ']') !== -1 || ad.indexOf('[') === -1) {
                geciktir(panel);
            }
        });
    });
})();
