(function () {
  'use strict';

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateCounters();

    var searchInput = document.getElementById('commentSearch');
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

  /* ==================== CONFIRM ACTION (APPROVE / REJECT / RESTORE) ==================== */
  window.confirmCommentAction = function (type, commentId, commentName) {
    var configs = {
      approve: {
        title: 'Onay',
        message: 'Bu yorumu onaylamak istediğinize emin misiniz?',
        type: 'success',
        confirmText: 'Evet, Onayla',
        confirmIcon: 'bi bi-check-lg',
        detailTitle: commentName
      },
      reject: {
        title: 'Reddetme Onayı',
        message: 'Bu yorumu reddetmek istediğinize emin misiniz?',
        type: 'warning',
        confirmText: 'Evet, Reddet',
        confirmIcon: 'bi bi-x-lg',
        detailTitle: commentName
      },
      restore: {
        title: 'Geri Yükleme Onayı',
        message: 'Bu yorumu geri yüklemek istediğinize emin misiniz?',
        type: 'success',
        confirmText: 'Evet, Geri Yükle',
        confirmIcon: 'bi bi-arrow-counterclockwise',
        detailTitle: commentName
      }
    };

    var config = configs[type];
    if (!config) return;

    AdminModal.confirm(config).then(function (confirmed) {
      if (!confirmed) return;
      var form = document.getElementById(type + 'Form-' + commentId);
      if (form) form.submit();
    });
  };

  /* ==================== DELETE MODAL ==================== */
  window.openDeleteModal = function (commentId, commentName) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu yorumu silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: commentName,
      // Yorumlar yumuşak siliniyor; "geri alınamaz" demek yanlıştı.
      warning: 'Silinen yorum "Silinmiş" sekmesinden geri alınabilir.'
    }).then(function (confirmed) {
      if (!confirmed) return;

      // Sayfada hazır form varsa (detay ekranı) o kullanılıyor; listede
      // satır başına form açmamak için gerektiğinde kuruluyor.
      var form = document.getElementById('deleteForm-' + commentId);

      if (!form) {
        form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/blog-comments/' + commentId;
        form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
        document.body.appendChild(form);
      }

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
