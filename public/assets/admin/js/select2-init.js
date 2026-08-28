/**
 * Select2 4.1.0 across the whole panel.
 *
 * Every <select> in the admin becomes a Select2 without the page having to ask
 * for it, so a new form gets search, keyboard handling and the same look as the
 * rest without repeating an init call. The search box is on for every list,
 * however short. A select that must stay native opts out with data-no-select2;
 * one that wants no search box says data-select2-search="never".
 *
 * The Bootstrap 5 theme handles the shape; the panel's own tokens (colours,
 * radius, focus ring) are laid over it in styles.css so it matches the dark and
 * the light theme alike.
 *
 * Notes on the two traps this file exists to avoid:
 *   - A select inside a modal needs the modal as its dropdown parent, or the
 *     dropdown renders behind the backdrop and its search box cannot be typed in.
 *   - A select inside a hidden language tab measures zero, so width is set to
 *     100% rather than to the copied element width.
 */
(function ($) {
    'use strict';

    if (!$ || !$.fn || !$.fn.select2) {
        return;
    }

    var SKIP_SELECTOR = [
        '[data-no-select2]',
        '.select2-hidden-accessible',
        '.no-select2',
        '.dropzone select',
        '.tox select'
    ].join(', ');

    /**
     * Toolbar filters are sized by their own CSS (min-width, auto), so
     * stretching them to the width of their flex row would redraw the toolbar.
     */
    var INLINE_SELECTOR = [
        '.cl-filter-select',
        '.msg-sort-select',
        '.sort-select',
        'select[name="per_page"]',
        'select[id="perPage"]',
        'select[id="perPageSelect"]'
    ].join(', ');

    var AdminSelect2 = {
        defaults: {
            theme: 'bootstrap-5',
            width: '100%',
            language: (document.documentElement.lang || 'tr').slice(0, 2),
            // Arama kutusu her listede açık. Önce yalnız sekiz seçenekten uzun
            // listelerde çıkıyordu; aynı görünen iki açılır listeden birinde
            // yazılabilip ötekinde yazılamaması, kısa listede de kullanıcıyı
            // klavyeden fareye geçmeye zorluyordu. Tek tek "always" demek
            // zorunda kalan alanlar da bunu söylüyordu.
            // Tek tek kapatmak gerekirse: data-select2-search="never".
            minimumResultsForSearch: 0
        },

        /**
         * The empty first option is the placeholder — the same "— Seçiniz —"
         * the native select showed, so nothing about the form reads differently.
         */
        placeholderOf: function ($select) {
            if ($select.data('placeholder')) {
                return String($select.data('placeholder'));
            }

            var first = $select.find('option').first();

            if (first.length && first.attr('value') === '') {
                return first.text().trim();
            }

            return $select.prop('multiple') ? '' : null;
        },

        /**
         * A required field keeps its value once chosen: offering a clear button
         * would only let the visitor put the form back into an invalid state.
         */
        isRequired: function ($select) {
            var rules = $select.attr('data-validation-engine') || '';

            return $select.prop('required') || rules.indexOf('required') !== -1;
        },

        optionsFor: function ($select) {
            var options = $.extend({}, AdminSelect2.defaults);
            var placeholder = AdminSelect2.placeholderOf($select);
            var $modal = $select.closest('.modal');

            if (placeholder !== null && placeholder !== '') {
                options.placeholder = placeholder;
                options.allowClear = !AdminSelect2.isRequired($select) && !$select.prop('multiple');
            }

            if ($modal.length) {
                options.dropdownParent = $modal;
            }

            if ($select.data('tags')) {
                options.tags = true;
            }

            if ($select.data('close-on-select') === false) {
                options.closeOnSelect = false;
            }

            options.width = $select.data('select2-width')
                || ($select.is(INLINE_SELECTOR) ? 'auto' : '100%');

            // Arama artık varsayılan; nitelik yalnızca kapatmak için var.
            // "always" da kabul ediliyor: varsayılanı yineliyor, kırmıyor.
            var search = $select.data('select2-search');

            if (search === 'never') {
                options.minimumResultsForSearch = Infinity;
            } else if (search === 'always') {
                options.minimumResultsForSearch = 0;
            }

            return options;
        },

        apply: function (root) {
            $(root || document).find('select').not(SKIP_SELECTOR).each(function () {
                var $select = $(this);

                if ($select.data('select2')) {
                    return;
                }

                $select.select2(AdminSelect2.optionsFor($select));
            });
        },

        /**
         * Rebuild after the options themselves changed (a dependent dropdown
         * refilled by fetch, a row cloned into a repeater).
         */
        refresh: function (root) {
            $(root || document).find('select.select2-hidden-accessible').each(function () {
                var $select = $(this);

                $select.select2('destroy');
                $select.select2(AdminSelect2.optionsFor($select));
            });
        }
    };

    /**
     * The prompt the validation engine builds for a field carries that field's
     * id in its class name.
     */
    function promptOf($select) {
        var id = $select.attr('id');

        if (id && $.escapeSelector) {
            return $('.' + $.escapeSelector(id) + 'formError');
        }

        return $select.parent().find('.formError');
    }

    /**
     * The engine inserts its message directly after the field it checked, which
     * with Select2 means between the hidden select and the box the visitor
     * actually sees — the message ends up above the field while every other
     * field shows it below, and the invalid-border rule loses the adjacency it
     * needs. Both are fixed by putting the message back after the box.
     */
    function movePromptsBelowSelect2(root) {
        $(root || document).find('.formError').each(function () {
            var $prompt = $(this);
            var $container = $prompt.next('.select2-container');

            if ($container.length) {
                $container.after($prompt);
            }
        });
    }

    // Without this the error stays on screen after the visitor picks a value.
    $(document).on('change select2:select select2:clear', 'select.select2-hidden-accessible', function () {
        var $select = $(this);

        if (!$select.closest('form').is('[data-validate]')) {
            return;
        }

        if ($select.val() !== null && $select.val() !== '') {
            promptOf($select).remove();
            $select.removeClass('is-invalid');
        }
    });

    $(document).on('jqv.field.result', 'select.select2-hidden-accessible', function () {
        window.setTimeout(movePromptsBelowSelect2, 0);
    });

    // A Select2 inside a modal is built while the modal is still hidden, so it
    // is measured again once the modal has its real width.
    $(document).on('shown.bs.modal', '.modal', function () {
        AdminSelect2.apply(this);
    });

    $(function () {
        AdminSelect2.apply(document);

        // Rows added later — repeaters, rows fetched with the Fetch API — are
        // picked up without every page having to remember to call apply().
        if (typeof MutationObserver === 'function') {
            var pending = null;

            new MutationObserver(function (mutations) {
                var added = mutations.some(function (mutation) {
                    return mutation.addedNodes && mutation.addedNodes.length > 0;
                });

                if (!added || pending) {
                    return;
                }

                pending = window.setTimeout(function () {
                    pending = null;
                    AdminSelect2.apply(document);
                    movePromptsBelowSelect2(document);
                }, 120);
            }).observe(document.body, { childList: true, subtree: true });
        }
    });

    window.AdminSelect2 = AdminSelect2;
})(window.jQuery);
