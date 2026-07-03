{{-- AI Görsel Üretme Modal --}}
<div class="modal fade" id="igAiImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ig-ai-img-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-stars me-2"></i> AI ile Görsel Üret
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Tek tıkla profesyonel ürün/çiftlik görseli üretir. Üretilen görseli Form'a aktar veya yeniden dene.
                </p>

                <div class="mb-3">
                    <label class="form-label small">Konu / Açıklama</label>
                    <textarea class="form-control" id="aiImagePromptInput" rows="3"
                              placeholder="Örn: Ahşap masada cam şişede taze süt, kırsal arka plan, sabah ışığı"
                              maxlength="2000"></textarea>
                    <small class="form-text text-muted">Boş bırakırsan medya tipine uygun varsayılan prompt kullanılır.</small>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small">Aspect Ratio</label>
                        <select class="form-select" id="aiImageAspectRatio">
                            <option value="1:1">1:1 — Kare (Feed)</option>
                            <option value="3:4">3:4 — Dikey (Feed)</option>
                            <option value="4:3">4:3 — Yatay (Feed)</option>
                            <option value="9:16">9:16 — Reels / Story</option>
                            <option value="16:9">16:9 — Yatay video</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" class="btn-teal w-100" id="aiImageGenerateBtn">
                            <i class="bi bi-magic me-1"></i> <span data-label>Görseli Üret</span>
                        </button>
                    </div>
                </div>

                <div class="ig-ai-img-result d-none" id="aiImageResultWrap">
                    <hr class="ig-ai-img-divider">
                    <label class="form-label small mb-2">Üretilen Görsel</label>
                    <img src="" alt="AI üretilen görsel" class="ig-ai-img-preview" id="aiImageResultPreview">
                    <div class="text-muted small mt-2" id="aiImageResultMeta"></div>
                </div>

                <div class="alert alert-danger d-none mt-3" id="aiImageError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn-glass d-none" id="aiImageRetryBtn">
                    <i class="bi bi-arrow-repeat me-1"></i> Yeniden Üret
                </button>
                <button type="button" class="btn-teal d-none" id="aiImageUseBtn">
                    <i class="bi bi-check-lg me-1"></i> Bu Görseli Kullan
                </button>
            </div>
        </div>
    </div>
</div>
