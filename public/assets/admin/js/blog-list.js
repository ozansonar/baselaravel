// ==================== BLOG LIST PAGE - JavaScript ====================

(function () {
  'use strict';

  // ==================== SELECT ALL / BULK ====================
  window.toggleSelectAll = function (checkbox) {
    var rows = document.querySelectorAll('#contentTableBody .content-checkbox');
    rows.forEach(function (cb) { cb.checked = checkbox.checked; });
    updateBulk();
  };

  window.updateBulk = function () {
    var checked = document.querySelectorAll('#contentTableBody .content-checkbox:checked').length;
    var bulk = document.getElementById('bulkActions');
    var count = document.getElementById('selectedCount');
    if (!bulk || !count) return;

    if (checked > 0) {
      bulk.classList.remove('d-none');
      count.textContent = checked;
    } else {
      bulk.classList.add('d-none');
    }
  };

  window.bulkContentAction = function (action) {
    var checked = document.querySelectorAll('#contentTableBody .content-checkbox:checked');
    if (checked.length === 0) return;

    if (action === 'delete') {
      document.getElementById('bulkDeleteCount').textContent = checked.length;
      var modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
      modal.show();
      return;
    }

    var actionText = { publish: 'yayınlamak', draft: 'taslağa almak' };
    AdminModal.confirm({
      title: 'Toplu İşlem Onayı',
      message: checked.length + ' içeriği ' + (actionText[action] || action) + ' istediğinize emin misiniz?',
      type: 'warning',
      confirmText: 'Evet, Devam Et',
      confirmIcon: 'bi bi-check-lg'
    }).then(function (confirmed) {
      if (!confirmed) return;
      checked.forEach(function (cb) { cb.checked = false; });
      var selectAll = document.getElementById('selectAll');
      if (selectAll) selectAll.checked = false;
      updateBulk();
    });
  };

  window.confirmBulkDelete = function () {
    var checked = document.querySelectorAll('#contentTableBody .content-checkbox:checked');
    checked.forEach(function (cb) { cb.checked = false; });
    var selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    updateBulk();
  };


  // ==================== DELETE MODAL ====================
  window.openDeleteModal = function (title, id) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu içeriği silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: title,
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var baseUrl = window.location.pathname;
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = baseUrl.replace(/\/$/, '') + '/' + id;
      form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
      document.body.appendChild(form);
      form.submit();
    });
  };


  // ==================== COUNTER ANIMATION ====================
  function animateCounters() {
    var duration = 1500;
    var elements = document.querySelectorAll('.usr-stat-value, .cl-tab-count');

    elements.forEach(function (el) {
      var originalText = el.textContent.trim();
      var target = parseInt(originalText.replace(/\./g, ''), 10);
      if (isNaN(target) || target === 0) return;

      function formatNumber(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }

      el.textContent = '0';
      var startTime = null;

      function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        var current = Math.floor(eased * target);
        el.textContent = formatNumber(current);
        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          el.textContent = originalText;
        }
      }

      requestAnimationFrame(step);
    });
  }

  // ==================== INIT ====================
  function init() {
    animateCounters();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
