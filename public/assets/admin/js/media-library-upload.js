/**
 * MediaLibrary toplu yükleme — Dropzone.js entegrasyonu.
 *
 * Mevcut admin layout'ta Dropzone library'si zaten yüklü
 * (assets/vendor/dropzone/dropzone.min.js). Bu dosya sadece show.blade.php
 * sayfasındaki #mediaLibraryDropzone'ı init eder.
 *
 * Davranış:
 *   - Sayfa üstündeki #mlibProductSelect ürün adını belirler
 *   - Dropzone'a sürüklenen her dosya AYRI POST (paralel 4) ile gider
 *   - Backend tek dosya işler, JSON döner
 *   - Client-side kare 1:1 + min 1080×1080 kontrolü (UX)
 *   - Hatalı dosya için kırmızı X mesajı (diğerleri devam eder)
 *
 * CLAUDE.md uyumu:
 *   - Vanilla JS, jQuery yok
 *   - AdminModal.confirm/status (alert/confirm yasak)
 *   - CSRF meta'dan
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var dzElement = document.getElementById('mediaLibraryDropzone');
    var productSelect = document.getElementById('mlibProductSelect');
    var startBtn = document.getElementById('mlibStartUploadBtn');
    var clearBtn = document.getElementById('mlibClearAllBtn');

    if (!dzElement || typeof Dropzone === 'undefined') {
        return;
    }

    Dropzone.autoDiscover = false;

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var uploadUrl = dzElement.getAttribute('data-upload-url');

    var dropzone = new Dropzone(dzElement, {
        url: uploadUrl,
        method: 'POST',
        paramName: 'image',
        maxFilesize: 4,                                  // MB
        acceptedFiles: 'image/jpeg,image/png,image/webp',
        maxFiles: 50,
        parallelUploads: 6,                              // her dosya AYRI POST, 6 paralel
        uploadMultiple: false,                            // her dosya ayrı request — ZORUNLU
        autoProcessQueue: true,                           // sürükle-bırak → anında upload
        addRemoveLinks: true,
        timeout: 60000,
        thumbnailWidth: 120,
        thumbnailHeight: 120,
        thumbnailMethod: 'crop',
        headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken.content } : {},

        // Türkçe mesajlar
        dictDefaultMessage: 'Kare görselleri buraya sürükle veya tıkla<br><small>JPG / PNG / WebP, max 4 MB, min 1024×1024</small>',
        dictRemoveFile: 'Kaldır',
        dictCancelUpload: 'İptal',
        dictFileTooBig: 'Dosya çok büyük ({{filesize}}MB). Maks: {{maxFilesize}}MB.',
        dictInvalidFileType: 'Sadece JPG / PNG / WebP yüklenebilir.',
        dictMaxFilesExceeded: 'En fazla {{maxFiles}} dosya yüklenebilir.',
        dictResponseError: 'Sunucu hatası: {{statusCode}}',

        // Client-side kare doğrulama (sunucu da doğrular ama UX hızlansın)
        accept: function (file, done) {
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(img.src);
                if (img.width !== img.height) {
                    done('Kare değil — 1:1 oran zorunlu (' + img.width + '×' + img.height + ').');
                } else if (img.width < 1024) {
                    done('Çok küçük — min 1024×1024 (yüklenen: ' + img.width + '×' + img.height + ').');
                } else {
                    done();
                }
            };
            img.onerror = function () {
                URL.revokeObjectURL(img.src);
                done('Görsel okunamadı.');
            };
            img.src = URL.createObjectURL(file);
        },

        init: function () {
            var dz = this;

            // Her POST'a ürün adını ekle
            this.on('sending', function (file, xhr, formData) {
                if (productSelect) {
                    formData.append('product_name', productSelect.value);
                }
                if (csrfToken) {
                    formData.append('_token', csrfToken.content);
                }
            });

            this.on('success', function (file, response) {
                if (response && response.success) {
                    file.assetId = response.asset_id;

                    // Inline result panel — yeşil satır
                    appendResultRow(file, true, 'yüklendi.');

                    // DOM'a yeni asset tile prepend (sayfa yenileme YOK)
                    prependAssetTile(response);

                    // Empty state varsa gizle
                    var emptyState = document.getElementById('mlibEmptyState');
                    if (emptyState) emptyState.classList.add('d-none');

                    // Toast özet
                    if (typeof showToast === 'function') {
                        showToast(file.name + ' yüklendi.', 'success');
                    }

                    // Dropzone preview'u temizle (kullanıcı yeni dosya yükleyebilsin)
                    setTimeout(function () { dz.removeFile(file); }, 800);
                }
            });

            this.on('error', function (file, message) {
                var msg;
                if (typeof message === 'string') {
                    msg = message;
                } else if (message && message.errors) {
                    // Laravel validation errors
                    msg = Object.values(message.errors).flat().join(' ');
                } else if (message && message.message) {
                    msg = message.message;
                } else {
                    msg = 'Yükleme hatası.';
                }

                var errorEl = file.previewElement
                    ? file.previewElement.querySelector('[data-dz-errormessage]')
                    : null;
                if (errorEl) errorEl.textContent = msg;

                // Inline result panel — kırmızı satır
                appendResultRow(file, false, msg);

                if (typeof showToast === 'function') {
                    showToast(file.name + ': ' + msg, 'error');
                }
            });

            this.on('addedfile', function () {
                // autoProcessQueue: true olduğu için Dropzone otomatik upload başlatır.
                // queuecomplete event'i ARTIK kullanılmıyor — her dosya success/error
                // event'inde inline panel + DOM prepend yapar. Sayfa yenileme YOK,
                // peş peşe yükleme akıcı.
            });
        },
    });

    // "Tümünü Yükle" butonu — autoProcessQueue: true olduğu için artık gereksiz.
    // Geriye dönük uyum için DOM'da varsa pasif bırak (eski cache görünüm).
    if (startBtn) {
        startBtn.style.display = 'none';
    }

    // "Tümünü Kaldır"
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (dropzone.files.length === 0) return;

            var doClear = function () {
                dropzone.removeAllFiles(true);
                if (startBtn) startBtn.disabled = true;
            };

            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Tümünü Kaldır?',
                    message: 'Kuyruktaki ' + dropzone.files.length + ' dosya silinecek (sunucuya yüklenmemiş olanlar). Devam edilsin mi?',
                    type: 'warning',
                    confirmText: 'Evet, Kaldır',
                }).then(function (confirmed) {
                    if (confirmed) doClear();
                });
            } else {
                doClear();
            }
        });
    }

    // Ürün seçimi değişirse kuyruğu uyar
    if (productSelect) {
        productSelect.addEventListener('change', function () {
            var queued = dropzone.getQueuedFiles().length;
            if (queued === 0) return;

            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Ürün değişti',
                    message: 'Kuyrukta ' + queued + ' dosya var. Yeni ürüne yüklemek için onayla, eski ürüne yüklemek için iptal et.',
                    type: 'warning',
                    confirmText: 'Yeni ürüne yükle',
                }).then(function (confirmed) {
                    if (!confirmed) {
                        // Eski seçime geri dön
                        productSelect.value = productSelect.dataset.previousValue || productSelect.options[0].value;
                    } else {
                        productSelect.dataset.previousValue = productSelect.value;
                    }
                });
            }
        });

        // İlk değeri sakla
        productSelect.dataset.previousValue = productSelect.value;
    }

    // ─── Helper'lar ───

    /**
     * Inline sonuç paneline yeni satır ekler.
     * İlk satırsa container'ı initialize eder; sonraki satırlar appendChild ile birikir.
     */
    function appendResultRow(file, success, message) {
        var resultEl = document.getElementById('mlibUploadResult');
        if (!resultEl) return;

        // İlk satırsa container'ı kur
        var list = resultEl.querySelector('.mlib-upload-result__list');
        if (!list) {
            resultEl.classList.remove('d-none');
            resultEl.classList.remove('mlib-upload-result--success');
            resultEl.classList.add('mlib-upload-result--mixed');
            resultEl.innerHTML =
                '<div class="mlib-upload-result__title">' +
                '<i class="bi bi-cloud-arrow-up me-1"></i> Yükleme Sonuçları' +
                '<button type="button" class="btn-link mlib-upload-result__clear" onclick="document.getElementById(\'mlibUploadResult\').classList.add(\'d-none\');document.getElementById(\'mlibUploadResult\').innerHTML=\'\'">Temizle</button>' +
                '</div>' +
                '<ul class="mlib-upload-result__list mb-0"></ul>';
            list = resultEl.querySelector('.mlib-upload-result__list');
        }

        // XSS güvenliği — DOM API ile metin set et (innerHTML değil)
        var li = document.createElement('li');
        li.className = success ? 'mlib-result-row mlib-result-row--success' : 'mlib-result-row mlib-result-row--error';

        var icon = document.createElement('i');
        icon.className = success ? 'bi bi-check-circle-fill text-success me-1' : 'bi bi-x-circle-fill text-danger me-1';
        li.appendChild(icon);

        var nameStrong = document.createElement('strong');
        nameStrong.textContent = file.name;
        li.appendChild(nameStrong);

        if (message) {
            var msgSpan = document.createTextNode(' — ' + message);
            li.appendChild(msgSpan);
        }

        list.appendChild(li);
    }

    /**
     * Asset grid'inin başına yeni tile ekler.
     * Server JSON response'undan minimum HTML oluşturur (uygunsa Blade ile uyumlu).
     */
    function prependAssetTile(asset) {
        var grid = document.getElementById('mlibAssetGrid');
        if (!grid) return;

        var col = document.createElement('div');
        col.className = 'col-xl-3 col-lg-4 col-md-6';
        col.dataset.assetId = String(asset.asset_id);

        var safeProductName = (asset.product_name || 'Genel').replace(/'/g, '\\\'');

        col.innerHTML =
            '<div class="mlib-asset-tile">' +
                '<a href="' + escAttr(asset.image_url) + '" class="mlib-asset-tile__preview" data-mlib-lightbox data-caption="' + escAttr(asset.product_name || 'Görsel') + '" title="Büyüt">' +
                    '<img src="' + escAttr(asset.thumbnail_url) + '" alt="' + escAttr(asset.product_name || 'Görsel') + '" loading="lazy" class="img-fluid">' +
                '</a>' +
                '<div class="mlib-asset-tile__meta">' +
                    '<div class="mlib-asset-tile__usage"><i class="bi bi-arrow-repeat me-1"></i>0× kullanıldı</div>' +
                    '<div class="mlib-asset-tile__last-used"><i class="bi bi-dot me-1"></i>Henüz kullanılmamış</div>' +
                '</div>' +
                '<div class="mlib-asset-tile__actions">' +
                    '<button type="button" class="usr-action-btn" ' +
                        'onclick="mlibOpenEdit(' + asset.asset_id + ', \'\', \'' + safeProductName + '\', true)" ' +
                        'title="Düzenle"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="usr-action-btn ail-action-danger" ' +
                        'data-asset-id="' + asset.asset_id + '" ' +
                        'data-asset-title="#' + asset.asset_id + '" ' +
                        'onclick="mlibConfirmDelete(this)" ' +
                        'title="Sil"><i class="bi bi-trash"></i></button>' +
                '</div>' +
            '</div>';

        grid.insertBefore(col, grid.firstChild);

        // Subtle highlight animation
        col.classList.add('mlib-asset-tile-new');
        setTimeout(function () { col.classList.remove('mlib-asset-tile-new'); }, 1500);
    }

    /**
     * HTML attribute escape — XSS koruması.
     */
    function escAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
