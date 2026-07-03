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

  /* ==================== SELECT ALL / BULK ==================== */
  window.toggleSelectAll = function (master) {
    document.querySelectorAll('#commentsTableBody .comment-checkbox').forEach(function (cb) {
      cb.checked = master.checked;
    });
    updateBulk();
  };

  window.updateBulk = function () {
    var checked = document.querySelectorAll('#commentsTableBody .comment-checkbox:checked').length;
    var bulk    = document.getElementById('bulkActions');
    var counter = document.getElementById('selectedCount');
    if (checked > 0) {
      bulk.classList.remove('d-none');
      counter.textContent = checked;
    } else {
      bulk.classList.add('d-none');
    }
    var all   = document.getElementById('selectAll');
    var total = document.querySelectorAll('#commentsTableBody .comment-checkbox').length;
    all.indeterminate = checked > 0 && checked < total;
    all.checked       = checked === total && total > 0;
  };

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
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/admin/blog-comments/' + commentId;
      form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
      document.body.appendChild(form);
      form.submit();
    });
  };

  /* ==================== BULK DELETE ==================== */
  window.openBulkDeleteModal = function () {
    var checked = document.querySelectorAll('#commentsTableBody .comment-checkbox:checked');
    if (checked.length === 0) {
      showToast('Lütfen yorum seçin', 'warning');
      return;
    }

    AdminModal.confirm({
      title: 'Toplu Silme Onayı',
      message: checked.length + ' yorumu silmek istediğinize emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;

      var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      var completed = 0;
      var ids = [];
      checked.forEach(function (cb) { ids.push(cb.value); });

      ids.forEach(function (id) {
        fetch('/admin/blog-comments/' + id, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
          }
        }).then(function () {
          completed++;
          if (completed === ids.length) {
            showToast(ids.length + ' yorum silindi', 'success');
            setTimeout(function () { location.reload(); }, 800);
          }
        }).catch(function () {
          completed++;
        });
      });
    });
  };

  /* ==================== BULK APPROVE ==================== */
  window.openBulkApproveModal = function () {
    var checked = document.querySelectorAll('#commentsTableBody .comment-checkbox:checked');
    if (checked.length === 0) {
      showToast('Lütfen yorum seçin', 'warning');
      return;
    }

    AdminModal.confirm({
      title: 'Toplu Onay',
      message: checked.length + ' yorumu onaylamak istediğinize emin misiniz?',
      type: 'success',
      confirmText: 'Evet, Onayla',
      confirmIcon: 'bi bi-check-lg'
    }).then(function (confirmed) {
      if (!confirmed) return;

      var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      var completed = 0;
      var ids = [];
      checked.forEach(function (cb) { ids.push(cb.value); });

      ids.forEach(function (id) {
        fetch('/admin/blog-comments/' + id + '/approve', {
          method: 'PATCH',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
          }
        }).then(function () {
          completed++;
          if (completed === ids.length) {
            showToast(ids.length + ' yorum onaylandı', 'success');
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
