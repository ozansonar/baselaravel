(function () {
  'use strict';

  /* ==================== ICON PREVIEW ==================== */
  window.updateIconPreview = function (value) {
    var preview = document.getElementById('iconPreview');
    if (!preview) return;
    var iconClass = value && value.trim() ? value.trim() : 'bi-tag';
    preview.innerHTML = '<i class="bi ' + iconClass + '"></i>';
  };

})();
