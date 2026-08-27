'use strict';

/**
 * /admin/kampanyalar/yeni ve /duzenle — kampanya formu.
 *
 * Üç iş yapar: seçilen hedef kitleye ait bölümü açar, elle alıcı satırlarını
 * ekler/çıkarır ve yüklenen Excel dosyasını kaydetmeden okuyup ne bulduğunu
 * gösterir.
 */
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Yalnızca seçilen hedef kitlenin bölümü görünür: elle liste yazan kişiden
     * Excel dosyası istenmemeli.
     */
    function initAudiencePanels() {
        var radios = document.querySelectorAll('.js-audience-radio');

        if (!radios.length) {
            return;
        }

        function apply() {
            var selected = document.querySelector('.js-audience-radio:checked');
            var value = selected ? selected.value : null;

            document.querySelectorAll('.js-audience-panel').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-audience') !== value;
            });
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', apply);
        });

        apply();
    }

    /**
     * Elle alıcı girişi: her satır kendi e-posta, ad ve soyad alanını taşır.
     *
     * Satır adları dizinle gidiyor (manual_rows[0][email]); silme sonrası
     * dizinler yeniden numaralanıyor ki sunucuya boşluksuz bir dizi ulaşsın.
     */
    function initManualRows() {
        var list = document.getElementById('manualRows');
        var addButton = document.getElementById('manualAddRow');
        var template = document.getElementById('manualRowTemplate');
        var counter = document.getElementById('manualRowsCount');

        if (!list || !addButton || !template) {
            return;
        }

        function rows() {
            return Array.prototype.slice.call(list.querySelectorAll('[data-row]'));
        }

        function renumber() {
            rows().forEach(function (row, index) {
                row.querySelectorAll('input[name]').forEach(function (input) {
                    input.name = input.name.replace(/manual_rows\[[^\]]*\]/, 'manual_rows[' + index + ']');
                });
            });
        }

        function refresh() {
            var all = rows();

            // Tek satır kaldığında silme düğmesi işe yaramaz: form en az bir
            // satır göstermek zorunda.
            all.forEach(function (row) {
                var button = row.querySelector('[data-remove-row]');

                if (button) {
                    button.disabled = all.length === 1;
                }
            });

            if (counter) {
                var filled = all.filter(function (row) {
                    var email = row.querySelector('input[type="email"]');

                    return email && email.value.trim() !== '';
                }).length;

                counter.textContent = filled > 0 ? filled + ' alıcı' : '';
            }

            renumber();
        }

        addButton.addEventListener('click', function () {
            var markup = template.innerHTML.replace(/__INDEX__/g, String(rows().length));
            var holder = document.createElement('div');

            holder.innerHTML = markup.trim();

            var row = holder.firstElementChild;

            list.appendChild(row);
            refresh();

            var email = row.querySelector('input[type="email"]');

            if (email) {
                email.focus();
            }
        });

        list.addEventListener('click', function (event) {
            var button = event.target.closest('[data-remove-row]');

            if (!button || button.disabled) {
                return;
            }

            var row = button.closest('[data-row]');

            if (row) {
                row.remove();
                refresh();
            }
        });

        list.addEventListener('input', function (event) {
            if (event.target.type === 'email') {
                refresh();
            }
        });

        refresh();
    }

    /**
     * Excel/CSV önizlemesi.
     *
     * Sütunlar başlık adına göre eşleşiyor, başlık yoksa tahmin ediliyor;
     * dosyanın doğru okunduğu kampanya kaydedilmeden görülmeli, yoksa hata
     * ancak gönderim anında ortaya çıkar.
     */
    function initImportPreview() {
        var input = document.getElementById('recipient_file');
        var box = document.getElementById('importPreview');

        if (!input || !box) {
            return;
        }

        function show(html) {
            box.innerHTML = html;
            box.classList.remove('d-none');
        }

        function hide() {
            box.innerHTML = '';
            box.classList.add('d-none');
        }

        function escape(value) {
            var node = document.createElement('span');

            node.textContent = value === null || value === undefined ? '' : String(value);

            return node.innerHTML;
        }

        function renderResult(data) {
            var rows = (data.sample || []).map(function (row) {
                return '<tr><td>' + escape(row.email) + '</td><td>' + escape(row.name || '—') + '</td></tr>';
            }).join('');

            var hidden = data.total - (data.sample || []).length;

            var summary = '<div class="cmp-import-preview__head">'
                + '<i class="bi bi-check-circle-fill"></i>'
                + '<strong>' + data.total + ' geçerli alıcı bulundu</strong>'
                + (data.invalid > 0
                    ? '<span class="cmp-import-preview__warn">' + data.invalid + ' satır geçersiz adres olduğu için atlandı</span>'
                    : '')
                + '</div>';

            show(summary
                + '<div class="cmp-import-preview__table"><table><thead><tr><th>E-posta</th><th>Ad</th></tr></thead>'
                + '<tbody>' + rows + '</tbody></table></div>'
                + (hidden > 0 ? '<p class="cmp-import-preview__more">ve ' + hidden + ' alıcı daha</p>' : ''));
        }

        function renderError(message) {
            show('<div class="cmp-import-preview__head cmp-import-preview__head--error">'
                + '<i class="bi bi-exclamation-triangle-fill"></i>'
                + '<strong>' + escape(message) + '</strong></div>');
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            if (!file) {
                hide();

                return;
            }

            show('<div class="cmp-import-preview__head">'
                + '<i class="bi bi-arrow-repeat bk-spin"></i>'
                + '<strong>Dosya okunuyor…</strong></div>');

            var payload = new FormData();

            payload.append('recipient_file', file);

            fetch(input.dataset.previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: payload
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (response) {
                    if (response.ok) {
                        renderResult(response.data);

                        return;
                    }

                    // Doğrulama hatası mesajı errors altında, okuma hatası
                    // doğrudan message alanında geliyor.
                    var errors = response.data.errors || {};
                    var first = errors.recipient_file ? errors.recipient_file[0] : null;

                    renderError(first || response.data.message || 'Dosya okunamadı.');
                })
                .catch(function (error) {
                    renderError(error.message);
                });
        });
    }

    initAudiencePanels();
    initManualRows();
    initImportPreview();
})();

/**
 * Attachments are removed by their own form, not the campaign form, so the file
 * is gone even if the campaign edit is abandoned.
 */
function removeAttachment(id) {
    var form = document.getElementById('attachmentForm');

    if (!form || !window.campaignAttachmentUrl) {
        return;
    }

    form.action = window.campaignAttachmentUrl.replace('ATTACHMENT_ID', id);
    form.submit();
}
