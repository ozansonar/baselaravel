(function () {
    'use strict';

    // ── Matris kategori filtresi ──
    function initCategoryFilter() {
        var select = document.getElementById('matrixCategoryFilter');

        if (!select) {
            return;
        }

        select.addEventListener('change', function () {
            var value = this.value;

            document.querySelectorAll('.rp-matrix-table tbody tr[data-cat]').forEach(function (row) {
                row.classList.toggle('d-none', value !== 'all' && row.dataset.cat !== value);
            });
        });
    }

    // ── Checkbox işaretlenince hücreyi vurgula ──
    function initCheckHighlight() {
        document.querySelectorAll('.rp-check input[type="checkbox"]').forEach(function (input) {
            input.addEventListener('change', function () {
                this.closest('.rp-check').classList.toggle('granted', this.checked);
            });
        });
    }

    // ── Rol ekle / düzenle modalı ──
    function initRoleModal() {
        var modalEl = document.getElementById('roleModal');
        var form = document.getElementById('roleForm');

        if (!modalEl || !form) {
            return;
        }

        var storeUrl = form.getAttribute('action');
        var methodInput = document.getElementById('roleFormMethod');
        var title = document.getElementById('roleModalTitle');
        var nameInput = document.getElementById('roleName');
        var slugInput = document.getElementById('roleSlug');
        var descInput = document.getElementById('roleDescription');

        var addBtn = document.getElementById('addRoleBtn');

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                form.setAttribute('action', storeUrl);
                methodInput.value = 'POST';
                title.textContent = 'Yeni Rol';
                nameInput.value = '';
                slugInput.value = '';
                slugInput.removeAttribute('readonly');
                descInput.value = '';
            });
        }

        document.querySelectorAll('.js-edit-role').forEach(function (button) {
            button.addEventListener('click', function () {
                var data = this.dataset;

                form.setAttribute('action', storeUrl + '/' + data.id);
                methodInput.value = 'PUT';
                title.textContent = 'Rolü Düzenle';
                nameInput.value = data.name || '';
                slugInput.value = data.slug || '';
                descInput.value = data.description || '';

                // A system role's slug is referenced in code, so it is read-only.
                if (data.system === '1') {
                    slugInput.setAttribute('readonly', 'readonly');
                } else {
                    slugInput.removeAttribute('readonly');
                }

                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });
    }

    // ── Silme onayı ──
    function initDeleteModal() {
        var modalEl = document.getElementById('deleteRoleModal');
        var form = document.getElementById('deleteRoleForm');
        var nameEl = document.getElementById('deleteRoleName');

        if (!modalEl || !form) {
            return;
        }

        var baseUrl = document.getElementById('roleForm')
            ? document.getElementById('roleForm').getAttribute('action')
            : null;

        document.querySelectorAll('.js-delete-role').forEach(function (button) {
            button.addEventListener('click', function () {
                if (baseUrl) {
                    form.setAttribute('action', baseUrl + '/' + this.dataset.id);
                }

                nameEl.textContent = this.dataset.name || '';
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCategoryFilter();
        initCheckHighlight();
        initRoleModal();
        initDeleteModal();
    });
})();
