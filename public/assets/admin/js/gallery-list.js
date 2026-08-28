// ==================== GALLERY LIST PAGE - JavaScript ====================

(function () {
  'use strict';

  // ==================== BÜYÜTME ====================
  function lightboxKur() {
    if (typeof GLightbox === 'undefined' || !document.querySelector('.glightbox')) {
      return;
    }

    GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true });
  }


  // ==================== DELETE MODAL ====================
  window.openDeleteModal = function (title, id) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu galeri öğesini silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: title,
      warning: 'Bu işlem geri alınabilir.'
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
    lightboxKur();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
