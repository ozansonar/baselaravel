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

/* Toplu seçim ve toplu işlemler assets/admin/js/bulk-actions.js dosyasında:
 aynı kod yedi listede kopyalanmıştı ve her biri kayıt başına ayrı bir
 istek atıyordu — elli kayıt elli istek, yarısı düşerse ortada karışık bir
 sonuç. İçerik listesinde ise hiç istek gitmiyordu. */

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

// ========== Init ==========
document.addEventListener('DOMContentLoaded', function () {
    animateCounters();
    restoreView();
});
