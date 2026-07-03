// ==================== CONTENT LIST PAGE - JavaScript ====================

(function () {
  'use strict';

  // ==================== STATUS TAB FILTERING ====================
  window.filterByStatus = function (status, btn) {
    document.querySelectorAll('.cl-status-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');

    var rows = document.querySelectorAll('#contentTableBody tr');
    rows.forEach(function (row) {
      var rowStatus = row.getAttribute('data-status');
      row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
    });
  };

  // ==================== SECTION NAVIGATION ====================
  window.scrollToSection = function (id, el) {
    var target = document.getElementById(id);
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('.stg-nav-item').forEach(function (item) { item.classList.remove('active'); });
    if (el) el.classList.add('active');
  };

})();
