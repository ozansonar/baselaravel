{{-- Görsel Uyumsuz — Kırpma Seçim Modal --}}
<div class="modal fade" id="igCropChoiceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ig-crop-choice-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-crop me-2"></i> Görsel Uyumsuz — Düzeltme Seçimi
                </h5>
            </div>
            <div class="modal-body">
                <p class="ig-crop-choice-msg" id="igCropChoiceMessage">
                    Görsel Instagram standartlarına uymuyor.
                </p>

                <div class="ig-crop-choice-options">
                    <button type="button" class="ig-crop-choice-btn ig-crop-choice-btn--auto" id="igCropAutoBtn">
                        <div class="ig-crop-choice-icon"><i class="bi bi-magic"></i></div>
                        <div class="ig-crop-choice-info">
                            <strong>Otomatik Kırp</strong>
                            <small>Sistem ortalayarak Instagram standardına getirir. (Önerilen — hızlı)</small>
                        </div>
                    </button>

                    <button type="button" class="ig-crop-choice-btn ig-crop-choice-btn--manual" id="igCropManualBtn">
                        <div class="ig-crop-choice-icon"><i class="bi bi-crop"></i></div>
                        <div class="ig-crop-choice-info">
                            <strong>Elle Kırp</strong>
                            <small>Kırpma alanını sen seç, ürünün ortasını koru. (CropperJS)</small>
                        </div>
                    </button>

                    <button type="button" class="ig-crop-choice-btn ig-crop-choice-btn--cancel" id="igCropCancelBtn" data-bs-dismiss="modal">
                        <div class="ig-crop-choice-icon"><i class="bi bi-x-lg"></i></div>
                        <div class="ig-crop-choice-info">
                            <strong>İptal</strong>
                            <small>Görseli yeniden seç (uygun boyutta).</small>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- CropperJS Modal — Manuel Kırpma --}}
<div class="modal fade" id="igCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ig-cropper-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-crop me-2"></i> Görseli Kırp
                    <small class="text-muted ms-2" id="igCropperRatioLabel"></small>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="ig-cropper-canvas-wrap">
                    <img id="igCropperImage" alt="Kırpılacak görsel">
                </div>
                <div class="alert alert-info mt-3 small mb-0" id="igCropperHint">
                    <i class="bi bi-info-circle me-1"></i>
                    Çerçeveyi sürükleyerek/yeniden boyutlandırarak istediğin alanı seç.
                    Aspect ratio kilitli — tipe göre otomatik ayarlandı.
                </div>
                <div class="alert alert-danger mt-3 small mb-0 d-none" id="igCropperError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn-teal" id="igCropperApplyBtn">
                    <i class="bi bi-check-lg me-1"></i> <span data-label>Kırp ve Kullan</span>
                </button>
            </div>
        </div>
    </div>
</div>
