// ==================== CONTENT LIST PAGE - JavaScript ====================

(function () {
  'use strict';

  var deleteTargetId = null;

  // ==================== STATUS TAB FILTERING ====================
  window.filterByStatus = function (status, btn) {
    document.querySelectorAll('.cl-status-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');

    var rows = document.querySelectorAll('#contentTableBody tr');
    rows.forEach(function (row) {
      var rowStatus = row.getAttribute('data-status');
      row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
    });
    updateRowCount();
  };


  // ==================== CONTENT FILTERING ====================
  window.filterContent = function () {
    var search = document.getElementById('contentSearch').value.toLowerCase();
    var category = document.getElementById('filterCategory').value;
    var author = document.getElementById('filterAuthor').value;
    var type = document.getElementById('filterType').value;
    var rows = document.querySelectorAll('#contentTableBody tr');

    rows.forEach(function (row) {
      var title = (row.querySelector('.cl-content-title') || {}).textContent || '';
      var authorName = (row.querySelector('.cl-author-cell span') || {}).textContent || '';
      var meta = (row.querySelector('.cl-content-meta') || {}).textContent || '';
      var rowCategory = row.getAttribute('data-category');
      var rowAuthor = row.getAttribute('data-author');
      var rowType = row.getAttribute('data-type');

      var matchSearch = !search || title.toLowerCase().indexOf(search) !== -1 || authorName.toLowerCase().indexOf(search) !== -1 || meta.toLowerCase().indexOf(search) !== -1;
      var matchCategory = !category || rowCategory === category;
      var matchAuthor = !author || rowAuthor === author;
      var matchType = !type || rowType === type;

      row.style.display = (matchSearch && matchCategory && matchAuthor && matchType) ? '' : 'none';
    });
    updateRowCount();
  };


  // ==================== RESET FILTERS ====================
  window.resetFilters = function () {
    document.getElementById('contentSearch').value = '';
    document.getElementById('filterCategory').selectedIndex = 0;
    document.getElementById('filterAuthor').selectedIndex = 0;
    document.getElementById('filterDate').selectedIndex = 0;
    document.getElementById('filterType').selectedIndex = 0;

    document.querySelectorAll('.cl-status-tab').forEach(function (t, i) {
      t.classList.toggle('active', i === 0);
    });

    document.querySelectorAll('#contentTableBody tr').forEach(function (row) {
      row.style.display = '';
    });
    updateRowCount();
  };


  // ==================== ROW COUNT ====================
  function updateRowCount() {
    var visible = document.querySelectorAll('#contentTableBody tr:not([style*="display: none"])').length;
    var info = document.querySelector('.cl-pagination-info span');
    if (info) {
      info.innerHTML = 'Toplam <strong>247</strong> içerikten <strong>1-' + visible + '</strong> arası gösteriliyor';
    }
  }


  // ==================== SELECT ALL / BULK ====================
  window.toggleSelectAll = function (checkbox) {
    var rows = document.querySelectorAll('#contentTableBody tr:not([style*="display: none"]) .usr-checkbox');
    rows.forEach(function (cb) { cb.checked = checkbox.checked; });
    updateBulk();
  };

  window.updateBulk = function () {
    var checked = document.querySelectorAll('#contentTableBody .usr-checkbox:checked').length;
    var bulk = document.getElementById('bulkActions');
    var count = document.getElementById('selectedCount');
    if (checked > 0) {
      bulk.classList.remove('d-none');
      count.textContent = checked;
    } else {
      bulk.classList.add('d-none');
    }
  };

  window.bulkAction = function (action) {
    var count = document.querySelectorAll('#contentTableBody .usr-checkbox:checked').length;
    if (count === 0) return;

    if (action === 'delete') {
      document.getElementById('bulkDeleteCount').textContent = count;
      var modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
      modal.show();
      return;
    }

    var actionText = { publish: 'yayınlamak', draft: 'taslağa almak' };
    if (confirm(count + ' içeriği ' + (actionText[action] || action) + ' istediğinize emin misiniz?')) {
      showToast(count + ' içerik başarıyla işlendi.', 'success');
      document.querySelectorAll('#contentTableBody .usr-checkbox:checked').forEach(function (cb) { cb.checked = false; });
      document.getElementById('selectAll').checked = false;
      updateBulk();
    }
  };

  window.confirmBulkDelete = function () {
    var count = document.querySelectorAll('#contentTableBody .usr-checkbox:checked').length;
    showToast(count + ' içerik başarıyla silindi.', 'success');
    document.querySelectorAll('#contentTableBody .usr-checkbox:checked').forEach(function (cb) { cb.checked = false; });
    document.getElementById('selectAll').checked = false;
    updateBulk();
  };


  // ==================== SORT TABLE ====================
  var sortState = {};
  window.sortTable = function (column) {
    sortState[column] = !sortState[column];
    var tbody = document.getElementById('contentTableBody');
    var rows = Array.from(tbody.querySelectorAll('tr'));

    var colMap = { title: '.cl-content-title', category: '.cl-category-badge', author: '.cl-author-cell span', status: '.usr-status-badge', views: '.cl-views', date: 'td:nth-child(7) .usr-meta' };
    var selector = colMap[column];

    rows.sort(function (a, b) {
      var aVal = (a.querySelector(selector) || {}).textContent || '';
      var bVal = (b.querySelector(selector) || {}).textContent || '';

      if (column === 'views') {
        aVal = parseInt(aVal.replace(/[^\d]/g, '')) || 0;
        bVal = parseInt(bVal.replace(/[^\d]/g, '')) || 0;
        return sortState[column] ? aVal - bVal : bVal - aVal;
      }

      return sortState[column]
        ? aVal.localeCompare(bVal, 'tr')
        : bVal.localeCompare(aVal, 'tr');
    });

    rows.forEach(function (row) { tbody.appendChild(row); });
  };


  // ==================== PAGINATION ====================
  window.goToPage = function (page) {
    page = parseInt(page);
    if (page < 1 || page > 10) return;

    document.querySelectorAll('.cl-page-btn').forEach(function (btn) { btn.classList.remove('active'); });
    showToast('Sayfa ' + page + ' yükleniyor...', 'info');
  };

  window.changePerPage = function () {
    var perPage = document.getElementById('perPage').value;
    showToast(perPage + ' sonuç gösteriliyor.', 'info');
  };


  // ==================== DELETE MODAL ====================
  window.openDeleteModal = function (title, id) {
    deleteTargetId = id;
    document.getElementById('deleteContentTitle').textContent = '"' + title + '"';
    var modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
  };

  window.confirmDelete = function () {
    if (deleteTargetId) {
      showToast('İçerik başarıyla silindi.', 'success');
      deleteTargetId = null;
    }
  };


  // ==================== ACTIONS ====================
  window.viewContent = function (id) {
    showToast('İçerik önizleme açılıyor...', 'info');
  };

  window.duplicateContent = function (id) {
    showToast('İçerik kopyalandı. Taslak olarak kaydedildi.', 'success');
  };

  window.exportData = function (format) {
    var formatNames = { csv: 'CSV', json: 'JSON', excel: 'Excel', pdf: 'PDF' };
    showToast((formatNames[format] || format) + ' dosyası indiriliyor...', 'success');
  };


  // ==================== TOAST ====================
  function showToast(message, type) {
    var existing = document.querySelector('.ca-toast');
    if (existing) existing.remove();

    var iconMap = { success: 'bi-check-circle-fill', error: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };
    var toast = document.createElement('div');
    toast.className = 'ca-toast ca-toast-' + type;
    toast.innerHTML = '<i class="bi ' + (iconMap[type] || iconMap.info) + '"></i> ' + message;
    document.body.appendChild(toast);

    requestAnimationFrame(function () { toast.classList.add('show'); });
    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
  }

  // ==================== COUNTER ANIMATION ====================
  function animateCounters() {
    var duration = 1500;
    var elements = document.querySelectorAll('.usr-stat-value, .cl-tab-count');

    elements.forEach(function (el) {
      var originalText = el.textContent.trim();
      var suffix = '';
      var numericText = originalText;

      // K, M gibi suffix'leri ayır
      var suffixMatch = originalText.match(/([KMBkmb]+)$/);
      if (suffixMatch) {
        suffix = suffixMatch[1];
        numericText = originalText.replace(/[KMBkmb]+$/, '').trim();
      }

      // Noktaları kaldırıp sayıya çevir (Türkçe format: 1.247 → 1247)
      var target = parseInt(numericText.replace(/\./g, ''), 10);
      if (isNaN(target) || target === 0) return;

      // Binlik nokta formatlama (Türkçe)
      function formatNumber(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }

      el.textContent = '0' + suffix;
      var startTime = null;

      function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);

        // easeOutExpo: hızlı başla, yavaş bitir
        var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        var current = Math.floor(eased * target);

        el.textContent = formatNumber(current) + suffix;

        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          // Animasyon bitince orijinal metni birebir geri koy
          el.textContent = originalText;
        }
      }

      requestAnimationFrame(step);
    });
  }

  // Sayfa yüklendiğinde başlat
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateCounters);
  } else {
    animateCounters();
  }

})();
