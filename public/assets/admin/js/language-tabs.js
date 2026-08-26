(function () {
    'use strict';

    // Remember which language tab was open so a failed save, or simply coming
    // back to the form, does not drop the translator on the first tab again.
    function initLanguageTabs() {
        document.querySelectorAll('[id$="ActiveLocale"]').forEach(function (input) {
            var listId = input.id.replace('ActiveLocale', '');
            var list = document.getElementById(listId);

            if (!list) {
                return;
            }

            list.querySelectorAll('.lang-tabs__btn').forEach(function (button) {
                button.addEventListener('shown.bs.tab', function () {
                    input.value = this.dataset.locale || '';
                });
            });

            // A tab holding a validation error takes precedence over the
            // remembered one; that is where the user needs to look.
            var invalid = list.querySelector('.lang-tabs__btn--invalid');

            if (invalid && typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getOrCreateInstance(invalid).show();
                input.value = invalid.dataset.locale || '';
            }
        });
    }

    // ==================== SAVE BUTTON LABELS ====================

    /**
     * Saving concerns the tab on screen, so the button says which language it
     * is about. "Kaydet" on its own reads like it saves everything.
     */
    function labelSpanOf(button) {
        var span = button.querySelector('.lang-save-label');

        if (span) {
            return span;
        }

        var text = '';

        Array.prototype.slice.call(button.childNodes).forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) {
                text += node.textContent;
                button.removeChild(node);
            }
        });

        span = document.createElement('span');
        span.className = 'lang-save-label';
        span.dataset.base = text.trim();
        button.appendChild(span);

        return span;
    }

    function languageNameOf(button) {
        var name = button.querySelector('span:not([class])');

        return name ? name.textContent.trim() : (button.dataset.locale || '').toUpperCase();
    }

    function labelSaveButtons(list) {
        var form = list.closest('form');
        var active = list.querySelector('.lang-tabs__btn.active');

        if (!form || !active) {
            return;
        }

        var language = languageNameOf(active);

        Array.prototype.filter.call(
            document.querySelectorAll('button[type="submit"], input[type="submit"]'),
            function (button) { return button.form === form; }
        ).forEach(function (button) {
            var span = labelSpanOf(button);
            span.textContent = span.dataset.base + ' · ' + language;
        });
    }

    function initSaveLabels() {
        document.querySelectorAll('.lang-tabs').forEach(function (list) {
            labelSaveButtons(list);

            list.querySelectorAll('.lang-tabs__btn').forEach(function (button) {
                button.addEventListener('shown.bs.tab', function () { labelSaveButtons(list); });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLanguageTabs();
        initSaveLabels();
    });
})();
