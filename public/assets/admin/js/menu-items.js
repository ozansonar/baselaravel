(function () {
    'use strict';

    var config = window.menuConfig || {};
    var tree = document.getElementById('menuTree');
    var modalEl = document.getElementById('menuItemModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var form = document.getElementById('menuItemForm');
    var modalTitle = document.getElementById('menuItemModalTitle');
    var formMethod = document.getElementById('formMethod');
    var parentInput = document.getElementById('parentIdInput');
    var labelInput = document.getElementById('itemLabel');
    var iconInput = document.getElementById('itemIcon');
    var routeNameSelect = document.getElementById('itemRouteName');
    var urlInput = document.getElementById('itemUrl');
    var displayTypeSelect = document.getElementById('itemDisplayType');
    var targetSelect = document.getElementById('itemTarget');
    var activeCheckbox = document.getElementById('itemIsActive');
    var routeFields = document.getElementById('routeFields');
    var urlFields = document.getElementById('urlFields');
    var routeParamsWrapper = document.getElementById('routeParamsWrapper');
    var routeParamsList = document.getElementById('routeParamsList');
    var saveBtn = document.getElementById('saveItemBtn');
    var statusEl = document.getElementById('reorderStatus');

    if (!tree && !modal) {
        return;
    }

    // ── Sortable on every nested ul ──
    function initSortable(ul) {
        new Sortable(ul, {
            group: 'menu-tree',
            handle: '.menu-drag-handle',
            animation: 150,
            fallbackOnBody: true,
            invertSwap: true,
            ghostClass: 'menu-tree-ghost',
            chosenClass: 'menu-tree-chosen',
            dragClass: 'menu-tree-drag',
            onEnd: saveOrder
        });
    }

    if (tree) {
        initSortable(tree);
        tree.querySelectorAll('.menu-tree-nested').forEach(initSortable);
    }

    function serializeTree(ul) {
        var items = [];
        ul.querySelectorAll(':scope > li').forEach(function (li) {
            var nested = li.querySelector(':scope > ul');
            items.push({
                id: parseInt(li.dataset.itemId, 10),
                children: nested ? serializeTree(nested) : []
            });
        });
        return items;
    }

    function saveOrder() {
        if (!tree) return;
        var data = serializeTree(tree);

        fetch(tree.dataset.reorderUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ tree: data })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success && statusEl) {
                statusEl.classList.remove('d-none');
                setTimeout(function () { statusEl.classList.add('d-none'); }, 2000);
            }
        })
        .catch(function () {
            if (window.AdminModal && typeof AdminModal.status === 'function') {
                AdminModal.status({ title: 'Hata', message: 'Sıralama kaydedilemedi.', type: 'danger' });
            }
        });
    }

    // ── Modal: link type toggle ──
    document.querySelectorAll('input[name="link_type"]').forEach(function (radio) {
        radio.addEventListener('change', toggleLinkFields);
    });

    // The server asks for a route or a URL depending on the link type
    // (required_if), so the rule follows the field that is on screen — leaving
    // it on the hidden one would flag a field nobody can see.
    function requireOnly(wanted, other) {
        wanted.setAttribute('data-validation-engine', 'validate[required]');
        other.removeAttribute('data-validation-engine');
        other.classList.remove('is-invalid');

        // A field with no rule is never revisited, so its old message would
        // otherwise sit there for good.
        var container = other.closest('.stg-field, .mb-3, .col-md-6, .col-12') || other.parentElement;

        if (container) {
            container.querySelectorAll('.formError').forEach(function (el) { el.remove(); });
        }
    }

    function toggleLinkFields() {
        var type = document.querySelector('input[name="link_type"]:checked').value;
        if (type === 'route') {
            routeFields.classList.remove('d-none');
            urlFields.classList.add('d-none');
            urlInput.value = '';
            requireOnly(routeNameSelect, urlInput);
        } else {
            routeFields.classList.add('d-none');
            urlFields.classList.remove('d-none');
            routeNameSelect.value = '';
            routeParamsList.innerHTML = '';
            routeParamsWrapper.classList.add('d-none');
            requireOnly(urlInput, routeNameSelect);
        }
    }

    // The modal opens with "route" preselected; match that from the start.
    toggleLinkFields();

    // ── Route params dynamic UI ──
    var routeParamRules = {
        'pages.show': ['slug'],
        'products.index': ['categorySlug'],
        'products.show': ['slug'],
        'blog.show': ['categorySlug', 'slug'],
        'blog.category': ['categorySlug']
    };

    routeNameSelect.addEventListener('change', function () {
        renderRouteParams(this.value, {});
    });

    function renderRouteParams(routeName, currentValues) {
        routeParamsList.innerHTML = '';
        var keys = routeParamRules[routeName] || [];
        if (keys.length === 0) {
            routeParamsWrapper.classList.add('d-none');
            return;
        }
        routeParamsWrapper.classList.remove('d-none');
        keys.forEach(function (key) {
            var div = document.createElement('div');
            div.className = 'input-group';
            div.innerHTML =
                '<span class="input-group-text">' + key + '</span>' +
                '<input type="text" class="form-control" name="route_params[' + key + ']" value="' + (currentValues[key] || '') + '" required>';
            routeParamsList.appendChild(div);
        });
    }

    // ── Open modal: add root ──
    var addRootBtn = document.getElementById('addRootItemBtn');
    if (addRootBtn) {
        addRootBtn.addEventListener('click', function () {
            resetForm();
            modalTitle.textContent = 'Yeni Menü Öğesi';
            form.action = config.storeUrl;
            formMethod.value = 'POST';
        });
    }

    // ── Open modal: add child ──
    document.querySelectorAll('.js-add-child').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetForm();
            modalTitle.textContent = 'Yeni Alt Öğe';
            parentInput.value = this.dataset.parentId;
            form.action = config.storeUrl;
            formMethod.value = 'POST';
            modal.show();
        });
    });

    // ── Open modal: edit ──
    document.querySelectorAll('.js-edit-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = JSON.parse(btn.dataset.item);
            resetForm();
            modalTitle.textContent = 'Menü Öğesi Düzenle: ' + item.label;
            form.action = config.itemUrlTemplate.replace('__ID__', item.id);
            formMethod.value = 'PUT';

            labelInput.value = item.label || '';
            iconInput.value = item.icon || '';
            displayTypeSelect.value = item.display_type || 'link';
            targetSelect.value = item.target || '_self';
            activeCheckbox.checked = !!item.is_active;
            parentInput.value = item.parent_id || '';

            if (item.link_type === 'url') {
                document.getElementById('linkTypeUrl').checked = true;
                urlInput.value = item.url || '';
            } else {
                document.getElementById('linkTypeRoute').checked = true;
                routeNameSelect.value = item.route_name || '';
                renderRouteParams(item.route_name, item.route_params || {});
            }
            toggleLinkFields();
            modal.show();
        });
    });

    function resetForm() {
        form.reset();
        parentInput.value = '';
        document.getElementById('linkTypeRoute').checked = true;
        activeCheckbox.checked = true;
        routeParamsList.innerHTML = '';
        routeParamsWrapper.classList.add('d-none');
        toggleLinkFields();
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
})();
