(function () {
  'use strict';

  /* ==================== SLUG GENERATION ==================== */
  var trMap = {
    'ç': 'c', 'Ç': 'c', 'ğ': 'g', 'Ğ': 'g', 'ı': 'i', 'İ': 'i',
    'ö': 'o', 'Ö': 'o', 'ş': 's', 'Ş': 's', 'ü': 'u', 'Ü': 'u'
  };

  window.generateSlug = function (value) {
    var slug = value.toLowerCase();
    Object.keys(trMap).forEach(function (key) {
      slug = slug.replace(new RegExp(key, 'g'), trMap[key]);
    });
    slug = slug
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');

    var slugInput = document.getElementById('slug');
    if (slugInput) slugInput.value = slug;

    updateSeoPreview();
  };

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

  /* ==================== SECTION NAVIGATION ==================== */
  window.scrollToSection = function (sectionId, navItem) {
    var section = document.getElementById(sectionId);
    if (!section) return;

    var offset = 20;
    var top = section.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top: top, behavior: 'smooth' });

    if (navItem) {
      document.querySelectorAll('.stg-nav-item').forEach(function (item) {
        item.classList.remove('active');
      });
      navItem.classList.add('active');
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
