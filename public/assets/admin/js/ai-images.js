(function () {
    'use strict';

    var config = window.aiImageConfig || {};
    var csrfToken = config.csrfToken || '';
    var generateUrl = config.generateUrl || '';

    // ── Elements ──
    var form = document.getElementById('aiImageForm');
    var generateBtn = document.getElementById('generateBtn');
    var generateBtnText = document.getElementById('generateBtnText');
    var promptText = document.getElementById('promptText');
    var promptCharCount = document.getElementById('promptCharCount');
    var promptTemplate = document.getElementById('promptTemplate');

    var previewEmpty = document.getElementById('previewEmpty');
    var previewLoading = document.getElementById('previewLoading');
    var previewResult = document.getElementById('previewResult');
    var previewError = document.getElementById('previewError');
    var previewImage = document.getElementById('previewImage');
    var previewDownload = document.getElementById('previewDownload');
    var previewTime = document.getElementById('previewTime');
    var previewErrorText = document.getElementById('previewErrorText');

    // ── Character counter ──
    if (promptText && promptCharCount) {
        var updateCharCount = function () {
            promptCharCount.textContent = String(promptText.value.length);
        };
        promptText.addEventListener('input', updateCharCount);
        updateCharCount();
    }

    // ── Prompt template → pre-fill textarea + reference image preview ──
    var templateRefPreview = document.getElementById('templateRefPreview');
    var templateRefImage   = document.getElementById('templateRefImage');
    var templateRefLink    = document.getElementById('templateRefLink');
    var templateRefName    = document.getElementById('templateRefName');

    function updateTemplateReferencePreview(option) {
        if (! templateRefPreview || ! templateRefImage) return;
        var refUrl  = option ? option.getAttribute('data-reference-image') : '';
        var refName = option ? option.getAttribute('data-reference-image-name') : '';
        if (refUrl) {
            templateRefImage.src = refUrl;
            if (templateRefLink) templateRefLink.href = refUrl;
            if (templateRefName) templateRefName.textContent = refName || 'referans-gorsel';
            templateRefPreview.classList.remove('d-none');
        } else {
            templateRefPreview.classList.add('d-none');
            templateRefImage.src = '';
            if (templateRefLink) templateRefLink.removeAttribute('href');
            if (templateRefName) templateRefName.textContent = '';
        }
    }

    if (promptTemplate && promptText) {
        promptTemplate.addEventListener('change', function () {
            var selected = promptTemplate.options[promptTemplate.selectedIndex];
            var tpl = selected ? selected.getAttribute('data-prompt') : null;
            if (tpl) {
                promptText.value = tpl;
                promptText.dispatchEvent(new Event('input'));
                promptText.focus();
            }
            updateTemplateReferencePreview(selected);
        });

        // Sayfa açıldığında seçili şablon varsa preview'ı doldur
        if (promptTemplate.selectedIndex > 0) {
            updateTemplateReferencePreview(promptTemplate.options[promptTemplate.selectedIndex]);
        }
    }

    // ── Preview state helpers ──
    function hideAllPreviewStates() {
        [previewEmpty, previewLoading, previewResult, previewError].forEach(function (el) {
            if (el) el.classList.add('d-none');
        });
    }

    function showPreviewLoading() {
        hideAllPreviewStates();
        if (previewLoading) previewLoading.classList.remove('d-none');
    }

    function showPreviewResult(image) {
        hideAllPreviewStates();
        if (previewResult) previewResult.classList.remove('d-none');
        if (previewImage && image.image_url) {
            previewImage.src = image.image_url;
            previewImage.alt = image.final_prompt || 'AI Generated';
        }
        if (previewDownload && image.image_url) {
            previewDownload.href = image.image_url;
        }
        if (previewTime && image.generation_time_ms) {
            var seconds = (image.generation_time_ms / 1000).toFixed(1);
            previewTime.innerHTML = '<i class="bi bi-stopwatch me-1"></i>' + seconds + ' sn';
        }
    }

    function showPreviewError(message) {
        hideAllPreviewStates();
        if (previewError) previewError.classList.remove('d-none');
        if (previewErrorText) previewErrorText.textContent = message || 'Bilinmeyen hata.';
    }

    function setGeneratingState(isGenerating) {
        if (!generateBtn) return;
        generateBtn.disabled = isGenerating;
        if (generateBtnText) {
            generateBtnText.textContent = isGenerating ? 'Oluşturuluyor...' : 'Görseli Oluştur';
        }
    }

    // ── Form submission ──
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var promptValue = (promptText.value || '').trim();
            if (promptValue.length < 10) {
                showPreviewError('Prompt en az 10 karakter olmalı.');
                return;
            }

            var formData = new FormData(form);

            setGeneratingState(true);
            showPreviewLoading();

            fetch(generateUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                });
            })
            .then(function (result) {
                var data = result.data || {};
                var image = data.image || {};

                if (data.success && image.image_url) {
                    showPreviewResult(image);
                    if (window.AdminModal && typeof AdminModal.status === 'function') {
                        AdminModal.status({ title: 'Başarılı', message: 'Görsel oluşturuldu. Galeri yenileniyor...', type: 'success' });
                    }
                    // Reload after short delay so the gallery reflects the new image.
                    setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    var msg = data.message || 'Görsel oluşturulamadı.';
                    showPreviewError(msg);
                    if (window.AdminModal && typeof AdminModal.status === 'function') {
                        AdminModal.status({ title: 'Hata', message: msg, type: 'danger' });
                    }
                }
            })
            .catch(function (err) {
                showPreviewError('İstek gönderilemedi: ' + (err && err.message ? err.message : 'bilinmeyen hata'));
            })
            .finally(function () {
                setGeneratingState(false);
            });
        });
    }

    // ── Prompt Manager Modal: edit ──
    var promptForm = document.getElementById('promptForm');
    var promptFormMethod = document.getElementById('promptFormMethod');
    var promptFormTitle = document.getElementById('promptFormTitle');
    var promptName = document.getElementById('promptName');
    var promptDescription = document.getElementById('promptDescription');
    var promptBody = document.getElementById('promptBody');
    var promptIsActive = document.getElementById('promptIsActive');
    var promptSubmitText = document.getElementById('promptSubmitText');
    var promptCancelBtn = document.getElementById('promptCancelBtn');
    var promptStoreUrl = promptForm ? promptForm.action : '';
    var promptReferenceImage = document.getElementById('promptReferenceImage');
    var promptReferenceImagePreviewWrap = document.getElementById('promptReferenceImagePreviewWrap');
    var promptReferenceImagePreview = document.getElementById('promptReferenceImagePreview');
    var promptReferenceImageRemove = document.getElementById('promptReferenceImageRemove');

    function resetPromptForm() {
        if (!promptForm) return;
        promptForm.reset();
        promptForm.action = promptStoreUrl;
        if (promptFormMethod) promptFormMethod.value = 'POST';
        if (promptFormTitle) {
            promptFormTitle.innerHTML = '<i class="bi bi-plus-square me-1"></i> Yeni Şablon Ekle';
        }
        if (promptSubmitText) promptSubmitText.textContent = 'Ekle';
        if (promptCancelBtn) promptCancelBtn.classList.add('d-none');
        if (promptIsActive) promptIsActive.checked = true;
        // Reference image preview reset
        if (promptReferenceImagePreviewWrap) promptReferenceImagePreviewWrap.classList.add('d-none');
        if (promptReferenceImagePreview) promptReferenceImagePreview.src = '';
        if (promptReferenceImageRemove) promptReferenceImageRemove.checked = false;
    }

    document.querySelectorAll('.js-edit-prompt').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data;
            try {
                data = JSON.parse(btn.dataset.prompt);
            } catch (e) {
                return;
            }
            if (!promptForm) return;

            promptForm.action = promptStoreUrl.replace(/\/prompts.*$/, '/prompts/' + data.id);
            if (promptFormMethod) promptFormMethod.value = 'PUT';
            if (promptFormTitle) {
                promptFormTitle.innerHTML = '<i class="bi bi-pencil me-1"></i> Şablon Düzenle: <span class="text-teal">' + (data.name || '') + '</span>';
            }
            if (promptSubmitText) promptSubmitText.textContent = 'Güncelle';
            if (promptCancelBtn) promptCancelBtn.classList.remove('d-none');

            if (promptName) promptName.value = data.name || '';
            if (promptDescription) promptDescription.value = data.description || '';
            if (promptBody) promptBody.value = data.prompt || '';
            if (promptIsActive) promptIsActive.checked = !!data.is_active;

            // Mevcut referans görseli preview'a yansıt
            if (promptReferenceImagePreviewWrap && promptReferenceImagePreview) {
                if (data.reference_image_path) {
                    promptReferenceImagePreview.src = '/uploads/' + data.reference_image_path;
                    promptReferenceImagePreviewWrap.classList.remove('d-none');
                } else {
                    promptReferenceImagePreviewWrap.classList.add('d-none');
                    promptReferenceImagePreview.src = '';
                }
            }
            if (promptReferenceImage) promptReferenceImage.value = '';
            if (promptReferenceImageRemove) promptReferenceImageRemove.checked = false;

            promptForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (promptCancelBtn) {
        promptCancelBtn.addEventListener('click', resetPromptForm);
    }

    // Reset form when modal closes.
    var promptModal = document.getElementById('promptManagerModal');
    if (promptModal) {
        promptModal.addEventListener('hidden.bs.modal', resetPromptForm);
    }

    // ── Delete confirmations (gallery + prompt rows) ──
    document.querySelectorAll('.js-delete-form').forEach(function (formEl) {
        formEl.addEventListener('submit', function (e) {
            var btn = formEl.querySelector('button[data-confirm]');
            var msg = btn ? btn.dataset.confirm : 'Silmek istediğinize emin misiniz?';
            e.preventDefault();
            if (window.AdminModal && AdminModal.confirm) {
                AdminModal.confirm({
                    title: 'Onay',
                    message: msg,
                    confirmText: 'Sil',
                    confirmClass: 'btn-danger',
                    onConfirm: function () { formEl.submit(); }
                });
            } else {
                formEl.submit();
            }
        });
    });

    // ── Prompt karakter sayacı + form scroll-into-view ──────────────
    if (promptBody) {
        var counterEl = document.getElementById('promptBodyCounter');

        function updatePromptCounter() {
            if (! counterEl) return;
            var len = promptBody.value.length;
            counterEl.textContent = len.toLocaleString('tr-TR');
            counterEl.classList.remove('text-warning', 'text-danger', 'fw-bold');
            if (len > 19000) counterEl.classList.add('text-danger', 'fw-bold');
            else if (len > 16000) counterEl.classList.add('text-warning');
        }
        promptBody.addEventListener('input', updatePromptCounter);
        updatePromptCounter();
    }

    // Validation hatası varsa form'u görünür yap + içine scroll et
    var hasErrors = document.querySelector('#promptForm .alert-danger');
    if (hasErrors && promptForm) {
        // Form gizliyse aç (Bootstrap collapse veya display:none)
        promptForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (promptBody) promptBody.focus();
    }
})();
