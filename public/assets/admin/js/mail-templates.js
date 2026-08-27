'use strict';

/**
 * /admin/mail-templates — kart listesinden e-posta önizleme.
 *
 * Süzme ve sıralama sunucuda yapılıyor; burada kalan tek iş, düzenleme
 * ekranına gitmeden şablonun kayıtlı hâlini örnek verilerle göstermek.
 * Önizleme uç noktası gövde gönderilmediğinde şablonun kendi içeriğini
 * kullanıyor, o yüzden istek boş gidiyor.
 */
(function () {
    var buttons = document.querySelectorAll('.mt-preview-btn');

    if (!buttons.length) {
        return;
    }

    var modalElement = document.getElementById('mtPreviewModal');
    var titleElement = document.getElementById('mtPreviewTitle');
    var subjectElement = document.getElementById('mtPreviewSubject');
    var frame = document.getElementById('mtPreviewFrame');

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function renderPreview(html) {
        var doc = frame.contentDocument || frame.contentWindow.document;

        doc.open();
        doc.write(html);
        doc.close();
    }

    function showError(message) {
        AdminModal.status({
            title: 'Önizleme açılamadı',
            message: message || 'Şablon önizlemesi yüklenirken bir hata oluştu.',
            type: 'danger'
        });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var icon = button.querySelector('i');
            var originalIcon = icon ? icon.className : null;

            button.disabled = true;

            if (icon) {
                icon.className = 'bi bi-arrow-repeat bk-spin';
            }

            fetch(button.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: '{}'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Sunucu ' + response.status + ' döndü.');
                    }

                    return response.json();
                })
                .then(function (data) {
                    titleElement.textContent = button.dataset.name || 'E-posta Önizleme';
                    subjectElement.textContent = data.subject || '';
                    renderPreview(data.html || '');

                    new bootstrap.Modal(modalElement).show();
                })
                .catch(function (error) {
                    showError(error.message);
                })
                .finally(function () {
                    button.disabled = false;

                    if (icon && originalIcon) {
                        icon.className = originalIcon;
                    }
                });
        });
    });
})();
