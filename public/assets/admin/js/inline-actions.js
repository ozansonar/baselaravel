/**
 * Nitelik olarak yazılan olay işleyicilerinin yerini alan merkezi bağlayıcı.
 *
 * Panelde `onclick="..."`, `onchange="..."`, `oninput="..."` biçiminde 219
 * işleyici vardı. İkisi birden sorun çıkarıyordu:
 *
 *  1. **İçerik güvenlik politikası.** Nitelik değeri betiğin kendisi olduğu
 *     için oraya nonce konulamıyor; hepsini serbest bırakmak
 *     `script-src-attr 'unsafe-inline'` demekti ve politikada açık duran tek
 *     taviz oydu.
 *  2. **Dağınıklık.** Aynı iş (bir süzgeç formunu göndermek) yetmiş beş ayrı
 *     görünümde, yetmiş beş kez yazılmıştı. Birinde bir düzeltme ötekilere
 *     geçmiyordu.
 *
 * Şimdi işaret nitelikte, davranış burada: görünüm bir `data-*` kancası
 * taşıyor, bağlantıyı bu dosya kuruyor.
 *
 * ## Neden `window[ad]()` yok
 *
 * Kanca değerinden fonksiyon adı türetmek (`window[el.dataset.fn]()`) kolay
 * olurdu ama sayfaya sızan bir değerin keyfi bir fonksiyonu çağırmasına yol
 * açardı — CSP'yi kaldırıp yerine aynı kapıyı açmak olurdu. Bunun yerine
 * `EYLEMLER` haritası sabit: yalnız burada yazılı olan çağrılabiliyor.
 *
 * ## Delegasyon
 *
 * Dinleyiciler belgeye bir kez bağlanıyor, elemanlara değil. Sonradan
 * eklenen satırlar (AJAX ile gelen liste, açılan modal) ayrıca bağlanmayı
 * beklemeden çalışıyor.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
       Beyaz listeli eylemler

       Anahtar: data-action değeri. Değer: elemanı alan bir kapanış.
       Buraya yazılmayan hiçbir şey çağrılamaz.
       --------------------------------------------------------------- */

    var EYLEMLER = {
        /* Silme onayı — modülün kendi openDeleteModal'ı. İmza modülden
           modüle değişiyor: kimi (id, ad), kimi (ad, id) alıyor. Kanca
           ikisini de taşıyor, sıra burada çözülüyor. */
        'sil': function (el) {
            cagir(window.openDeleteModal, [el.dataset.id, el.dataset.label]);
        },
        'sil-tersine': function (el) {
            cagir(window.openDeleteModal, [el.dataset.label, el.dataset.id]);
        },

        /* Yorum moderasyonu: onayla / reddet / sil */
        'yorum-eylem': function (el) {
            cagir(window.confirmCommentAction, [el.dataset.eylem, el.dataset.id, el.dataset.label]);
        },

        /* Dil silme — ilişkili içerik sayısını da soruyor */
        'dil-sil': function (el) {
            cagir(window.openLanguageDelete, [el.dataset.id, el.dataset.label, el.dataset.count]);
        },

        /* İletişim mesajları ekranı */
        'mesaj-ac': function (el) { cagir(window.openMessage, [el.dataset.id]); },
        'mesaj-detay-kapat': function () { cagir(window.closeDetail, []); },
        'mesaj-detay-sil': function () { cagir(window.deleteDetail, []); },
        'mesaj-yanit-gonder': function () { cagir(window.sendReply, []); },
        'mesaj-yanit-ac': function () { cagir(window.toggleReplyForm, []); },
        'mesaj-klasor': function () { cagir(window.toggleFolders, []); },
        'mesaj-toplu': function () { cagir(window.toggleBulk, []); },
        'mesaj-toplu-sil': function () { cagir(window.bulkDelete, []); },

        /* Kullanıcı formu */
        'avatar-kaldir': function () { cagir(window.removeAvatar, []); },

        /* Kampanya ekleri */
        'ek-kaldir': function (el) { cagir(window.removeAttachment, [el.dataset.id]); },

        /* Dosya yöneticisi */
        'dosya-sil-onay': function (el) { cagir(window.fmgrConfirmDelete, [el]); },

        /* İçerik sürümleri: önizleme ve geri yükleme onayı */
        'surum-onizle': function (el) { cagir(window.openRevisionPreview, [el.dataset.id]); },
        'surum-geri-yukle': function (el) { cagir(window.openRevisionRestore, [el.dataset.id, el.dataset.label]); },

        /* Push duyurusu: sıradaki gönderimi iptal etme onayı */
        'push-iptal': function (el) { cagir(window.openPushCancel, [el.dataset.label]); },

        /* Blog: sosyal paylaşım önizlemesi */
        'blog-paylas': function (el) { cagir(window.shareBlogToSocial, [el.dataset.id]); },

        /* Raporlar ekranı */
        'rapor-zamanla': function (el) {
            var veri = el.dataset.schedule;
            cagir(window.openScheduleModal, [veri ? JSON.parse(veri) : null, el.dataset.type || undefined]);
        },
        'rapor-onizle': function (el) { cagir(window.openPreviewModal, [el.dataset.type]); },
        'rapor-zamanlama-sil': function (el) {
            cagir(window.openDeleteScheduleModal, [el.dataset.id, el.dataset.label]);
        },

        /* Kullanıcı listesi: kart / tablo görünümü */
        'gorunum': function (el) { cagir(window.switchView, [el.dataset.view, el]); },

        /* Sayfayı yenile — çevrimdışı ekranı ve hata sayfaları */
        'yenile': function () { window.location.reload(); },

        /* Olayın üst öğeye taşınmasını durdur; satır tıklaması tetiklenmesin */
        'durdur': function () { /* iş yok: durdurma aşağıda yapılıyor */ }
    };

    /**
     * Bir fonksiyonu, tanımlıysa çağırır.
     *
     * Fonksiyon **adıyla değil kendisiyle** geçiliyor: adından arama yapmak
     * (`window[ad]`) sayfadan gelen bir değerin keyfi bir fonksiyonu
     * çağırmasına giden ilk adım olurdu ve hangi fonksiyonun çağrıldığı
     * okunurken görünmezdi. Burada her çağrı yazılı: `window.openDeleteModal`.
     *
     * Fonksiyonlar sayfa özel betiklerde tanımlı; o betik yüklenmemişse
     * (başka bir ekrandayız) sessizce çekiliyoruz — konsolu hata dolduran bir
     * kanca, kancasız olmaktan kötüdür. Erişim çağrı anında yapıldığı için
     * yükleme sırası da sorun değil.
     */
    function cagir(fn, args) {
        if (typeof fn !== 'function') {
            return;
        }

        fn.apply(null, args || []);
    }

    /** Kimliğinden ya da yakınlığından bir form bulur. */
    function formBul(el, kimlik) {
        if (kimlik) {
            var hedef = document.getElementById(kimlik);
            if (hedef) return hedef;
        }

        return el.closest('form');
    }

    /* ---------------------------------------------------------------
       Tıklama
       --------------------------------------------------------------- */

    document.addEventListener('click', function (olay) {
        var el = olay.target.closest('[data-action], [data-submit-form], [data-confirm-submit], [data-scroll-to], [data-settings-tab], [data-click-target]');

        if (!el) {
            return;
        }

        /* Satır içindeki düğme, satırın kendi tıklamasını tetiklemesin. */
        if (el.hasAttribute('data-stop')) {
            olay.stopPropagation();
        }

        /* Bağlantı görünümlü düğmeler sayfayı zıplatmasın. */
        if (el.tagName === 'A' && (el.getAttribute('href') || '').indexOf('#') === 0) {
            olay.preventDefault();
        }

        if (el.hasAttribute('data-scroll-to')) {
            cagir(window.scrollToSection, [el.dataset.scrollTo, el]);
            return;
        }

        if (el.hasAttribute('data-settings-tab')) {
            cagir(window.switchSettingsTab, [el, el.dataset.settingsTab]);
            return;
        }

        /* Gizli dosya girdisini görünür bir düğmeden tetiklemek. */
        if (el.hasAttribute('data-click-target')) {
            var hedef = document.getElementById(el.dataset.clickTarget);
            if (hedef) hedef.click();
            return;
        }

        /* Onay isteyip sonra formu gönderen düğme. Toplu işlemlerin
           (`data-bulk-action`) tekil kayıt karşılığı; ikisi de aynı kutuyu
           kullanıyor, biri seçili satırları öteki tek formu gönderiyor. */
        if (el.hasAttribute('data-confirm-submit')) {
            var onaylanacak = formBul(el, el.dataset.confirmSubmit);
            if (!onaylanacak) return;

            /* Kutu yüklenmemişse işlem yapılmıyor. Tarayıcının kendi
               confirm()'i yedekti ama ulaşılamazdı — modal işaretlemesi ve
               betiği admin layout'una koşulsuz basılıyor — ve proje onu
               yasaklıyor. Onay alamadan göndermektense hiç göndermemek doğru
               taraf; sebep konsola yazılıyor ki sessiz bir düğme kalmasın. */
            if (typeof AdminModal === 'undefined') {
                console.error('AdminModal yüklenmedi: form onay alınamadığı için gönderilmedi.');

                return;
            }

            AdminModal.confirm({
                title: el.dataset.confirmTitle || 'Emin misiniz?',
                message: el.dataset.confirmMessage || '',
                type: el.dataset.confirmType || 'danger',
                confirmText: el.dataset.confirmText || 'Evet',
                confirmIcon: el.dataset.confirmIcon || 'bi bi-check-lg'
            }).then(function (onay) {
                if (onay) onaylanacak.submit();
            });

            return;
        }

        if (el.hasAttribute('data-submit-form')) {
            var form = formBul(el, el.dataset.submitForm);
            if (form) form.submit();
            return;
        }

        var eylem = EYLEMLER[el.dataset.action];
        if (eylem) eylem(el);
    });

    /* ---------------------------------------------------------------
       Değişim — süzgeçler, seçiciler, anahtarlar
       --------------------------------------------------------------- */

    document.addEventListener('change', function (olay) {
        var el = olay.target;

        if (!el.matches || !el.matches('[data-submit-form], [data-scroll-select], [data-toggle-class], [data-hint-target], [data-per-page], [data-action]')) {
            return;
        }

        /* Uzun formlarda bölüme atlayan seçici: seçim kalıcı olmamalı,
           kullanıcı aynı bölüme ikinci kez atlayabilmeli. */
        if (el.hasAttribute('data-scroll-select')) {
            cagir(window.scrollToSection, [el.value, null]);
            el.selectedIndex = 0;
            return;
        }

        /* Bir anahtarın açık/kapalı olması başka bir kutuyu gizliyor. */
        if (el.hasAttribute('data-toggle-class')) {
            var kutu = document.getElementById(el.dataset.toggleClass);
            if (kutu) kutu.classList.toggle(el.dataset.toggleClassName || 'd-none', !el.checked);
            return;
        }

        /* Seçilen seçeneğin açıklaması yanındaki alana yazılıyor. */
        if (el.hasAttribute('data-hint-target')) {
            var alan = document.getElementById(el.dataset.hintTarget);
            var secim = el.selectedOptions[0];
            if (alan) alan.textContent = (secim && secim.dataset.hint) || el.dataset.hintDefault || '';
            return;
        }

        /* Sayfa boyutu seçicisi: ekranın kendi changePerPage'i adresi
           yeniden kuruyor (süzgeçleri koruyarak), form göndermiyor. */
        if (el.hasAttribute('data-per-page')) {
            cagir(window.changePerPage, [el.value]);
            return;
        }

        if (el.hasAttribute('data-submit-form')) {
            var form = formBul(el, el.dataset.submitForm);
            if (form) form.submit();
            return;
        }

        var eylem = EYLEMLER[el.dataset.action];
        if (eylem) eylem(el);
    });

    /* ---------------------------------------------------------------
       Yazım — karakter sayacı ve arama önizlemesi
       --------------------------------------------------------------- */

    document.addEventListener('input', function (olay) {
        var el = olay.target;

        if (!el.matches || !el.matches('[data-char-counter], [data-seo-preview], [data-icon-preview], [data-route-params]')) {
            return;
        }

        if (el.hasAttribute('data-char-counter')) {
            cagir(window.updateCharCounter, [el, parseInt(el.dataset.charCounter, 10)]);
        }

        if (el.hasAttribute('data-seo-preview')) {
            cagir(window.updateSeoPreview, [el]);
        }

        if (el.hasAttribute('data-icon-preview')) {
            cagir(window.updateIconPreview, [el]);
        }

        if (el.hasAttribute('data-route-params')) {
            cagir(window.customRouteParams, [el.value]);
        }
    });

    /* Bazı alanlar seçicidir: `input` yerine `change` veriyorlar. */
    document.addEventListener('change', function (olay) {
        var el = olay.target;

        if (el.matches && el.matches('[data-route-params]')) {
            cagir(window.customRouteParams, [el.value]);
        }

        if (el.matches && el.matches('[data-avatar-preview]')) {
            cagir(window.previewAvatar, [el]);
        }
    });
})();
