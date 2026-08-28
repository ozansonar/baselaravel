/**
 * Ek görsellerinin büyütme penceresi.
 *
 * Ön yüzde hazır bir lightbox kütüphanesi yok (GLightbox yalnızca yönetim
 * tarafında yüklü) ve tek bir görseli büyütmek için front'a kütüphane eklemek
 * her ziyaretçiye bedel çıkarırdı. Bu yüzden pencere burada, elde kuruluyor.
 *
 * alert/confirm kullanılmıyor; pencere kendi işaretlemesiyle çiziliyor.
 * CLAUDE.md uyumu: vanilla JS, jQuery yok.
 */
'use strict';

(function () {
    var dugmeler = Array.prototype.slice.call(
        document.querySelectorAll('[data-att-lightbox]')
    );

    if (dugmeler.length === 0) {
        return;
    }

    var metin = {
        close: 'Kapat',
        prev: 'Önceki',
        next: 'Sonraki',
        download: 'İndir'
    };

    // Etiketler sunucudan geliyor: pencere ziyaretçinin dilinde açılmalı,
    // JS içine gömülü Türkçe metin İngilizce sayfada yanlış olurdu.
    var veri = document.getElementById('attachmentsLabels');

    if (veri) {
        try {
            metin = JSON.parse(veri.textContent) || metin;
        } catch (e) {
            // Etiketler okunamadıysa gömülü karşılıklar kalıyor; pencere
            // etiketsiz açılmaktansa varsayılanla açılsın.
        }
    }

    var katman = null;
    var gorselAlani = null;
    var baslikAlani = null;
    var indirBaglantisi = null;
    var oncekiDugme = null;
    var sonrakiDugme = null;
    var aktif = -1;
    // Pencere kapanınca odak, açan düğmeye dönmeli; yoksa klavye kullanan
    // ziyaretçi sayfanın başına savruluyor.
    var acanDugme = null;

    function katmaniKur() {
        katman = document.createElement('div');
        katman.className = 'att-lightbox';
        katman.setAttribute('role', 'dialog');
        katman.setAttribute('aria-modal', 'true');
        katman.hidden = true;

        katman.innerHTML = [
            '<button type="button" class="att-lightbox__btn att-lightbox__btn--close" data-att-close>',
            '  <i class="fa-solid fa-xmark"></i>',
            '</button>',
            '<button type="button" class="att-lightbox__btn att-lightbox__btn--prev" data-att-prev>',
            '  <i class="fa-solid fa-chevron-left"></i>',
            '</button>',
            '<button type="button" class="att-lightbox__btn att-lightbox__btn--next" data-att-next>',
            '  <i class="fa-solid fa-chevron-right"></i>',
            '</button>',
            '<figure class="att-lightbox__figure">',
            '  <img class="att-lightbox__img" src="" alt="">',
            '  <figcaption class="att-lightbox__cap">',
            '    <span data-att-caption></span>',
            '    <a class="att-lightbox__dl" data-att-download download>',
            '      <i class="fa-solid fa-download"></i><span></span>',
            '    </a>',
            '  </figcaption>',
            '</figure>'
        ].join('');

        document.body.appendChild(katman);

        gorselAlani = katman.querySelector('.att-lightbox__img');
        baslikAlani = katman.querySelector('[data-att-caption]');
        indirBaglantisi = katman.querySelector('[data-att-download]');
        oncekiDugme = katman.querySelector('[data-att-prev]');
        sonrakiDugme = katman.querySelector('[data-att-next]');

        var kapatDugmesi = katman.querySelector('[data-att-close]');
        kapatDugmesi.setAttribute('aria-label', metin.close);
        oncekiDugme.setAttribute('aria-label', metin.prev);
        sonrakiDugme.setAttribute('aria-label', metin.next);
        indirBaglantisi.querySelector('span').textContent = metin.download;

        kapatDugmesi.addEventListener('click', kapat);
        oncekiDugme.addEventListener('click', function () { goster(aktif - 1); });
        sonrakiDugme.addEventListener('click', function () { goster(aktif + 1); });

        // Arka plana tıklamak kapatır; görselin üstüne tıklamak kapatmaz.
        katman.addEventListener('click', function (olay) {
            if (olay.target === katman) {
                kapat();
            }
        });
    }

    function goster(sira) {
        if (sira < 0) {
            sira = dugmeler.length - 1;
        }

        if (sira >= dugmeler.length) {
            sira = 0;
        }

        var dugme = dugmeler[sira];
        aktif = sira;

        gorselAlani.src = dugme.dataset.src;
        gorselAlani.alt = dugme.dataset.caption || '';
        baslikAlani.textContent = dugme.dataset.caption || '';

        // İndirme adresi kartın kendi bağlantısından alınıyor: dosya
        // kullanıcının verdiği adla insin, diskteki eğik adla değil.
        var kart = dugme.closest('.att-img');
        var baglanti = kart ? kart.querySelector('.att-img__dl') : null;
        indirBaglantisi.href = baglanti ? baglanti.href : dugme.dataset.src;

        // Tek görselde ileri/geri bir işe yaramıyor, düğmeler saklanıyor.
        var tekli = dugmeler.length < 2;
        oncekiDugme.hidden = tekli;
        sonrakiDugme.hidden = tekli;
    }

    function ac(sira, kaynak) {
        if (katman === null) {
            katmaniKur();
        }

        acanDugme = kaynak || null;
        katman.hidden = false;
        goster(sira);

        // Sınıf bir sonraki karede ekleniyor; aynı karede eklenirse geçiş
        // hiç oynamıyor ve pencere birden beliriyor.
        window.requestAnimationFrame(function () {
            katman.classList.add('is-open');
        });

        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', tusaBas);
    }

    function kapat() {
        if (katman === null || katman.hidden) {
            return;
        }

        katman.classList.remove('is-open');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', tusaBas);

        window.setTimeout(function () {
            katman.hidden = true;
            gorselAlani.src = '';
        }, 240);

        if (acanDugme) {
            acanDugme.focus();
            acanDugme = null;
        }
    }

    function tusaBas(olay) {
        if (olay.key === 'Escape') {
            kapat();

            return;
        }

        if (olay.key === 'ArrowLeft') {
            goster(aktif - 1);

            return;
        }

        if (olay.key === 'ArrowRight') {
            goster(aktif + 1);
        }
    }

    dugmeler.forEach(function (dugme, sira) {
        dugme.addEventListener('click', function () {
            ac(sira, dugme);
        });
    });
})();
