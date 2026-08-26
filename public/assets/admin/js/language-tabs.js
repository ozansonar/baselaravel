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

    // ==================== CLEARING A LANGUAGE ====================

    /** Puts one language tab back to the state it had before anyone typed. */
    function clearPane(pane) {
        pane.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.type === 'hidden') {
                return;
            }

            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = false;

                return;
            }

            if (field.tagName === 'SELECT') {
                field.value = field.options.length ? field.options[0].value : '';

                return;
            }

            // A sort order goes back to its default rather than to nothing.
            field.value = field.dataset.fvDefault !== undefined ? field.dataset.fvDefault : '';
        });

        // Rich text editors keep their own copy of the content.
        if (window.tinymce) {
            pane.querySelectorAll('textarea').forEach(function (textarea) {
                var editor = window.tinymce.get(textarea.id);

                if (editor) {
                    editor.setContent('');
                }
            });
        }

        // Findings about fields that no longer hold anything.
        pane.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        pane.querySelectorAll('.formError').forEach(function (el) { el.remove(); });
    }

    function activePaneOf(listId) {
        var list = document.getElementById(listId);
        var active = list ? list.querySelector('.lang-tabs__btn.active') : null;
        var target = active ? active.getAttribute('data-bs-target') : null;

        return target ? document.querySelector(target) : null;
    }

    function initClearButtons() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-lang-clear]');

            if (!button) {
                return;
            }

            var listId = button.getAttribute('data-lang-clear');
            var pane = activePaneOf(listId);

            if (!pane || typeof AdminModal === 'undefined') {
                return;
            }

            // The tab holds a flag, a name and sometimes a badge; the plain
            // span is the language name.
            var active = document.getElementById(listId).querySelector('.lang-tabs__btn.active');
            var name = active.querySelector('span:not([class])');
            var label = name ? name.textContent.trim() : (active.dataset.locale || '').toUpperCase();

            AdminModal.confirm({
                title: 'Bu dili temizle',
                message: label + ' sekmesindeki bütün girdiler silinecek. Diğer diller etkilenmez.',
                type: 'warning',
                confirmText: 'Evet, temizle',
                confirmIcon: 'bi bi-eraser'
            }).then(function (confirmed) {
                if (confirmed) {
                    clearPane(pane);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLanguageTabs();
        initClearButtons();
    });
})();
