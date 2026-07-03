/**
 * Instagram API Logları admin sayfası
 * - Detay modal AJAX yüklemesi
 * - JSON pretty-print + kopyalama
 * - data-confirm attribute'ü olan butonlar için AdminModal entegrasyonu
 */
(function () {
    'use strict';

    var modalEl = document.getElementById('igLogDetailModal');
    if (!modalEl) return;

    var bsModal      = null;
    var loadingEl    = document.getElementById('igLogModalLoading');
    var errorEl      = document.getElementById('igLogModalError');
    var errorMsgEl   = document.getElementById('igLogModalErrorMsg');
    var contentEl    = document.getElementById('igLogModalContent');
    var idEl         = document.getElementById('igLogModalId');
    var actionEl     = document.getElementById('igLogModalAction');
    var statusEl     = document.getElementById('igLogModalStatus');
    var durationEl   = document.getElementById('igLogModalDuration');
    var dateEl       = document.getElementById('igLogModalDate');
    var postEl       = document.getElementById('igLogModalPost');
    var errBoxEl     = document.getElementById('igLogModalErrorBox');
    var errTextEl    = document.getElementById('igLogModalErrorText');
    var requestEl    = document.getElementById('igLogModalRequest');
    var responseEl   = document.getElementById('igLogModalResponse');

    function openModal() {
        if (!bsModal) {
            bsModal = new bootstrap.Modal(modalEl);
        }
        bsModal.show();
    }

    function showLoading() {
        loadingEl.classList.remove('d-none');
        errorEl.classList.add('d-none');
        contentEl.classList.add('d-none');
    }

    function showError(msg) {
        loadingEl.classList.add('d-none');
        contentEl.classList.add('d-none');
        errorEl.classList.remove('d-none');
        errorMsgEl.textContent = msg || 'Bilinmeyen hata oluştu.';
    }

    function showContent() {
        loadingEl.classList.add('d-none');
        errorEl.classList.add('d-none');
        contentEl.classList.remove('d-none');
    }

    function prettyJson(value) {
        if (value === null || value === undefined) return '';
        if (typeof value === 'string') {
            // Stringi JSON olarak parse etmeyi dene
            try {
                var parsed = JSON.parse(value);
                return JSON.stringify(parsed, null, 2);
            } catch (e) {
                return value;
            }
        }
        try {
            return JSON.stringify(value, null, 2);
        } catch (e) {
            return String(value);
        }
    }

    function loadDetail(logId) {
        showLoading();
        openModal();

        var url = '/admin/instagram-logs/' + encodeURIComponent(logId);
        var token = document.querySelector('meta[name="csrf-token"]');

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
        })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('Sunucu hatası (' + res.status + ')');
            }
            return res.json();
        })
        .then(function (data) {
            renderDetail(data);
            showContent();
        })
        .catch(function (err) {
            showError(err && err.message ? err.message : 'Detay yüklenemedi.');
        });
    }

    function renderDetail(d) {
        idEl.textContent = '#' + d.id;
        actionEl.innerHTML = '<code>' + escapeHtml(d.action) + '</code><br><small class="text-muted">' + escapeHtml(d.action_label || '') + '</small>';

        if (d.status === 'success') {
            statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Başarılı</span>';
        } else {
            statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Başarısız</span>';
        }

        durationEl.textContent = d.api_duration_ms !== null && d.api_duration_ms !== undefined
            ? Number(d.api_duration_ms).toLocaleString('tr-TR') + ' ms'
            : '—';

        dateEl.textContent = d.created_at || '—';

        if (d.instagram_post_id) {
            postEl.innerHTML = '<a href="/admin/instagram-posts/' + d.instagram_post_id + '/edit" class="text-teal">#' + d.instagram_post_id + ' (Düzenle)</a>';
        } else {
            postEl.innerHTML = '<span class="text-muted">—</span>';
        }

        if (d.error_message) {
            errBoxEl.classList.remove('d-none');
            errTextEl.textContent = d.error_message;
        } else {
            errBoxEl.classList.add('d-none');
            errTextEl.textContent = '';
        }

        requestEl.textContent = d.request_payload ? prettyJson(d.request_payload) : '';
        responseEl.textContent = d.response_pretty || (d.response_body ? prettyJson(d.response_body) : '');
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function copyToClipboard(text, button) {
        if (!text) return;

        var done = function () {
            var original = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-lg me-1"></i> Kopyalandı';
            setTimeout(function () {
                button.innerHTML = original;
            }, 1500);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fallbackCopy(text, done);
            });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
    }

    // ──────── Event delegation ────────

    // Detay butonları
    document.querySelectorAll('.ig-log-detail-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-log-id');
            if (id) loadDetail(id);
        });
    });

    // Kopyala butonları (modal içi)
    modalEl.addEventListener('click', function (e) {
        var btn = e.target.closest('.ig-log-copy-btn');
        if (!btn) return;
        var targetId = btn.getAttribute('data-target');
        var target = document.getElementById(targetId);
        if (target) copyToClipboard(target.textContent, btn);
    });

    // data-confirm attribute'ü olan butonlar — form submit'i AdminModal ile onaya bağla.
    // Kullanım: <button type="submit" data-confirm="Mesaj"> veya <form data-confirm="...">
    var boundForms = new WeakSet();
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        var form = el.tagName === 'FORM' ? el : el.closest('form');
        if (!form || boundForms.has(form)) return;
        boundForms.add(form);

        form.addEventListener('submit', function (ev) {
            if (form.dataset.igLogConfirmed === '1') return;
            if (typeof AdminModal === 'undefined') return;

            // Submit'i tetikleyen butonun mesajı öncelikli (form içinde birden fazla button olabilir)
            var trigger = ev.submitter && ev.submitter.hasAttribute('data-confirm') ? ev.submitter : null;
            var msg = trigger ? trigger.getAttribute('data-confirm') : form.getAttribute('data-confirm');

            // Tetikleyici butonda yoksa, formdaki ilk data-confirm'i al (fallback)
            if (!msg) {
                var fallbackBtn = form.querySelector('[data-confirm]');
                msg = fallbackBtn ? fallbackBtn.getAttribute('data-confirm') : null;
            }

            if (!msg) return;

            ev.preventDefault();
            AdminModal.confirm({
                title: 'Onay Gerekli',
                message: msg,
                type: 'warning',
                confirmText: 'Devam Et',
                confirmIcon: 'bi bi-check-lg',
            }).then(function (ok) {
                if (ok) {
                    form.dataset.igLogConfirmed = '1';
                    // Submit eden butonun name/value'sunu da gönder
                    if (trigger && trigger.name) {
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = trigger.name;
                        hidden.value = trigger.value || '';
                        form.appendChild(hidden);
                    }
                    form.submit();
                }
            });
        });
    });

    // Modal kapatıldığında temizlik
    modalEl.addEventListener('hidden.bs.modal', function () {
        showLoading();
        idEl.textContent = '';
    });
})();
