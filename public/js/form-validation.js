/**
 * Sitenin genel (front) tarafı için merkezî form doğrulama.
 *
 * Admin'deki assets/admin/js/form-validation.js ile aynı sözdizimini konuşur —
 * form bir kez katılır:
 *
 *     <form data-validate novalidate> ... </form>
 *
 * ve her alan kuralını kendi üstünde taşır:
 *
 *     <input data-validation-engine="validate[required,custom[email],maxSize[255]]">
 *
 * Ayrı bir dosya olması bilinçli: front ile admin JS'i paylaşmıyor. Buradaki
 * sürüm front'un ihtiyacı kadarını içeriyor — admin'deki çok dilli sekme
 * kapsamı, Select2 ve zengin metin düzenleyici desteği burada yok, çünkü
 * front formlarında bunların hiçbiri kullanılmıyor.
 *
 * Tarayıcının kendi doğrulaması (required / type=email) bilerek kullanılmıyor:
 * mesajı biçimlendirilemiyor, Türkçeleştirilemiyor ve sunucudaki kuralla
 * uyuşmadığı yerde kullanıcıyı yanlış yönlendiriyor. Kural tek yerde,
 * alanın üstünde duruyor; sunucu her zaman son söz.
 */
(function ($) {
    'use strict';

    if (!$ || !$.fn || !$.fn.validationEngine) {
        return;
    }

    var SUBMITTING_CLASS = 'fv-submitting';

    var FormValidation = {
        /** funcCall[FormValidation.rules.x] hedefleri burada yaşıyor. */
        rules: {},

        defaults: {
            promptPosition: 'inline',
            scroll: false,
            focusFirstField: false,
            showArrow: false,
            autoHidePrompt: false,
            // Tek mesaj yeter: boş bir zorunlu alan "zorunludur" demeli,
            // ayrıca biçiminden de şikâyet etmemeli.
            maxErrorsPerField: 1,
            addFailureCssClassToField: 'is-invalid',
            addSuccessCssClassToField: ''
        }
    };

    // ==================== ÖZEL KURALLAR ====================

    if ($.validationEngineLanguage && $.validationEngineLanguage.allRules) {
        var allRules = $.validationEngineLanguage.allRules;
        var TEXT_KEYS = [
            'alertText', 'alertText2', 'alertTextOk', 'alertTextLoad',
            'alertTextCheckboxMultiple', 'alertTextCheckboxe'
        ];

        // Paketin Türkçe metinleri "* " ile başlıyor; mesajın önünde uyarı
        // ikonu gösterildiği için bu işaret bir kez burada temizleniyor.
        Object.keys(allRules).forEach(function (name) {
            TEXT_KEYS.forEach(function (key) {
                if (typeof allRules[name][key] === 'string') {
                    allRules[name][key] = allRules[name][key].replace(/^\*\s*/, '');
                }
            });
        });

        // Paketteki onlyLetterSp yalnızca ASCII biliyor, "Ömer" ya da "Çağla"
        // reddedilirdi; bu desen FormRequest'lerdeki regex ile birebir aynı.
        allRules.letters = {
            regex: /^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/,
            alertText: 'Bu alanda sadece harf kullanılabilir'
        };
    }

    /**
     * validate[funcCall[FormValidation.rules.imageFile]]
     *
     * Sınırlarını alanın kendi üstünden okuyor:
     *   data-max-size="2" data-accept="image/jpeg,image/png"
     */
    FormValidation.rules.imageFile = function ($field) {
        var input = $field[0];

        if (!input.files || input.files.length === 0) {
            return;
        }

        var file = input.files[0];
        var maxMb = parseFloat(input.dataset.maxSize || '2');
        var accepted = (input.dataset.accept || 'image/jpeg,image/png,image/webp').split(',');

        if (accepted.indexOf(file.type) === -1) {
            return 'Görsel JPG, PNG veya WebP formatında olmalıdır';
        }

        if (file.size > maxMb * 1024 * 1024) {
            return 'Görsel en fazla ' + maxMb + ' MB olabilir';
        }
    };

    /**
     * validate[funcCallRequired[FormValidation.rules.requiredWithPassword]]
     *
     * Sunucudaki required_with:password kuralının istemci karşılığı: alan
     * yalnızca yeni şifre yazıldığında zorunlu olur.
     *
     * funcCall değil funcCallRequired: eklenti, "required" taşımayan boş bir
     * alanın hatasını sonda siliyor (bu sayede isteğe bağlı alanlar boş
     * bırakılabiliyor). funcCallRequired hata döndüğünde alanı zorunlu sayıp
     * bu muafiyeti devre dışı bırakıyor.
     */
    FormValidation.rules.requiredWithPassword = function ($field) {
        var input = $field[0];
        var otherId = input.dataset.fvRequiredWith;
        var other = otherId ? document.getElementById(otherId) : null;

        if (other && other.value.trim() !== '' && input.value.trim() === '') {
            return 'Şifrenizi değiştirmek için mevcut şifrenizi girin';
        }
    };

    // ==================== GİRİŞ MASKELERİ ====================

    /**
     * data-fv-mask="letters|digits|decimal"
     *
     * Kural gönderimde denetler, maske yanlış karakterin yazılmasını en baştan
     * engeller. Desenler custom[letters] / custom[integer] / custom[number] ile
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
            var cleaned = value.replace(/,/g, '.').replace(/[^0-9.]/g, '');
            var firstDot = cleaned.indexOf('.');

            if (firstDot === -1) {
                return cleaned;
            }

            return cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
        }
    };

    /**
     * Alanı maskeden geçirir ve imleci yerinde bırakır.
     *
     * Doğrudan value ataması imleci metnin sonuna atıyor; ortadan düzeltme
     * yapan biri her tuşta sona fırlardı.
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

    // Belge düzeyinde dinleniyor: maske form dışındaki alanlarda da geçerli ve
    // sonradan gelen alanların ayrıca bağlanması gerekmiyor.
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

    // ==================== HATAYI GÖSTERME ====================

    /** Alanın mesajının nereye çizileceği söylenmişse orası. */
    function promptTargetOf(field) {
        var id = field.getAttribute('data-prompt-target');

        return id ? document.getElementById(id) : null;
    }

    /**
     * İlk hatalı alanı ekrana getirir. Alan listesi eklentiden geliyor: önceki
     * turun mesajları ~200ms boyunca sayfada solarak duruyor, DOM okunsaydı
     * kullanıcı az önce düzelttiği alana geri gönderilirdi.
     */
    function revealFirstError(form, invalidFields) {
        var field = Array.prototype.filter.call(invalidFields || [], function (element) {
            return element && element.form === form;
        })[0];

        if (!field) {
            return;
        }

        window.setTimeout(function () {
            var visible = field.offsetParent !== null;
            var target = visible
                ? field
                : (promptTargetOf(field) || field.closest('.form-group, .mb-3, .col-12') || field);

            if (target && target.offsetParent !== null) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (visible) {
                field.focus({ preventScroll: true });
            }
        }, 200);
    }

    // ==================== GÖNDERİM ====================

    function submitButtonsOf(form) {
        return Array.prototype.filter.call(
            document.querySelectorAll('button[type="submit"], input[type="submit"]'),
            function (button) { return button.form === form; }
        );
    }

    function spin(button) {
        if (button.dataset.fvSpinning === '1') {
            return;
        }

        button.dataset.fvSpinning = '1';
        button.dataset.fvLabel = button.innerHTML;
        button.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            'Gönderiliyor...';
    }

    /**
     * Formu kilitler: düğmeler ölür, tıklanan döner. İkinci tıklamayı iptal
     * etmek için false döner — kayıt formuna iki kez basmak iki kayıt demek.
     */
    function lockForm(form) {
        // AJAX ile gönderilen formlar kendi düğme durumlarını yönetiyor;
        // burada da kilitlenirse düğme istek bittikten sonra dönen spinner'da
        // takılı kalır.
        if (form.hasAttribute('data-fv-no-lock')) {
            return true;
        }

        if (form.classList.contains(SUBMITTING_CLASS)) {
            return false;
        }

        form.classList.add(SUBMITTING_CLASS);
        form.setAttribute('aria-busy', 'true');

        var buttons = submitButtonsOf(form);
        var clicked = form.fvClickedButton;
        var spinning = clicked && buttons.indexOf(clicked) !== -1 ? clicked : buttons[0];

        buttons.forEach(function (button) {
            if (button === spinning) {
                spin(button);
            }

            button.disabled = true;
        });

        return true;
    }

    // ==================== BAĞLAMA ====================

    function setup(form, options) {
        if (form.dataset.fvReady === '1') {
            return;
        }

        form.dataset.fvReady = '1';

        // Tarayıcının kendi baloncuğu devre dışı: mesaj bizim.
        form.setAttribute('novalidate', 'novalidate');

        submitButtonsOf(form).forEach(function (button) {
            button.addEventListener('click', function () {
                form.fvClickedButton = button;
            });
        });

        $(form).validationEngine('attach', $.extend({}, FormValidation.defaults, options || {}, {
            onValidationComplete: function ($validatedForm, valid) {
                var element = $validatedForm[0];

                if (!valid) {
                    revealFirstError(element, ($validatedForm.data('jqv') || {}).InvalidFields);

                    return false;
                }

                return lockForm(element);
            }
        }));
    }

    /**
     * Formu hemen denetler ve geçip geçmediğini döner.
     *
     * AJAX ile gönderilen formlar (footer'daki bülten kutusu) sayfayı
     * yenilemediği için eklentinin submit kancasına güvenemiyor: kanca yalnızca
     * tarayıcının kendi gönderimini durdurabiliyor, fetch çağrısını değil.
     * Bu yüzden istek atılmadan hemen önce buradan soruyorlar.
     *
     * Dikkat: eklentinin alan düzeyindeki 'validate' çağrısı geçerli alan için
     * true, hatalı alan için false dönüyor — kaynaktaki yorum satırı bunun
     * tersini söylüyor ama davranış bu yönde. Karışıklık burada kapatılıyor.
     */
    FormValidation.isValid = function (form) {
        var hatali = 0;

        form.querySelectorAll('[data-validation-engine]').forEach(function (field) {
            if ($(field).validationEngine('validate') === false) {
                hatali++;
            }
        });

        return hatali === 0;
    };

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
