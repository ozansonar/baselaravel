/**
 * Central client side validation for the admin panel.
 *
 * Wraps jQuery Validation Engine 3.1.0 so a form only opts in once:
 *
 *     <form data-validate> ... </form>
 *
 * and every field states its own rules through the data attribute:
 *
 *     <input data-validation-engine="validate[required,maxSize[120]]">
 *
 * Browser level validation (required / type=email / minlength) is deliberately
 * NOT used anywhere: an empty required field sitting in a hidden language tab
 * makes the browser block the submit with a message nobody can see. The engine
 * validates hidden fields too and we bring the offending tab forward instead.
 *
 * Fields may also carry an input mask — data-fv-mask="letters|digits|decimal" —
 * which strips characters the field cannot accept while they are being typed.
 * The mask and the rule are two halves of one guarantee, not alternatives.
 *
 * The module also owns the submit lifecycle. Once a form passes, every submit
 * button turns into a disabled spinner, so a second click cannot fire a second
 * POST and the user gets immediate feedback that the save started.
 */
(function ($) {
    'use strict';

    if (!$ || !$.fn || !$.fn.validationEngine) {
        return;
    }

    var SUBMITTING_CLASS = 'fv-submitting';

    var FormValidation = {
        /** funcCall[FormValidation.rules.x] targets live here. */
        rules: {},

        /** Shared engine options; a form may override them via FormValidation.init(). */
        defaults: {
            promptPosition: 'inline',
            // Hidden language tabs and the TinyMCE textarea must be checked too.
            validateNonVisibleFields: true,
            // Scrolling and focus are handled here, because the field may live
            // in a tab that is not on screen yet.
            scroll: false,
            focusFirstField: false,
            showArrow: false,
            autoHidePrompt: false,
            // One message at a time: an empty required field should say it is
            // required, not also complain about its format.
            maxErrorsPerField: 1,
            addFailureCssClassToField: 'is-invalid',
            addSuccessCssClassToField: ''
        }
    };

    // ==================== CUSTOM RULES ====================

    if ($.validationEngineLanguage && $.validationEngineLanguage.allRules) {
        var allRules = $.validationEngineLanguage.allRules;
        var TEXT_KEYS = [
            'alertText', 'alertText2', 'alertTextOk', 'alertTextLoad',
            'alertTextCheckboxMultiple', 'alertTextCheckboxe'
        ];

        // The bundled Turkish strings are all prefixed with "* "; the panel
        // shows a warning icon instead, so strip the marker once here.
        Object.keys(allRules).forEach(function (name) {
            TEXT_KEYS.forEach(function (key) {
                if (typeof allRules[name][key] === 'string') {
                    allRules[name][key] = allRules[name][key].replace(/^\*\s*/, '');
                }
            });
        });

        // validate[custom[slug]] — lowercase words joined by single hyphens.
        allRules.slug = {
            regex: /^[a-z0-9]+(?:-[a-z0-9]+)*$/,
            alertText: 'Sadece küçük harf, rakam ve tire kullanın'
        };

        // validate[custom[sitePath]] — an in-site path, which has to start with /.
        allRules.sitePath = {
            regex: /^\/\S*$/,
            alertText: '/ ile başlamalı ve boşluk içermemeli'
        };

        // validate[custom[redirectTarget]] — yönlendirme hedefi: ya / ile başlayan
        // site içi bir yol ya da tam http(s) adresi. Çift bölü ("//evil.test")
        // ve ters bölü, tarayıcıyı site dışına çıkarabildiği için dışarıda;
        // sunucudaki SafeRedirectTarget aynı biçimi bekliyor ve ayrıca adresin
        // izin verilen alan adlarından biri olduğunu doğruluyor.
        allRules.redirectTarget = {
            regex: /^(\/(?!\/)[^\s\\]*|https?:\/\/[^\s\\]+)$/,
            alertText: '/ ile başlayan bir yol ya da http(s):// ile tam adres olmalı'
        };

        // validate[custom[langCode]] — an ISO 639-1 code: two lowercase letters.
        allRules.langCode = {
            regex: /^[a-z]{2}$/,
            alertText: 'İki küçük harften oluşmalı (ör: tr, en)'
        };

        // The bundled onlyLetterSp rule is ASCII only, which would reject
        // Ömer or Çağla; this mirrors the regex the FormRequests use.
        allRules.letters = {
            regex: /^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/,
            alertText: 'Bu alanda sadece harf kullanılabilir'
        };
    }

    /**
     * validate[funcCall[FormValidation.rules.imageFile]]
     *
     * Reads its limits off the input so a field can tighten them:
     *   data-max-size="4" data-accept="image/jpeg,image/png"
     */
    FormValidation.rules.imageFile = function ($field) {
        var input = $field[0];

        if (!input.files || input.files.length === 0) {
            return;
        }

        var file = input.files[0];
        var maxMb = parseFloat(input.dataset.maxSize || '4');
        var accepted = (input.dataset.accept || 'image/jpeg,image/png,image/webp').split(',');

        if (accepted.indexOf(file.type) === -1) {
            return 'Görsel JPG, PNG veya WebP formatında olmalıdır';
        }

        if (file.size > maxMb * 1024 * 1024) {
            return 'Görsel en fazla ' + maxMb + ' MB olabilir';
        }
    };

    // ==================== INPUT MASKS ====================

    /**
     * data-fv-mask="letters|digits|decimal"
     *
     * Kurallar gönderimde denetler; maske yanlış karakterin yazılmasını en
     * baştan engeller. İkisi birlikte çalışır: maske olmadan kullanıcı hatayı
     * ancak kaydete basınca görür, maske tek başına da yeterli değildir çünkü
     * boş bırakma ve uzunluk gibi kuralları bilmez.
     *
     * Desenler `custom[letters]`, `custom[integer]` ve `custom[number]` ile
     * bilerek aynı: maskenin geçirdiği bir değeri kural reddederse kullanıcı
     * düzeltemeyeceği bir hataya bakar.
     */
    var MASKS = {
        letters: function (value) {
            return value.replace(/[^a-zA-ZçÇğĞıİöÖşŞüÜ\s]/g, '');
        },
        digits: function (value) {
            return value.replace(/\D/g, '');
        },
        decimal: function (value) {
            // Türkçe klavyede ondalık ayırıcı virgül; nokta bekleyen sunucuya
            // gitmeden çevriliyor.
            var cleaned = value.replace(/,/g, '.').replace(/[^0-9.]/g, '');
            var firstDot = cleaned.indexOf('.');

            if (firstDot === -1) {
                return cleaned;
            }

            // İkinci ve sonraki noktalar atılıyor: "1.2.3" geçerli bir sayı değil.
            return cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
        }
    };

    /**
     * Alanı maskeden geçirir ve imleci yerinde bırakır.
     *
     * Doğrudan value ataması imleci metnin sonuna atıyor; ortadan düzeltme
     * yapan biri her tuşta sona fırlar. Silinen karakter sayısı kadar geri
     * alınarak imleç kullanıcının bıraktığı yerde tutuluyor.
     */
    function applyMask(field, mask) {
        var before = field.value;
        var after = mask(before);

        if (after === before) {
            return;
        }

        var caret = field.selectionStart;
        var removedBeforeCaret = before.slice(0, caret).length - mask(before.slice(0, caret)).length;

        field.value = after;

        if (field.type !== 'email' && field.type !== 'number') {
            // Bu iki türde tarayıcı seçim aralığı vermiyor, çağrı hata atar.
            field.setSelectionRange(caret - removedBeforeCaret, caret - removedBeforeCaret);
        }
    }

    /**
     * Maske belge düzeyinde dinleniyor: sonradan eklenen satırlar (alıcı
     * satırları, tekrarlanan alan grupları) ayrıca bağlanmak zorunda kalmasın.
     */
    function watchMasks() {
        document.addEventListener('input', function (event) {
            var field = event.target;

            if (!field || !field.dataset || !field.dataset.fvMask) {
                return;
            }

            var mask = MASKS[field.dataset.fvMask];

            if (mask) {
                applyMask(field, mask);
            }
        });
    }

    // ==================== ACTIVE LANGUAGE ONLY ====================

    /**
     * A multilingual form is one form with one field per language, and only the
     * tab on screen is being worked on. Saving therefore concerns that tab: the
     * rules of the hidden tabs are lifted before validation, and their fields
     * are left out of the request entirely, so a half typed draft in another
     * language neither blocks the save nor overwrites what is stored.
     */
    function fieldsOfInactivePanes(form) {
        var active = form.querySelector('.tab-pane.active');

        if (!active) {
            return [];
        }

        var fields = [];

        form.querySelectorAll('.tab-pane').forEach(function (pane) {
            if (pane === active) {
                return;
            }

            pane.querySelectorAll('input, select, textarea').forEach(function (field) {
                fields.push(field);
            });
        });

        return fields;
    }

    /** Only the visible tab carries rules while the form is being checked. */
    function scopeRulesToActivePane(form) {
        form.querySelectorAll('[data-fv-rule]').forEach(function (field) {
            field.setAttribute('data-validation-engine', field.dataset.fvRule);
            delete field.dataset.fvRule;
        });

        fieldsOfInactivePanes(form).forEach(function (field) {
            var rule = field.getAttribute('data-validation-engine');

            if (rule) {
                field.dataset.fvRule = rule;
                field.removeAttribute('data-validation-engine');
            }

            field.classList.remove('is-invalid');
        });

        form.querySelectorAll('.tab-pane:not(.active) .formError').forEach(function (el) {
            el.remove();
        });
    }

    /** Keeps the hidden tabs out of the payload once the save is going ahead. */
    function excludeInactivePanes(form) {
        fieldsOfInactivePanes(form).forEach(function (field) {
            field.disabled = true;
        });
    }

    // ==================== UNSAVED WORK IN OTHER TABS ====================

    /**
     * Only the visible tab is sent, so anything typed into another language and
     * left behind is about to be lost when the page reloads. The editor is told
     * before that happens rather than after.
     */
    function fieldIsDirty(field) {
        if (field.type === 'hidden') {
            return false;
        }

        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked !== field.defaultChecked;
        }

        if (field.type === 'file') {
            return field.files && field.files.length > 0;
        }

        if (field.tagName === 'SELECT') {
            // With no option marked selected the browser picks the first one,
            // and that is the untouched state — not a change.
            var preselected = Array.prototype.findIndex.call(
                field.options,
                function (option) { return option.defaultSelected; }
            );

            return field.selectedIndex !== (preselected === -1 ? 0 : preselected);
        }

        return field.value !== field.defaultValue;
    }

    function languageNameOfPane(pane) {
        var trigger = document.querySelector('[data-bs-target="#' + pane.id + '"]');

        if (!trigger) {
            return pane.id;
        }

        var name = trigger.querySelector('span:not([class])');

        return name ? name.textContent.trim() : (trigger.dataset.locale || '').toUpperCase();
    }

    /** Languages holding edits that this save will not carry. */
    function unsavedLanguages(form) {
        var active = form.querySelector('.tab-pane.active');

        if (!active) {
            return [];
        }

        var languages = [];

        form.querySelectorAll('.tab-pane').forEach(function (pane) {
            if (pane === active) {
                return;
            }

            var dirty = Array.prototype.some.call(
                pane.querySelectorAll('input, select, textarea'),
                fieldIsDirty
            );

            if (dirty) {
                languages.push(languageNameOfPane(pane));
            }
        });

        return languages;
    }

    function confirmDiscarding(form, languages, activeName) {
        AdminModal.confirm({
            title: 'Diğer dillerdeki değişiklikler',
            message: languages.join(' ve ') + ' sekmesinde kaydedilmemiş değişiklik var. '
                + 'Bu kayıt yalnızca ' + activeName + ' içeriğini kaydeder; diğerleri kaybolur.',
            type: 'warning',
            confirmText: 'Yine de kaydet',
            confirmIcon: 'bi bi-check-lg',
            cancelText: 'Vazgeç'
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            form.fvDiscardConfirmed = true;
            form.requestSubmit();
        });
    }

    // ==================== EDITOR SYNC ====================

    /**
     * TinyMCE keeps its content in an iframe, so the textarea the engine reads
     * is stale until the editor writes back into it.
     */
    function syncEditors() {
        if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
            window.tinymce.triggerSave();
        }
    }

    // ==================== FIELD DEFAULTS ====================

    /**
     * data-fv-default="0" — an emptied field falls back to its default instead
     * of travelling to the server as null. Applied on blur so the user sees the
     * value that will actually be saved, and again on submit as a safety net.
     */
    /**
     * Doğrulama boyunca placeholder'ları alandan çeker.
     *
     * Eklentinin "required" kuralı, alanın değeri placeholder metnine eşitse
     * alanı boş sayıyor — eski tarayıcılarda placeholder'ı taklit eden kodun
     * kalıntısı. Sonuç, ipucunda yazan örneği birebir yazan kullanıcının
     * "bu alan zorunludur" hatası alması: dil kodu alanında ipucu "de", eklemek
     * istediği dil de "de". Nitelik yalnızca denetim sürerken kalkıyor, hemen
     * ardından geri konuyor.
     */
    function hidePlaceholders(form) {
        form.querySelectorAll('[placeholder]').forEach(function (field) {
            field.dataset.fvPlaceholder = field.getAttribute('placeholder');
            field.removeAttribute('placeholder');
        });
    }

    function restorePlaceholders(form) {
        if (!form) {
            return;
        }

        form.querySelectorAll('[data-fv-placeholder]').forEach(function (field) {
            field.setAttribute('placeholder', field.dataset.fvPlaceholder);
            delete field.dataset.fvPlaceholder;
        });
    }

    function applyDefaults(form) {
        form.querySelectorAll('[data-fv-default]').forEach(function (field) {
            if (String(field.value || '').trim() === '') {
                field.value = field.dataset.fvDefault;
            }
        });
    }

    function watchDefaults(form) {
        form.querySelectorAll('[data-fv-default]').forEach(function (field) {
            field.addEventListener('blur', function () {
                if (String(field.value || '').trim() === '') {
                    field.value = field.dataset.fvDefault;
                }
            });
        });
    }

    // ==================== ERROR REVEAL ====================

    /** Where the plugin was told to render this field's message, if anywhere. */
    function promptTargetOf(field) {
        var id = field.getAttribute('data-prompt-target');

        return id ? document.getElementById(id) : null;
    }

    /**
     * Brings the first failing field into view — switching language tabs when
     * the error sits in a tab the user is not looking at.
     *
     * The field list comes from the plugin rather than from the DOM: prompts
     * from the previous round are faded out over ~200ms, so a stale message
     * is still on the page while this runs and reading the DOM would send the
     * user back to the tab they just fixed.
     */
    function revealFirstError(form, invalidFields) {
        var fields = Array.prototype.filter.call(invalidFields || [], function (element) {
            return element && form.contains(element);
        });

        // The tab the user is working in comes first: being thrown into another
        // language while fixing this one is disorienting, and the other tab is
        // reported only once nothing is left to fix here.
        var pane = form.querySelector('.tab-pane.active');
        var field = (pane && fields.filter(function (element) {
            return pane.contains(element);
        })[0]) || fields[0];

        if (!field) {
            return;
        }

        var owner = field.closest('.tab-pane');

        if (owner && !owner.classList.contains('active') && typeof bootstrap !== 'undefined') {
            var trigger = document.querySelector('[data-bs-target="#' + owner.id + '"]');

            if (trigger) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }
        }

        // Let the tab finish showing before measuring where to scroll.
        window.setTimeout(function () {
            var visible = field.offsetParent !== null;
            // A hidden field cannot be scrolled to — a rich text editor covers
            // one, and the language guard is a hidden input — so aim at where
            // its message was rendered, or at the block that holds it.
            var target = visible
                ? field
                : (promptTargetOf(field) || field.closest('.col-12, .col-md-6, .card-dark') || field);

            if (target.offsetParent !== null) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (visible) {
                field.focus({ preventScroll: true });
            }

            // A failure that belongs to the form rather than to one visible
            // field gets a modal as well: the message sits at the top of a long
            // page and the user is usually at the bottom, next to the button.
            if (field.hasAttribute('data-fv-modal') && typeof AdminModal !== 'undefined') {
                var slot = promptTargetOf(field);

                AdminModal.status({
                    title: 'Eksik bilgi',
                    message: slot ? slot.textContent.trim() : 'Lütfen formu kontrol edin.',
                    type: 'danger'
                });
            }
        }, 200);
    }

    // ==================== SUBMIT LIFECYCLE ====================

    function spin(button) {
        // Freeze the width so swapping the label does not make the row jump.
        button.style.minWidth = button.offsetWidth + 'px';

        var label = (button.textContent || '').trim();
        var spinner = document.createElement('span');
        spinner.className = 'fv-spinner';
        spinner.setAttribute('aria-hidden', 'true');

        button.textContent = '';
        button.appendChild(spinner);

        if (label) {
            button.appendChild(document.createTextNode(' ' + label));
        }
    }

    /**
     * Every submit button that belongs to this form.
     *
     * A button may sit outside the form and point at it with form="id" — the
     * page header does exactly that on several screens — and element.form
     * resolves both cases.
     */
    function submitButtonsOf(form) {
        return Array.prototype.filter.call(
            document.querySelectorAll('button[type="submit"], input[type="submit"]'),
            function (button) { return button.form === form; }
        );
    }

    /**
     * Locks the form for good: the buttons go dead and the clicked one spins.
     * Returns false when a submit is already running, which cancels the second.
     */
    function lockForm(form) {
        if (form.classList.contains(SUBMITTING_CLASS)) {
            return false;
        }

        form.classList.add(SUBMITTING_CLASS);
        form.setAttribute('aria-busy', 'true');
        excludeInactivePanes(form);

        var clicked = form.fvClickedButton;

        // A disabled button is left out of the payload, so carry the value the
        // user chose (Taslak Kaydet / Yayınla) in a hidden field instead.
        if (clicked && clicked.name) {
            var carrier = document.createElement('input');
            carrier.type = 'hidden';
            carrier.name = clicked.name;
            carrier.value = clicked.value;
            form.appendChild(carrier);
        }

        var buttons = submitButtonsOf(form);

        // Nothing was clicked when the submit came from a script or the Enter
        // key; spin the first button so there is still visible feedback.
        var spinning = clicked && buttons.indexOf(clicked) !== -1 ? clicked : buttons[0];

        buttons.forEach(function (button) {
            if (button === spinning) {
                spin(button);
            }

            button.disabled = true;
            button.classList.add('is-loading');
        });

        return true;
    }

    /** Remembers which submit button started this round, wherever it sits. */
    function trackSubmitButton(form) {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('button[type="submit"], input[type="submit"]');

            if (button && button.form === form) {
                form.fvClickedButton = button;
            }
        }, true);
    }

    // ==================== SETUP ====================

    function setup(form, options) {
        if (form.dataset.fvReady === '1') {
            return;
        }

        form.dataset.fvReady = '1';
        trackSubmitButton(form);

        var $form = $(form);

        watchDefaults(form);

        // Bound before the engine attaches so the editor content and the field
        // defaults are already in place by the time the rules run.
        $form.on('submit', function () {
            syncEditors();
            applyDefaults(form);
            scopeRulesToActivePane(form);
            hidePlaceholders(form);
        });

        $form.validationEngine('attach', $.extend({}, FormValidation.defaults, options || {}, {
            onValidationComplete: function ($validatedForm, valid) {
                restorePlaceholders($validatedForm[0]);

                if (!valid) {
                    revealFirstError($validatedForm[0], ($validatedForm.data('jqv') || {}).InvalidFields);

                    return false;
                }

                var form = $validatedForm[0];
                var pending = typeof AdminModal !== 'undefined' && !form.fvDiscardConfirmed
                    ? unsavedLanguages(form)
                    : [];

                if (pending.length > 0) {
                    var active = form.querySelector('.tab-pane.active');
                    confirmDiscarding(form, pending, active ? languageNameOfPane(active) : 'bu dil');

                    return false;
                }

                return lockForm(form);
            }
        }));
    }

    FormValidation.init = function (target, options) {
        var form = typeof target === 'string' ? document.querySelector(target) : target;

        if (form) {
            setup(form, options);
        }
    };

    // Maske form dışındaki alanlarda da geçerli (süzgeç çubukları gibi), bu
    // yüzden data-validate beklemeden bağlanıyor.
    watchMasks();

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-validate]').forEach(function (form) {
            setup(form);
        });
    });

    window.FormValidation = FormValidation;
})(window.jQuery);
