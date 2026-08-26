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


  // ==================== SLUG GENERATION ====================
  var slugManuallyEdited = false;
  var slugField = document.getElementById('slug');
  if (slugField) {
    slugField.addEventListener('input', function () {
      slugManuallyEdited = true;
    });
    // If slug already has value (edit mode), mark as manually edited
    if (slugField.value.trim() !== '') {
      slugManuallyEdited = true;
    }
  }

  window.generateSlug = function (title) {
    if (slugManuallyEdited) return;
    if (!slugField) return;

    var charMap = {
      'ç': 'c', 'Ç': 'c', 'ğ': 'g', 'Ğ': 'g', 'ı': 'i', 'İ': 'i',
      'ö': 'o', 'Ö': 'o', 'ş': 's', 'Ş': 's', 'ü': 'u', 'Ü': 'u'
    };

    var slug = title.toLowerCase();
    for (var key in charMap) {
      slug = slug.split(key).join(charMap[key]);
    }
    slug = slug
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');

    slugField.value = slug;

    // Update SEO preview slug
    var seoSlug = document.getElementById('seoPreviewSlug');
    if (seoSlug) seoSlug.textContent = slug || 'yeni-icerik';
  };


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
