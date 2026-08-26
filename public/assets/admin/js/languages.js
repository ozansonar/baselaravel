/**
 * Languages screen — delete confirmation.
 *
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
        warning.className = contentCount > 0
            ? 'text-neon-orange mb-4'
            : 'text-clr-secondary mb-4';
    }

    new bootstrap.Modal(document.getElementById('deleteLanguageModal')).show();
}
