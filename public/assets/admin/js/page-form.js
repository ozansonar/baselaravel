(function () {
  'use strict';

  /* Slug üretimi ve anlık düzeltme slug.js'e taşındı — bkz. content-add.js */

  /* ==================== CHARACTER COUNTER ==================== */
  window.updateCharCounter = function (input, max) {
    var counter = document.getElementById(input.id + '-counter');
    if (!counter) return;
    var len = input.value.length;
    counter.textContent = len;
    if (len > max) {
      counter.classList.add('text-danger');
    } else {
      counter.classList.remove('text-danger');
    }
  };

  /* ==================== SEO PREVIEW ==================== */
  window.updateSeoPreview = function () {
    var title = document.getElementById('meta_title');
    var desc = document.getElementById('meta_description');
    var slug = document.getElementById('slug');
    var pageTitle = document.getElementById('title');

    var previewTitle = document.getElementById('seoPreviewTitle');
    var previewDesc = document.getElementById('seoPreviewDesc');
    var previewSlug = document.getElementById('seoPreviewSlug');

    if (previewTitle) {
      previewTitle.textContent = (title && title.value) ? title.value : (pageTitle ? pageTitle.value || 'Sayfa Başlığı' : 'Sayfa Başlığı');
    }
    if (previewDesc) {
      previewDesc.textContent = (desc && desc.value) ? desc.value : 'Sayfanızın meta açıklaması burada görünecek.';
    }
    if (previewSlug && slug) {
      previewSlug.textContent = slug.value || 'sayfa-url';
    }
  };

  /* ==================== SCROLL SPY ==================== */
  function updateActiveNav() {
    var sections = document.querySelectorAll('.card-dark[id^="section-"]');
    var navItems = document.querySelectorAll('.stg-nav-item');
    if (sections.length === 0 || navItems.length === 0) return;

    var scrollPos = window.pageYOffset + 80;
    var activeIndex = 0;

    sections.forEach(function (section, index) {
      if (section.offsetTop <= scrollPos) {
        activeIndex = index;
      }
    });

    navItems.forEach(function (item, index) {
      if (index === activeIndex) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });
  }

  /* ==================== INIT ==================== */
  function init() {
    // Initialize char counters for existing values
    var titleInput = document.getElementById('title');
    var excerptInput = document.getElementById('excerpt');
    var metaTitleInput = document.getElementById('meta_title');
    var metaDescInput = document.getElementById('meta_description');

    if (titleInput && titleInput.value) updateCharCounter(titleInput, 120);
    if (excerptInput && excerptInput.value) updateCharCounter(excerptInput, 300);
    if (metaTitleInput && metaTitleInput.value) updateCharCounter(metaTitleInput, 60);
    if (metaDescInput && metaDescInput.value) updateCharCounter(metaDescInput, 160);

    // Scroll spy
    var scrollTimer = null;
    window.addEventListener('scroll', function () {
      if (scrollTimer) clearTimeout(scrollTimer);
      scrollTimer = setTimeout(updateActiveNav, 50);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
