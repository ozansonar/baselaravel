/**
 * Vanilla JS Lightbox — sıfır bağımlılık (Bootstrap'siz, jQuery'siz).
 *
 * Galeri tarzı görsel önizleme. Sayfada `[data-mlib-lightbox]` selector'üne
 * uyan link'ler (genelde galeri thumbnail wrap'leri) tıklandığında modal
 * açılır. Ok tuşları + ESC + swipe (touch) destekler.
 *
 * Kullanım:
 *   <a href="full.jpg" data-mlib-lightbox data-caption="açıklama">
 *     <img src="thumb.jpg" alt="...">
 *   </a>
 *
 * mlibLightbox.refresh() — DOM'a yeni link eklendiğinde galeri listesini
 * yeniden tarar (otomatik delegation kullanıldığı için aslında gerek yok
 * ama public API olarak sağlanır).
 */
'use strict';

(function () {
    var SELECTOR = '[data-mlib-lightbox]';
    var overlay = null;
    var imgEl = null;
    var captionEl = null;
    var counterEl = null;
    var prevBtn = null;
    var nextBtn = null;
    var closeBtn = null;
    var current = -1;
    var items = [];
    var touchStartX = 0;

    function ensureOverlay() {
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'mlib-lightbox';
        overlay.innerHTML =
            '<button type="button" class="mlib-lightbox__close" aria-label="Kapat">' +
                '<i class="bi bi-x-lg"></i>' +
            '</button>' +
            '<button type="button" class="mlib-lightbox__nav mlib-lightbox__nav--prev" aria-label="Önceki">' +
                '<i class="bi bi-chevron-left"></i>' +
            '</button>' +
            '<button type="button" class="mlib-lightbox__nav mlib-lightbox__nav--next" aria-label="Sonraki">' +
                '<i class="bi bi-chevron-right"></i>' +
            '</button>' +
            '<div class="mlib-lightbox__stage">' +
                '<img class="mlib-lightbox__image" alt="">' +
            '</div>' +
            '<div class="mlib-lightbox__bottom">' +
                '<span class="mlib-lightbox__counter"></span>' +
                '<span class="mlib-lightbox__caption"></span>' +
            '</div>';

        document.body.appendChild(overlay);

        imgEl = overlay.querySelector('.mlib-lightbox__image');
        captionEl = overlay.querySelector('.mlib-lightbox__caption');
        counterEl = overlay.querySelector('.mlib-lightbox__counter');
        prevBtn = overlay.querySelector('.mlib-lightbox__nav--prev');
        nextBtn = overlay.querySelector('.mlib-lightbox__nav--next');
        closeBtn = overlay.querySelector('.mlib-lightbox__close');

        // Olay dinleyicileri
        closeBtn.addEventListener('click', close);
        prevBtn.addEventListener('click', prev);
        nextBtn.addEventListener('click', next);
        overlay.addEventListener('click', function (e) {
            // Sadece overlay'e tıklandıysa kapat (image veya butona değil)
            if (e.target === overlay || e.target.classList.contains('mlib-lightbox__stage')) {
                close();
            }
        });

        // Touch swipe
        overlay.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        overlay.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 50) {
                if (dx > 0) prev(); else next();
            }
        }, { passive: true });

        return overlay;
    }

    function refresh() {
        items = Array.prototype.slice.call(document.querySelectorAll(SELECTOR));
    }

    function open(index) {
        refresh();
        if (items.length === 0) return;

        ensureOverlay();
        current = ((index % items.length) + items.length) % items.length; // wrap
        renderCurrent();
        document.body.classList.add('mlib-lightbox-open');
        overlay.classList.add('mlib-lightbox--active');

        document.addEventListener('keydown', onKey);
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove('mlib-lightbox--active');
        document.body.classList.remove('mlib-lightbox-open');
        document.removeEventListener('keydown', onKey);
    }

    function next() {
        if (items.length === 0) return;
        current = (current + 1) % items.length;
        renderCurrent();
    }

    function prev() {
        if (items.length === 0) return;
        current = (current - 1 + items.length) % items.length;
        renderCurrent();
    }

    function renderCurrent() {
        var link = items[current];
        if (!link) return;

        var src = link.getAttribute('href') || link.dataset.full || '';
        var caption = link.dataset.caption || link.getAttribute('title') || '';
        var alt = link.querySelector('img')?.getAttribute('alt') || caption;

        imgEl.src = '';     // kısa flicker önler
        imgEl.alt = alt;
        // Sonraki frame'de set et (yumuşak geçiş)
        requestAnimationFrame(function () { imgEl.src = src; });

        captionEl.textContent = caption;
        counterEl.textContent = (current + 1) + ' / ' + items.length;

        // Tek görsel varsa nav butonlarını gizle
        var multi = items.length > 1;
        prevBtn.style.display = multi ? '' : 'none';
        nextBtn.style.display = multi ? '' : 'none';
    }

    function onKey(e) {
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') prev();
        else if (e.key === 'ArrowRight') next();
    }

    // Event delegation — gelecekte DOM'a eklenen link'ler de çalışır
    document.addEventListener('click', function (e) {
        var link = e.target.closest(SELECTOR);
        if (!link) return;

        e.preventDefault();
        refresh();
        var index = items.indexOf(link);
        if (index === -1) {
            items.push(link);
            index = items.length - 1;
        }
        open(index);
    });

    // Public API
    window.mlibLightbox = {
        open: open,
        close: close,
        refresh: refresh,
    };
})();
