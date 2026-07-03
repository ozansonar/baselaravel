// ==================== PRODUCTS PAGE - JavaScript ====================

(function () {
  'use strict';

  // ==================== VIEW SWITCHING ====================
  function switchView(view) {
    localStorage.setItem('prd_view', view);

    var gridView = document.getElementById('productGridView');
    var tableView = document.getElementById('productTableView');
    var gridBtn = document.getElementById('gridViewBtn');
    var tableBtn = document.getElementById('tableViewBtn');

    if (!gridView || !tableView) return;

    if (view === 'grid') {
      gridView.classList.remove('d-none');
      tableView.classList.add('d-none');
      if (gridBtn) gridBtn.classList.add('active');
      if (tableBtn) tableBtn.classList.remove('active');
    } else {
      gridView.classList.add('d-none');
      tableView.classList.remove('d-none');
      if (gridBtn) gridBtn.classList.remove('active');
      if (tableBtn) tableBtn.classList.add('active');
    }
  }
  window.switchView = switchView;


  // ==================== SELECT ALL / BULK (TABLE VIEW) ====================
  window.toggleSelectAllProducts = function (checkbox) {
    var rows = document.querySelectorAll('#productTableBody .product-checkbox');
    rows.forEach(function (cb) { cb.checked = checkbox.checked; });
    updateProductBulk();
  };

  window.updateProductBulk = function () {
    var checked = document.querySelectorAll('#productTableBody .product-checkbox:checked').length;
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

  window.bulkProductAction = function (action) {
    var checked = document.querySelectorAll('#productTableBody .product-checkbox:checked');
    if (checked.length === 0) return;

    if (action === 'delete') {
      document.getElementById('bulkDeleteCount').textContent = checked.length;
      var modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
      modal.show();
      return;
    }

    var ids = [];
    checked.forEach(function (cb) { ids.push(cb.value); });

    var actionText = { activate: 'aktif etmek', draft: 'taslağa almak' };
    AdminModal.confirm({
      title: 'Toplu İşlem Onayı',
      message: checked.length + ' ürünü ' + (actionText[action] || action) + ' istediğinize emin misiniz?',
      type: 'warning',
      confirmText: 'Evet, Devam Et',
      confirmIcon: 'bi bi-check-lg'
    }).then(function (confirmed) {
      if (!confirmed) return;
      showToast(checked.length + ' ürün başarıyla işlendi.', 'success');
      checked.forEach(function (cb) { cb.checked = false; });
      var selectAll = document.getElementById('selectAllProducts');
      if (selectAll) selectAll.checked = false;
      window.updateProductBulk();
    });
  };

  window.confirmBulkDelete = function () {
    var checked = document.querySelectorAll('#productTableBody .product-checkbox:checked');
    showToast(checked.length + ' ürün başarıyla silindi.', 'success');
    checked.forEach(function (cb) { cb.checked = false; });
    var selectAll = document.getElementById('selectAllProducts');
    if (selectAll) selectAll.checked = false;
    window.updateProductBulk();
  };


  // ==================== QUICK VIEW MODAL ====================
  window.openQuickView = function (id) {
    var data = (window.productData || {})[id];
    if (!data) return;

    var el = function (selector) { return document.getElementById(selector); };

    el('quickViewName').textContent = data.name;
    el('quickViewCategory').textContent = data.category;
    el('quickViewPrice').textContent = data.price;
    el('quickViewDesc').textContent = data.desc || '';
    el('quickViewSku').textContent = data.sku;
    el('quickViewStock').textContent = data.stock;
    el('quickViewStatus').textContent = data.status;
    el('quickViewEditBtn').href = data.editUrl;

    var mainImg = el('quickViewMainImg');
    if (data.image) {
      mainImg.src = data.image;
      mainImg.alt = data.name;
    } else {
      mainImg.src = '';
      mainImg.alt = 'Görsel yok';
    }

    var oldPrice = el('quickViewOldPrice');
    var discountTag = el('quickViewDiscount');
    if (data.oldPrice) {
      oldPrice.textContent = data.oldPrice;
      oldPrice.classList.remove('d-none');
      if (discountTag) {
        discountTag.textContent = data.discount;
        discountTag.classList.remove('d-none');
      }
    } else {
      oldPrice.classList.add('d-none');
      if (discountTag) discountTag.classList.add('d-none');
    }

    var modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
    modal.show();
  };


  // ==================== DELETE MODAL ====================
  window.openDeleteModal = function (title, id) {
    var data = (window.productData || {})[id];
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu ürünü silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: title,
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = (data && data.deleteUrl) ? data.deleteUrl : '/admin/products/' + id;
      form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
      document.body.appendChild(form);
      form.submit();
    });
  };


  // ==================== TOAST ====================
  function showToast(message, type) {
    if (typeof type === 'undefined') type = 'success';
    var titleMap = { success: 'Başarılı', error: 'Hata', danger: 'Hata', warning: 'Uyarı', info: 'Bilgi' };
    var modalType = type === 'error' ? 'danger' : type;
    if (typeof AdminModal !== 'undefined') {
      AdminModal.status({ title: titleMap[type] || 'Bilgi', message: message, type: modalType });
    }
  }


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
    var savedView = localStorage.getItem('prd_view');
    if (savedView === 'table') {
      switchView('table');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
