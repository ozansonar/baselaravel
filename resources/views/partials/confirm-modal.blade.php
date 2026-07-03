{{-- Global Confirm Modal --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content confirm-modal">
            <button type="button" class="confirm-modal__close" data-bs-dismiss="modal" aria-label="Kapat">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="confirm-modal__icon-wrap" id="confirmModalIconWrap">
                <i id="confirmModalIcon"></i>
            </div>
            <h5 class="confirm-modal__title" id="confirmModalTitle"></h5>
            <div class="confirm-modal__body" id="confirmModalBody"></div>
            <div class="confirm-modal__actions">
                <button type="button" class="confirm-modal__btn confirm-modal__btn--cancel" id="confirmModalCancelBtn" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="confirm-modal__btn confirm-modal__btn--confirm" id="confirmModalConfirmBtn">Evet, Sil</button>
            </div>
        </div>
    </div>
</div>
