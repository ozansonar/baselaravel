/**
 * Instagram Posts — FORM (create + edit) sayfası.
 *
 * İçerik:
 *   1. Caption character counter (renk eşikli: warn 1800+, danger 1950+)
 *   2. Hashtag preview
 *   3. Live Instagram preview (caption, hashtag, ana görsel, dots, fake likes)
 *   4. Dropzone (ana görsel + carousel) drag/drop + file input
 *   5. Image dimension validation (Reels/Image/Story aspect ratio)
 *   6. Crop choice modal + CropperJS manuel kırpma
 *   7. AI Caption üretici (POST /admin/instagram-posts/generate-caption)
 *   8. AI Image üretici modal (POST /admin/instagram-posts/generate-image)
 *   9. Media type seçici (data-ig-context show/hide) + FB toggle (Story disable)
 *   10. Form action butonları (taslak / planla / şimdi paylaş) + publish_now confirm
 *   11. Draft autosave (localStorage; caption/hashtag/scheduled_at; 5 sn debounce)
 *
 * Index sayfası (bulk/list) için instagram-posts-index.js ayrı dosya.
 */
(function () {
    'use strict';

    // ──────────────────────────────────────────────────────────────
    // (1) Caption counter — renk eşikli
    // ──────────────────────────────────────────────────────────────
    function updateCaptionCounter(el) {
        var counterId = el.getAttribute('data-ig-counter') || 'captionCounter';
        var counter = document.getElementById(counterId);
        var len = el.value.length;
        if (counter) counter.textContent = len;

        var warnAt   = parseInt(el.getAttribute('data-ig-counter-warn') || '1800', 10);
        var dangerAt = parseInt(el.getAttribute('data-ig-counter-danger') || '1950', 10);

        // Counter parent rengini değiştir (form-text)
        if (counter) {
            counter.classList.remove('text-warning', 'text-danger', 'fw-bold');
            if (len >= dangerAt) {
                counter.classList.add('text-danger', 'fw-bold');
            } else if (len >= warnAt) {
                counter.classList.add('text-warning');
            }
        }

        updatePreviewCaption(el.value);
    }

    // ──────────────────────────────────────────────────────────────
    // (2) Hashtag preview
    // ──────────────────────────────────────────────────────────────
    function renderHashtagPreview(raw) {
        var preview = document.getElementById('hashtagPreview');
        if (preview) {
            var parts = raw.split(/[\s,]+/);
            var tags = [];
            for (var i = 0; i < parts.length; i++) {
                var t = parts[i].replace(/^#+/, '').trim();
                if (t) tags.push('#' + t);
            }
            preview.textContent = tags.slice(0, 30).join(' ');
        }
        updatePreviewHashtags(raw);
    }

    // ──────────────────────────────────────────────────────────────
    // (3) Live Instagram preview helpers
    // ──────────────────────────────────────────────────────────────
    function updatePreviewCaption(text) {
        var captionTextEl = document.getElementById('igPreviewCaptionText');
        var overlayTextEl = document.getElementById('igPreviewOverlayCaptionText');
        var cleaned = (text || '').trim();

        if (captionTextEl) {
            if (cleaned === '') {
                captionTextEl.textContent = 'Caption yazılınca burada görünecek...';
                captionTextEl.style.color = '#aaa';
            } else {
                captionTextEl.style.color = '';
                if (cleaned.length > 125) {
                    captionTextEl.innerHTML = '<span class="ig-caption-truncated"></span>' +
                                              '<span class="ig-caption-more">...devamını gör</span>';
                    captionTextEl.querySelector('.ig-caption-truncated').textContent = cleaned.substring(0, 125);
                } else {
                    captionTextEl.textContent = cleaned;
                }
            }
        }

        // Reels/Story overlay (mode aktif değilse de güncelliyoruz, geçişte hazır olur)
        if (overlayTextEl) {
            overlayTextEl.textContent = cleaned.length > 100 ? cleaned.substring(0, 100) + '…' : cleaned;
        }
    }

    function updatePreviewHashtags(raw) {
        var hashEl = document.getElementById('igPreviewHashtags');
        var overlayHashEl = document.getElementById('igPreviewOverlayHashtags');

        [hashEl, overlayHashEl].forEach(function (el) { if (el) el.innerHTML = ''; });

        if (! raw || raw.trim() === '') return;

        var parts = raw.split(/[\s,]+/);
        parts.forEach(function (p) {
            var t = p.replace(/^#+/, '').trim();
            if (! t) return;
            if (hashEl) {
                var a = document.createElement('a');
                a.href = '#';
                a.textContent = '#' + t;
                hashEl.appendChild(a);
                hashEl.appendChild(document.createTextNode(' '));
            }
            if (overlayHashEl) {
                var b = document.createElement('a');
                b.href = '#';
                b.textContent = '#' + t;
                overlayHashEl.appendChild(b);
                overlayHashEl.appendChild(document.createTextNode(' '));
            }
        });
    }

    function updatePreviewMainImage(src) {
        var media = document.getElementById('igPreviewMedia');
        var placeholder = document.getElementById('igPreviewPlaceholder');
        if (! media) return;

        var img = document.getElementById('igPreviewMainImg');
        if (! img) {
            img = document.createElement('img');
            img.id = 'igPreviewMainImg';
            img.alt = 'Preview';
            var dots = document.getElementById('igPreviewDots');
            if (dots) {
                media.insertBefore(img, dots);
            } else {
                media.appendChild(img);
            }
        }
        img.src = src;
        if (placeholder) placeholder.style.display = 'none';
    }

    function updatePreviewCarouselDots(count, activeIndex) {
        var dots = document.getElementById('igPreviewDots');
        if (! dots) return;

        if (count <= 1) {
            dots.classList.add('is-hidden');
            dots.innerHTML = '';
            return;
        }
        dots.classList.remove('is-hidden');
        dots.innerHTML = '';
        for (var i = 0; i < count; i++) {
            var d = document.createElement('span');
            d.className = 'ig-preview-dot' + (i === (activeIndex || 0) ? ' active' : '');
            dots.appendChild(d);
        }
    }

    // Fake likes — Server'da rand() yerine client'ta üret (SSR cache'i bozmasın)
    function setFakeLikesOnce() {
        var likesEl = document.getElementById('igPreviewLikes');
        if (! likesEl) return;
        var n = Math.floor(Math.random() * (230 - 45 + 1)) + 45;
        likesEl.textContent = n + ' beğenme';
    }

    // Geri uyumluluk için window'a aç (eski script'ler / harici çağrılar)
    window.updateCaptionCounter = updateCaptionCounter;
    window.renderHashtagPreview = renderHashtagPreview;
    window.updatePreviewMainImage = updatePreviewMainImage;
    window.updatePreviewCarouselDots = updatePreviewCarouselDots;

    // ──────────────────────────────────────────────────────────────
    // DOMContentLoaded
    // ──────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {

        setFakeLikesOnce();

        // ── Counter + hashtag input bind (artık inline handler yok) ──
        var captionEl = document.getElementById('captionInput');
        if (captionEl) {
            captionEl.addEventListener('input', function () { updateCaptionCounter(this); });
            updateCaptionCounter(captionEl); // initial
        }

        var hashtagsEl = document.getElementById('hashtagsInput');
        if (hashtagsEl) {
            hashtagsEl.addEventListener('input', function () { renderHashtagPreview(this.value); });
            renderHashtagPreview(hashtagsEl.value); // initial
        }

        // ── Form action butonları (data-ig-action) ──
        document.querySelectorAll('[data-ig-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var actionEl = document.getElementById('formAction');
                if (actionEl) actionEl.value = btn.getAttribute('data-ig-action');
            });
        });

        // ──────────────────────────────────────────────────────────
        // Helpers
        // ──────────────────────────────────────────────────────────
        function humanSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function bindDropzone(zoneEl, onFiles) {
            if (! zoneEl) return;

            ['dragenter', 'dragover'].forEach(function (evt) {
                zoneEl.addEventListener(evt, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    zoneEl.classList.add('ig-drag-active');
                });
            });

            ['dragleave', 'drop'].forEach(function (evt) {
                zoneEl.addEventListener(evt, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    zoneEl.classList.remove('ig-drag-active');
                });
            });

            zoneEl.addEventListener('drop', function (e) {
                var files = (e.dataTransfer && e.dataTransfer.files) || [];
                if (files.length > 0) onFiles(files);
            });
        }

        // ──────────────────────────────────────────────────────────
        // (4-5-6) Ana görsel dropzone + image dim validation + crop
        // ──────────────────────────────────────────────────────────
        var mainDropzone     = document.getElementById('mainImageDropzone');
        var imageInput       = document.getElementById('imageInput');
        var mainDzContent    = document.getElementById('mainDropzoneContent');
        var mainDzSelected   = document.getElementById('mainDropzoneSelected');
        var mainDzPreview    = document.getElementById('newImagePreview');
        var mainDzFileName   = document.getElementById('mainDropzoneFileName');
        var mainDzFileSize   = document.getElementById('mainDropzoneFileSize');
        var mainDzRemove     = document.getElementById('mainDropzoneRemove');

        function setMainImage(file) {
            if (! file || ! mainDzPreview) return;
            var url = URL.createObjectURL(file);
            mainDzPreview.src = url;
            if (mainDzFileName) mainDzFileName.textContent = file.name;
            if (mainDzFileSize) mainDzFileSize.textContent = humanSize(file.size);
            if (mainDzContent) mainDzContent.classList.add('is-hidden');
            if (mainDzSelected) mainDzSelected.classList.remove('is-hidden');

            updatePreviewMainImage(url);
            updateAllPreviewDots();
        }

        function clearMainImage() {
            if (imageInput) imageInput.value = '';
            if (mainDzContent) mainDzContent.classList.remove('is-hidden');
            if (mainDzSelected) mainDzSelected.classList.add('is-hidden');
        }

        // Carousel image count → dots
        function updateAllPreviewDots() {
            var count = 0;
            if (imageInput && imageInput.files && imageInput.files.length > 0) count = 1;
            else if (document.getElementById('igPreviewMainImg')) count = 1;

            if (carouselInput && carouselInput.files) {
                count += carouselInput.files.length;
            }

            var existingImgs = document.querySelectorAll('.ig-carousel-item input[type="checkbox"]');
            existingImgs.forEach(function (cb) { if (! cb.checked) count++; });

            updatePreviewCarouselDots(count, 0);
        }

        function getCurrentMediaType() {
            var checked = document.querySelector('input[name="media_type"]:checked');
            return checked ? checked.value : 'image';
        }

        function readImageDimensions(file) {
            return new Promise(function (resolve, reject) {
                if (! file || ! file.type || file.type.indexOf('image/') !== 0) {
                    resolve(null);
                    return;
                }
                var img = new Image();
                var url = URL.createObjectURL(file);
                img.onload = function () {
                    URL.revokeObjectURL(url);
                    resolve({ width: this.naturalWidth, height: this.naturalHeight });
                };
                img.onerror = function () {
                    URL.revokeObjectURL(url);
                    reject(new Error('Görsel okunamadı (bozuk dosya?)'));
                };
                img.src = url;
            });
        }

        function validateImageForMediaType(dims, mediaType) {
            if (! dims) return null;

            var width = dims.width;
            var height = dims.height;
            var ratio = width / height;

            if (width < 320 || height < 320) {
                return 'Görsel çok küçük (' + width + '×' + height + '). Minimum 320×320 px gerekli.';
            }
            if (width > 1440) {
                return 'Görsel çok geniş (' + width + ' px). Maksimum 1440 px genişlik. Lütfen yeniden boyutlandırın.';
            }

            if (mediaType === 'image') {
                if (ratio < 0.8 || ratio > 1.91) {
                    return 'Feed Post için aspect ratio uygun değil (mevcut: ' + ratio.toFixed(2) + '). ' +
                           'Instagram Feed 4:5 (dikey, 0.80) ile 1.91:1 (yatay, 1.91) arasında oran kabul eder. ' +
                           'Önerilen: 1080×1080 (kare) veya 1080×1350 (dikey).';
                }
            } else if (mediaType === 'story') {
                if (ratio < 0.5 || ratio > 0.7) {
                    return 'Story için aspect ratio uygun değil (mevcut: ' + ratio.toFixed(2) + '). ' +
                           'Story 9:16 (≈0.56) civarı dikey görsel ister, 0.50 — 0.70 arası kabul edilir. ' +
                           'Önerilen: 1080×1920 px.';
                }
            }
            return null;
        }

        function processSelectedImage(file) {
            if (! file) return;

            readImageDimensions(file)
                .then(function (dims) {
                    var mediaType = getCurrentMediaType();
                    var error = validateImageForMediaType(dims, mediaType);

                    if (error) {
                        if (mediaType === 'reels') {
                            if (typeof AdminModal !== 'undefined') {
                                AdminModal.status({ title: 'Görsel uyumsuz', message: error, type: 'danger' });
                            }
                            if (imageInput) imageInput.value = '';
                            clearMainImage();
                            return;
                        }
                        openCropChoiceModal(file, mediaType, error);
                        return;
                    }

                    setMainImage(file);
                })
                .catch(function (err) {
                    if (typeof AdminModal !== 'undefined') {
                        AdminModal.status({ title: 'Görsel okunamadı', message: err.message || 'Bilinmeyen hata', type: 'danger' });
                    }
                    if (imageInput) imageInput.value = '';
                    clearMainImage();
                });
        }

        // ── Crop choice + CropperJS ────────────────────────────────
        var cropChoiceModalEl = document.getElementById('igCropChoiceModal');
        var cropperModalEl    = document.getElementById('igCropperModal');
        var pendingCropFile = null;
        var pendingCropMediaType = 'image';
        var cropperInstance = null;
        var cropperApplied = false;
        var cropAppliedAny = false;

        function openCropChoiceModal(file, mediaType, errorMessage) {
            if (! cropChoiceModalEl || typeof bootstrap === 'undefined') {
                if (typeof AdminModal !== 'undefined') {
                    AdminModal.status({ title: 'Görsel uyumsuz', message: errorMessage, type: 'danger' });
                }
                if (imageInput) imageInput.value = '';
                clearMainImage();
                return;
            }

            pendingCropFile = file;
            pendingCropMediaType = mediaType;

            var msgEl = document.getElementById('igCropChoiceMessage');
            if (msgEl) msgEl.textContent = errorMessage;

            bootstrap.Modal.getOrCreateInstance(cropChoiceModalEl).show();
        }

        function uploadCropResult(formData) {
            var token = document.querySelector('meta[name="csrf-token"]');
            return fetch(window.IG_AUTOFIX_URL || '/admin/instagram-posts/auto-fix-image', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                },
                body: formData,
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (! result.ok || ! result.data || ! result.data.success) {
                    var msg = (result.data && result.data.message) ? result.data.message : 'Görsel işlenemedi.';
                    throw new Error(msg);
                }
                return result.data;
            });
        }

        function applyCroppedResult(data) {
            cropAppliedAny = true;

            var aiImagePathInput = document.getElementById('aiImagePath');
            if (aiImagePathInput) aiImagePathInput.value = data.image_path || '';

            if (imageInput) imageInput.value = '';

            if (mainDzContent)  mainDzContent.classList.add('is-hidden');
            if (mainDzSelected) mainDzSelected.classList.remove('is-hidden');
            if (mainDzPreview)  mainDzPreview.src = data.url + '?t=' + Date.now();
            if (mainDzFileName) mainDzFileName.textContent = data.mode === 'manual' ? 'Manuel kırpılmış' : 'Otomatik kırpılmış';
            if (mainDzFileSize) mainDzFileSize.textContent = '';

            updatePreviewMainImage(data.url + '?t=' + Date.now());
            updateAllPreviewDots();
        }

        // Otomatik Kırp
        var autoBtn = document.getElementById('igCropAutoBtn');
        if (autoBtn) {
            autoBtn.addEventListener('click', function () {
                if (! pendingCropFile) return;
                autoBtn.disabled = true;
                autoBtn.innerHTML = '<div class="ig-crop-choice-icon"><span class="spinner-border spinner-border-sm"></span></div>' +
                                    '<div class="ig-crop-choice-info"><strong>Kırpılıyor…</strong><small>Lütfen bekle.</small></div>';

                var fd = new FormData();
                fd.append('image', pendingCropFile);
                fd.append('media_type', pendingCropMediaType);
                fd.append('mode', 'auto');

                uploadCropResult(fd)
                    .then(function (data) {
                        applyCroppedResult(data);
                        bootstrap.Modal.getInstance(cropChoiceModalEl)?.hide();
                        if (typeof AdminModal !== 'undefined') {
                            AdminModal.status({ title: 'Görsel düzeltildi', message: data.message || 'Otomatik kırpma uygulandı.', type: 'success' });
                        }
                    })
                    .catch(function (err) {
                        if (typeof AdminModal !== 'undefined') {
                            AdminModal.status({ title: 'Kırpma başarısız', message: err.message, type: 'danger' });
                        }
                    })
                    .finally(function () {
                        autoBtn.disabled = false;
                        autoBtn.innerHTML = '<div class="ig-crop-choice-icon"><i class="bi bi-magic"></i></div>' +
                                            '<div class="ig-crop-choice-info"><strong>Otomatik Kırp</strong><small>Sistem ortalayarak Instagram standardına getirir. (Önerilen — hızlı)</small></div>';
                    });
            });
        }

        // Elle Kırp
        var manualBtn = document.getElementById('igCropManualBtn');
        if (manualBtn) {
            manualBtn.addEventListener('click', function () {
                if (! pendingCropFile || ! cropperModalEl) return;
                if (typeof Cropper === 'undefined') {
                    if (typeof AdminModal !== 'undefined') {
                        AdminModal.status({
                            title: 'CropperJS yüklenemedi',
                            message: 'Manuel kırpma için CropperJS lib yüklenemedi. Otomatik kırpma kullanabilirsin.',
                            type: 'danger',
                        });
                    }
                    return;
                }

                bootstrap.Modal.getInstance(cropChoiceModalEl)?.hide();

                var cropperImg = document.getElementById('igCropperImage');
                var ratioLabel = document.getElementById('igCropperRatioLabel');
                var errorEl    = document.getElementById('igCropperError');

                if (errorEl) { errorEl.classList.add('d-none'); errorEl.textContent = ''; }

                var lockedRatio = pendingCropMediaType === 'story' ? (9 / 16) : 1;
                if (ratioLabel) {
                    ratioLabel.textContent = pendingCropMediaType === 'story' ? '(9:16 Story)' : '(1:1 / Feed)';
                }

                if (cropperInstance) {
                    cropperInstance.destroy();
                    cropperInstance = null;
                }

                cropperApplied = false;

                if (cropperImg) {
                    var url = URL.createObjectURL(pendingCropFile);
                    cropperImg.src = url;
                    cropperImg.onload = function () {
                        cropperInstance = new Cropper(cropperImg, {
                            aspectRatio: lockedRatio,
                            viewMode: 1,
                            dragMode: 'move',
                            autoCropArea: 0.9,
                            background: false,
                            zoomable: true,
                            scalable: false,
                            rotatable: false,
                        });
                    };
                }

                bootstrap.Modal.getOrCreateInstance(cropperModalEl).show();
            });
        }

        // CropperJS Apply
        var cropApplyBtn = document.getElementById('igCropperApplyBtn');
        if (cropApplyBtn) {
            cropApplyBtn.addEventListener('click', function () {
                if (! cropperInstance || ! pendingCropFile) return;

                var errorEl = document.getElementById('igCropperError');
                var label   = cropApplyBtn.querySelector('[data-label]');

                cropApplyBtn.disabled = true;
                if (label) label.textContent = 'Kırpılıyor…';

                var cropData = cropperInstance.getData(true);
                var fd = new FormData();
                fd.append('image', pendingCropFile);
                fd.append('media_type', pendingCropMediaType);
                fd.append('mode', 'manual');
                fd.append('crop_x',      Math.max(0, Math.round(cropData.x)));
                fd.append('crop_y',      Math.max(0, Math.round(cropData.y)));
                fd.append('crop_width',  Math.round(cropData.width));
                fd.append('crop_height', Math.round(cropData.height));

                uploadCropResult(fd)
                    .then(function (data) {
                        cropperApplied = true;
                        applyCroppedResult(data);
                        bootstrap.Modal.getInstance(cropperModalEl)?.hide();
                        if (typeof AdminModal !== 'undefined') {
                            AdminModal.status({ title: 'Görsel kırpıldı', message: data.message || 'Manuel kırpma uygulandı.', type: 'success' });
                        }
                    })
                    .catch(function (err) {
                        if (errorEl) {
                            errorEl.textContent = err.message;
                            errorEl.classList.remove('d-none');
                        }
                    })
                    .finally(function () {
                        cropApplyBtn.disabled = false;
                        if (label) label.textContent = 'Kırp ve Kullan';
                    });
            });
        }

        // Cropper modal kapanışı
        if (cropperModalEl) {
            cropperModalEl.addEventListener('hidden.bs.modal', function () {
                if (cropperInstance) {
                    cropperInstance.destroy();
                    cropperInstance = null;
                }
                var cropperImg = document.getElementById('igCropperImage');
                if (cropperImg && cropperImg.src) {
                    URL.revokeObjectURL(cropperImg.src);
                    cropperImg.src = '';
                }

                if (! cropperApplied) {
                    pendingCropFile = null;
                    if (imageInput) imageInput.value = '';
                    clearMainImage();
                }
                cropperApplied = false;
            });
        }

        // Choice modal kapanışı
        if (cropChoiceModalEl) {
            cropChoiceModalEl.addEventListener('hidden.bs.modal', function () {
                if (cropperModalEl && cropperModalEl.classList.contains('show')) return;
                if (cropAppliedAny) {
                    cropAppliedAny = false;
                    return;
                }
                pendingCropFile = null;
                if (imageInput) imageInput.value = '';
                clearMainImage();
            });
        }

        // Image input + dropzone
        if (imageInput) {
            imageInput.addEventListener('change', function (e) {
                var file = e.target.files && e.target.files[0];
                processSelectedImage(file);
            });
        }
        if (mainDzRemove) {
            mainDzRemove.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                clearMainImage();
            });
        }
        bindDropzone(mainDropzone, function (files) {
            if (imageInput) {
                var dt = new DataTransfer();
                dt.items.add(files[0]);
                imageInput.files = dt.files;
            }
            processSelectedImage(files[0]);
        });

        // ── Carousel multi dropzone ────────────────────────────────
        var carouselDropzone = document.getElementById('carouselDropzone');
        var carouselInput    = document.getElementById('additionalImagesInput');
        var carouselContent  = document.getElementById('carouselDropzoneContent');
        var carouselPreview  = document.getElementById('carouselPreviewWrap');

        function renderCarouselPreviews(files) {
            if (! carouselPreview) return;
            carouselPreview.innerHTML = '';
            Array.from(files).forEach(function (f) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.alt = f.name;
                img.title = f.name + ' (' + humanSize(f.size) + ')';
                carouselPreview.appendChild(img);
            });
            if (carouselContent) {
                if (files.length > 0) {
                    carouselContent.innerHTML =
                        '<i class="bi bi-check-circle-fill ig-dropzone-icon ig-dropzone-icon-success"></i>' +
                        '<strong>' + files.length + ' görsel seçildi</strong>' +
                        '<small>Yeni dosya seçmek için tıkla, eskiler kaybolur</small>';
                }
            }
            updateAllPreviewDots();
        }

        document.querySelectorAll('.ig-carousel-item input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', updateAllPreviewDots);
        });

        // Initial dots (edit mode'da mevcut görseller var)
        setTimeout(function () {
            try {
                var existing = document.getElementById('igPreviewMainImg');
                var existingExtras = existing && existing.dataset.existingImages
                    ? JSON.parse(existing.dataset.existingImages)
                    : [];
                if (existing) {
                    var totalExisting = 1 + existingExtras.length;
                    updatePreviewCarouselDots(totalExisting, 0);
                }
            } catch (e) { /* ignore */ }
        }, 0);

        function processCarouselFiles(files) {
            var arr = Array.from(files || []);
            if (arr.length === 0) {
                renderCarouselPreviews([]);
                return;
            }

            Promise.all(arr.map(function (f) {
                return readImageDimensions(f).catch(function () { return null; });
            }))
            .then(function (allDims) {
                var failedIndex = -1;
                var failedMessage = null;
                for (var i = 0; i < allDims.length; i++) {
                    var err = validateImageForMediaType(allDims[i], 'image');
                    if (err) { failedIndex = i; failedMessage = err; break; }
                }

                if (failedIndex >= 0) {
                    if (typeof AdminModal !== 'undefined') {
                        AdminModal.status({
                            title: 'Carousel görseli uyumsuz',
                            message: '#' + (failedIndex + 1) + ' (' + arr[failedIndex].name + '): ' + failedMessage,
                            type: 'danger',
                        });
                    }
                    if (carouselInput) carouselInput.value = '';
                    renderCarouselPreviews([]);
                    return;
                }
                renderCarouselPreviews(arr);
            });
        }

        if (carouselInput) {
            carouselInput.addEventListener('change', function (e) {
                processCarouselFiles(e.target.files || []);
            });
        }
        bindDropzone(carouselDropzone, function (files) {
            if (carouselInput) {
                var dt = new DataTransfer();
                Array.from(files).forEach(function (f) { dt.items.add(f); });
                carouselInput.files = dt.files;
            }
            processCarouselFiles(files);
        });

        // ──────────────────────────────────────────────────────────
        // (10) Form submit confirm (publish_now)
        // ──────────────────────────────────────────────────────────
        var form = document.getElementById('instagramPostForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                var actionEl = document.getElementById('formAction');
                if (! actionEl) return;
                if (actionEl.value !== 'publish_now') return;
                if (typeof AdminModal === 'undefined' || form.dataset.confirmed === '1') return;

                e.preventDefault();
                AdminModal.confirm({
                    title: 'Hemen Yayınla',
                    message: 'Bu gönderi Instagram hesabınızda hemen yayınlanacak. Devam edilsin mi?',
                    type: 'warning',
                    confirmText: 'Evet, Yayınla',
                    confirmIcon: 'bi bi-send-fill',
                }).then(function (ok) {
                    if (ok) {
                        form.dataset.confirmed = '1';
                        clearAutosave(); // Submit başarılı kabul; draft'ı temizle
                        form.submit();
                    }
                });
            });
        }

        // ──────────────────────────────────────────────────────────
        // (7) AI Caption Generator
        // ──────────────────────────────────────────────────────────
        var aiBtn = document.getElementById('aiGenerateBtn');
        if (aiBtn) {
            var aiLabelEl = aiBtn.querySelector('[data-label]');
            var aiOrigLabel = aiLabelEl ? aiLabelEl.textContent : 'AI ile Üret';

            aiBtn.addEventListener('click', async function () {
                var productSelect = document.getElementById('aiProductSelect');
                var toneSelect    = document.getElementById('aiToneSelect');
                var topicInput    = document.getElementById('aiTopicInput');
                var statusEl      = document.getElementById('aiGenerateStatus');
                var captionElLocal = document.getElementById('captionInput');
                var hashtagsElLocal = document.getElementById('hashtagsInput');

                var productId = productSelect ? productSelect.value : '';
                var topic     = topicInput ? topicInput.value.trim() : '';
                var tone      = toneSelect ? toneSelect.value : 'samimi ve doğal';

                if (! productId && ! topic) {
                    if (typeof AdminModal !== 'undefined') {
                        AdminModal.status({ title: 'Eksik bilgi', message: 'Ürün seçmen veya konu yazman gerek.', type: 'warning' });
                    }
                    return;
                }

                aiBtn.disabled = true;
                if (aiLabelEl) aiLabelEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>AI üretiyor (~30 sn)…';
                if (statusEl) statusEl.textContent = 'Lütfen bekleyin (~30 sn)...';

                try {
                    var csrf = document.querySelector('meta[name="csrf-token"]')?.content
                        || document.querySelector('input[name="_token"]')?.value
                        || '';

                    var url = aiBtn.dataset.url || (window.IG_AI_URL || '');

                    var response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            product_id: productId || null,
                            topic: topic || null,
                            tone: tone,
                        }),
                    });

                    var data = await response.json();

                    if (! data.success) {
                        if (typeof AdminModal !== 'undefined') {
                            AdminModal.status({ title: 'AI Hatası', message: data.message || 'Bilinmeyen hata', type: 'danger' });
                        }
                        if (statusEl) statusEl.textContent = '';
                        return;
                    }

                    if (captionElLocal && data.caption) {
                        captionElLocal.value = data.caption;
                        updateCaptionCounter(captionElLocal);
                    }
                    if (hashtagsElLocal && Array.isArray(data.hashtags)) {
                        hashtagsElLocal.value = data.hashtags.join(' ');
                        renderHashtagPreview(hashtagsElLocal.value);
                    }

                    if (statusEl) statusEl.textContent = '✓ AI üretti, içerikler aşağıya yerleşti';
                } catch (e) {
                    if (typeof AdminModal !== 'undefined') {
                        AdminModal.status({ title: 'İstek Hatası', message: e.message, type: 'danger' });
                    }
                    if (statusEl) statusEl.textContent = '';
                } finally {
                    aiBtn.disabled = false;
                    if (aiLabelEl) aiLabelEl.textContent = aiOrigLabel;
                }
            });
        }

        // ──────────────────────────────────────────────────────────
        // (9) Media type seçici show/hide + FB toggle
        // ──────────────────────────────────────────────────────────
        var mediaTypeRadios = document.querySelectorAll('input[name="media_type"]');
        if (mediaTypeRadios.length > 0) {
            function applyMediaContext(activeType) {
                document.querySelectorAll('[data-ig-context]').forEach(function (el) {
                    var ctxList = (el.getAttribute('data-ig-context') || '').split(/\s+/).filter(Boolean);
                    if (ctxList.indexOf(activeType) === -1) {
                        el.classList.add('d-none');
                    } else {
                        el.classList.remove('d-none');
                    }
                });
                document.querySelectorAll('.ig-media-type-card').forEach(function (card) {
                    var radio = card.querySelector('input[name="media_type"]');
                    if (radio && radio.value === activeType) {
                        card.classList.add('is-active');
                    } else {
                        card.classList.remove('is-active');
                    }
                });

                // Önizleme kartı: data-ig-mode attribute'unu güncelle
                // CSS bu attribute'a göre Reels/Story layout'u uygular
                var previewEl = document.getElementById('igPreview');
                if (previewEl) previewEl.setAttribute('data-ig-mode', activeType);

                // Video önizleme: video kabul etmeyen modlara (image/feed)
                // geçilirse mevcut video önizlemesi temizlenir.
                if (activeType !== 'reels' && activeType !== 'story') {
                    var pv = document.getElementById('igPreviewVideo');
                    if (pv && ! pv.classList.contains('d-none')) {
                        pv.pause();
                        pv.removeAttribute('src');
                        pv.load();
                        pv.classList.add('d-none');
                        var ph = document.getElementById('igPreviewPlaceholder');
                        var im = document.getElementById('igPreviewMainImg');
                        if (im) im.classList.remove('d-none');
                        if (ph) ph.classList.remove('d-none');
                    }
                }
                // FB checkbox toggle
                var fbWrap = document.querySelector('[data-ig-fb-toggle]');
                if (fbWrap) {
                    var fbCheckbox = fbWrap.querySelector('input[type="checkbox"][name="publish_to_facebook"]');
                    var fbHint     = fbWrap.querySelector('[data-ig-fb-hint]');
                    var serverDisabled = fbCheckbox && fbCheckbox.dataset.serverDisabled === '1';

                    if (fbCheckbox && fbCheckbox.dataset.serverDisabled === undefined) {
                        fbCheckbox.dataset.serverDisabled = fbCheckbox.disabled ? '1' : '0';
                        fbCheckbox.dataset.serverHint = fbHint ? fbHint.innerHTML : '';
                        serverDisabled = fbCheckbox.disabled;
                    }

                    if (activeType === 'story') {
                        if (fbCheckbox) {
                            fbCheckbox.checked = false;
                            fbCheckbox.disabled = true;
                        }
                        fbWrap.classList.add('ig-target-disabled');
                        if (fbHint) fbHint.innerHTML = '<span class="text-warning">Story\'ler Facebook Page API\'sinde desteklenmiyor.</span>';
                    } else {
                        if (fbCheckbox && ! serverDisabled) {
                            fbCheckbox.disabled = false;
                            fbWrap.classList.remove('ig-target-disabled');
                        }
                        if (fbHint && fbCheckbox && fbCheckbox.dataset.serverHint !== undefined) {
                            fbHint.innerHTML = fbCheckbox.dataset.serverHint;
                        }
                    }
                }
            }

            mediaTypeRadios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (this.checked) applyMediaContext(this.value);
                });
            });

            var checkedRadio = document.querySelector('input[name="media_type"]:checked');
            if (checkedRadio) applyMediaContext(checkedRadio.value);
        }

        // ──────────────────────────────────────────────────────────
        // (8) AI Görsel Üret modalı
        // ──────────────────────────────────────────────────────────
        var aiImgBtn   = document.getElementById('igAiImageBtn');
        var aiImgModal = document.getElementById('igAiImageModal');
        if (aiImgBtn && aiImgModal && typeof bootstrap !== 'undefined') {
            var aiBsModal       = new bootstrap.Modal(aiImgModal);
            var promptInput     = document.getElementById('aiImagePromptInput');
            var aspectSelect    = document.getElementById('aiImageAspectRatio');
            var generateBtn     = document.getElementById('aiImageGenerateBtn');
            // Initial markup'ı sakla — setLoading false'a dönerken aynen geri yükle
            var generateBtnInitial = generateBtn ? generateBtn.innerHTML : '';
            var resultWrap      = document.getElementById('aiImageResultWrap');
            var resultPreview   = document.getElementById('aiImageResultPreview');
            var resultMeta      = document.getElementById('aiImageResultMeta');
            var errorBox        = document.getElementById('aiImageError');
            var retryBtn        = document.getElementById('aiImageRetryBtn');
            var useBtn          = document.getElementById('aiImageUseBtn');
            var aiImagePathInput = document.getElementById('aiImagePath');

            var lastGenerated = null;

            function syncAspectFromMediaType() {
                var checked = document.querySelector('input[name="media_type"]:checked');
                var type = checked ? checked.value : 'image';
                if (! aspectSelect) return;

                Array.prototype.forEach.call(aspectSelect.options, function (opt) {
                    if (type === 'reels' || type === 'story') {
                        opt.disabled = (opt.value !== '9:16');
                    } else {
                        opt.disabled = (opt.value === '9:16');
                    }
                });

                if (type === 'reels' || type === 'story') {
                    aspectSelect.value = '9:16';
                } else if (aspectSelect.value === '9:16' || ! aspectSelect.value) {
                    aspectSelect.value = '1:1';
                }
            }

            function resetModalState() {
                if (errorBox) { errorBox.classList.add('d-none'); errorBox.textContent = ''; }
                if (resultWrap) resultWrap.classList.add('d-none');
                if (resultPreview) resultPreview.src = '';
                if (resultMeta) resultMeta.textContent = '';
                if (retryBtn) retryBtn.classList.add('d-none');
                if (useBtn)   useBtn.classList.add('d-none');
                lastGenerated = null;
            }

            function setLoading(isLoading) {
                if (! generateBtn) return;
                generateBtn.disabled = isLoading;
                if (retryBtn) retryBtn.disabled = isLoading;
                if (useBtn)   useBtn.disabled = isLoading;

                if (isLoading) {
                    generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Üretiliyor (~10-20 sn)…';
                } else {
                    generateBtn.innerHTML = generateBtnInitial;
                }
            }

            function callGenerate() {
                resetModalState();
                setLoading(true);

                var checkedMediaType = document.querySelector('input[name="media_type"]:checked');
                var mediaType = checkedMediaType ? checkedMediaType.value : 'image';

                var token = document.querySelector('meta[name="csrf-token"]');
                var formData = new FormData();
                formData.append('_token', token ? token.getAttribute('content') : '');
                formData.append('media_type', mediaType);
                formData.append('aspect_ratio', aspectSelect ? aspectSelect.value : '1:1');
                if (promptInput && promptInput.value.trim()) {
                    formData.append('prompt', promptInput.value.trim());
                }
                var productSelect = document.getElementById('aiProductSelect');
                var topicInput    = document.getElementById('aiTopicInput');
                if (productSelect && productSelect.value) formData.append('product_id', productSelect.value);
                if (topicInput && topicInput.value.trim()) formData.append('topic', topicInput.value.trim());

                fetch(window.IG_GENERATE_IMAGE_URL || '/admin/instagram-posts/generate-image', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    setLoading(false);
                    if (! result.ok || ! result.data || ! result.data.success) {
                        var msg = (result.data && result.data.message) ? result.data.message : 'Görsel üretilemedi.';
                        if (errorBox) { errorBox.textContent = msg; errorBox.classList.remove('d-none'); }
                        if (retryBtn) retryBtn.classList.remove('d-none');
                        return;
                    }
                    lastGenerated = { path: result.data.image_path, url: result.data.url, model: result.data.model_used };
                    if (resultPreview) resultPreview.src = result.data.url + '?t=' + Date.now();
                    if (resultMeta)    resultMeta.textContent = 'Model: ' + (result.data.model_used || '-');
                    if (resultWrap)    resultWrap.classList.remove('d-none');
                    if (retryBtn)      retryBtn.classList.remove('d-none');
                    if (useBtn)        useBtn.classList.remove('d-none');
                })
                .catch(function (err) {
                    setLoading(false);
                    if (errorBox) {
                        errorBox.textContent = 'İstek hatası: ' + (err.message || err);
                        errorBox.classList.remove('d-none');
                    }
                    if (retryBtn) retryBtn.classList.remove('d-none');
                });
            }

            aiImgBtn.addEventListener('click', function () {
                resetModalState();
                syncAspectFromMediaType();
                aiBsModal.show();
            });

            if (generateBtn) generateBtn.addEventListener('click', callGenerate);
            if (retryBtn)    retryBtn.addEventListener('click', callGenerate);

            if (useBtn) {
                useBtn.addEventListener('click', function () {
                    if (! lastGenerated || ! aiImagePathInput) return;

                    aiImagePathInput.value = lastGenerated.path;

                    var imageInputLocal = document.getElementById('imageInput');
                    if (imageInputLocal) imageInputLocal.value = '';

                    var dzContent  = document.getElementById('mainDropzoneContent');
                    var dzSelected = document.getElementById('mainDropzoneSelected');
                    var dzPreview  = document.getElementById('newImagePreview');
                    var dzName     = document.getElementById('mainDropzoneFileName');
                    var dzSize     = document.getElementById('mainDropzoneFileSize');
                    if (dzContent)  dzContent.classList.add('is-hidden');
                    if (dzSelected) dzSelected.classList.remove('is-hidden');
                    if (dzPreview)  dzPreview.src = lastGenerated.url + '?t=' + Date.now();
                    if (dzName)     dzName.textContent = 'AI üretilen görsel';
                    if (dzSize)     dzSize.textContent = lastGenerated.model || '';

                    updatePreviewMainImage(lastGenerated.url + '?t=' + Date.now());

                    aiBsModal.hide();
                });
            }

            var dzRemoveBtn = document.getElementById('mainDropzoneRemove');
            if (dzRemoveBtn && aiImagePathInput) {
                dzRemoveBtn.addEventListener('click', function () { aiImagePathInput.value = ''; });
            }
            var imageInputAi = document.getElementById('imageInput');
            if (imageInputAi && aiImagePathInput) {
                imageInputAi.addEventListener('change', function () {
                    if (this.files && this.files.length > 0) aiImagePathInput.value = '';
                });
            }
        }

        // ──────────────────────────────────────────────────────────
        // (11) Draft autosave (localStorage)
        // ──────────────────────────────────────────────────────────
        var formEl = document.getElementById('instagramPostForm');
        var autosaveKey = formEl ? formEl.getAttribute('data-ig-autosave-key') : null;
        var autosaveBannerShown = false;

        function getAutosavePayload() {
            return {
                caption:       document.getElementById('captionInput')?.value || '',
                hashtags:      document.getElementById('hashtagsInput')?.value || '',
                scheduled_at:  document.getElementById('scheduledAt')?.value || '',
                media_type:    getCurrentMediaType(),
                saved_at:      Date.now(),
            };
        }

        function saveDraft() {
            if (! autosaveKey) return;
            try {
                var payload = getAutosavePayload();
                // Boş içerik kaydetme (tüm alanlar boşsa storage'ı kirletme)
                if (! payload.caption && ! payload.hashtags && ! payload.scheduled_at) return;
                localStorage.setItem(autosaveKey, JSON.stringify(payload));
            } catch (e) { /* QuotaExceeded vb. — sessizce geç */ }
        }

        function clearAutosave() {
            if (! autosaveKey) return;
            try { localStorage.removeItem(autosaveKey); } catch (e) {}
        }

        function loadAutosave() {
            if (! autosaveKey) return null;
            try {
                var raw = localStorage.getItem(autosaveKey);
                if (! raw) return null;
                var data = JSON.parse(raw);
                // 7 günden eski draft'ı görmezden gel
                if (! data || ! data.saved_at || (Date.now() - data.saved_at) > 7 * 24 * 60 * 60 * 1000) {
                    clearAutosave();
                    return null;
                }
                return data;
            } catch (e) {
                return null;
            }
        }

        function offerRestore() {
            if (! autosaveKey || autosaveBannerShown) return;
            var draft = loadAutosave();
            if (! draft) return;

            var captionElL  = document.getElementById('captionInput');
            var hashtagsElL = document.getElementById('hashtagsInput');
            var scheduledEl = document.getElementById('scheduledAt');

            // Eğer form'da zaten içerik varsa (edit modu veya old() ile gelen) draft restore etme
            var hasCurrentContent = (captionElL && captionElL.value.trim() !== '')
                || (hashtagsElL && hashtagsElL.value.trim() !== '')
                || (scheduledEl && scheduledEl.value !== '');
            if (hasCurrentContent) return;

            if (typeof AdminModal === 'undefined') return;
            autosaveBannerShown = true;

            var savedAt = new Date(draft.saved_at);
            AdminModal.confirm({
                title: 'Kaydedilmemiş Taslak Bulundu',
                message: 'Bu sayfada ' + savedAt.toLocaleString('tr-TR') + ' tarihinde kaydedilmemiş içerik var. Geri yüklensin mi?',
                type: 'warning',
                confirmText: 'Geri Yükle',
                cancelText: 'Yoksay (Sil)',
            }).then(function (ok) {
                if (ok) {
                    if (captionElL && draft.caption)   { captionElL.value = draft.caption; updateCaptionCounter(captionElL); }
                    if (hashtagsElL && draft.hashtags) { hashtagsElL.value = draft.hashtags; renderHashtagPreview(hashtagsElL.value); }
                    if (scheduledEl && draft.scheduled_at) { scheduledEl.value = draft.scheduled_at; }
                    if (draft.media_type) {
                        var radio = document.querySelector('input[name="media_type"][value="' + draft.media_type + '"]');
                        if (radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change'));
                        }
                    }
                } else {
                    clearAutosave();
                }
            });
        }

        // 5 sn debounce ile autosave
        if (autosaveKey) {
            var saveTimer = null;
            function scheduleSave() {
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(saveDraft, 5000);
            }
            ['captionInput', 'hashtagsInput', 'scheduledAt'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', scheduleSave);
            });
            document.querySelectorAll('input[name="media_type"]').forEach(function (r) {
                r.addEventListener('change', scheduleSave);
            });

            // Sayfa yüklenince teklif et (250ms gecikme — modal stack'i bozulmasın)
            setTimeout(offerRestore, 250);

            // Form successfully submit (publish_now değil) → draft'ı temizle
            // Not: publish_now confirm akışı kendi clearAutosave'ini çağırıyor
            if (formEl) {
                formEl.addEventListener('submit', function () {
                    var actionEl = document.getElementById('formAction');
                    if (actionEl && actionEl.value !== 'publish_now') {
                        clearAutosave();
                    }
                });
            }
        }

        // ── Recovery aksiyonları: "Şimdi Yayınla" + "Retry sıfırla" ──
        // publish-plan partial'ındaki butonlar AdminModal onayı ister.
        document.querySelectorAll('.ig-publish-now-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (typeof AdminModal === 'undefined') return;
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();
                AdminModal.confirm({
                    title: 'Hemen Yayınla',
                    message: 'Bu gönderi Instagram hesabında hemen yayınlanacak. Devam edilsin mi?',
                    type: 'warning',
                    confirmText: 'Evet, Yayınla',
                    confirmIcon: 'bi bi-send-fill',
                }).then(function (ok) {
                    if (! ok) return;
                    form.dataset.confirmed = '1';
                    form.submit();
                });
            });
        });

        document.querySelectorAll('.ig-reset-retry-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (typeof AdminModal === 'undefined') return;
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();
                AdminModal.confirm({
                    title: 'Retry Sayacını Sıfırla',
                    message: 'Status "Planlandı"ya dönecek, retry sayısı 0\'a sıfırlanacak. ' +
                             'Cron 5 dakika içinde tekrar deneyecek. Önce caption/hashtag uzunluğunu ' +
                             'kontrol ettin mi?',
                    type: 'warning',
                    confirmText: 'Evet, Sıfırla',
                    confirmIcon: 'bi bi-arrow-counterclockwise',
                }).then(function (ok) {
                    if (! ok) return;
                    form.dataset.confirmed = '1';
                    form.submit();
                });
            });
        });

        // ── TikTok recovery butonları (docs/tiktok.md Bölüm 6.6) ──
        document.querySelectorAll('.ig-tt-publish-now-form, .ig-tt-reset-retry-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (typeof AdminModal === 'undefined') return;
                if (form.dataset.confirmed === '1') return;
                e.preventDefault();

                var isReset = form.classList.contains('ig-tt-reset-retry-form');
                AdminModal.confirm({
                    title: isReset ? 'TikTok Retry Sıfırla' : 'Şimdi TikTok\'a Paylaş',
                    message: isReset
                        ? 'Retry sayacı 0\'a sıfırlanacak. Cron 5dk içinde yeniden deneyecek. ' +
                          'Önce Settings\'te TikTok bağlantı + post mode kontrol et.'
                        : 'Bu post hemen TikTok\'a paylaşılacak (Direct Post veya Inbox modu Settings\'e göre).',
                    type: 'warning',
                    confirmText: isReset ? 'Evet, Sıfırla' : 'Evet, Paylaş',
                    confirmIcon: isReset ? 'bi bi-arrow-counterclockwise' : 'bi bi-tiktok',
                }).then(function (ok) {
                    if (! ok) return;
                    form.dataset.confirmed = '1';
                    form.submit();
                });
            });
        });

        // ── Client-side video süre doğrulama ───────────────────────
        // Server-side InstagramVideoDuration rule kaldırıldı (shell_exec
        // shared hosting'de disabled). Bunun yerine browser'ın doğal
        // <video> metadata desteğiyle anlık feedback veriyoruz.
        //
        // Bypass: kullanıcı F12 ile validation'ı atlasa bile Meta Graph
        // API videoyu reddeder ve hata log'a + Telegram'a düşer.
        // Önizleme panelindeki video/img/placeholder elementlerini cache'le
        var previewVideo       = document.getElementById('igPreviewVideo');
        var previewMainImg     = document.getElementById('igPreviewMainImg');
        var previewPlaceholder = document.getElementById('igPreviewPlaceholder');
        var currentBlobUrl     = null; // Önceki blob URL — yeni file gelince revoke et

        function clearPreviewVideo() {
            if (previewVideo) {
                previewVideo.pause();
                previewVideo.removeAttribute('src');
                previewVideo.load();
                previewVideo.classList.add('d-none');
            }
            if (currentBlobUrl) {
                URL.revokeObjectURL(currentBlobUrl);
                currentBlobUrl = null;
            }
            // Img ya da placeholder geri görünür
            if (previewMainImg) previewMainImg.classList.remove('d-none');
            if (previewPlaceholder) previewPlaceholder.classList.remove('d-none');
        }

        function showPreviewVideo(blobUrl) {
            if (! previewVideo) return;
            if (previewMainImg) previewMainImg.classList.add('d-none');
            if (previewPlaceholder) previewPlaceholder.classList.add('d-none');
            previewVideo.src = blobUrl;
            previewVideo.classList.remove('d-none');
        }

        document.querySelectorAll('[data-ig-video-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                var feedback = input.parentNode.querySelector('[data-ig-video-feedback]');
                if (! feedback) return;

                if (! input.files || input.files.length === 0) {
                    feedback.classList.add('d-none');
                    feedback.innerHTML = '';
                    clearPreviewVideo();
                    return;
                }

                var file = input.files[0];

                // Media type bul (radio group)
                var mediaTypeRadio = document.querySelector('input[name="media_type"]:checked');
                var mediaType = mediaTypeRadio ? mediaTypeRadio.value : null;

                // Sınırları input attribute'lerinden oku
                var min = null, max = null, label = null;
                if (mediaType === 'reels') {
                    min = parseInt(input.dataset.reelsMin || '3', 10);
                    max = parseInt(input.dataset.reelsMax || '90', 10);
                    label = 'Reels';
                } else if (mediaType === 'story') {
                    min = parseInt(input.dataset.storyMin || '1', 10);
                    max = parseInt(input.dataset.storyMax || '60', 10);
                    label = 'Story';
                } else {
                    // image, feed vb. → video alanı zaten gizli
                    feedback.classList.add('d-none');
                    return;
                }

                // Browser metadata okuma — aynı blob URL'i hem süre kontrolü
                // hem de sağ paneldeki önizleme video'su için kullanacağız.
                if (currentBlobUrl) {
                    URL.revokeObjectURL(currentBlobUrl);
                    currentBlobUrl = null;
                }
                var url = URL.createObjectURL(file);
                var probe = document.createElement('video');
                probe.preload = 'metadata';

                probe.onloadedmetadata = function () {
                    var duration = Math.round(probe.duration);

                    if (! isFinite(duration) || duration <= 0) {
                        URL.revokeObjectURL(url);
                        // Metadata bozuk — server tarafına bırak, preview da koyma
                        clearPreviewVideo();
                        feedback.classList.remove('d-none', 'text-success', 'text-danger');
                        feedback.classList.add('text-muted');
                        feedback.innerHTML = '<i class="bi bi-info-circle me-1"></i>' +
                            'Süre okunamadı. Sunucu kontrolüne bırakıldı.';
                        return;
                    }

                    if (duration < min || duration > max) {
                        URL.revokeObjectURL(url);
                        // Limit ihlali — input temizle + modal uyar + önizleme kaldır
                        input.value = '';
                        clearPreviewVideo();
                        feedback.classList.remove('d-none', 'text-success', 'text-muted');
                        feedback.classList.add('text-danger');
                        feedback.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' +
                            '<strong>Video ' + duration + ' saniye</strong> — ' +
                            label + ' için ' + min + '-' + max + ' saniye gereklidir.';

                        if (typeof AdminModal !== 'undefined') {
                            AdminModal.status({
                                title: label + ' Süre Uyumsuzluğu',
                                message: 'Yüklediğiniz video ' + duration + ' saniye. ' +
                                         label + ' için süre ' + min + '-' + max + ' saniye olmalı. ' +
                                         'Lütfen videoyu kısaltıp tekrar deneyin.',
                                type: 'warning',
                            });
                        }
                        return;
                    }

                    // OK — feedback yeşil + önizleme paneline videoyu yansıt
                    feedback.classList.remove('d-none', 'text-danger', 'text-muted');
                    feedback.classList.add('text-success');
                    feedback.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>' +
                        'Süre: ' + duration + ' saniye — ' + label + ' için uygun.';

                    currentBlobUrl = url;
                    showPreviewVideo(url);
                };

                probe.onerror = function () {
                    URL.revokeObjectURL(url);
                    clearPreviewVideo();
                    feedback.classList.remove('d-none', 'text-success', 'text-danger');
                    feedback.classList.add('text-muted');
                    feedback.innerHTML = '<i class="bi bi-info-circle me-1"></i>' +
                        'Süre tarayıcıda okunamadı. Sunucu kontrolüne bırakıldı.';
                };

                probe.src = url;
            });
        });

    });
})();
