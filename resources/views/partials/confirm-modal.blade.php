{{-- Global Confirm Modal --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 text-center p-4 p-md-5">
            <div class="d-flex justify-content-center mb-3">
                <span class="result-icon result-icon--warning"><i class="fa-solid fa-triangle-exclamation"></i></span>
            </div>
            <h5 class="fw-bold mb-2" id="confirmModalTitle">{{ __('site.actions.sure') }}</h5>
            <p class="text-muted mb-4" id="confirmModalBody"></p>
            <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">{{ __('site.actions.cancel') }}</button>
                <button type="button" class="btn btn-primary px-4" id="confirmModalConfirmBtn">{{ __('site.actions.yes') }}</button>
            </div>
        </div>
    </div>
</div>
