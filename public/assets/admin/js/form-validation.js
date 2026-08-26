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

    /**
     * validate[funcCall[FormValidation.rules.anyLanguageFilled]]
     *
     * For forms where no single language is mandatory but the record cannot be
     * completely empty. Put it on a hidden input inside the form.
     */
    FormValidation.rules.anyLanguageFilled = function ($field) {
        var form = $field[0].form || $field[0].closest('form');
        var panes = form ? form.querySelectorAll('.tab-pane') : [];

        if (panes.length === 0) {
            return;
        }

        var filled = Array.prototype.some.call(panes, function (pane) {
            return Array.prototype.some.call(
                pane.querySelectorAll('input, select, textarea'),
                function (element) {
                    // data-fv-ignore marks fields that always carry a value —
                    // a sort order, a switch that defaults to on — which would
                    // otherwise make an untouched tab look filled in.
                    if (element.disabled || element.type === 'hidden' || element.hasAttribute('data-fv-ignore')) {
                        return false;
                    }

                    if (element.type === 'checkbox' || element.type === 'radio') {
                        return element.checked;
                    }

                    if (element.type === 'file') {
                        return element.files && element.files.length > 0;
                    }

                    // An empty rich text editor still writes markup back.
                    return String(element.value || '').replace(/<[^>]*>/g, '').trim() !== '';
                }
            );
        });

        if (!filled) {
            return 'En az bir dilde içerik girmelisiniz';
        }
    };

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
        });

        $form.validationEngine('attach', $.extend({}, FormValidation.defaults, options || {}, {
            onValidationComplete: function ($validatedForm, valid) {
                if (!valid) {
                    revealFirstError($validatedForm[0], ($validatedForm.data('jqv') || {}).InvalidFields);

                    return false;
                }

                return lockForm($validatedForm[0]);
            }
        }));
    }

    FormValidation.init = function (target, options) {
        var form = typeof target === 'string' ? document.querySelector(target) : target;

        if (form) {
            setup(form, options);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-validate]').forEach(function (form) {
            setup(form);
        });
    });

    window.FormValidation = FormValidation;
})(window.jQuery);
