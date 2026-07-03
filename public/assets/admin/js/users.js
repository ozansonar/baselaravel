/**
 * Users List Page
 * - Counter animation
 * - View toggle (table/grid)
 * - Checkbox / bulk operations
 * - Delete modal
 * - Bulk delete modal
 */

// ========== Counter Animation ==========
function animateCounters() {
    document.querySelectorAll('[data-count]').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(target)) return;

        var start = 0;
        var duration = 800;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(eased * target).toLocaleString('tr-TR');
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('tr-TR');
            }
        }

        requestAnimationFrame(step);
    });
}

// ========== View Toggle ==========
function switchView(view, btn) {
    var tableView = document.getElementById('tableView');
    var gridView = document.getElementById('gridView');

    if (!tableView || !gridView) return;

    if (view === 'grid') {
        tableView.classList.add('d-none');
        gridView.classList.remove('d-none');
    } else {
        tableView.classList.remove('d-none');
        gridView.classList.add('d-none');
    }

    document.querySelectorAll('.usr-view-btn').forEach(function (b) {
        b.classList.remove('active');
    });
    if (btn) btn.classList.add('active');

    try {
        localStorage.setItem('usersView', view);
    } catch (e) {}
}

function restoreView() {
    try {
        var saved = localStorage.getItem('usersView');
        if (saved === 'grid') {
            var gridBtn = document.querySelector('.usr-view-btn:last-child');
            switchView('grid', gridBtn);
        }
    } catch (e) {}
}

// ========== Checkbox / Bulk Operations ==========
function toggleSelectAll(masterCheckbox) {
    var checkboxes = document.querySelectorAll('#userTableBody .usr-checkbox');
    checkboxes.forEach(function (cb) {
        cb.checked = masterCheckbox.checked;
    });
    updateBulk();
}

function updateBulk() {
    var checkboxes = document.querySelectorAll('#userTableBody .usr-checkbox:checked');
    var bulkActions = document.getElementById('bulkActions');
    var selectedCount = document.getElementById('selectedCount');

    if (!bulkActions || !selectedCount) return;

    if (checkboxes.length > 0) {
        bulkActions.classList.remove('d-none');
        selectedCount.textContent = checkboxes.length;
    } else {
        bulkActions.classList.add('d-none');
    }

    var masterCheckbox = document.getElementById('selectAll');
    var allCheckboxes = document.querySelectorAll('#userTableBody .usr-checkbox');
    if (masterCheckbox && allCheckboxes.length > 0) {
        masterCheckbox.checked = checkboxes.length === allCheckboxes.length;
        masterCheckbox.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
    }
}

// ========== Delete Modal ==========
function openDeleteModal(id, name) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu kullanıcıyı silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: name,
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var deleteForm = document.getElementById('deleteForm');
      var baseUrl = (deleteForm && deleteForm.getAttribute('data-action-url')) || '/admin/users/';
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = baseUrl + id;
      form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
      document.body.appendChild(form);
      form.submit();
    });
}

// ========== Bulk Delete ==========
function confirmBulkDelete() {
    var checkboxes = document.querySelectorAll('#userTableBody .usr-checkbox:checked');
    if (checkboxes.length === 0) return;

    var bulkDeleteCount = document.getElementById('bulkDeleteCount');
    if (bulkDeleteCount) {
        bulkDeleteCount.textContent = checkboxes.length + ' kullanıcı seçildi';
    }

    var modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
    modal.show();
}

function executeBulkDelete() {
    var checkboxes = document.querySelectorAll('#userTableBody .usr-checkbox:checked');
    var ids = [];
    checkboxes.forEach(function (cb) {
        ids.push(cb.value);
    });

    if (ids.length === 0) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) return;

    var promises = ids.map(function (id) {
        return fetch('/admin/users/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                'Accept': 'application/json'
            }
        });
    });

    Promise.all(promises).then(function () {
        window.location.reload();
    });
}

// ========== Init ==========
document.addEventListener('DOMContentLoaded', function () {
    animateCounters();
    restoreView();
});
