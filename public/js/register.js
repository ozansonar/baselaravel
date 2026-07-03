(function () {
    'use strict';

    var form = document.getElementById('registerForm');
    if (!form) return;

    var passwordInput = document.getElementById('password');
    var confirmInput = document.getElementById('password_confirmation');
    var strengthFill = document.getElementById('passwordStrengthFill');
    var strengthText = document.getElementById('passwordStrengthText');
    var strengthWrapper = document.getElementById('passwordStrength');

    // ─── Character Filters ────────────────────────────────────
    var NAME_REGEX = /^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/;
    var NAME_CHAR_REGEX = /[^a-zA-ZçÇğĞıİöÖşŞüÜ\s]/g;
    var PHONE_CHAR_REGEX = /[^0-9]/g;
    var EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    var PHONE_REGEX = /^(05\d{2}\s?\d{3}\s?\d{2}\s?\d{2}|\+90\s?5\d{2}\s?\d{3}\s?\d{2}\s?\d{2})$/;

    // Block invalid characters on keypress for name fields
    function filterNameInput(e) {
        var char = e.data;
        if (char && !NAME_REGEX.test(char)) {
            e.target.value = e.target.value.replace(NAME_CHAR_REGEX, '');
        }
    }

    // Block non-numeric chars on phone field
    function filterPhoneInput(e) {
        var raw = e.target.value.replace(PHONE_CHAR_REGEX, '');
        if (raw.length > 11) raw = raw.substring(0, 11);

        // Format: 05XX XXX XX XX
        var formatted = '';
        for (var i = 0; i < raw.length; i++) {
            if (i === 4 || i === 7 || i === 9) formatted += ' ';
            formatted += raw[i];
        }
        e.target.value = formatted;
    }

    // Attach input filters
    var firstNameInput = document.getElementById('first_name');
    var lastNameInput = document.getElementById('last_name');
    var phoneInput = document.getElementById('phone');

    if (firstNameInput) firstNameInput.addEventListener('input', filterNameInput);
    if (lastNameInput) lastNameInput.addEventListener('input', filterNameInput);
    if (phoneInput) phoneInput.addEventListener('input', filterPhoneInput);

    // Block paste of invalid chars
    function filterNamePaste(e) {
        var pasted = (e.clipboardData || window.clipboardData).getData('text');
        if (NAME_CHAR_REGEX.test(pasted)) {
            e.preventDefault();
            var clean = pasted.replace(NAME_CHAR_REGEX, '');
            document.execCommand('insertText', false, clean);
        }
    }

    function filterPhonePaste(e) {
        var pasted = (e.clipboardData || window.clipboardData).getData('text');
        e.preventDefault();
        var clean = pasted.replace(PHONE_CHAR_REGEX, '');
        document.execCommand('insertText', false, clean);
    }

    if (firstNameInput) firstNameInput.addEventListener('paste', filterNamePaste);
    if (lastNameInput) lastNameInput.addEventListener('paste', filterNamePaste);
    if (phoneInput) phoneInput.addEventListener('paste', filterPhonePaste);

    // ─── Validation Rules ─────────────────────────────────────
    function validateField(input) {
        var rules = (input.dataset.validate || '').split('|');
        var label = input.dataset.label || input.name;
        var value = input.value.trim();

        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i];
            var parts = rule.split(':');
            var ruleName = parts[0];
            var ruleParam = parts[1];

            switch (ruleName) {
                case 'required':
                    if (!value) return label + ' alanı zorunludur.';
                    break;

                case 'name':
                    if (value && !NAME_REGEX.test(value))
                        return label + ' yalnızca harf ve boşluk içerebilir.';
                    break;

                case 'email':
                    if (value && !EMAIL_REGEX.test(value))
                        return 'Geçerli bir e-posta adresi girin.';
                    break;

                case 'phone':
                    var rawPhone = value.replace(/\s/g, '');
                    if (rawPhone && !PHONE_REGEX.test(value))
                        return 'Geçerli bir telefon numarası girin. (05XX XXX XX XX)';
                    break;

                case 'min':
                    if (value && value.length < parseInt(ruleParam, 10))
                        return label + ' en az ' + ruleParam + ' karakter olmalıdır.';
                    break;

                case 'max':
                    if (value && value.length > parseInt(ruleParam, 10))
                        return label + ' en fazla ' + ruleParam + ' karakter olabilir.';
                    break;

                case 'match':
                    var matchInput = document.getElementById(ruleParam);
                    if (matchInput && value && value !== matchInput.value)
                        return 'Şifreler eşleşmiyor.';
                    break;
            }
        }

        return null;
    }

    // ─── Feedback Display ─────────────────────────────────────
    function showError(input, message) {
        var feedback = document.getElementById(input.name + '-feedback');
        if (!feedback) return;
        feedback.innerHTML = '<span class="login-error"><i class="fa-solid fa-circle-exclamation me-1"></i>' + message + '</span>';
        input.classList.add('login-input--error');
        input.classList.remove('login-input--valid');
    }

    function showSuccess(input) {
        var feedback = document.getElementById(input.name + '-feedback');
        if (!feedback) return;
        feedback.innerHTML = '';
        input.classList.remove('login-input--error');
        input.classList.add('login-input--valid');
    }

    function clearFeedback(input) {
        var feedback = document.getElementById(input.name + '-feedback');
        if (!feedback) return;
        feedback.innerHTML = '';
        input.classList.remove('login-input--error', 'login-input--valid');
    }

    // ─── Password Strength ────────────────────────────────────
    function calcPasswordStrength(password) {
        if (!password) return { score: 0, label: '', level: '' };

        var score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        if (score <= 1) return { score: 20, label: 'Çok Zayıf', level: 'very-weak' };
        if (score === 2) return { score: 40, label: 'Zayıf', level: 'weak' };
        if (score === 3) return { score: 60, label: 'Orta', level: 'medium' };
        if (score === 4) return { score: 80, label: 'Güçlü', level: 'strong' };
        return { score: 100, label: 'Çok Güçlü', level: 'very-strong' };
    }

    function updateStrengthBar(password) {
        var result = calcPasswordStrength(password);

        if (!password) {
            strengthWrapper.classList.remove('active');
            strengthFill.style.width = '0';
            strengthText.textContent = '';
            return;
        }

        strengthWrapper.classList.add('active');
        strengthFill.style.width = result.score + '%';

        // Reset classes
        strengthFill.className = 'password-strength-fill';
        strengthText.className = 'password-strength-text';

        if (result.level) {
            strengthFill.classList.add('password-strength-fill--' + result.level);
            strengthText.classList.add('password-strength-text--' + result.level);
        }
        strengthText.textContent = result.label;
    }

    // ─── Real-time Validation ─────────────────────────────────
    var inputs = form.querySelectorAll('[data-validate]');
    inputs.forEach(function (input) {
        input.addEventListener('blur', function () {
            if (!input.value.trim() && !input.dataset.touched) return;
            input.dataset.touched = 'true';

            var error = validateField(input);
            if (error) {
                showError(input, error);
            } else if (input.value.trim()) {
                showSuccess(input);
            } else {
                clearFeedback(input);
            }
        });

        input.addEventListener('input', function () {
            if (input.dataset.touched === 'true') {
                var error = validateField(input);
                if (error) {
                    showError(input, error);
                } else if (input.value.trim()) {
                    showSuccess(input);
                } else {
                    clearFeedback(input);
                }
            }
        });
    });

    // ─── Password Events ──────────────────────────────────────
    if (passwordInput) {
        passwordInput.addEventListener('input', function () {
            updateStrengthBar(this.value);

            if (confirmInput && confirmInput.dataset.touched === 'true' && confirmInput.value) {
                var error = validateField(confirmInput);
                if (error) {
                    showError(confirmInput, error);
                } else {
                    showSuccess(confirmInput);
                }
            }
        });
    }

    // ─── Toggle Password ──────────────────────────────────────
    document.querySelectorAll('.login-toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.dataset.target;
            var input = document.getElementById(targetId);
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // ─── Form Submit ──────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        var hasError = false;

        inputs.forEach(function (input) {
            input.dataset.touched = 'true';
            var error = validateField(input);
            if (error) {
                showError(input, error);
                if (!hasError) {
                    input.focus();
                    hasError = true;
                }
            } else if (input.value.trim()) {
                showSuccess(input);
            }
        });

        if (hasError) {
            e.preventDefault();
        }
    });
})();
