'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── Top bar hide on scroll + Navbar scroll effect ──
    var navbar = document.querySelector('.navbar-custom');
    var topBar = document.querySelector('.top-bar');
    var lastScrollY = 0;

    if (navbar) {
        window.addEventListener('scroll', function () {
            var currentY = window.scrollY;
            navbar.classList.toggle('scrolled', currentY > 50);

            if (topBar) {
                if (currentY > 80) {
                    topBar.classList.add('top-bar-hidden');
                    navbar.classList.add('top-bar-collapsed');
                } else {
                    topBar.classList.remove('top-bar-hidden');
                    navbar.classList.remove('top-bar-collapsed');
                }
            }
            lastScrollY = currentY;
        }, { passive: true });
    }

    // ── Mega menu mobile toggle ──
    var megaItem = document.querySelector('.nav-item-mega');
    if (megaItem) {
        var megaLink = megaItem.querySelector('.nav-link');
        megaLink.addEventListener('click', function (e) {
            if (window.innerWidth < 992) {
                e.preventDefault();
                megaItem.classList.toggle('mega-open');
            }
        });

        document.addEventListener('click', function (e) {
            if (!megaItem.contains(e.target)) {
                megaItem.classList.remove('mega-open');
            }
        });
    }

    // ── Scroll to top button ──
    const scrollTopBtn = document.querySelector('.scroll-top');
    if (scrollTopBtn) {
        window.addEventListener('scroll', function () {
            scrollTopBtn.classList.toggle('visible', window.scrollY > 300);
        }, { passive: true });

        scrollTopBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ── Animate on scroll (Intersection Observer) ──
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    if (animateElements.length > 0) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        animateElements.forEach(function (el) {
            observer.observe(el);
        });
    }

    // ── Flash message auto-dismiss ──
    const flashAlerts = document.querySelectorAll('.alert-dismissible[data-auto-dismiss]');
    flashAlerts.forEach(function (alert) {
        const delay = parseInt(alert.dataset.autoDismiss, 10) || 5000;
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, delay);
    });

    // ── CSRF token for fetch API ──
    window.csrfToken = function () {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    };

    // ── Generic fetch helper ──
    window.fetchJson = async function (url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken(),
            },
        };

        const config = Object.assign({}, defaults, options);
        if (options.headers) {
            config.headers = Object.assign({}, defaults.headers, options.headers);
        }

        const response = await fetch(url, config);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    };

    // ── Global Result Modal ──
    window.showResultModal = function (type, message, title) {
        var modal   = document.getElementById('resultModal');
        var iconWrap = document.getElementById('resultModalIconWrap');
        var icon    = document.getElementById('resultModalIcon');
        var titleEl = document.getElementById('resultModalTitle');
        var body    = document.getElementById('resultModalBody');
        var btn     = document.getElementById('resultModalBtn');

        if (!modal) return;

        // Reset classes
        iconWrap.className = 'result-modal-icon-wrap';
        icon.className     = '';
        btn.className      = 'result-modal-btn';
        titleEl.className  = 'result-modal-title';

        if (type === 'success') {
            iconWrap.classList.add('result-modal-icon-wrap--success');
            icon.classList.add('fa-solid', 'fa-check');
            btn.classList.add('result-modal-btn--success');
            titleEl.classList.add('result-modal-title--success');
            titleEl.textContent = title || 'Başarılı!';
        } else if (type === 'warning') {
            iconWrap.classList.add('result-modal-icon-wrap--warning');
            icon.classList.add('fa-solid', 'fa-triangle-exclamation');
            btn.classList.add('result-modal-btn--warning');
            titleEl.classList.add('result-modal-title--warning');
            titleEl.textContent = title || 'Uyarı!';
        } else if (type === 'info') {
            iconWrap.classList.add('result-modal-icon-wrap--info');
            icon.classList.add('fa-solid', 'fa-circle-info');
            btn.classList.add('result-modal-btn--info');
            titleEl.classList.add('result-modal-title--info');
            titleEl.textContent = title || 'Bilgi';
        } else {
            iconWrap.classList.add('result-modal-icon-wrap--error');
            icon.classList.add('fa-solid', 'fa-xmark');
            btn.classList.add('result-modal-btn--error');
            titleEl.classList.add('result-modal-title--error');
            titleEl.textContent = title || 'Hata!';
        }

        body.innerHTML = message;

        var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();
    };

    // ── Global Confirm Modal ──
    window.showConfirmModal = function (options) {
        var modal      = document.getElementById('confirmModal');
        var iconWrap   = document.getElementById('confirmModalIconWrap');
        var icon       = document.getElementById('confirmModalIcon');
        var titleEl    = document.getElementById('confirmModalTitle');
        var body       = document.getElementById('confirmModalBody');
        var confirmBtn = document.getElementById('confirmModalConfirmBtn');
        var cancelBtn  = document.getElementById('confirmModalCancelBtn');

        if (!modal) return;

        var type = options.type || 'warning';

        // Reset classes
        iconWrap.className = 'confirm-modal__icon-wrap';
        icon.className     = '';
        confirmBtn.className = 'confirm-modal__btn confirm-modal__btn--confirm';

        if (type === 'danger') {
            iconWrap.classList.add('confirm-modal__icon-wrap--danger');
            icon.classList.add('fa-solid', 'fa-trash-can');
            confirmBtn.classList.add('confirm-modal__btn--confirm--danger');
        } else {
            iconWrap.classList.add('confirm-modal__icon-wrap--warning');
            icon.classList.add('fa-solid', 'fa-triangle-exclamation');
            confirmBtn.classList.add('confirm-modal__btn--confirm--warning');
        }

        titleEl.textContent = options.title || 'Emin misiniz?';
        body.innerHTML      = options.message || '';
        confirmBtn.textContent = options.confirmText || 'Evet, Sil';
        cancelBtn.textContent  = options.cancelText || 'Vazgeç';

        // Remove previous listeners
        var newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', function () {
            var bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
            if (typeof options.onConfirm === 'function') options.onConfirm();
        });

        var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();
    };

    // ── Delete confirmation (form submit) ──
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.dataset.confirm) {
            e.preventDefault();
            showConfirmModal({
                type: 'danger',
                title: 'Silme Onayı',
                message: form.dataset.confirm,
                confirmText: 'Evet, Sil',
                onConfirm: function () {
                    form.removeAttribute('data-confirm');
                    form.submit();
                }
            });
        }
    });

    // ── Search Modal Autocomplete ──
    var modalInput   = document.getElementById('modalSearchInput');
    var modalResults = document.getElementById('modalSearchResults');
    var modalHint    = document.getElementById('modalSearchHint');
    var searchTimer  = null;

    var priceFormatter = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2 });

    function renderModalHint() {
        modalResults.innerHTML =
            '<div class="search-modal-hint" id="modalSearchHint">' +
            '<i class="fa-solid fa-seedling search-modal-hint-icon"></i>' +
            '<p>Aramaya başlamak için yazmaya başlayın</p>' +
            '<span>Meyve, sebze, peynir ve daha fazlası...</span>' +
            '</div>';
    }

    function runModalSearch(q) {
        fetch('/ara/oneri?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.suggestions || data.suggestions.length === 0) {
                modalResults.innerHTML = '<div class="search-modal-empty">Sonuç bulunamadı</div>';
                return;
            }

            var html = '';
            data.suggestions.forEach(function (item) {
                html += '<a href="/urun/' + item.slug + '" class="search-modal-item">' +
                    '<img src="' + item.image + '" alt="' + item.name + '" class="search-modal-item-img" loading="lazy">' +
                    '<div class="search-modal-item-info">' +
                    '<span class="search-modal-item-name">' + item.name + '</span>' +
                    '<span class="search-modal-item-price">' + priceFormatter.format(item.price) + ' ₺</span>' +
                    '</div></a>';
            });

            html += '<a href="/ara?q=' + encodeURIComponent(q) + '" class="search-modal-all">' +
                    '<i class="fa-solid fa-arrow-right"></i> Tüm sonuçları gör</a>';
            modalResults.innerHTML = html;
        })
        .catch(function () {
            renderModalHint();
        });
    }

    if (modalInput) {
        // Focus input when modal opens; reset state on close
        var searchModalEl = document.getElementById('searchModal');
        if (searchModalEl) {
            searchModalEl.addEventListener('shown.bs.modal', function () {
                modalInput.focus();
            });
            searchModalEl.addEventListener('hidden.bs.modal', function () {
                modalInput.value = '';
                renderModalHint();
            });
        }

        modalInput.addEventListener('input', function () {
            var q = this.value.trim();
            clearTimeout(searchTimer);

            if (q.length < 2) {
                renderModalHint();
                return;
            }

            searchTimer = setTimeout(function () {
                runModalSearch(q);
            }, 300);
        });
    }

    /* ── Popup Modals ── */
    var popupModals = document.querySelectorAll('[data-popup-id]');
    if (popupModals.length > 0) {
        var popupQueue = Array.from(popupModals);
        var currentPopupIndex = 0;

        function showNextPopup() {
            if (currentPopupIndex >= popupQueue.length) return;

            var modalEl = popupQueue[currentPopupIndex];
            var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

            modalEl.addEventListener('hidden.bs.modal', function handler() {
                modalEl.removeEventListener('hidden.bs.modal', handler);
                currentPopupIndex++;
                if (currentPopupIndex < popupQueue.length) {
                    setTimeout(showNextPopup, 300);
                }
            });

            bsModal.show();
        }

        setTimeout(showNextPopup, 800);
    }

});
