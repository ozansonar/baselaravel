/**
 * Görsel Kütüphanesi — Dropzone.js entegrasyonu (asenkron paralel upload).
 *
 * Davranış:
 *   - Form-level ortak ayarlar (medya tipi, tema, mood, ...) sağdaki form'dan alınır
 *   - Her dosya AYRI POST (parallelUploads: 6) ile gönderilir
 *   - Video tespit edilirse: poster önceden upload edilmiş olmalı (poster_path)
 *   - Başarı/hata sonuçları alt panele inline biriker, sayfa yenilenmez
 *
 * Backend endpoint'leri:
 *   POST /admin/gorsel-kutuphanesi/yukle/tek    — tek dosya işler
 *   POST /admin/gorsel-kutuphanesi/yukle/poster — video poster (önceden) yüklenir
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var dzElement = document.getElementById('ilDropzone');
    if (!dzElement || typeof Dropzone === 'undefined') {
        return;
    }

    Dropzone.autoDiscover = false;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    // Form-level inputs
    var inputMediaType  = document.getElementById('ilUploadMediaType');
    var inputTheme      = document.getElementById('ilUploadTheme');
    var inputMood       = document.getElementById('ilUploadMood');
    var inputSeason     = document.getElementById('ilUploadSeason');
    var inputVisibility = document.getElementById('ilUploadVisibility');
    var inputTags       = document.getElementById('ilUploadTags');
    var inputDesc       = document.getElementById('ilUploadDescription');
    var inputAlt        = document.getElementById('ilUploadAlt');

    // Poster
    var posterFile   = document.getElementById('ilPosterFile');
    var posterStatus = document.getElementById('ilPosterStatus');
    var uploadedPosterPath = null;

    var clearBtn = document.getElementById('ilDzClearBtn');
    var counter  = document.getElementById('ilDzCounter');

    // ── Poster upload (video için pre-requisite) ──
    if (posterFile) {
        posterFile.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) return;

            posterStatus.innerHTML = '<i class="bi bi-hourglass-split text-warning"></i> <small>Poster yükleniyor...</small>';

            var fd = new FormData();
            fd.append('poster', file);
            fd.append('_token', csrfToken);

            fetch('/admin/gorsel-kutuphanesi/yukle/poster', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (r) {
                if (r.ok && r.data.success) {
                    uploadedPosterPath = r.data.poster_path;
                    posterStatus.classList.add('is-loaded');
                    posterStatus.innerHTML =
                        '<img src="' + escAttr(r.data.thumbnail) + '" alt="Poster">' +
                        '<div class="mt-2"><i class="bi bi-check-circle-fill text-success"></i> ' +
                        '<small class="text-success">Poster yüklendi — şimdi video yükleyebilirsin</small></div>';
                } else {
                    posterStatus.innerHTML =
                        '<i class="bi bi-exclamation-triangle text-danger"></i> ' +
                        '<small class="text-danger">' + escAttr(r.data.message || 'Yüklenemedi') + '</small>';
                }
            })
            .catch(function (e) {
                posterStatus.innerHTML =
                    '<i class="bi bi-exclamation-triangle text-danger"></i> ' +
                    '<small class="text-danger">İstek başarısız: ' + escAttr(e.message) + '</small>';
            });
        });
    }

    // ── Dropzone init ──
    var dropzone = new Dropzone(dzElement, {
        url: dzElement.getAttribute('action'),
        method: 'POST',
        paramName: 'file',
        maxFilesize: 100,                                // MB (video için)
        acceptedFiles: 'image/jpeg,image/png,image/webp,video/mp4,video/quicktime',
        maxFiles: 60,
        parallelUploads: 6,                              // Her dosya AYRI POST, 6 paralel
        uploadMultiple: false,                           // Her dosya ayrı request
        autoProcessQueue: true,
        addRemoveLinks: true,
        timeout: 300000,                                 // 5 dk (büyük video için)
        thumbnailWidth: 140,
        thumbnailHeight: 140,
        thumbnailMethod: 'crop',
        headers: { 'X-CSRF-TOKEN': csrfToken },

        dictDefaultMessage: '',  // HTML message kendi tasarımımızda
        dictRemoveFile: 'Kaldır',
        dictCancelUpload: 'İptal',
        dictFileTooBig: 'Dosya çok büyük ({{filesize}}MB). Maks: {{maxFilesize}}MB.',
        dictInvalidFileType: 'Sadece görsel (jpg/png/webp) veya video (mp4/mov) yüklenebilir.',
        dictMaxFilesExceeded: 'En fazla {{maxFiles}} dosya yüklenebilir.',
        dictResponseError: 'Sunucu hatası: {{statusCode}}',

        accept: function (file, done) {
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            var isVideo = ext === 'mp4' || ext === 'mov';

            // Video için poster zorunluluğu
            if (isVideo && !uploadedPosterPath) {
                done('Video yüklemek için önce sol paneldeki "Video Posteri" alanına thumbnail yükle.');
                return;
            }

            done();
        },

        init: function () {
            var dz = this;

            // Her POST'a form-level fields'ı ekle
            this.on('sending', function (file, xhr, formData) {
                formData.append('media_type',  inputMediaType ? inputMediaType.value : 'feed');
                formData.append('theme',       inputTheme ? inputTheme.value : '');
                formData.append('mood',        inputMood ? inputMood.value : '');
                formData.append('season',      inputSeason ? inputSeason.value : '');
                formData.append('visibility',  inputVisibility ? inputVisibility.value : 'private');
                formData.append('tags',        inputTags ? inputTags.value : '');
                formData.append('description', inputDesc ? inputDesc.value : '');
                formData.append('alt_text',    inputAlt ? inputAlt.value : '');

                // Video için poster path
                var ext = (file.name.split('.').pop() || '').toLowerCase();
                if ((ext === 'mp4' || ext === 'mov') && uploadedPosterPath) {
                    formData.append('poster_path', uploadedPosterPath);
                }
            });

            this.on('success', function (file, response) {
                if (response && response.success) {
                    appendResultRow(file, true, response.message || 'eklendi');
                } else {
                    appendResultRow(file, false, response.message || 'Bilinmeyen sonuç');
                }
                updateCounter();
            });

            this.on('error', function (file, message, xhr) {
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

                appendResultRow(file, false, msg);
                updateCounter();
            });

            this.on('addedfile', updateCounter);
            this.on('removedfile', updateCounter);
            this.on('queuecomplete', function () {
                if (counter) {
                    var success = dz.files.filter(function (f) { return f.status === 'success'; }).length;
                    var failed  = dz.files.filter(function (f) { return f.status === 'error'; }).length;
                    counter.innerHTML = success + ' başarılı · ' + failed + ' hatalı · <a href="/admin/gorsel-kutuphanesi" class="text-teal">Listeye Git →</a>';
                }
            });
        },
    });

    // Tümünü kaldır
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (dropzone.files.length === 0) return;
            if (!confirm('Kuyruktaki ' + dropzone.files.length + ' dosya silinsin mi?')) return;
            dropzone.removeAllFiles(true);
            updateCounter();
        });
    }

    // ── Helpers ──

    function updateCounter() {
        if (!counter) return;
        var total = dropzone.files.length;
        var success = dropzone.files.filter(function (f) { return f.status === 'success'; }).length;
        var queued  = dropzone.files.filter(function (f) { return f.status === 'queued' || f.status === 'uploading'; }).length;

        counter.textContent = total + ' dosya' + (queued > 0 ? ' · ' + queued + ' bekliyor' : '') + (success > 0 ? ' · ' + success + ' yüklendi' : '');
        if (clearBtn) clearBtn.disabled = total === 0;
    }

    function appendResultRow(file, success, message) {
        var resultEl = document.getElementById('ilUploadResult');
        if (!resultEl) return;

        var list = resultEl.querySelector('.il-upload-result__list');
        if (!list) {
            resultEl.classList.remove('d-none');
            resultEl.classList.add('il-upload-result');
            resultEl.innerHTML =
                '<div class="il-upload-result__title">' +
                '<span><i class="bi bi-list-ul me-1"></i> Yükleme Sonuçları</span>' +
                '<button type="button" class="il-upload-result__clear" id="ilResultClearBtn">Temizle</button>' +
                '</div>' +
                '<ul class="il-upload-result__list"></ul>';
            list = resultEl.querySelector('.il-upload-result__list');

            var clearResultBtn = document.getElementById('ilResultClearBtn');
            if (clearResultBtn) {
                clearResultBtn.addEventListener('click', function () {
                    resultEl.classList.add('d-none');
                    resultEl.innerHTML = '';
                });
            }
        }

        var li = document.createElement('li');
        li.className = success ? 'text-success' : 'text-danger';

        var icon = document.createElement('i');
        icon.className = success
            ? 'bi bi-check-circle-fill me-2'
            : 'bi bi-x-circle-fill me-2';
        li.appendChild(icon);

        var nameStrong = document.createElement('strong');
        nameStrong.textContent = file.name;
        li.appendChild(nameStrong);

        if (message) {
            var msgSpan = document.createTextNode(' — ' + message);
            li.appendChild(msgSpan);
        }

        list.appendChild(li);
        list.scrollTop = list.scrollHeight;
    }

    function escAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
