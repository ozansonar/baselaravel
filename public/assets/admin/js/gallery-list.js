// ==================== GALLERY LIST PAGE - JavaScript ====================

(function () {
  'use strict';

  // ==================== TOPLU SEÇİM ====================
  //
  // Seçim kutuları listenin içinde, gönderilecek formlar dışında duruyor
  // (iç içe form olmasın diye). Seçilen kimlikleri gönderim anında forma
  // buradan yazıyoruz. Tablo ve ızgara görünümü aynı sınıfı kullandığı için
  // aşağıdaki kodun hangi görünümde olduğumuzu bilmesi gerekmiyor.

  function secilenler() {
    return Array.prototype.slice.call(document.querySelectorAll('.gallery-checkbox:checked'));
  }

  window.toggleSelectAll = function (checkbox) {
    document.querySelectorAll('.gallery-checkbox').forEach(function (cb) {
      cb.checked = checkbox.checked;
    });

    updateBulk();
  };

  window.updateBulk = function () {
    var secili = secilenler().length;
    var cubuk = document.getElementById('bulkActions');
    var sayac = document.getElementById('selectedCount');

    if (!cubuk || !sayac) {
      return;
    }

    cubuk.classList.toggle('d-none', secili === 0);
    sayac.textContent = secili;

    // Başlıktaki kutu listenin durumunu anlatıyor: hepsi seçiliyse dolu,
    // bir kısmı seçiliyse belirsiz (üçüncü hâl), hiçbiri seçili değilse boş.
    var hepsi = document.getElementById('selectAll');

    if (hepsi) {
      var toplam = document.querySelectorAll('.gallery-checkbox').length;
      hepsi.checked = toplam > 0 && secili === toplam;
      hepsi.indeterminate = secili > 0 && secili < toplam;
    }
  };

  window.clearGallerySelection = function () {
    document.querySelectorAll('.gallery-checkbox').forEach(function (cb) { cb.checked = false; });
    updateBulk();
  };

  /** Seçilen kimlikleri gizli forma yazar ve formu döndürür. */
  function formuDoldur(formId) {
    var form = document.getElementById(formId);

    if (!form) {
      return null;
    }

    // Önceki turdan kalan alanlar temizleniyor: aynı sayfada iki kez
    // gönderilirse kimlikler birikirdi.
    form.querySelectorAll('input[name="ids[]"]').forEach(function (el) { el.remove(); });

    secilenler().forEach(function (cb) {
      var alan = document.createElement('input');
      alan.type = 'hidden';
      alan.name = 'ids[]';
      alan.value = cb.value;
      form.appendChild(alan);
    });

    return form;
  }

  window.bulkGalleryAction = function (action) {
    var secili = secilenler();

    if (secili.length === 0) {
      return;
    }

    if (action === 'delete') {
      document.getElementById('bulkDeleteCount').textContent = secili.length;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkDeleteModal')).show();

      return;
    }

    if (action === 'restore') {
      AdminModal.confirm({
        title: 'Toplu Geri Yükleme',
        message: secili.length + ' galeri öğesini geri yüklemek istediğinize emin misiniz?',
        type: 'warning',
        confirmText: 'Evet, Geri Yükle',
        confirmIcon: 'bi bi-arrow-counterclockwise'
      }).then(function (onay) {
        if (!onay) {
          return;
        }

        var form = formuDoldur('bulkRestoreForm');
        if (form) form.submit();
      });
    }
  };

  window.confirmBulkDelete = function () {
    var form = formuDoldur('bulkDeleteForm');
    if (form) form.submit();
  };


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
    updateBulk();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
