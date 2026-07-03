/**
 * Admin Profile Page
 */

function initAvatarUpload() {
    var input = document.getElementById('avatarInput');
    var preview = document.getElementById('avatarPreview');
    var removeCheck = document.getElementById('removeAvatarCheck');

    if (!input || !preview) return;

    input.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            alert('Dosya boyutu 2MB\'dan büyük olamaz.');
            this.value = '';
            return;
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar önizleme" id="avatarImg">';

            if (removeCheck) {
                removeCheck.checked = false;
            }
        };
        reader.readAsDataURL(file);
    });

    if (removeCheck) {
        removeCheck.addEventListener('change', function () {
            if (this.checked) {
                input.value = '';
                var placeholder = document.getElementById('avatarPlaceholder');
                if (!placeholder) {
                    preview.innerHTML = '<div class="prf-avatar-placeholder" id="avatarPlaceholder">' +
                        '<i class="bi bi-camera"></i>' +
                        '<span>Fotoğraf Yükle</span>' +
                        '</div>';
                }
            }
        });
    }
}

function initPasswordStrength() {
    var input = document.getElementById('password');
    var wrapper = document.getElementById('passwordStrength');
    var bar = document.getElementById('strengthBar');
    var text = document.getElementById('strengthText');

    if (!input || !wrapper || !bar || !text) return;

    input.addEventListener('input', function () {
        var val = this.value;

        if (!val) {
            wrapper.classList.add('d-none');
            return;
        }

        wrapper.classList.remove('d-none');

        var score = 0;
        if (val.length >= 8) score++;
        if (val.length >= 12) score++;
        if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
        if (/\d/.test(val)) score++;
        if (/[^a-zA-Z0-9]/.test(val)) score++;

        var levels = [
            { width: '20%', color: '#ef4444', label: 'Çok Zayıf' },
            { width: '40%', color: '#f97316', label: 'Zayıf' },
            { width: '60%', color: '#eab308', label: 'Orta' },
            { width: '80%', color: '#22c55e', label: 'Güçlü' },
            { width: '100%', color: '#14b8a6', label: 'Çok Güçlü' }
        ];

        var level = levels[Math.min(score, levels.length - 1)];
        bar.style.width = level.width;
        bar.style.background = level.color;
        text.textContent = level.label;
        text.style.color = level.color;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initAvatarUpload();
    initPasswordStrength();
});
