'use strict';

/* ==========================================================================
   Laravel Base — Front theme switch (light / dark)

   The theme itself is applied by a tiny inline script in the layout head, so
   the page is painted in the right theme from the first frame. This file only
   owns the button: it wires the toggle, keeps every toggle on the page in
   sync, and follows the operating system until the visitor makes a choice of
   their own.

   Storage: localStorage['theme'] = 'light' | 'dark'.
   No entry means "follow the system", which is the default for a first visit.
   ========================================================================== */

(function () {
    var STORAGE_KEY = 'theme';
    var root = document.documentElement;

    /**
     * Reads the visitor's own choice. Private-mode browsers throw on access,
     * and a site that cannot remember a preference must still be able to
     * switch it, so a failure means "no choice stored".
     */
    function storedTheme() {
        try {
            var value = window.localStorage.getItem(STORAGE_KEY);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (e) {
            return null;
        }
    }

    function systemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function currentTheme() {
        return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    /**
     * Applies a theme and re-labels every toggle. The label names the action,
     * not the state: a button reading "Koyu tema" is understood as "switch to
     * dark", which is what a screen reader user needs to hear.
     */
    function applyTheme(theme) {
        root.setAttribute('data-bs-theme', theme);

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            var label = theme === 'dark'
                ? (button.dataset.labelLight || 'Light theme')
                : (button.dataset.labelDark || 'Dark theme');

            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);

            var text = button.querySelector('[data-theme-label]');
            if (text) text.textContent = label;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(currentTheme());

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var next = currentTheme() === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                try {
                    window.localStorage.setItem(STORAGE_KEY, next);
                } catch (e) { /* private mode: the switch still works for this page */ }
            });
        });
    });

    /* The system preference keeps driving the page until the visitor overrides
       it — someone whose laptop flips to dark at sunset expects the site to
       follow, unless they already told it otherwise. */
    if (window.matchMedia) {
        var query = window.matchMedia('(prefers-color-scheme: dark)');
        var onSystemChange = function (event) {
            if (storedTheme() !== null) return;
            applyTheme(event.matches ? 'dark' : 'light');
        };

        if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', onSystemChange);
        } else if (typeof query.addListener === 'function') {
            query.addListener(onSystemChange);
        }
    }
})();
