(function () {
  'use strict';

  /* Slug üretimi ve anlık düzeltme slug.js'e taşındı — bkz. content-add.js */

  /* Karakter sayacı ve SEO önizlemesi content-form.js'e taşındı
     — bkz. content-add.js */

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
