/**
 * Interface texts screen — filtering and per-field revert.
 *
 * The page can hold hundreds of inputs, so filtering runs against a
 * pre-rendered lowercase string on each row and only toggles a class. No
 * re-rendering, no fetch: typing stays instant on a long list.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var search = document.getElementById('translationSearch');
        var onlyChanged = document.getElementById('onlyChanged');
        var rows = Array.prototype.slice.call(document.querySelectorAll('.translation-row'));
        var sections = Array.prototype.slice.call(document.querySelectorAll('.translation-section'));
        var counter = document.getElementById('searchCount');
        var empty = document.getElementById('noResults');

        if (!rows.length) return;

        var timer = null;

        function apply() {
            var term = (search ? search.value : '').trim().toLowerCase();
            var changedOnly = onlyChanged ? onlyChanged.checked : false;
            var visible = 0;

            rows.forEach(function (row) {
                var matchesTerm = term === '' || row.dataset.search.indexOf(term) !== -1;
                var matchesChanged = !changedOnly || row.dataset.changed === '1';
                var show = matchesTerm && matchesChanged;

                row.classList.toggle('is-hidden', !show);
                if (show) visible++;
            });

            // A section with nothing left in it is noise.
            sections.forEach(function (section) {
                var any = section.querySelector('.translation-row:not(.is-hidden)');
                section.classList.toggle('is-hidden', !any);
            });

            if (counter) {
                counter.textContent = (term === '' && !changedOnly)
                    ? ''
                    : visible + ' metin gösteriliyor';
            }

            if (empty) empty.classList.toggle('d-none', visible > 0);
        }

        function debounced() {
            if (timer) clearTimeout(timer);
            timer = setTimeout(apply, 120);
        }

        if (search) search.addEventListener('input', debounced);
        if (onlyChanged) onlyChanged.addEventListener('change', apply);

        // Revert one field to the value shipped in the language file. Saving is
        // what actually removes the override — this only fills the box back in.
        document.querySelectorAll('.translation-revert').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = button.closest('.stg-field').querySelector('input, textarea');
                if (!field) return;

                field.value = field.dataset.default || '';
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.focus();
            });
        });

        // Mark a row as changed as soon as it differs from the shipped default,
        // so the "only changed" filter reflects what is on screen.
        document.querySelectorAll('.translation-row input, .translation-row textarea').forEach(function (field) {
            field.addEventListener('input', function () {
                var row = field.closest('.translation-row');
                if (!row) return;

                row.dataset.changed = field.value.trim() !== (field.dataset.default || '').trim() ? '1' : '0';
            });
        });
    });
})();
