{{--
  AI Görsel Detay Modal — Hem /admin/instagram-posts/bulk/{id} (bulk durum)
  hem /admin/ai-images sayfasında kullanılan ortak partial.

  Açma: bir butona data-ai-image-detail="{ai_image_id}" eklersin, JS:
    fetch('/admin/ai-images/images/{id}/detail') → modal'ı doldurur, gösterir.

  Modal'ı sayfaya bir kez include et, JS event delegation ile çalışır.
--}}
<div class="modal fade" id="aiImageDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ai-detail-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-stars me-2 text-purple"></i>
                    AI Görsel Detayları
                    <span class="text-muted ms-2 small" id="aiDetailIdBadge"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <div class="modal-body">
                {{-- Loading --}}
                <div id="aiDetailLoading" class="text-center py-5">
                    <span class="spinner-border text-teal" role="status"></span>
                    <div class="mt-2 small text-muted">Detay yükleniyor…</div>
                </div>

                {{-- Error --}}
                <div id="aiDetailError" class="alert alert-danger d-none"></div>

                {{-- Content --}}
                <div id="aiDetailContent" class="d-none">

                    <div class="row g-4">
                        {{-- SOL: Üretilen görsel --}}
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2"><i class="bi bi-image me-1"></i> Üretilen Görsel</h6>
                            <div class="ai-detail-image-wrap mb-3">
                                <img id="aiDetailImage" src="" alt="Üretilen görsel" class="img-fluid rounded">
                                <div id="aiDetailImagePlaceholder" class="ai-detail-image-placeholder d-none">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Görsel üretilmedi (hatalı veya beklemede)</span>
                                </div>
                            </div>

                            <div class="small text-muted">
                                <div><strong>Model:</strong> <span id="aiDetailModel">—</span></div>
                                <div><strong>Sağlayıcı:</strong> <span id="aiDetailProvider">—</span></div>
                                <div><strong>Boyut:</strong> <span id="aiDetailDimensions">—</span></div>
                                <div><strong>Aspect Ratio:</strong> <span id="aiDetailAspect">—</span></div>
                                <div><strong>Süre:</strong> <span id="aiDetailDuration">—</span></div>
                                <div><strong>Maliyet:</strong> <span id="aiDetailCost">—</span></div>
                                <div><strong>Deneme Sayısı:</strong> <span id="aiDetailAttempts">—</span></div>
                                <div><strong>Tarih:</strong> <span id="aiDetailCreatedAt">—</span></div>
                                <div><strong>Durum:</strong> <span id="aiDetailStatus">—</span></div>
                            </div>
                        </div>

                        {{-- SAĞ: Template + prompts --}}
                        <div class="col-md-6">

                            {{-- Kullanılan template --}}
                            <h6 class="text-muted mb-2"><i class="bi bi-file-earmark-text me-1"></i> Kullanılan Template</h6>
                            <div id="aiDetailTemplateBlock" class="card-glass p-3 mb-3">
                                <div id="aiDetailNoTemplate" class="text-muted small d-none">
                                    Template kullanılmamış (raw prompt).
                                </div>
                                <div id="aiDetailTemplateInfo" class="d-none">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong id="aiDetailTemplateName" class="text-teal"></strong>
                                        <small id="aiDetailTemplateDesc" class="text-muted"></small>
                                    </div>
                                    <details>
                                        <summary class="small text-clr-secondary" style="cursor:pointer;">Şablon prompt'u</summary>
                                        <pre class="ai-detail-pre mt-2"><code id="aiDetailTemplatePrompt"></code></pre>
                                    </details>
                                </div>
                            </div>

                            {{-- Referans görsel (varsa) --}}
                            <div id="aiDetailReferenceBlock" class="d-none mb-3">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-image-fill me-1"></i> Referans Görsel
                                    <small class="text-muted">(AI'a image-to-image olarak yollandı)</small>
                                </h6>
                                <div class="ai-detail-reference-wrap">
                                    <img id="aiDetailReferenceImage" src="" alt="Referans görsel" class="img-fluid rounded">
                                </div>
                            </div>

                            {{-- Final prompt (gönderilen) --}}
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-arrow-up-right me-1"></i> Gönderilen Prompt (Final)
                            </h6>
                            <pre class="ai-detail-pre mb-3"><code id="aiDetailFinalPrompt">—</code></pre>

                            {{-- API Response (collapsible) --}}
                            <details class="mb-3">
                                <summary class="text-muted">
                                    <i class="bi bi-arrow-down-left me-1"></i> API Yanıtı (Response Payload)
                                </summary>
                                <pre class="ai-detail-pre mt-2"><code id="aiDetailResponsePayload">—</code></pre>
                            </details>

                            <details class="mb-3">
                                <summary class="text-muted">
                                    <i class="bi bi-cloud-upload me-1"></i> İstek Payload (Request)
                                </summary>
                                <pre class="ai-detail-pre mt-2"><code id="aiDetailRequestPayload">—</code></pre>
                            </details>

                            <div id="aiDetailErrorMessage" class="alert alert-warning small d-none"></div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-glass" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
    <style>
        .ai-detail-image-wrap, .ai-detail-reference-wrap {
            background: #000;
            border-radius: 8px;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
        }
        .ai-detail-image-wrap img, .ai-detail-reference-wrap img {
            max-width: 100%;
            max-height: 60vh;
            object-fit: contain;
        }
        .ai-detail-reference-wrap {
            min-height: auto;
            background: rgba(255,255,255,0.06);
            padding: 12px;
        }
        .ai-detail-reference-wrap img { max-height: 200px; }
        .ai-detail-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #888;
            gap: 8px;
        }
        .ai-detail-image-placeholder i { font-size: 2.5rem; }
        .ai-detail-pre {
            background: rgba(0,0,0,0.45);
            color: #d4d4d4;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.78rem;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            max-height: 320px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0;
        }
        .ai-detail-modal .card-glass {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        'use strict';
        var modalEl = document.getElementById('aiImageDetailModal');
        if (! modalEl || typeof bootstrap === 'undefined') return;
        var bsModal = new bootstrap.Modal(modalEl);

        var loadingEl   = document.getElementById('aiDetailLoading');
        var errorEl     = document.getElementById('aiDetailError');
        var contentEl   = document.getElementById('aiDetailContent');
        var idBadge     = document.getElementById('aiDetailIdBadge');

        function setText(id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = val == null || val === '' ? '—' : String(val);
        }
        function setHtml(id, val) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = val;
        }

        function showLoading() {
            loadingEl.classList.remove('d-none');
            contentEl.classList.add('d-none');
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }
        function showContent() {
            loadingEl.classList.add('d-none');
            contentEl.classList.remove('d-none');
            errorEl.classList.add('d-none');
        }
        function showError(msg) {
            loadingEl.classList.add('d-none');
            contentEl.classList.add('d-none');
            errorEl.textContent = msg || 'Detay yüklenemedi.';
            errorEl.classList.remove('d-none');
        }

        function formatJson(v) {
            if (v == null) return '—';
            try {
                return typeof v === 'string' ? v : JSON.stringify(v, null, 2);
            } catch (e) {
                return String(v);
            }
        }

        function loadDetail(id) {
            showLoading();
            if (idBadge) idBadge.textContent = '#' + id;

            fetch('/admin/ai-images/images/' + id + '/detail', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (r) {
                if (! r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (d) {
                // Üretilen görsel
                var img = document.getElementById('aiDetailImage');
                var ph  = document.getElementById('aiDetailImagePlaceholder');
                if (d.image_url) {
                    img.src = d.image_url;
                    img.classList.remove('d-none');
                    ph.classList.add('d-none');
                } else {
                    img.classList.add('d-none');
                    ph.classList.remove('d-none');
                }

                // Meta bilgiler
                setText('aiDetailModel', d.model);
                setText('aiDetailProvider', d.provider);
                setText('aiDetailDimensions', d.width && d.height ? d.width + '×' + d.height + ' px' : '');
                setText('aiDetailAspect', d.aspect_ratio);
                setText('aiDetailDuration', d.generation_time_ms ? d.generation_time_ms + ' ms' : '');
                setText('aiDetailCost', d.cost_usd != null ? '$' + Number(d.cost_usd).toFixed(4) : '');
                setText('aiDetailAttempts', d.attempt_count);
                setText('aiDetailCreatedAt', d.created_at);
                setText('aiDetailStatus', d.status);

                // Template
                var noTpl = document.getElementById('aiDetailNoTemplate');
                var tplInfo = document.getElementById('aiDetailTemplateInfo');
                if (d.template) {
                    noTpl.classList.add('d-none');
                    tplInfo.classList.remove('d-none');
                    setText('aiDetailTemplateName', d.template.name);
                    setText('aiDetailTemplateDesc', d.template.description || '');
                    setText('aiDetailTemplatePrompt', d.template.prompt);
                } else {
                    noTpl.classList.remove('d-none');
                    tplInfo.classList.add('d-none');
                }

                // Referans görsel
                var refBlock = document.getElementById('aiDetailReferenceBlock');
                if (d.template && d.template.reference_image_url) {
                    document.getElementById('aiDetailReferenceImage').src = d.template.reference_image_url;
                    refBlock.classList.remove('d-none');
                } else {
                    refBlock.classList.add('d-none');
                }

                // Promptlar
                setText('aiDetailFinalPrompt', d.final_prompt);
                setText('aiDetailResponsePayload', formatJson(d.response_payload));
                setText('aiDetailRequestPayload', formatJson(d.request_payload));

                // Error mesajı
                var errBlock = document.getElementById('aiDetailErrorMessage');
                if (d.error_message) {
                    errBlock.textContent = '⚠ ' + d.error_message;
                    errBlock.classList.remove('d-none');
                } else {
                    errBlock.classList.add('d-none');
                }

                showContent();
            })
            .catch(function (err) { showError('Detay yüklenemedi: ' + err.message); });
        }

        // Trigger: data-ai-image-detail="{id}" olan butonlara click
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-ai-image-detail]');
            if (! trigger) return;
            var id = trigger.getAttribute('data-ai-image-detail');
            if (! id) return;
            e.preventDefault();
            bsModal.show();
            loadDetail(id);
        });
    })();
    </script>
    @endpush
@endonce
