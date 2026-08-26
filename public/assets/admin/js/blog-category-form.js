// ==================== BLOG CATEGORY FORM - JavaScript ====================

(function () {
  'use strict';



  // ==================== ICON PREVIEW ====================
  // Each language keeps its own icon, so the preview belongs to the input that
  // fired rather than to a single shared box.
  window.updateIconPreview = function (input) {
    var preview = document.getElementById('iconPreview_' + (input.id || '').replace('icon_', ''));

    if (!preview) {
      return;
    }

    var value = (input.value || '').trim();
    var icon = document.createElement('i');
    // Bootstrap Icons need the bi prefix; Font Awesome classes bring their own.
    icon.className = value ? (value.indexOf('bi-') === 0 ? 'bi ' + value : value) : 'bi bi-tag';

    preview.textContent = '';
    preview.appendChild(icon);
  };

})();
