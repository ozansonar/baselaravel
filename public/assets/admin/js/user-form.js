/**
 * User Form Page (Create & Edit)
 * - Section navigation
 * - Avatar preview & remove
 * - Password toggle & strength meter
 * - Role card selection
 * - Avatar name sync
 */

// ========== Section Navigation ==========
function scrollToSection(id, el) {
    var target = document.getElementById(id);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('.stg-nav-item').forEach(function (n) {
        n.classList.remove('active');
    });
    if (el) {
        el.classList.add('active');
    }
}

// ========== Avatar Preview ==========
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        if (file.size > 1024 * 1024) {
            AdminModal.status({
              title: 'Dosya Hatası',
              message: 'Dosya boyutu 1MB\'dan küçük olmalıdır',
              type: 'warning'
            });
            input.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('avatarImg').src = e.target.result;
            var removeBtn = document.getElementById('removeAvatarBtn');
            if (removeBtn) {
                removeBtn.classList.remove('d-none');
            }
        };
        reader.readAsDataURL(file);
    }
}

function removeAvatar() {
    var firstName = document.getElementById('firstName');
    var lastName = document.getElementById('lastName');
    var first = (firstName && firstName.value.trim()) || 'Yeni';
    var last = (lastName && lastName.value.trim()) || 'Kullanıcı';

    document.getElementById('avatarImg').src =
        'https://ui-avatars.com/api/?name=' + encodeURIComponent(first) + '+' + encodeURIComponent(last) + '&background=14b8a6&color=fff&size=120';

    var removeBtn = document.getElementById('removeAvatarBtn');
    if (removeBtn) {
        removeBtn.classList.add('d-none');
    }

    var avatarInput = document.getElementById('avatarInput');
    if (avatarInput) {
        avatarInput.value = '';
    }

    var removeFlag = document.getElementById('removeAvatarFlag');
    if (removeFlag) {
        removeFlag.value = '1';
    }
}

// ========== Password Toggle ==========
function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    var icon = btn.querySelector('i');
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// ========== Password Strength ==========
function initPasswordStrength() {
    var passwordInput = document.getElementById('password');
    if (!passwordInput) return;

    passwordInput.addEventListener('input', function () {
        var val = this.value;
        var strengthEl = document.getElementById('passwordStrength');
        var bars = strengthEl ? strengthEl.querySelectorAll('.uf-strength-bar') : [];
        var text = document.getElementById('strengthText');

        if (!val) {
            if (strengthEl) strengthEl.classList.add('d-none');
            return;
        }
        if (strengthEl) strengthEl.classList.remove('d-none');

        var score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        var labels = ['Çok Zayıf', 'Zayıf', 'Orta', 'Güçlü'];
        var colors = ['var(--neon-red, #ef4444)', 'var(--neon-orange)', 'var(--neon-blue)', 'var(--neon-green)'];

        bars.forEach(function (bar, i) {
            if (i < score) {
                bar.style.background = colors[score - 1];
                bar.style.opacity = '1';
            } else {
                bar.style.background = 'var(--border-color)';
                bar.style.opacity = '0.3';
            }
        });

        if (text) {
            text.textContent = labels[score - 1] || 'Çok Zayıf';
            text.style.color = colors[score - 1] || colors[0];
        }
    });
}

// ========== Role Card Selection ==========
function initRoleCards() {
    document.querySelectorAll('.uf-role-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var checkbox = this.querySelector('.uf-role-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                this.classList.toggle('active', checkbox.checked);
            }
        });
    });
}

// ========== Avatar Name Sync ==========
function initAvatarNameSync() {
    var firstNameInput = document.getElementById('firstName');
    var lastNameInput = document.getElementById('lastName');

    if (!firstNameInput || !lastNameInput) return;

    function updateAvatar() {
        var first = firstNameInput.value.trim() || 'Yeni';
        var last = lastNameInput.value.trim() || 'Kullanıcı';

        var nameEl = document.getElementById('avatarUserName');
        if (nameEl) {
            nameEl.textContent = first + ' ' + last;
        }

        var avatarInput = document.getElementById('avatarInput');
        var hasCustomFile = avatarInput && avatarInput.files && avatarInput.files.length > 0;

        if (!hasCustomFile) {
            var img = document.getElementById('avatarImg');
            var currentSrc = img ? img.src : '';
            if (currentSrc.indexOf('ui-avatars.com') !== -1) {
                img.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(first) + '+' + encodeURIComponent(last) + '&background=14b8a6&color=fff&size=120';
            }
        }
    }

    firstNameInput.addEventListener('input', updateAvatar);
    lastNameInput.addEventListener('input', updateAvatar);
}

// ========== Scroll Spy for Nav ==========
function initScrollSpy() {
    var sections = document.querySelectorAll('.stg-content .card-dark[id]');
    var navItems = document.querySelectorAll('.stg-nav-item');

    if (!sections.length || !navItems.length) return;

    window.addEventListener('scroll', function () {
        var scrollPos = window.scrollY + 120;

        sections.forEach(function (section) {
            var top = section.offsetTop;
            var height = section.offsetHeight;
            var id = section.getAttribute('id');

            if (scrollPos >= top && scrollPos < top + height) {
                navItems.forEach(function (item) {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === '#' + id) {
                        item.classList.add('active');
                    }
                });
            }
        });
    });
}

// ========== Init ==========
document.addEventListener('DOMContentLoaded', function () {
    initPasswordStrength();
    initRoleCards();
    initAvatarNameSync();
    initScrollSpy();
});
