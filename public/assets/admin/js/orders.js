(function () {
  'use strict';

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateCounters();

    // Submit search on Enter
    var searchInput = document.getElementById('orderSearch');
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
      var raw = el.textContent.trim();
      if (raw.indexOf('%') !== -1 || raw.indexOf('M') !== -1 || raw.indexOf('₺') !== -1) return;
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
    document.querySelectorAll('#ordersTableBody .order-checkbox').forEach(function (cb) {
      cb.checked = master.checked;
    });
    updateBulk();
  };

  window.updateBulk = function () {
    var checked = document.querySelectorAll('#ordersTableBody .order-checkbox:checked').length;
    var bulk    = document.getElementById('bulkActions');
    var counter = document.getElementById('selectedCount');
    if (checked > 0) {
      bulk.classList.remove('d-none');
      counter.textContent = checked;
    } else {
      bulk.classList.add('d-none');
    }
    var all   = document.getElementById('selectAll');
    var total = document.querySelectorAll('#ordersTableBody .order-checkbox').length;
    all.indeterminate = checked > 0 && checked < total;
    all.checked       = checked === total && total > 0;
  };

  /* ==================== BULK STATUS UPDATE ==================== */
  window.bulkStatusUpdate = function (status) {
    var checked = document.querySelectorAll('#ordersTableBody .order-checkbox:checked');
    if (checked.length === 0) {
      showToast('Lütfen sipariş seçin', 'warning');
      return;
    }

    var ids = [];
    checked.forEach(function (cb) { ids.push(cb.value); });

    var labels = {
      processing: 'Hazırlanıyor',
      shipped:    'Kargoda',
      cancelled:  'İptal'
    };

    AdminModal.confirm({
      title: 'Toplu Durum Güncelleme',
      message: checked.length + ' siparişi "' + (labels[status] || status) + '" olarak güncellemek istediğinize emin misiniz?',
      type: 'warning',
      confirmText: 'Evet, Güncelle',
      confirmIcon: 'bi bi-check-lg'
    }).then(function (confirmed) {
      if (!confirmed) return;

      // Update each order status via AJAX
      var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      var completed = 0;

      ids.forEach(function (id) {
        fetch('/admin/orders/' + id + '/status', {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
          },
          body: JSON.stringify({ status: status })
        }).then(function () {
          completed++;
          if (completed === ids.length) {
            showToast(ids.length + ' sipariş güncellendi', 'success');
            setTimeout(function () { location.reload(); }, 800);
          }
        }).catch(function () {
          completed++;
        });
      });
    });
  };

  /* ==================== STATUS MODAL ==================== */
  window.openStatusModal = function (orderId, orderNumber, currentStatus) {
    document.getElementById('statusOrderNumber').textContent = '#' + orderNumber;
    document.getElementById('statusForm').action = '/admin/orders/' + orderId + '/status';

    // Pre-select current status
    document.querySelectorAll('#statusForm input[name="status"]').forEach(function (radio) {
      radio.checked = radio.value === currentStatus;
    });

    var modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
  };

  /* ==================== DELETE MODAL ==================== */
  window.openDeleteModal = function (orderId, orderNumber) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu siparişi silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: '#' + orderNumber,
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/admin/orders/' + orderId;
      form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
      document.body.appendChild(form);
      form.submit();
    });
  };

  /* ==================== EXPORT (placeholder) ==================== */
  window.exportOrders = function (type) {
    var labels = { csv: 'CSV', excel: 'Excel', pdf: 'PDF' };
    showToast('Siparişler ' + (labels[type] || type) + ' olarak dışa aktarılıyor...', 'info');
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
