{{-- Global Result Modal (Success / Error) --}}
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content result-modal">
            <button type="button" class="result-modal-close" data-bs-dismiss="modal" aria-label="Kapat">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="result-modal-icon-wrap" id="resultModalIconWrap">
                <i id="resultModalIcon"></i>
            </div>
            <h5 class="result-modal-title" id="resultModalTitle"></h5>
            <div class="result-modal-body" id="resultModalBody"></div>
            <button type="button" class="result-modal-btn" id="resultModalBtn" data-bs-dismiss="modal">Tamam</button>
        </div>
    </div>
</div>
