/**
 * Sürüm geçmişi ekranı.
 *
 * İki iş yapıyor: bir sürümün içeriğini gösteriyor ve geri yükleme onayını
 * alıyor. İçerikler sayfaya bir kez, `<script type="application/json">`
 * içinde basılıyor — her satır için ayrı istek atmak, yirmi satırlık bir
 * listede yirmi istek demekti.
 */
(function () {
    'use strict';

    /** Alan adlarının okunabilir karşılıkları. */
    var ETIKETLER = {
        title: 'Başlık',
        slug: 'Adres (slug)',
        excerpt: 'Özet',
        content: 'İçerik',
        body: 'İçerik',
        sections: 'Bölümler',
        image: 'Kapak görseli',
        blog_category_id: 'Kategori',
        status: 'Durum',
        meta_title: 'Meta başlık',
        meta_description: 'Meta açıklama',
        published_at: 'Yayın tarihi'
    };

    /** Uzun metinler kutuyu doldurmasın; tam hâli içeriğin kendisinde. */
    var EN_FAZLA = 400;

    function veriler() {
        var el = document.getElementById('revisionPayloads');

        if (!el) {
            return {};
        }

        try {
            return JSON.parse(el.textContent) || {};
        } catch (e) {
            return {};
        }
    }

    function metneCevir(deger) {
        if (deger === null || deger === undefined || deger === '') {
            return '—';
        }

        if (typeof deger === 'object') {
            deger = JSON.stringify(deger);
        }

        deger = String(deger);

        // Etiketler ayıklanıyor: kutu metni textContent ile yazıyor, ham HTML
        // ekranda etiket olarak görünürdü.
        deger = deger.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

        return deger.length > EN_FAZLA ? deger.slice(0, EN_FAZLA) + '…' : (deger || '—');
    }

    /**
     * Bir sürümün alanlarını önizleme kutusuna basar.
     */
    window.openRevisionPreview = function (id) {
        var kutu = document.getElementById('revisionModal');
        var liste = document.getElementById('revisionFields');

        if (!kutu || !liste) {
            return;
        }

        var alanlar = veriler()[String(id)] || {};

        liste.innerHTML = '';

        Object.keys(alanlar).forEach(function (alan) {
            var satir = document.createElement('div');
            satir.className = 'rdr-meta__row';

            var ad = document.createElement('span');
            ad.textContent = ETIKETLER[alan] || alan;

            var deger = document.createElement('strong');
            deger.textContent = metneCevir(alanlar[alan]);

            satir.appendChild(ad);
            satir.appendChild(deger);
            liste.appendChild(satir);
        });

        new bootstrap.Modal(kutu).show();
    };

    /**
     * Geri yükleme onayı: formu doğru sürüme yönlendirir.
     */
    window.openRevisionRestore = function (id, tarih) {
        var kutu = document.getElementById('revisionRestoreModal');
        var form = document.getElementById('revisionRestoreForm');

        if (!kutu || !form || !window.revisionRestoreUrl) {
            return;
        }

        form.action = window.revisionRestoreUrl.replace('REVISION', String(id));

        var etiket = document.getElementById('revisionRestoreLabel');
        if (etiket) {
            etiket.textContent = tarih || '';
        }

        new bootstrap.Modal(kutu).show();
    };
})();
