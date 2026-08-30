/**
 * Global Modal System
 *
 * Usage:
 *   AdminModal.confirm({ ... })   → Confirm dialog (returns Promise<boolean>)
 *   AdminModal.status({ ... })    → Status/alert dialog
 *
 * Confirm options:
 *   title        : string (required)
 *   message      : string (required)
 *   type         : 'danger'|'warning'|'info'|'success' (default: 'danger')
 *   confirmText  : string (default: 'Evet, Sil')
 *   cancelText   : string (default: 'Vazgeç')
 *   confirmIcon  : string BS icon class (default: 'bi bi-trash3')
 *   detailTitle  : string (optional, shows detail box)
 *   detailMeta   : string (optional)
 *   warning      : string (optional, shows warning strip)
 *
 * Status options:
 *   title        : string (required)
 *   message      : string (required)
 *   type         : 'success'|'danger'|'warning'|'info' (default: 'success')
 *   buttonText   : string (default: 'Tamam')
 */
;(function () {
  'use strict';

  var confirmModal  = null;
  var statusModal   = null;
  var loadingModal  = null;

  /**
   * Metni düz metne indirger: etiketleri atar, varlık imlerini çözer.
   *
   * Kutulara metin textContent ile yazılıyor, yani içindeki <strong> ekranda
   * etiket olarak görünüyordu: "<strong>Bahar Kampanyası</strong> popup'ını
   * silmek istediğinizden emin misiniz?". innerHTML'e geçmek çözüm değil —
   * mesajlarda kullanıcının girdiği içerik (başlık, dosya adı) var.
   *
   * Etiketler önce düzenli ifadeyle atılıyor, sonra kalan varlık imleri
   * textarea üzerinden çözülüyor: textarea'nın içeriği HTML olarak
   * ayrıştırılmaz, yani &quot; çözülürken yeni bir eleman doğmaz.
   */
  function plain(value) {
    if (value === null || value === undefined) return '';

    var stripped = String(value).replace(/<[^>]*>/g, '');

    if (stripped.indexOf('&') === -1) return stripped;

    var holder = document.createElement('textarea');
    holder.innerHTML = stripped;

    return holder.value;
  }

  /* ==================== TYPE CONFIG ==================== */
  var typeConfig = {
    danger:  { icon: 'bi bi-trash3',                    color: 'red',    statusIcon: 'bi bi-x-lg' },
    warning: { icon: 'bi bi-exclamation-triangle-fill',  color: 'orange', statusIcon: 'bi bi-exclamation-lg' },
    info:    { icon: 'bi bi-info-circle-fill',           color: 'blue',   statusIcon: 'bi bi-info-lg' },
    success: { icon: 'bi bi-check-circle-fill',          color: 'green',  statusIcon: 'bi bi-check-lg' }
  };

  /* ==================== CONFIRM ==================== */
  function showConfirm(options) {
    var opts = Object.assign({
      title: 'Emin misiniz?',
      message: 'Bu işlemi gerçekleştirmek istediğinize emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      cancelText: 'Vazgeç',
      confirmIcon: null,
      detailTitle: null,
      detailMeta: null,
      warning: null
    }, options);

    var cfg = typeConfig[opts.type] || typeConfig.danger;

    // Icon wrapper
    var iconWrapper = document.getElementById('gcm-icon-wrapper');
    iconWrapper.className = 'confirm-status-icon confirm-status-' + cfg.color;

    var icon = document.getElementById('gcm-icon');
    icon.className = 'confirm-icon-animated ' + (opts.confirmIcon || cfg.icon);

    var ring = document.getElementById('gcm-ring');
    ring.className = 'confirm-icon-ring confirm-ring-' + cfg.color;

    // Texts
    document.getElementById('gcm-title').textContent = plain(opts.title);
    document.getElementById('gcm-message').textContent = plain(opts.message);
    document.getElementById('gcm-cancel-text').textContent = plain(opts.cancelText);
    document.getElementById('gcm-confirm-text').textContent = plain(opts.confirmText);

    // Confirm button icon
    var confirmIconEl = document.getElementById('gcm-confirm-icon');
    confirmIconEl.className = opts.confirmIcon || cfg.icon;

    // Confirm button style
    var confirmBtn = document.getElementById('gcm-confirm-btn');
    confirmBtn.className = 'btn-danger-custom';
    if (opts.type === 'warning') confirmBtn.classList.add('btn-orange-gradient');
    if (opts.type === 'success') confirmBtn.classList.add('btn-success-gradient');
    if (opts.type === 'info')    confirmBtn.classList.add('btn-info-gradient');

    // Detail box
    var detailBox = document.getElementById('gcm-detail');
    if (opts.detailTitle) {
      detailBox.classList.remove('d-none');
      document.getElementById('gcm-detail-title').textContent = plain(opts.detailTitle);
      document.getElementById('gcm-detail-meta').textContent = plain(opts.detailMeta);
      var detailIconEl = document.getElementById('gcm-detail-icon');
      detailIconEl.className = 'confirm-detail-icon confirm-detail-icon-' + cfg.color;
      document.getElementById('gcm-detail-icon-i').className = cfg.icon;
    } else {
      detailBox.classList.add('d-none');
    }

    // Warning strip
    var warningEl = document.getElementById('gcm-warning');
    if (opts.warning) {
      warningEl.classList.remove('d-none');
      document.getElementById('gcm-warning-text').textContent = plain(opts.warning);
    } else {
      warningEl.classList.add('d-none');
    }

    // Show modal + return promise
    return new Promise(function (resolve) {
      if (!confirmModal) {
        confirmModal = new bootstrap.Modal(document.getElementById('globalConfirmModal'));
      }

      var confirmBtnEl = document.getElementById('gcm-confirm-btn');
      var modalEl = document.getElementById('globalConfirmModal');

      // Clean up old listeners
      var newBtn = confirmBtnEl.cloneNode(true);
      confirmBtnEl.parentNode.replaceChild(newBtn, confirmBtnEl);

      var resolved = false;

      newBtn.addEventListener('click', function () {
        resolved = true;
        confirmModal.hide();
        resolve(true);
      });

      modalEl.addEventListener('hidden.bs.modal', function handler() {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        if (!resolved) resolve(false);
      });

      confirmModal.show();
    });
  }

  /* ==================== STATUS ==================== */
  function showStatus(options) {
    var opts = Object.assign({
      title: 'İşlem Başarılı',
      message: '',
      type: 'success',
      buttonText: 'Tamam'
    }, options);

    var cfg = typeConfig[opts.type] || typeConfig.success;

    // Icon
    var iconWrapper = document.getElementById('gsm-icon-wrapper');
    iconWrapper.className = 'status-modal-icon ' + opts.type;

    var icon = document.getElementById('gsm-icon');
    icon.className = cfg.statusIcon;

    // Texts
    document.getElementById('gsm-title').textContent = plain(opts.title);
    document.getElementById('gsm-message').textContent = plain(opts.message);
    document.getElementById('gsm-close-text').textContent = plain(opts.buttonText);

    // Button style
    var closeBtn = document.getElementById('gsm-close-btn');
    closeBtn.className = 'btn-teal';
    if (opts.type === 'danger')  closeBtn.classList.add('avatar-gradient-red');
    if (opts.type === 'warning') closeBtn.classList.add('btn-warning-gradient');
    if (opts.type === 'info')    closeBtn.classList.add('btn-info-gradient');

    if (!statusModal) {
      statusModal = new bootstrap.Modal(document.getElementById('globalStatusModal'));
    }

    return new Promise(function (resolve) {
      var modalEl = document.getElementById('globalStatusModal');
      modalEl.addEventListener('hidden.bs.modal', function handler() {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        resolve();
      });
      statusModal.show();
    });
  }

  /* ==================== AI LOADING ==================== */
  function showLoading(options) {
    var opts = Object.assign({
      title: 'AI Üretiyor...',
      message: 'İçerik oluşturuluyor, lütfen bekleyin.'
    }, options);

    document.getElementById('galm-title').textContent   = opts.title;
    document.getElementById('galm-message').textContent = opts.message;

    if (!loadingModal) {
      loadingModal = new bootstrap.Modal(document.getElementById('globalAiLoadingModal'));
    }
    loadingModal.show();
  }

  function hideLoading() {
    if (loadingModal) {
      loadingModal.hide();
    }
  }

  /* ==================== PUBLIC API ==================== */
  window.AdminModal = {
    confirm: showConfirm,
    status: showStatus,
    loading: { show: showLoading, hide: hideLoading }
  };

})();
