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
 * Hazır dil kutucukları: tek tıkla kod, ad, yerel ad ve bayrak dolar; hepsi
 * sonrasında değiştirilebilir. Otuza yakın seçenek olduğu için liste ayrıca
 * yazarak süzülebiliyor — aranan dili gözle bulmak yerine yazmak daha hızlı.
 */
function initPresets() {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.js-language-preset'));

    if (!buttons.length) {
        return;
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            set('code', button.dataset.code);
            set('name', button.dataset.name);
            set('native_name', button.dataset.native);
            set('flag', button.dataset.flag);

            buttons.forEach(function (other) { other.classList.remove('active'); });
            button.classList.add('active');

            // Doldurulan alanlar ekranın altında kalabiliyor; seçimin bir
            // karşılığı olduğu görünsün diye forma gidiliyor.
            var code = document.getElementById('code');

            if (code) {
                code.scrollIntoView({ behavior: 'smooth', block: 'center' });
                code.focus({ preventScroll: true });
            }
        });
    });

    initPresetSearch(buttons);

    function set(id, value) {
        var field = document.getElementById(id);

        if (field) {
            field.value = value;
        }
    }
}

/**
 * Arama metnini karşılaştırılabilir hâle getirir.
 *
 * "İspanyolca" yazan da "ispanyolca" yazan da aynı sonucu bulmalı: Türkçe
 * küçültme İ'yi i yapıyor, ardından aksanlar (español → espanol, İsveççe →
 * isvecce) ayrıştırılıp atılıyor. Noktasız ı ayrıca i'ye çekiliyor — birleşik
 * işaret taşımadığı için ayrıştırma onu görmüyor. Kiril ve Çin yazısı bu
 * işlemlerden değişmeden çıkıyor, yani "рус" ya da "中" da aranabiliyor.
 */
function normalizeSearch(value) {
    return String(value)
        .toLocaleLowerCase('tr')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ı/g, 'i');
}

/**
 * Kutucukları yazarak süzer. Arama hem Türkçe adı hem dilin kendi adını hem de
 * kodu kapsıyor: kullanıcı "almanca" da yazabilir "deutsch" da, "de" de.
 */
function initPresetSearch(buttons) {
    var input = document.getElementById('presetSearch');
    var empty = document.getElementById('presetEmpty');

    if (!input) {
        return;
    }

    // Her tuş vuruşunda yeniden hesaplanmasın: kart metinleri değişmiyor.
    buttons.forEach(function (button) {
        button.dataset.searchKey = normalizeSearch(button.dataset.search || '');
    });

    input.addEventListener('input', function () {
        var term = normalizeSearch(input.value.trim());
        var found = 0;

        buttons.forEach(function (button) {
            var match = term === '' || (button.dataset.searchKey || '').indexOf(term) !== -1;

            button.classList.toggle('d-none', !match);

            if (match) {
                found++;
            }
        });

        if (empty) {
            empty.classList.toggle('d-none', found > 0);
        }
    });

    // Enter formu göndermesin: burası arama kutusu, kaydetme düğmesi değil.
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });
}
