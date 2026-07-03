{{-- ==================== AI LOADING MODAL ==================== --}}
<div class="modal fade" id="globalAiLoadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="ai-loading-icon">
                    <i class="bi bi-robot"></i>
                </div>
                <h4 class="fw-800-mb" id="galm-title">AI Üretiyor...</h4>
                <p class="text-muted-label" id="galm-message">İçerik oluşturuluyor, lütfen bekleyin.</p>
                <div class="ai-loading-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== GLOBAL CONFIRM MODAL ==================== --}}
<div class="modal fade" id="globalConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="confirm-status-icon" id="gcm-icon-wrapper">
                    <i class="confirm-icon-animated" id="gcm-icon"></i>
                    <div class="confirm-icon-ring" id="gcm-ring"></div>
                </div>
                <h4 class="fw-800-mb mt-2" id="gcm-title"></h4>
                <p class="confirm-desc" id="gcm-message"></p>
                <div class="confirm-detail-box d-none" id="gcm-detail">
                    <div class="d-flex align-items-center gap-3">
                        <div class="confirm-detail-icon" id="gcm-detail-icon">
                            <i id="gcm-detail-icon-i"></i>
                        </div>
                        <div class="text-start">
                            <div class="confirm-detail-title" id="gcm-detail-title"></div>
                            <div class="confirm-detail-meta" id="gcm-detail-meta"></div>
                        </div>
                    </div>
                </div>
                <div class="confirm-warning-strip d-none" id="gcm-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span id="gcm-warning-text"></span>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <button class="btn-glass" data-bs-dismiss="modal" id="gcm-cancel-btn">
                        <i class="bi bi-x-lg"></i> <span id="gcm-cancel-text">Vazgeç</span>
                    </button>
                    <button class="btn-danger-custom" id="gcm-confirm-btn">
                        <i id="gcm-confirm-icon"></i> <span id="gcm-confirm-text"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ==================== GLOBAL STATUS MODAL ==================== --}}
<div class="modal fade" id="globalStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="status-modal-icon" id="gsm-icon-wrapper">
                    <i id="gsm-icon"></i>
                </div>
                <h4 class="fw-800-mb" id="gsm-title"></h4>
                <p class="text-muted-label" id="gsm-message"></p>
                <button class="btn-teal" data-bs-dismiss="modal" id="gsm-close-btn">
                    <i class="bi bi-check-lg"></i> <span id="gsm-close-text">Tamam</span>
                </button>
            </div>
        </div>
    </div>
</div>
