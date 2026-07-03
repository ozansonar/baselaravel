(function () {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

    var modalEl = document.getElementById('redirectModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var form = document.getElementById('redirectForm');
    var modalTitle = document.getElementById('redirectModalTitle');
    var formMethod = document.getElementById('redirectFormMethod');
    var oldUrlInput = document.getElementById('oldUrl');
    var newUrlInput = document.getElementById('newUrl');
    var statusCodeSelect = document.getElementById('statusCode');
    var noteInput = document.getElementById('redirectNote');
    var isActiveCheckbox = document.getElementById('redirectIsActive');
    var newUrlWrapper = document.getElementById('newUrlWrapper');
    var statusCodeDesc = document.getElementById('statusCodeDesc');
    var storeUrl = form ? form.action : '';

    var statusDescriptions = {
        '301': 'URL kalıcı olarak taşındı. SEO değeri yeni URL\'ye aktarılır. Google eski URL\'yi zamanla düşürür.',
        '302': 'URL geçici olarak başka yere yönlendirildi. SEO değeri eski URL\'de kalır.',
        '307': '302 gibi ama HTTP metodu (POST/GET) değişmez. API yönlendirmeleri için uygundur.',
        '308': '301 gibi ama HTTP metodu değişmez. API\'ler için kalıcı taşıma.',
        '404': 'Sayfa mevcut değil. Yönlendirme yapılmaz, 404 sayfası gösterilir.',
        '410': 'Sayfa bilinçli olarak kaldırıldı ve geri gelmeyecek. Google daha hızlı düşürür.'
    };

    // ── Status code change → toggle new_url visibility + description ──
    if (statusCodeSelect) {
        statusCodeSelect.addEventListener('change', function () {
            updateStatusUI(this.value);
        });
    }

    function updateStatusUI(code) {
        var isNoRedirect = code === '404' || code === '410';

        if (newUrlWrapper) {
            newUrlWrapper.classList.toggle('d-none', isNoRedirect);
        }

        if (newUrlInput && isNoRedirect) {
            newUrlInput.value = '';
            newUrlInput.removeAttribute('required');
        } else if (newUrlInput) {
            newUrlInput.setAttribute('required', 'required');
        }

        if (statusCodeDesc) {
            var desc = statusDescriptions[code] || '';
            statusCodeDesc.querySelector('span').textContent = desc;
        }
    }

    // ── Add new redirect ──
    var addBtn = document.getElementById('addRedirectBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            resetForm();
            modalTitle.textContent = 'Yeni Yönlendirme';
            form.action = storeUrl;
            formMethod.value = 'POST';
        });
    }

    // ── Edit redirect ──
    document.querySelectorAll('.js-edit-redirect').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.dataset.redirect);
            resetForm();
            modalTitle.textContent = 'Yönlendirme Düzenle';
            form.action = storeUrl.replace(/\/redirects.*$/, '/redirects/' + data.id);
            formMethod.value = 'PUT';

            oldUrlInput.value = data.old_url || '';
            newUrlInput.value = data.new_url || '';
            statusCodeSelect.value = String(data.status_code || 301);
            noteInput.value = data.note || '';
            isActiveCheckbox.checked = !!data.is_active;

            updateStatusUI(String(data.status_code || 301));
            modal.show();
        });
    });

    function resetForm() {
        form.reset();
        formMethod.value = 'POST';
        isActiveCheckbox.checked = true;
        statusCodeSelect.value = '301';
        updateStatusUI('301');
    }

    // ── Delete confirmation ──
    document.querySelectorAll('.js-delete-form').forEach(function (formEl) {
        formEl.addEventListener('submit', function (e) {
            var btn = formEl.querySelector('button[data-confirm]');
            var msg = btn ? btn.dataset.confirm : 'Silmek istediğinize emin misiniz?';
            e.preventDefault();
            if (window.AdminModal && AdminModal.confirm) {
                AdminModal.confirm({
                    title: 'Onay',
                    message: msg,
                    confirmText: 'Sil',
                    confirmClass: 'btn-danger',
                    onConfirm: function () { formEl.submit(); }
                });
            } else {
                formEl.submit();
            }
        });
    });

    // ── Toggle active/inactive ──
    document.querySelectorAll('.js-toggle-active').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var url = this.dataset.url;
            fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && window.AdminModal && AdminModal.status) {
                    AdminModal.status.success('Durum güncellendi.');
                }
            })
            .catch(function () {
                if (window.AdminModal && AdminModal.status) {
                    AdminModal.status.error('Durum güncellenemedi.');
                }
                checkbox.checked = !checkbox.checked;
            });
        });
    });

    // ── Copy URL to clipboard ──
    document.querySelectorAll('.js-copy-url').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = this.dataset.url;
            navigator.clipboard.writeText(url).then(function () {
                if (window.AdminModal && AdminModal.status) {
                    AdminModal.status.success('URL kopyalandı.');
                }
            });
        });
    });

    // ── Debounced search auto-submit ──
    var searchInput = document.getElementById('redirectSearch');
    var filterForm = document.getElementById('filterForm');
    if (searchInput && filterForm) {
        var debounceTimer = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                filterForm.submit();
            }, 500);
        });
        // Enter → anında submit
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                filterForm.submit();
            }
        });
    }
})();
