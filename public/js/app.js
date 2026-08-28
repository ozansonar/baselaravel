'use strict';

/* ==========================================================================
   Laravel Base — Front JS (Vanilla ES6+)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    /* ---- Sticky header shadow on scroll ---- */
    const header = document.querySelector('.site-header');
    if (header) {
        const onScroll = () => header.classList.toggle('site-header--scrolled', window.scrollY > 8);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---- Scroll-to-top button ---- */
    const scrollTopBtn = document.querySelector('.scroll-top');
    if (scrollTopBtn) {
        window.addEventListener('scroll', function () {
            scrollTopBtn.classList.toggle('scroll-top--show', window.scrollY > 400);
        }, { passive: true });
        scrollTopBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ---- Auto-close mobile offcanvas nav on link click ---- */
    const offcanvasNav = document.getElementById('mobileNav');
    if (offcanvasNav) {
        offcanvasNav.querySelectorAll('a.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                const inst = bootstrap.Offcanvas.getInstance(offcanvasNav);
                if (inst) inst.hide();
            });
        });
    }

    /* ---- Active nav link by current path ----

       Eşleşme parça sınırında kesiliyor. Önce düz startsWith kullanılıyordu ve
       adresler dil önekine geçtiğinden anasayfa bağlantısı "/tr" oldu: "/tr" ile
       başlayan her sayfada — yani sitenin tamamında — Anasayfa etkin görünüyor,
       gerçekten açık olan sayfayla birlikte iki bağlantı birden yanıyordu.

       Kök bağlantı (/ ya da /tr gibi dil kökü) yalnız tam eşleşmede etkin.
       Ötekiler kendi adresinde ve altındaki sayfalarda etkin: /tr/blog,
       /tr/blog/kategori/yazi'da da yanar ama /tr/blogumsu'da yanmaz. */
    const LOCALE_ROOT = /^\/[a-z]{2}(-[a-z]{2})?$/;   // routes/web.php'deki {locale} kalıbı
    const normalizePath = function (value) {
        return value.replace(/\/+$/, '') || '/';
    };
    const path = normalizePath(window.location.pathname);

    document.querySelectorAll('.main-nav .nav-link, .mobile-nav .nav-link').forEach(function (link) {
        const href = link.getAttribute('href');
        if (!href || href === '#') return;
        try {
            const target = normalizePath(new URL(href, window.location.origin).pathname);
            const isRoot = target === '/' || LOCALE_ROOT.test(target);
            const active = isRoot
                ? path === target
                : path === target || path.startsWith(target + '/');

            if (active) link.classList.add('active');
        } catch (e) { /* noop */ }
    });

    /* ---- Delete/confirm forms via data-confirm ---- */
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.matches('[data-confirm]')) return;
        if (form.dataset.confirmed === '1') return;
        e.preventDefault();
        window.showConfirmModal({
            title: form.dataset.confirmTitle || 'Emin misiniz?',
            message: form.getAttribute('data-confirm'),
            confirmText: form.dataset.confirmBtn || 'Evet',
            onConfirm: function () { form.dataset.confirmed = '1'; form.submit(); },
        });
    });

    /* ---- Site popups (once per browser session) ---- */
    var popupEls = document.querySelectorAll('[data-popup-id]');
    if (popupEls.length && typeof bootstrap !== 'undefined') {
        var store = window.sessionStorage;
        var queue = Array.prototype.filter.call(popupEls, function (el) {
            try { return !store || store.getItem('popup_seen_' + el.dataset.popupId) !== '1'; }
            catch (e) { return true; }
        });
        var pi = 0;
        var showNextPopup = function () {
            if (pi >= queue.length) return;
            var el = queue[pi];
            var modal = bootstrap.Modal.getOrCreateInstance(el);
            el.addEventListener('hidden.bs.modal', function handler() {
                el.removeEventListener('hidden.bs.modal', handler);
                try { if (store) store.setItem('popup_seen_' + el.dataset.popupId, '1'); } catch (e) {}
                pi++;
                if (pi < queue.length) setTimeout(showNextPopup, 400);
            });
            modal.show();
        };
        if (queue.length) setTimeout(showNextPopup, 1000);
    }
});

/* ---- CSRF + fetch helpers ---- */
window.csrfToken = function () {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
};

window.fetchJson = async function (url, options = {}) {
    const opts = Object.assign({
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    }, options);
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(opts.body);
    }
    const res = await fetch(url, opts);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
};

/* ---- Global result modal ----

   message hem dizge hem dizi olabiliyor. Sunucudan birden çok hata dönünce
   satırlar önce '<br>' ile birleştiriliyordu; kutu metni textContent ile
   bastığı için ziyaretçi etiketin kendisini okuyordu ("...zorunludur.<br>E-posta
   ...").  Etiketi yorumlatmak (innerHTML) sunucudan gelen metni sayfaya HTML
   olarak sokardı; onun yerine her satır kendi düğümü olarak ekleniyor. */
window.showResultModal = function (type, message, title) {
    const el = document.getElementById('resultModal');
    const lines = Array.isArray(message) ? message : String(message ?? '').split('\n');

    if (!el) { alert(lines.join('\n')); return; }

    const map = {
        success: { icon: 'fa-circle-check',        cls: 'result-icon--success', title: 'Başarılı' },
        error:   { icon: 'fa-circle-exclamation',  cls: 'result-icon--error',   title: 'Hata' },
        warning: { icon: 'fa-triangle-exclamation',cls: 'result-icon--warning', title: 'Uyarı' },
        info:    { icon: 'fa-circle-info',          cls: 'result-icon--info',    title: 'Bilgi' },
    };
    const cfg = map[type] || map.info;

    const iconWrap = document.getElementById('resultModalIcon');
    iconWrap.className = 'result-icon ' + cfg.cls;
    iconWrap.innerHTML = '<i class="fa-solid ' + cfg.icon + '"></i>';
    document.getElementById('resultModalTitle').textContent = title || cfg.title;

    const body = document.getElementById('resultModalBody');
    body.textContent = '';
    lines.filter(Boolean).forEach(function (line, index) {
        if (index > 0) body.appendChild(document.createElement('br'));
        body.appendChild(document.createTextNode(line));
    });

    bootstrap.Modal.getOrCreateInstance(el).show();
};

/* ---- Global confirm modal ---- */
window.showConfirmModal = function (options) {
    const el = document.getElementById('confirmModal');
    if (!el) { if (confirm(options.message)) options.onConfirm && options.onConfirm(); return; }

    document.getElementById('confirmModalTitle').textContent = options.title || 'Emin misiniz?';
    document.getElementById('confirmModalBody').textContent = options.message || '';

    const confirmBtn = document.getElementById('confirmModalConfirmBtn');
    confirmBtn.textContent = options.confirmText || 'Evet';

    // Reset listener by cloning
    const fresh = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(fresh, confirmBtn);

    const modal = bootstrap.Modal.getOrCreateInstance(el);
    fresh.addEventListener('click', function () {
        modal.hide();
        if (typeof options.onConfirm === 'function') options.onConfirm();
    });
    modal.show();
};

/**
 * Newsletter sign-up in the footer.
 *
 * Posts without leaving the page: someone who subscribes from the bottom of an
 * article should not lose their place in it.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('newsletterForm');
        if (!form) return;

        var note = document.getElementById('newsletterNote');
        var button = document.getElementById('newsletterSubmit');
        var input = document.getElementById('newsletterEmail');

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            // Doğrulama motoru karar veriyor. Tarayıcının checkValidity'si
            // kullanılmıyor: mesajı biçimlendirilemiyor, Türkçeleştirilemiyor ve
            // sunucudaki kuralla uyuşmadığı yerde kullanıcıyı yanlış yönlendiriyor.
            if (window.FormValidation && !window.FormValidation.isValid(form)) {
                return;
            }

            var original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            fetch(form.getAttribute('action') || window.newsletterUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ email: input.value })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    button.disabled = false;
                    button.innerHTML = original;

                    if (result.ok && result.data.success) {
                        form.reset();
                        show(result.data.message, true);
                        return;
                    }

                    var errors = result.data.errors || {};
                    show(errors.email ? errors.email[0] : (result.data.message || 'İşlem tamamlanamadı.'), false);
                })
                .catch(function () {
                    button.disabled = false;
                    button.innerHTML = original;
                    show('Bağlantı hatası. Lütfen tekrar deneyin.', false);
                });
        });

        function show(message, ok) {
            if (!note) return;
            note.textContent = message;
            note.className = 'footer-newsletter__note d-block mt-2 ' + (ok ? 'text-success' : 'text-warning');
        }
    });
})();

/* ==========================================================================
   Scroll reveal

   Elements marked [data-reveal] start shifted and settle into place once they
   enter the viewport. When the visitor asked for reduced motion nothing is
   observed at all — the CSS already leaves those elements visible, so the page
   is complete without this file running.
   ========================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var targets = document.querySelectorAll('[data-reveal]');
        if (!targets.length) return;

        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // No observer (or no motion wanted): show everything straight away
        // rather than leaving the page half empty.
        if (reduced || typeof IntersectionObserver === 'undefined') {
            targets.forEach(function (el) { el.classList.add('is-revealed'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -12% 0px', threshold: 0.05 });

        targets.forEach(function (el) { observer.observe(el); });
    });
})();

/* ==========================================================================
   Reading progress

   The bar tracks how much of the article body has scrolled past, not how much
   of the document: comments and related posts are not part of the read.
   ========================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var bar = document.querySelector('[data-read-progress]');
        var body = document.querySelector('.article__body');
        if (!bar || !body) return;

        var update = function () {
            var rect = body.getBoundingClientRect();
            var total = rect.height - window.innerHeight;

            // Short article: it fits on one screen, so there is nothing to track.
            if (total <= 0) {
                bar.style.width = rect.bottom <= window.innerHeight ? '100%' : '0';
                return;
            }

            var passed = Math.min(Math.max(-rect.top, 0), total);
            bar.style.width = ((passed / total) * 100).toFixed(2) + '%';
        };

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    });
})();

/* ==========================================================================
   Gallery lightbox

   Photos enlarge inside the page. They used to open the raw upload in a new
   tab, which threw the visitor out of the gallery and showed them an unstyled
   image on a blank background.

   Markup: partials/lightbox.blade.php — one container per page, filled from
   whichever [data-lightbox-gallery] grid was clicked.
   ========================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var box = document.querySelector('[data-lightbox]');
        if (!box) return;

        var img     = box.querySelector('[data-lightbox-img]');
        var caption = box.querySelector('[data-lightbox-caption]');
        var closeBtn = box.querySelector('[data-lightbox-close]');
        var prevBtn = box.querySelector('[data-lightbox-prev]');
        var nextBtn = box.querySelector('[data-lightbox-next]');

        var items = [];
        var index = 0;
        var opener = null;

        function render() {
            var item = items[index];
            if (!item) return;

            var title = item.dataset.title || '';
            var extra = item.dataset.caption || '';

            img.setAttribute('src', item.getAttribute('href'));
            img.setAttribute('alt', title);
            caption.innerHTML = '';

            if (title) {
                var strong = document.createElement('strong');
                strong.textContent = title;
                caption.appendChild(strong);
            }
            if (extra) {
                caption.appendChild(document.createTextNode(extra));
            }

            // A single photo has nowhere to go; the arrows would be dead controls.
            var many = items.length > 1;
            prevBtn.hidden = !many;
            nextBtn.hidden = !many;
        }

        function open(gallery, clicked) {
            items = Array.prototype.slice.call(gallery.querySelectorAll('[data-lightbox-item]'));
            index = Math.max(0, items.indexOf(clicked));
            opener = clicked;

            render();
            box.hidden = false;
            // The class drives the fade, so it is set on the next frame — set
            // together with `hidden` the transition would never run.
            window.requestAnimationFrame(function () { box.classList.add('lightbox--open'); });
            document.body.style.overflow = 'hidden';
            closeBtn.focus();
        }

        function close() {
            box.classList.remove('lightbox--open');
            document.body.style.overflow = '';

            var done = function () {
                box.hidden = true;
                box.removeEventListener('transitionend', done);
            };
            box.addEventListener('transitionend', done);
            // Reduced motion kills the transition, and then transitionend never
            // fires — the panel would stay in the layout, invisible and on top.
            window.setTimeout(done, 400);

            if (opener) opener.focus();
            opener = null;
        }

        function step(delta) {
            if (items.length < 2) return;
            index = (index + delta + items.length) % items.length;
            render();
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-lightbox-item]');
            if (!trigger) return;

            var gallery = trigger.closest('[data-lightbox-gallery]');
            if (!gallery) return;

            event.preventDefault();
            open(gallery, trigger);
        });

        closeBtn.addEventListener('click', close);
        prevBtn.addEventListener('click', function () { step(-1); });
        nextBtn.addEventListener('click', function () { step(1); });

        // Clicking the backdrop closes; clicking the photo itself does not.
        box.addEventListener('click', function (event) {
            if (event.target === box) close();
        });

        document.addEventListener('keydown', function (event) {
            if (box.hidden) return;
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'ArrowLeft') { step(-1); return; }
            if (event.key === 'ArrowRight') { step(1); }
        });
    });
})();
