(function () {
  'use strict';

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateCounters();

    var searchInput = document.getElementById('sliderSearch');
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

  /* Toplu seçim ve toplu işlemler assets/admin/js/bulk-actions.js dosyasında:
     aynı kod yedi listede kopyalanmıştı ve her biri kayıt başına ayrı bir
     istek atıyordu — elli kayıt elli istek, yarısı düşerse ortada karışık bir
     sonuç. İçerik listesinde ise hiç istek gitmiyordu. */

  /* ==================== DELETE MODAL ==================== */
  window.openDeleteModal = function (sliderId, sliderName) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu slider\'ı silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: sliderName,
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/admin/sliders/' + sliderId;
      form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
      document.body.appendChild(form);
      form.submit();
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
