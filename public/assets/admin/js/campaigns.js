(function () {
  'use strict';

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateCounters();

    var searchInput = document.getElementById('campaignSearch');
    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          document.getElementById('filterForm').submit();
        }
      });
    }
  });

  /* ==================== COUNTER ANIMATION ==================== */
  function animateCounters() {
    var statValues = document.querySelectorAll('.usr-stat-value');
    statValues.forEach(function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10);
      if (isNaN(target) || target === 0) return;
      var duration  = 1400;
      var startTime = null;
      function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var eased    = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('tr-TR');
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString('tr-TR');
      }
      requestAnimationFrame(step);
    });
  }

  /* ==================== SELECT ALL / BULK ==================== */
  window.toggleSelectAll = function (master) {
    document.querySelectorAll('#campaignsTableBody .campaign-checkbox').forEach(function (cb) {
      cb.checked = master.checked;
    });
    updateBulk();
  };

  window.updateBulk = function () {
    var checked = document.querySelectorAll('#campaignsTableBody .campaign-checkbox:checked').length;
    var bulk    = document.getElementById('bulkActions');
    var counter = document.getElementById('selectedCount');
    if (checked > 0) {
      bulk.classList.remove('d-none');
      counter.textContent = checked;
    } else {
      bulk.classList.add('d-none');
    }
    var all   = document.getElementById('selectAll');
    var total = document.querySelectorAll('#campaignsTableBody .campaign-checkbox').length;
    all.indeterminate = checked > 0 && checked < total;
    all.checked       = checked === total && total > 0;
  };

  /* ==================== DELETE MODAL ==================== */
  window.openDeleteModal = function (campaignId, campaignName) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu kampanyayı silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: campaignName,
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;

      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/admin/campaigns/' + campaignId;

      var csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = '_token';
      csrf.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      form.appendChild(csrf);

      var method = document.createElement('input');
      method.type = 'hidden';
      method.name = '_method';
      method.value = 'DELETE';
      form.appendChild(method);

      document.body.appendChild(form);
      form.submit();
    });
  };

  /* ==================== BULK DELETE ==================== */
  window.openBulkDeleteModal = function () {
    var checked = document.querySelectorAll('#campaignsTableBody .campaign-checkbox:checked');
    if (checked.length === 0) {
      showToast('Lütfen kampanya seçin', 'warning');
      return;
    }

    AdminModal.confirm({
      title: 'Toplu Silme Onayı',
      message: checked.length + ' kampanyayı silmek istediğinize emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      warning: 'Bu işlem geri alınamaz. Seçilen kampanyalar kalıcı olarak silinecektir.'
    }).then(function (confirmed) {
      if (!confirmed) return;

      var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      var completed = 0;
      var ids = [];
      checked.forEach(function (cb) { ids.push(cb.value); });

      ids.forEach(function (id) {
        fetch('/admin/campaigns/' + id, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
          }
        }).then(function () {
          completed++;
          if (completed === ids.length) {
            showToast(ids.length + ' kampanya silindi', 'success');
            setTimeout(function () { location.reload(); }, 800);
          }
        }).catch(function () {
          completed++;
        });
      });
    });
  };

  /* ==================== TOAST ==================== */
  function showToast(message, type) {
    if (typeof type === 'undefined') type = 'success';
    var titleMap = { success: 'Başarılı', error: 'Hata', danger: 'Hata', warning: 'Uyarı', info: 'Bilgi' };
    var modalType = type === 'error' ? 'danger' : type;
    if (typeof AdminModal !== 'undefined') {
      AdminModal.status({ title: titleMap[type] || 'Bilgi', message: message, type: modalType });
    }
  }

})();
