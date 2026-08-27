'use strict';

/**
 * /admin/mail-logs — bekleyen maili şimdi gönderme ve yeniden gönderme.
 *
 * İkisi de aynı akış: onay al, isteği at, sonucu bildir, listeyi tazele.
 * Ayrıldıkları yer yalnızca metinler — kullanıcı "bekleyeni gönderme" ile
 * "gideni tekrar gönderme" arasındaki farkı onay ekranında görmeli.
 */
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function describe(button) {
        var recipient = button.dataset.recipient || '';
        var subject = button.dataset.subject || '';

        return subject ? subject + ' → ' + recipient : recipient;
    }

    function run(button, texts) {
        AdminModal.confirm({
            title: texts.title,
            message: texts.message,
            detailTitle: describe(button),
            type: texts.type,
            confirmText: texts.confirmText,
            confirmIcon: texts.confirmIcon
        }).then(function (confirmed) {
            if (!confirmed) return;

            var icon = button.querySelector('i');
            var originalIcon = icon ? icon.className : null;

            button.disabled = true;

            if (icon) {
                icon.className = 'bi bi-arrow-repeat bk-spin';
            }

            fetch(button.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() }
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (response) {
                    var success = response.ok && response.data.success;

                    AdminModal.status({
                        title: success ? 'Başarılı' : 'Gönderilemedi',
                        message: response.data.message || (success ? 'İşlem tamamlandı.' : 'E-posta gönderilemedi.'),
                        type: success ? 'success' : 'danger'
                    });

                    if (success) {
                        // Durum ve sayaçlar değişti; liste tazelensin.
                        window.setTimeout(function () { window.location.reload(); }, 1200);

                        return;
                    }

                    button.disabled = false;

                    if (icon && originalIcon) {
                        icon.className = originalIcon;
                    }
                })
                .catch(function (error) {
                    AdminModal.status({
                        title: 'Gönderilemedi',
                        message: error.message,
                        type: 'danger'
                    });

                    button.disabled = false;

                    if (icon && originalIcon) {
                        icon.className = originalIcon;
                    }
                });
        });
    }

    document.querySelectorAll('.ml-send-now').forEach(function (button) {
        button.addEventListener('click', function () {
            run(button, {
                title: 'Şimdi Gönder',
                message: 'Bu mail kuyrukta sırasını bekliyor. Beklemeden şimdi gönderilsin mi?',
                type: 'warning',
                confirmText: 'Evet, Gönder',
                confirmIcon: 'bi bi-send-fill'
            });
        });
    });

    document.querySelectorAll('.ml-resend').forEach(function (button) {
        button.addEventListener('click', function () {
            run(button, {
                title: 'Yeniden Gönder',
                message: 'Bu e-posta aynı alıcıya yeniden gönderilecek.',
                type: 'warning',
                confirmText: 'Evet, Gönder',
                confirmIcon: 'bi bi-arrow-repeat'
            });
        });
    });

    // Arama kutusunda Enter formu göndersin.
    var search = document.getElementById('mailLogSearch');

    if (search) {
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                document.getElementById('filterForm').submit();
            }
        });
    }
})();
