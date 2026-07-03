(function () {
    'use strict';

    var config = window.vertexConfig || {};
    var csrfToken = config.csrfToken || '';
    var generateUrl = config.generateUrl || '';
    var batchPollIntervalMs = config.batchPollIntervalMs || 3000;

    // ── Elements ──
    var form = document.getElementById('vertexGenerateForm');
    var btn = document.getElementById('vertexGenerateBtn');
    var btnText = document.getElementById('vertexGenerateBtnText');
    var promptSelect = document.getElementById('vertexPromptId');

    var templateDetails = document.getElementById('vertexTemplateDetails');
    var vtxModel = document.getElementById('vtxModel');
    var vtxAspectRatio = document.getElementById('vtxAspectRatio');
    var vtxImageSize = document.getElementById('vtxImageSize');
    var vtxMimeType = document.getElementById('vtxMimeType');
    var vtxRefImagesWrap = document.getElementById('vtxRefImagesWrap');
    var vtxRefImagesGrid = document.getElementById('vtxRefImagesGrid');
    var vtxRefImagesCount = document.getElementById('vtxRefImagesCount');
    var vtxVariablesWrap = document.getElementById('vtxVariablesWrap');
    var vtxVariablesGrid = document.getElementById('vtxVariablesGrid');
    var vtxVariablesCount = document.getElementById('vtxVariablesCount');

    var countValue = document.getElementById('vtxCountValue');
    var countCustom = document.getElementById('vtxCountCustom');
    var countPills = document.querySelectorAll('.vtx-count-pill');

    // ── Variable parser ──
    var VARIABLE_REGEX = /\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/g;

    function parseVariables(prompt) {
        var seen = {};
        var list = [];
        var match;
        VARIABLE_REGEX.lastIndex = 0;
        while ((match = VARIABLE_REGEX.exec(prompt)) !== null) {
            var name = match[1];
            if (! seen[name]) {
                seen[name] = true;
                list.push(name);
            }
        }
        return list;
    }

    function humanize(name) {
        var snake = name.replace(/([a-z])([A-Z])/g, '$1_$2').toLowerCase();
        return snake.split('_').filter(function (p) { return p !== ''; })
            .map(function (p) { return p.charAt(0).toUpperCase() + p.slice(1); })
            .join(' ');
    }

    function substituteVariables(prompt, values) {
        return prompt.replace(VARIABLE_REGEX, function (full, name) {
            if (Object.prototype.hasOwnProperty.call(values, name) && values[name] !== '') {
                return values[name];
            }
            return full;
        });
    }

    function collectVariableValues() {
        var values = {};
        if (! vtxVariablesGrid) return values;
        vtxVariablesGrid.querySelectorAll('[data-variable-name]').forEach(function (el) {
            values[el.dataset.variableName] = el.value || '';
        });
        return values;
    }

    function updatePromptPreview(rawPrompt) {
        if (! vtxPromptPreview) return;
        var values = collectVariableValues();
        var previewValues = {};
        for (var k in values) {
            if (Object.prototype.hasOwnProperty.call(values, k)) {
                previewValues[k] = values[k] === '__random__' ? '[🎲 Rastgele]' : values[k];
            }
        }
        var finalText = substituteVariables(rawPrompt, previewValues);
        vtxPromptPreview.textContent = finalText;
        if (vtxPromptLen) vtxPromptLen.textContent = finalText.length.toLocaleString('tr-TR');
    }

    var currentRandomOptions = {};

    function renderVariableInputs(rawPrompt) {
        if (! vtxVariablesWrap || ! vtxVariablesGrid) return;
        vtxVariablesGrid.innerHTML = '';

        var vars = parseVariables(rawPrompt);
        if (vars.length === 0) {
            vtxVariablesWrap.classList.add('d-none');
            return;
        }

        vtxVariablesWrap.classList.remove('d-none');
        if (vtxVariablesCount) vtxVariablesCount.textContent = String(vars.length);

        vars.forEach(function (name) {
            var wrap = document.createElement('div');
            wrap.className = 'mb-2';
            var label = document.createElement('label');
            label.className = 'form-label text-clr-secondary small mb-1 d-flex align-items-center justify-content-between';
            label.innerHTML =
                '<span><i class="bi bi-braces text-warning me-1"></i>' + humanize(name) + ' <span class="text-danger">*</span></span>' +
                '<code class="text-clr-muted small">{{' + name + '}}</code>';
            wrap.appendChild(label);

            var opts = currentRandomOptions[name];
            if (opts && opts.length > 0) {
                var select = document.createElement('select');
                select.className = 'form-select';
                select.name = 'variables[' + name + ']';
                select.dataset.variableName = name;
                select.required = true;

                var defOpt = document.createElement('option');
                defOpt.value = '__random__';
                defOpt.textContent = '🎲 Rastgele (' + opts.length + ' seçenek)';
                select.appendChild(defOpt);

                for (var oi = 0; oi < opts.length; oi++) {
                    var o = document.createElement('option');
                    o.value = opts[oi];
                    o.textContent = opts[oi];
                    select.appendChild(o);
                }
                select.addEventListener('change', function () { updatePromptPreview(rawPrompt); });
                wrap.appendChild(select);
            } else {
                var textarea = document.createElement('textarea');
                textarea.className = 'form-control';
                textarea.name = 'variables[' + name + ']';
                textarea.rows = 2;
                textarea.required = true;
                textarea.dataset.variableName = name;
                textarea.placeholder = humanize(name) + ' değerini yaz...';
                textarea.addEventListener('input', function () { updatePromptPreview(rawPrompt); });
                wrap.appendChild(textarea);
            }
            vtxVariablesGrid.appendChild(wrap);
        });
    }

    var vtxPromptPreview = document.getElementById('vtxPromptPreview');
    var vtxPromptLen = document.getElementById('vtxPromptLen');

    var previewEmpty = document.getElementById('vtxPreviewEmpty');
    var previewLoading = document.getElementById('vtxPreviewLoading');
    var previewResult = document.getElementById('vtxPreviewResult');
    var previewError = document.getElementById('vtxPreviewError');
    var previewImage = document.getElementById('vtxPreviewImage');
    var previewDownload = document.getElementById('vtxPreviewDownload');
    var previewTime = document.getElementById('vtxPreviewTime');
    var previewErrorText = document.getElementById('vtxPreviewErrorText');
    var pollTick = document.getElementById('vtxPollTick');

    // ── Count selector ──
    var currentCount = 1;

    function setCount(val) {
        currentCount = Math.max(1, parseInt(val, 10) || 1);
        if (countValue) countValue.value = currentCount;
        updateBtnLabel();

        countPills.forEach(function (b) {
            b.classList.toggle('active', parseInt(b.dataset.count, 10) === currentCount);
        });
    }

    function updateBtnLabel() {
        if (!btnText || btn.disabled) return;
        if (currentCount <= 1) {
            btnText.textContent = 'Görseli Üret';
        } else {
            btnText.textContent = currentCount + ' Görsel Üret';
        }
    }

    countPills.forEach(function (b) {
        b.addEventListener('click', function () {
            if (countCustom) countCustom.value = '';
            setCount(parseInt(b.dataset.count, 10));
        });
    });

    if (countCustom) {
        countCustom.addEventListener('input', function () {
            var v = parseInt(countCustom.value, 10);
            if (v > 0) {
                setCount(v);
            }
        });
    }

    // ── Template selection ──
    function updateTemplateDetails() {
        if (! promptSelect) return;
        var opt = promptSelect.options[promptSelect.selectedIndex];
        if (! opt || ! opt.value) {
            if (templateDetails) templateDetails.classList.add('d-none');
            return;
        }
        if (templateDetails) templateDetails.classList.remove('d-none');

        var prompt = opt.getAttribute('data-prompt') || '';
        var model = opt.getAttribute('data-model') || '';
        var aspect = opt.getAttribute('data-aspect-ratio') || '';
        var size = opt.getAttribute('data-image-size') || '';
        var mime = opt.getAttribute('data-mime-type') || '';
        var refImagesJson = opt.getAttribute('data-ref-images') || '[]';
        var refImages = [];
        try { refImages = JSON.parse(refImagesJson) || []; } catch (e) { refImages = []; }

        var randomOptionsJson = opt.getAttribute('data-random-options') || '{}';
        try { currentRandomOptions = JSON.parse(randomOptionsJson) || {}; } catch (e) { currentRandomOptions = {}; }

        if (vtxModel) vtxModel.textContent = model || '—';
        if (vtxAspectRatio) vtxAspectRatio.textContent = aspect || '—';
        if (vtxImageSize) vtxImageSize.textContent = size || '—';
        if (vtxMimeType) vtxMimeType.textContent = mime || '—';

        renderVariableInputs(prompt);
        updatePromptPreview(prompt);

        if (vtxRefImagesWrap && vtxRefImagesGrid) {
            vtxRefImagesGrid.innerHTML = '';
            if (refImages.length > 0) {
                vtxRefImagesWrap.classList.remove('d-none');
                if (vtxRefImagesCount) vtxRefImagesCount.textContent = String(refImages.length);
                refImages.forEach(function (img, i) {
                    var col = document.createElement('div');
                    col.className = 'col-4 col-md-3';
                    col.innerHTML =
                        '<a href="' + img.url + '" target="_blank" rel="noopener" class="vtx-ref-thumb" ' +
                        'title="' + (img.name || ('referans-' + (i + 1))) + ' (' + (img.mime || '') + ')">' +
                        '<img src="' + img.url + '" alt="ref-' + (i + 1) + '" loading="lazy">' +
                        '</a>';
                    vtxRefImagesGrid.appendChild(col);
                });
            } else {
                vtxRefImagesWrap.classList.add('d-none');
            }
        }

        updateBtnLabel();
    }

    if (promptSelect) {
        promptSelect.addEventListener('change', updateTemplateDetails);
        updateTemplateDetails();
    }

    // ── Progress bar width setter ──
    function setProgressWidths() {
        document.querySelectorAll('.vtx-progress-bar[aria-valuenow]').forEach(function (bar) {
            bar.style.width = bar.getAttribute('aria-valuenow') + '%';
        });
    }
    setProgressWidths();

    // ── Preview helpers ──
    function hideAllPreview() {
        [previewEmpty, previewLoading, previewResult, previewError].forEach(function (el) {
            if (el) el.classList.add('d-none');
        });
    }
    function showLoading(msg) {
        hideAllPreview();
        if (previewLoading) previewLoading.classList.remove('d-none');
        if (pollTick) pollTick.textContent = msg || '';
    }
    function showResult(data) {
        hideAllPreview();
        if (previewResult) previewResult.classList.remove('d-none');
        if (previewImage && data.image_url) {
            previewImage.src = data.image_url;
            previewImage.alt = data.prompt_name || 'Vertex Generated';
        }
        if (previewDownload && data.image_url) {
            previewDownload.href = data.image_url;
        }
        if (previewTime) {
            var parts = [];
            if (data.generation_time_ms) {
                parts.push('<i class="bi bi-stopwatch me-1"></i>' + (data.generation_time_ms / 1000).toFixed(1) + ' sn');
            }
            if (data.width && data.height) {
                parts.push(data.width + '×' + data.height);
            }
            previewTime.innerHTML = parts.join(' · ');
        }
    }
    function showError(message) {
        hideAllPreview();
        if (previewError) previewError.classList.remove('d-none');
        if (previewErrorText) previewErrorText.textContent = message || 'Bilinmeyen hata.';
    }
    function showBatchQueued(count) {
        hideAllPreview();
        if (previewLoading) previewLoading.classList.remove('d-none');
        var loadingText = previewLoading.querySelector('p');
        if (loadingText) loadingText.textContent = count + ' görsel kuyruğa alındı';
        var loadingSub = previewLoading.querySelectorAll('small');
        if (loadingSub[0]) loadingSub[0].textContent = 'Cron her 10 saniyede bir üretecek';
        if (pollTick) pollTick.textContent = 'Sayfa yenilendiğinde ilerlemeyi göreceksiniz';
    }
    function setBusy(busy) {
        if (! btn) return;
        btn.disabled = busy;
        if (busy) {
            btnText.textContent = 'Gönderiliyor...';
        } else {
            updateBtnLabel();
        }
    }

    // ── Parse validation errors ──
    function parseErrorMessage(data) {
        var msg = (data && data.message) || 'İstek başarısız.';
        if (data && data.errors) {
            var list = [];
            Object.keys(data.errors).forEach(function (key) {
                (data.errors[key] || []).forEach(function (m) { list.push(m); });
            });
            if (list.length > 0) msg = list.join(' — ');
        }
        return msg;
    }

    // ── Form submit — her istek (1 veya N) batch olarak kuyruğa alınır ──
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var promptId = promptSelect ? promptSelect.value : '';
            if (! promptId) {
                showError('Listeden bir şablon seç.');
                return;
            }

            setBusy(true);
            submitGeneration(promptId);
        });
    }

    function submitGeneration(promptId) {
        showLoading('Kuyruğa ekleniyor...');
        var variables = collectVariableValues();

        var body = {
            prompt_id: promptId,
            count: currentCount,
            variables: variables
        };

        fetch(generateUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        })
        .then(function (r) {
            return r.json().then(function (data) { return { status: r.status, data: data }; });
        })
        .then(function (result) {
            if (! result.data || ! result.data.success) {
                showError(parseErrorMessage(result.data));
                setBusy(false);
                return;
            }

            showBatchQueued(currentCount);
            setBusy(false);

            var countLabel = currentCount === 1 ? '1 görsel' : currentCount + ' görsel';
            if (window.AdminModal && AdminModal.status) {
                AdminModal.status({
                    title: 'Kuyruğa Alındı',
                    message: countLabel + ' kuyruğa eklendi. Cron tarafından üretilecek.',
                    type: 'success'
                });
            }

            setTimeout(function () { window.location.reload(); }, 2000);
        })
        .catch(function (err) {
            showError('İstek gönderilemedi: ' + (err && err.message ? err.message : 'bilinmeyen hata'));
            setBusy(false);
        });
    }

    // ── Active batch polling ──
    function initBatchPolling() {
        var activeBatchEls = document.querySelectorAll('.vtx-batch-active');
        if (activeBatchEls.length === 0) return;

        activeBatchEls.forEach(function (el) {
            var url = el.dataset.statusUrl;
            if (!url) return;
            pollBatch(el, url);
        });
    }

    function pollBatch(el, url) {
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var completed = el.querySelector('.vtx-batch-completed');
            var failed = el.querySelector('.vtx-batch-failed');
            var progress = el.querySelector('.vtx-batch-progress');
            var percent = el.querySelector('.vtx-batch-percent');
            var duration = el.querySelector('.vtx-batch-duration');

            if (completed) completed.textContent = data.completed_count;
            if (failed) failed.textContent = data.failed_count;
            if (progress) progress.style.width = data.progress + '%';
            if (percent) percent.textContent = '%' + Math.round(data.progress);
            if (duration) duration.textContent = data.duration;

            if (data.status === 'completed' || data.status === 'partial_completed' || data.status === 'cancelled') {
                window.location.reload();
                return;
            }

            setTimeout(function () { pollBatch(el, url); }, batchPollIntervalMs);
        })
        .catch(function () {
            setTimeout(function () { pollBatch(el, url); }, batchPollIntervalMs * 2);
        });
    }

    // ── Batch cancel ──
    document.querySelectorAll('.vtx-batch-cancel-form').forEach(function (frm) {
        frm.addEventListener('submit', function (e) {
            e.preventDefault();
            var cancelBtn = frm.querySelector('.vtx-batch-cancel-btn');

            if (window.AdminModal && typeof AdminModal.confirm === 'function') {
                AdminModal.confirm({
                    title: 'Batch İptal Edilsin Mi?',
                    message: 'Kuyruktaki bekleyen görseller iptal edilecek. Zaten üretilmiş görseller kalacak.',
                    type: 'warning',
                    confirmText: 'Evet, İptal Et',
                    confirmIcon: 'bi bi-x-circle',
                }).then(function (confirmed) {
                    if (confirmed) doCancelBatch(frm, cancelBtn);
                });
            } else {
                doCancelBatch(frm, cancelBtn);
            }
        });
    });

    function doCancelBatch(frm, cancelBtn) {
        if (cancelBtn) cancelBtn.disabled = true;
        fetch(frm.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                if (cancelBtn) cancelBtn.disabled = false;
                if (window.AdminModal && AdminModal.status) {
                    AdminModal.status({ title: 'Hata', message: data.message || 'İptal edilemedi.', type: 'danger' });
                }
            }
        })
        .catch(function () {
            if (cancelBtn) cancelBtn.disabled = false;
        });
    }

    // ── Init ──
    initBatchPolling();
})();
