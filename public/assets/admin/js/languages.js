/**
 * Languages screen — delete confirmation and the quick-fill presets.
 */
document.addEventListener('DOMContentLoaded', function () {
    initPresets();
});

/**
 * The warning names how much content is written in that language, because
 * removing it hides all of it from the site at once.
 */
function openLanguageDelete(id, name, contentCount) {
    var form = document.getElementById('deleteLanguageForm');
    if (!form || !window.languageDeleteUrl) return;

    form.action = window.languageDeleteUrl.replace('LANGUAGE_ID', id);

    var label = document.getElementById('deleteLanguageName');
    if (label) label.textContent = name;

    var warning = document.getElementById('deleteLanguageWarning');
    if (warning) {
        warning.textContent = contentCount > 0
            ? 'Bu dilde ' + contentCount + ' içerik kaydı var; hepsi sitede görünmez olur.'
            : 'Bu dilde içerik bulunmuyor.';
        warning.className = contentCount > 0 ? 'text-neon-orange mb-4' : 'text-clr-secondary mb-4';
    }

    new bootstrap.Modal(document.getElementById('deleteLanguageModal')).show();
}

/**
 * One click fills code, name, native name and flag for a common language, so
 * they do not have to be looked up. Everything stays editable afterwards.
 */
function initPresets() {
    var buttons = document.querySelectorAll('.js-language-preset');
    if (!buttons.length) return;

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            set('code', button.dataset.code);
            set('name', button.dataset.name);
            set('native_name', button.dataset.native);
            set('flag', button.dataset.flag);

            buttons.forEach(function (other) { other.classList.remove('is-active'); });
            button.classList.add('is-active');

            document.getElementById('code')?.focus();
        });
    });

    function set(id, value) {
        var field = document.getElementById(id);
        if (field) field.value = value;
    }
}
