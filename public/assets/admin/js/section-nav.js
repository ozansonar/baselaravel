// ==================== SECTION NAVIGATION ====================
//
// The quick jump menu next to a long form. Five page scripts each carried
// their own copy of this, and the pages that need it most — popups, gallery
// items, sliders — loaded none of them, so clicking a nav item threw
// "scrollToSection is not defined". It lives here now and the layout loads it
// for every admin page.
//
// Multilingual forms render one nav and one set of section cards per language
// tab, so every lookup stays inside the tab the user is actually looking at.

(function () {
  'use strict';

  function activePane() {
    return document.querySelector('.tab-pane.active') || document.body;
  }

  function navItemsOf(group) {
    return group ? Array.prototype.slice.call(group.querySelectorAll('.stg-nav-item')) : [];
  }

  /** The nav group the clicked item belongs to, or the visible one. */
  function groupFor(el) {
    if (el && el.closest) {
      var own = el.closest('.stg-nav-inner, .stg-nav');
      if (own) {
        return own;
      }
    }

    return activePane().querySelector('.stg-nav-inner, .stg-nav');
  }

  /**
   * Bölümü bulur.
   *
   * Çok dilli formlarda bölüm kimlikleri dil kodunu taşıyor
   * (section-basic_tr) ama sayfa formundaki gezinme tek ve dilsiz: dilsiz
   * kimlik hiçbir öğeyle eşleşmediği için tıklama sessizce hiçbir şey
   * yapmıyordu. Doğrudan eşleşme yoksa ekrandaki sekmenin karşılığı aranıyor.
   */
  function resolveTarget(id) {
    return document.getElementById(id)
      || activePane().querySelector('[id="' + id + '"], [id^="' + id + '_"]');
  }

  window.scrollToSection = function (id, el) {
    var target = resolveTarget(id);

    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    navItemsOf(groupFor(el)).forEach(function (item) { item.classList.remove('active'); });

    if (el) {
      el.classList.add('active');
    }
  };

  // Highlight the section being read. Positions come from the viewport, so it
  // does not matter which element is doing the scrolling.
  function onScroll() {
    var items = navItemsOf(groupFor(null));

    for (var i = items.length - 1; i >= 0; i--) {
      var href = items[i].getAttribute('href') || '';
      var section = href.charAt(0) === '#' ? resolveTarget(href.slice(1)) : null;

      if (section && section.getBoundingClientRect().top <= 140) {
        items.forEach(function (item) { item.classList.remove('active'); });
        items[i].classList.add('active');
        break;
      }
    }
  }

  window.addEventListener('scroll', onScroll, true);
})();
