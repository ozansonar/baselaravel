// ==================== BLOG CATEGORY FORM - JavaScript ====================

(function () {
  'use strict';

  // ==================== SECTION NAVIGATION ====================
  // Every language tab renders its own nav and its own section cards, so each
  // lookup stays inside the pane the user is actually looking at.

  function activePane() {
    return document.querySelector('.tab-pane.active') || document.body;
  }

  function navItemsOf(group) {
    return group ? Array.prototype.slice.call(group.querySelectorAll('.stg-nav-item')) : [];
  }

  window.scrollToSection = function (id, el) {
    var target = document.getElementById(id);
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    var group = el ? el.closest('.stg-nav-inner') : activePane().querySelector('.stg-nav-inner');
    navItemsOf(group).forEach(function (item) { item.classList.remove('active'); });

    if (el) {
      el.classList.add('active');
    }
  };

  // Positions are read from the viewport, so it does not matter which element
  // is doing the scrolling.
  function onScroll() {
    var items = navItemsOf(activePane().querySelector('.stg-nav-inner'));

    for (var i = items.length - 1; i >= 0; i--) {
      var section = document.getElementById((items[i].getAttribute('href') || '').slice(1));

      if (section && section.getBoundingClientRect().top <= 140) {
        items.forEach(function (item) { item.classList.remove('active'); });
        items[i].classList.add('active');
        break;
      }
    }
  }
  window.addEventListener('scroll', onScroll, true);


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
