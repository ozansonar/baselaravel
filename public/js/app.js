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

    /* ---- Active nav link by current path ---- */
    const path = window.location.pathname;
    document.querySelectorAll('.main-nav .nav-link, .mobile-nav .nav-link').forEach(function (link) {
        const href = link.getAttribute('href');
        if (!href || href === '#') return;
        try {
            const url = new URL(href, window.location.origin);
            if (url.pathname !== '/' && path.startsWith(url.pathname)) link.classList.add('active');
            if (url.pathname === '/' && path === '/') link.classList.add('active');
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

/* ---- Global result modal ---- */
window.showResultModal = function (type, message, title) {
    const el = document.getElementById('resultModal');
    if (!el) { alert(message); return; }

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
    document.getElementById('resultModalBody').textContent = message;

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
