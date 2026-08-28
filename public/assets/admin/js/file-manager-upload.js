/**
 * FileManager toplu yükleme — Dropzone.js entegrasyonu.
 *
 * Dropzone kütüphanesi admin layout'ta zaten yüklü
 * (assets/vendor/dropzone/dropzone.min.js). Bu dosya /admin/files
 * sayfasındaki bırakma alanını ve yükleme kuyruğunu kurar.
 *
 * Tasarım kararı: önizlemeler bırakma alanının İÇİNDE değil, altındaki
 * ayrı kuyruk panelinde (previewsContainer) satır satır listelenir.
 * Böylece bırakma alanı hep aynı yükseklikte kalır, 14 dosya bırakınca
 * sayfa üç ekran boyuna uzamaz ve her satırın kendi ilerleme çubuğu olur.
 *
 * Davranış:
 *   - Bırakılan her dosya AYRI POST ile gider (6 paralel)
 *   - Backend tek dosya işler, JSON döner (duplicate bayrağı dahil)
 *   - Satır durumları: bekliyor → yükleniyor → başarılı / kopya / hata
 *   - Kuyruk başlığında toplam ilerleme, sayaçlar ve sonuç özeti
 *   - Yükleme bitince sayfa YENİLENMEZ: yalnız alttaki liste sunucudan
 *     tazelenir (FileManagerList.refresh). Böylece başarılı dosyalar listeye
 *     anında düşerken hatalı satırlar iletisiyle birlikte ekranda kalır
 *
 * CLAUDE.md uyumu:
 *   - Vanilla JS, jQuery yok
 *   - alert/confirm yok → AdminModal
 *   - CSRF meta'dan
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var dzElement = document.getElementById('fileManagerDropzone');

    if (!dzElement || typeof Dropzone === 'undefined') {
        return;
    }

    var queuePanel = document.getElementById('fmgrQueue');
    var queueList  = document.getElementById('fmgrQueueList');
    var queueFill  = document.getElementById('fmgrQueueFill');
    var countTotal = document.getElementById('fmgrCountTotal');
    var countOk    = document.getElementById('fmgrCountOk');
    var countDup   = document.getElementById('fmgrCountDup');
    var countErr   = document.getElementById('fmgrCountErr');
    var summaryEl  = document.getElementById('fmgrQueueSummary');
    var clearBtn   = document.getElementById('fmgrClearAllBtn');
    var reloadBtn  = document.getElementById('fmgrReloadBtn');

    Dropzone.autoDiscover = false;

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var uploadUrl = dzElement.getAttribute('data-upload-url');

    /** Uzantıya göre Bootstrap Icons sınıfı — görsel olmayan dosyaların satır ikonu. */
    var ICONS = {
        pdf: 'bi-file-earmark-pdf', doc: 'bi-file-earmark-word', docx: 'bi-file-earmark-word',
        xls: 'bi-file-earmark-excel', xlsx: 'bi-file-earmark-excel', csv: 'bi-file-earmark-spreadsheet',
        ppt: 'bi-file-earmark-slides', pptx: 'bi-file-earmark-slides', txt: 'bi-file-earmark-text',
        zip: 'bi-file-earmark-zip', mp4: 'bi-file-earmark-play', mp3: 'bi-file-earmark-music',
    };

    var PREVIEW_TEMPLATE = [
        '<div class="fmgr-item">',
        '  <div class="fmgr-item__thumb">',
        '    <img data-dz-thumbnail alt="">',
        '    <i class="bi bi-file-earmark"></i>',
        '  </div>',
        '  <div class="fmgr-item__body">',
        '    <div class="fmgr-item__top">',
        '      <span class="fmgr-item__name" data-dz-name></span>',
        '      <span class="fmgr-item__size" data-dz-size></span>',
        '    </div>',
        '    <div class="fmgr-item__track"><span class="fmgr-item__fill" data-dz-uploadprogress></span></div>',
        '    <div class="fmgr-item__msg" data-dz-errormessage></div>',
        '  </div>',
        '  <div class="fmgr-item__state">',
        '    <i class="bi bi-check-circle-fill fmgr-item__ok"></i>',
        '    <i class="bi bi-exclamation-triangle-fill fmgr-item__err"></i>',
        '    <i class="bi bi-arrow-repeat fmgr-item__dup"></i>',
        '  </div>',
        '  <button type="button" class="fmgr-item__remove" data-dz-remove title="Kaldır" aria-label="Kaldır">',
        '    <i class="bi bi-x-lg"></i>',
        '  </button>',
        '</div>',
    ].join('');

    var dropzone = new Dropzone(dzElement, {
        url: uploadUrl,
        method: 'POST',
        paramName: 'file',
        maxFilesize: 50,                                  // MB (server max:51200 KB ile uyumlu)
        acceptedFiles: '.jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip,.mp4,.mp3',
        maxFiles: 50,
        parallelUploads: 6,                               // 6 paralel ayrı POST/dosya
        uploadMultiple: false,                            // her dosya ayrı request — ZORUNLU
        autoProcessQueue: true,                           // dosya eklendiği anda otomatik upload
        addRemoveLinks: false,                            // kaldırma düğmesi şablonda
        previewsContainer: queueList,                     // önizlemeler bırakma alanının dışında
        previewTemplate: PREVIEW_TEMPLATE,
        timeout: 180000,                                  // 3 dk (büyük video dosyaları için)
        thumbnailWidth: 84,
        thumbnailHeight: 84,
        thumbnailMethod: 'crop',
        headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken.content } : {},

        // Türkçe mesajlar
        dictRemoveFile: 'Kaldır',
        dictCancelUpload: 'İptal',
        dictFileTooBig: 'Dosya çok büyük ({{filesize}} MB). En fazla {{maxFilesize}} MB.',
        dictInvalidFileType: 'Bu dosya türü desteklenmiyor.',
        dictMaxFilesExceeded: 'En fazla {{maxFiles}} dosya yükleyebilirsin.',
        dictResponseError: 'Sunucu hatası: {{statusCode}}',

        init: function () {
            var dz = this;

            this.on('addedfile', function (file) {
                if (!file.previewElement) {
                    return;
                }

                var ext = (file.name.split('.').pop() || '').toLowerCase();
                var icon = file.previewElement.querySelector('.fmgr-item__thumb i');
                if (icon && ICONS[ext]) {
                    icon.className = 'bi ' + ICONS[ext];
                }

                refreshQueue();
            });

            this.on('removedfile', refreshQueue);
            this.on('processing', refreshQueue);

            this.on('sending', function (file, xhr, formData) {
                if (csrfToken) {
                    formData.append('_token', csrfToken.content);
                }
            });

            this.on('totaluploadprogress', function (progress) {
                if (queueFill) {
                    queueFill.style.width = progress + '%';
                }
            });

            this.on('success', function (file, response) {
                if (!response || !response.success) {
                    return;
                }

                file.fileId = response.file_id;
                file.isDuplicate = !!response.duplicate;

                if (file.isDuplicate && file.previewElement) {
                    file.previewElement.classList.add('is-duplicate');
                    setMessage(file, 'Bu dosya zaten yüklüydü — mevcut kayıt kullanıldı.');
                }

                refreshQueue();
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

                setMessage(file, msg);
                refreshQueue();
            });

            this.on('queuecomplete', function () {
                var ok  = successCount();
                var err = dz.getFilesWithStatus(Dropzone.ERROR).length;

                if (queueFill) {
                    queueFill.style.width = '100%';
                }

                refreshQueue();

                // Özet satır içi yazılıyor; showToast() bu projede AdminModal'ı
                // açıyor ve engelleyici kutu tam da sonucun görüleceği listeyi
                // kapatıyordu.
                setSummary(
                    ok + ' dosya yüklendi' + (err ? ' · ' + err + ' dosya başarısız' : ''),
                    err ? 'err' : 'ok'
                );

                if (ok === 0) {
                    return;
                }

                // Sayfa YENİLENMİYOR: yenilenirse hata satırları kaybolur ve
                // kullanıcı hangi dosyanın neden düştüğünü göremez. Bunun yerine
                // yalnız liste gövdesi sunucudan tazeleniyor; başarılı dosyalar
                // aşağıdaki listeye anında düşüyor, hatalı satırlar kuyrukta kalıyor.
                if (window.FileManagerList && typeof FileManagerList.refresh === 'function') {
                    FileManagerList.refresh().then(function (refreshed) {
                        if (refreshed) {
                            clearSettledFiles();
                        } else if (reloadBtn) {
                            // Tazeleme tutmadı (oturum düşmüş olabilir) — son çare.
                            reloadBtn.classList.remove('d-none');
                        }
                    });
                } else if (reloadBtn) {
                    reloadBtn.classList.remove('d-none');
                }
            });
        },
    });

    function setMessage(file, text) {
        var el = file.previewElement
            ? file.previewElement.querySelector('[data-dz-errormessage]')
            : null;

        if (el) {
            el.textContent = text;
        }
    }

    function successCount() {
        return dropzone.getFilesWithStatus(Dropzone.SUCCESS).length;
    }

    /**
     * Sorunsuz yüklenenleri kuyruktan düşürür — dosya artık aşağıdaki listede
     * görünüyor, satırın ekranda kalmasının bir anlamı yok. Kopya ve hatalı
     * satırlar kalır: ikisi de kullanıcının okuması gereken bir ileti taşıyor.
     */
    function clearSettledFiles() {
        setTimeout(function () {
            dropzone.getFilesWithStatus(Dropzone.SUCCESS).forEach(function (file) {
                if (!file.isDuplicate) {
                    dropzone.removeFile(file);
                }
            });

            refreshQueue();
        }, 1500);
    }

    function duplicateCount() {
        return dropzone.files.filter(function (f) { return f.isDuplicate; }).length;
    }

    function setSummary(text, tone) {
        if (!summaryEl) {
            return;
        }

        summaryEl.textContent = text;
        summaryEl.classList.toggle('fmgr-queue__summary--err', tone === 'err');
    }

    function setCount(el, value) {
        if (!el) {
            return;
        }

        el.querySelector('[data-fmgr-value]').textContent = String(value);
        el.classList.toggle('is-empty', value === 0);
    }

    function refreshQueue() {
        var total = dropzone.files.length;

        if (queuePanel) {
            queuePanel.classList.toggle('d-none', total === 0);
        }

        var dup = duplicateCount();

        if (countTotal) {
            countTotal.textContent = String(total);
        }

        setCount(countOk, successCount() - dup);
        setCount(countDup, dup);
        setCount(countErr, dropzone.getFilesWithStatus(Dropzone.ERROR).length);

        if (total === 0) {
            if (queueFill) {
                queueFill.style.width = '0%';
            }

            setSummary('', 'ok');
        }
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (dropzone.files.length === 0) {
                return;
            }

            var doClear = function () {
                dropzone.removeAllFiles(true);
                refreshQueue();
            };

            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Kuyruk Temizlensin Mi?',
                    message: 'Kuyruktaki <strong>' + dropzone.files.length + '</strong> satır ekrandan kaldırılacak. '
                        + 'Yüklenmiş dosyalar silinmez, sadece bu liste temizlenir.',
                    type: 'warning',
                    confirmText: 'Evet, Temizle',
                }).then(function (confirmed) {
                    if (confirmed) {
                        doClear();
                    }
                });
            } else {
                doClear();
            }
        });
    }

    if (reloadBtn) {
        reloadBtn.addEventListener('click', function () {
            window.location.reload();
        });
    }

    refreshQueue();
});
