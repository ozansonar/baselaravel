/**
 * FileManager toplu yükleme — Dropzone.js entegrasyonu.
 *
 * Mevcut admin layout'ta Dropzone library'si zaten yüklü
 * (assets/vendor/dropzone/dropzone.min.js). Bu dosya sadece
 * /admin/files sayfasındaki #fileManagerDropzone'ı init eder.
 *
 * Davranış:
 *   - Dropzone'a sürüklenen her dosya AYRI POST (paralel 4) ile gider
 *   - Backend tek dosya işler, JSON döner (duplicate flag dahil)
 *   - Hatalı dosya için kırmızı X mesajı (diğerleri devam eder)
 *   - Duplicate dosya için sarı uyarı + mevcut kaydın URL'i
 *   - "Tümünü Yükle" butonuyla manuel tetikleme
 *
 * CLAUDE.md uyumu:
 *   - Vanilla JS, jQuery yok
 *   - AdminModal.confirm/status (alert/confirm yasak)
 *   - CSRF meta'dan
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var dzElement = document.getElementById('fileManagerDropzone');
    var startBtn = document.getElementById('fmgrStartUploadBtn');
    var clearBtn = document.getElementById('fmgrClearAllBtn');

    if (!dzElement || typeof Dropzone === 'undefined') {
        return;
    }

    Dropzone.autoDiscover = false;

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var uploadUrl = dzElement.getAttribute('data-upload-url');

    var dropzone = new Dropzone(dzElement, {
        url: uploadUrl,
        method: 'POST',
        paramName: 'file',
        maxFilesize: 50,                                 // MB (server max:51200 KB ile uyumlu)
        acceptedFiles: '.jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip,.mp4,.mp3',
        maxFiles: 50,
        parallelUploads: 6,                              // 6 paralel ayrı POST/dosya
        uploadMultiple: false,                            // her dosya ayrı request — ZORUNLU
        autoProcessQueue: true,                           // dosya eklendiği anda otomatik upload
        addRemoveLinks: true,
        timeout: 180000,                                  // 3 dk (büyük video dosyaları için)
        thumbnailWidth: 100,
        thumbnailHeight: 100,
        thumbnailMethod: 'crop',
        headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken.content } : {},

        // Türkçe mesajlar
        dictDefaultMessage: 'Dosyaları buraya sürükle veya tıkla<br><small>JPG/PNG/WebP/GIF, PDF/DOC/XLSX/CSV, ZIP, MP4/MP3 — max 50 MB</small>',
        dictRemoveFile: 'Kaldır',
        dictCancelUpload: 'İptal',
        dictFileTooBig: 'Dosya çok büyük ({{filesize}}MB). Maks: {{maxFilesize}}MB.',
        dictInvalidFileType: 'Bu dosya türü desteklenmiyor.',
        dictMaxFilesExceeded: 'En fazla {{maxFiles}} dosya yükleyebilirsin.',
        dictResponseError: 'Sunucu hatası: {{statusCode}}',

        init: function () {
            var dz = this;

            this.on('sending', function (file, xhr, formData) {
                if (csrfToken) {
                    formData.append('_token', csrfToken.content);
                }
            });

            this.on('success', function (file, response) {
                if (!response || !response.success) {
                    return;
                }

                file.fileId = response.file_id;

                // Duplicate ise sarı uyarı, normal upload ise yeşil
                if (response.duplicate) {
                    file.previewElement.classList.add('dz-warning');
                    if (typeof showToast === 'function') {
                        showToast(file.name + ' — daha önce yüklenmiş, mevcut kayıt kullanıldı.', 'warning');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(file.name + ' yüklendi.', 'success');
                    }
                }
            });

            this.on('error', function (file, message) {
                var msg;
                if (typeof message === 'string') {
                    msg = message;
                } else if (message && message.errors) {
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

                if (typeof showToast === 'function') {
                    showToast(file.name + ': ' + msg, 'error');
                }
            });

            this.on('addedfile', function () {
                // autoProcessQueue: true olduğu için Dropzone otomatik upload başlatır.
                // "Tümünü Yükle" butonu kaldırıldı (gereksiz).
            });

            this.on('queuecomplete', function () {
                var successCount = dz.getFilesWithStatus(Dropzone.SUCCESS).length;
                var errorCount = dz.getFilesWithStatus(Dropzone.ERROR).length;

                if (window.AdminModal && typeof AdminModal.status === 'function') {
                    AdminModal.status({
                        title: 'Yükleme tamamlandı',
                        message: successCount + ' başarılı, ' + errorCount + ' başarısız.',
                        type: errorCount === 0 ? 'success' : 'warning',
                    });
                }

                // Başarılıları kuyruktan temizle, hatalı kalsın (kullanıcı görsün)
                dz.getFilesWithStatus(Dropzone.SUCCESS).forEach(function (f) {
                    dz.removeFile(f);
                });

                if (successCount > 0) {
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            });
        },
    });

    // "Tümünü Yükle" butonu — autoProcessQueue: true olduğu için artık gereksiz.
    // Geriye dönük uyum için DOM'da varsa pasif bırak (kullanıcı bilsin).
    if (startBtn) {
        startBtn.style.display = 'none';
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (dropzone.files.length === 0) return;

            var doClear = function () {
                dropzone.removeAllFiles(true);
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
});
