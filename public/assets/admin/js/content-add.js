// ==================== CONTENT ADD PAGE - JavaScript ====================

(function () {
  'use strict';



  // ==================== CHAR COUNTER ====================
  window.updateCharCounter = function (input, maxLen) {
    var counter = document.getElementById(input.id + '-counter');
    if (!counter) return;
    var len = input.value.length;
    counter.textContent = len;
  };


  // Slug üretimi ve anlık düzeltme slug.js'e taşındı: burada ve
  // page-form.js'te iki ayrı kopyası vardı, ikisi de dil sekmeleri geldikten
  // sonra var olmayan #slug alanını arıyordu.


  // ==================== SEO PREVIEW ====================
  window.updateSeoPreview = function () {
    var titleField = document.getElementById('title');
    var metaTitleField = document.getElementById('meta_title');
    var metaDescField = document.getElementById('meta_description');
    var slugField = document.getElementById('slug');

    var seoTitle = document.getElementById('seoPreviewTitle');
    var seoDesc = document.getElementById('seoPreviewDesc');
    var seoSlug = document.getElementById('seoPreviewSlug');

    if (seoTitle) {
      var displayTitle = '';
      if (metaTitleField && metaTitleField.value.trim()) {
        displayTitle = metaTitleField.value.trim();
      } else if (titleField && titleField.value.trim()) {
        displayTitle = titleField.value.trim();
      }
      seoTitle.textContent = displayTitle || 'İçerik Başlığı Buraya Gelecek';
    }

    if (seoDesc && metaDescField) {
      seoDesc.textContent = metaDescField.value.trim() || 'İçeriğinizin meta açıklaması burada görünecek.';
    }

    if (seoSlug && slugField) {
      seoSlug.textContent = slugField.value.trim() || 'yeni-icerik';
    }
  };

})();
