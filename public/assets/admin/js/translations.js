/**
 * Interface texts screen — filtering, per-field revert and the save bar.
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
        var onlyMissing = document.getElementById('onlyMissing');
        var rows = Array.prototype.slice.call(document.querySelectorAll('.translation-row'));
        var sections = Array.prototype.slice.call(document.querySelectorAll('.translation-section'));
        var counter = document.getElementById('searchCount');
        var empty = document.getElementById('noResults');
        var form = document.getElementById('translationForm');
        var fields = Array.prototype.slice.call(
            document.querySelectorAll('.translation-row input, .translation-row textarea')
        );

        if (!rows.length) return;

        var timer = null;

        function apply() {
            var term = (search ? search.value : '').trim().toLowerCase();
            var changedOnly = onlyChanged ? onlyChanged.checked : false;
            var missingOnly = onlyMissing ? onlyMissing.checked : false;
            var visible = 0;

            rows.forEach(function (row) {
                var matchesTerm = term === '' || row.dataset.search.indexOf(term) !== -1;
                var matchesChanged = !changedOnly || row.dataset.changed === '1';
                var matchesMissing = !missingOnly || row.dataset.missing === '1';
                var show = matchesTerm && matchesChanged && matchesMissing;

                row.classList.toggle('is-hidden', !show);
                if (show) visible++;
            });

            // A section with nothing left in it is noise.
            sections.forEach(function (section) {
                var any = section.querySelector('.translation-row:not(.is-hidden)');
                section.classList.toggle('is-hidden', !any);
            });

            if (counter) {
                counter.textContent = (term === '' && !changedOnly && !missingOnly)
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
        if (onlyMissing) onlyMissing.addEventListener('change', apply);

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

        initSaveBar(form, fields, rows, apply);
    });

    /**
     * Kaydet çubuğu ile kaydedilmemiş iş koruması.
     *
     * Sayfa yüzlerce alan taşıyor ve on sekiz bin piksele uzuyor: hem ortada
     * çalışırken bir kaydetme yolu olmalı hem de yirmi dakikalık iş sekme
     * kapatılınca sessizce kaybolmamalı.
     */
    function initSaveBar(form, fields, rows, apply) {
        var bar = document.getElementById('translationSaveBar');
        var status = document.getElementById('saveBarStatus');
        var saving = false;

        // Açılıştaki değerler ölçüt: kullanıcının bu oturumda ne değiştirdiğini
        // dosyadaki varsayılan değil, ekrana gelen değer belirler.
        fields.forEach(function (field) {
            field.dataset.initial = field.value;
        });

        function dirtyFields() {
            return fields.filter(function (field) {
                return field.value !== field.dataset.initial;
            });
        }

        function refresh() {
            var count = dirtyFields().length;

            if (bar) {
                bar.classList.toggle('is-dirty', count > 0);
            }

            if (status) {
                status.textContent = count > 0
                    ? count + ' metin kaydedilmeyi bekliyor'
                    : (status.dataset.idle || '');
            }
        }

        fields.forEach(function (field) {
            field.addEventListener('input', function () {
                // "Yalnızca değiştirilenler" süzgeci ekranda görüneni yansıtsın:
                // ölçüt dosyayla gelen varsayılan değer.
                var row = field.closest('.translation-row');

                if (row) {
                    row.dataset.changed =
                        field.value.trim() !== (field.dataset.default || '').trim() ? '1' : '0';
                }

                refresh();
            });
        });

        if (form) {
            form.addEventListener('submit', function () {
                // Kaydetmek de bir sayfa değişimi; uyarı burada susmalı.
                saving = true;
            });
        }

        window.addEventListener('beforeunload', function (event) {
            if (saving || dirtyFields().length === 0) {
                return;
            }

            // Tarayıcılar kendi metnini gösteriyor; standart olan preventDefault
            // ile returnValue'yu birlikte ayarlamak.
            event.preventDefault();
            event.returnValue = '';
        });

        refresh();
    }
})();
