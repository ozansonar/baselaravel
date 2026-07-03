// ==================== CITY PAGE - Form Helpers ====================

(function () {
    'use strict';

    // ── Section Navigation ──
    window.scrollToSection = window.scrollToSection || function (id, el) {
        var target = document.getElementById(id);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        document.querySelectorAll('.stg-nav-item').forEach(function (item) { item.classList.remove('active'); });
        if (el) el.classList.add('active');
    };

    var sections = ['section-basic', 'section-hero', 'section-content', 'section-shipping', 'section-seo', 'section-publish'];
    var navItems = document.querySelectorAll('.stg-nav-item');

    function onScroll() {
        var scrollPos = window.scrollY + 140;
        for (var i = sections.length - 1; i >= 0; i--) {
            var el = document.getElementById(sections[i]);
            if (el && el.offsetTop <= scrollPos) {
                navItems.forEach(function (n) { n.classList.remove('active'); });
                if (navItems[i]) navItems[i].classList.add('active');
                break;
            }
        }
    }
    window.addEventListener('scroll', onScroll);

    // ── Char Counter ──
    window.updateCharCounter = window.updateCharCounter || function (input, maxLen) {
        var counter = document.getElementById(input.id + '-counter');
        if (!counter) return;
        counter.textContent = input.value.length;
    };

    // ── Slug Generation (Turkish-aware) ──
    var slugManuallyEdited = false;
    var slugField = document.getElementById('city_slug');

    if (slugField) {
        slugField.addEventListener('input', function () {
            slugManuallyEdited = true;
        });
        if (slugField.value.trim() !== '') {
            slugManuallyEdited = true;
        }
    }

    window.generateCitySlug = function (cityName) {
        if (slugManuallyEdited) return;
        if (!slugField) return;

        var charMap = {
            'ç': 'c', 'Ç': 'c', 'ğ': 'g', 'Ğ': 'g', 'ı': 'i', 'İ': 'i',
            'ö': 'o', 'Ö': 'o', 'ş': 's', 'Ş': 's', 'ü': 'u', 'Ü': 'u'
        };

        var slug = (cityName || '').toLowerCase();
        for (var key in charMap) {
            slug = slug.split(key).join(charMap[key]);
        }
        slug = slug
            .replace(/[^a-z0-9\s\-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');

        slugField.value = slug;

        var seoSlug = document.getElementById('seoPreviewSlug');
        if (seoSlug) seoSlug.textContent = slug || 'sehir';
    };

    // ── SEO Preview ──
    window.updateSeoPreview = function () {
        var titleField = document.getElementById('title');
        var metaTitleField = document.getElementById('meta_title');
        var metaDescField = document.getElementById('meta_description');
        var slug = (slugField && slugField.value) ? slugField.value : 'sehir';

        var seoSlug = document.getElementById('seoPreviewSlug');
        var seoTitle = document.getElementById('seoPreviewTitle');
        var seoDesc = document.getElementById('seoPreviewDesc');

        if (seoSlug) seoSlug.textContent = slug;

        if (seoTitle) {
            var t = (metaTitleField && metaTitleField.value) ? metaTitleField.value : (titleField ? titleField.value : '');
            seoTitle.textContent = t || 'Şehir Köy Ürünleri';
        }

        if (seoDesc) {
            var d = metaDescField ? metaDescField.value : '';
            seoDesc.textContent = d || 'Şehre özel meta açıklama burada görünecek.';
        }
    };
})();
