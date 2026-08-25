{{-- Global Result Modal (success / error / warning / info) --}}
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 text-center p-4 p-md-5">
            <div class="d-flex justify-content-center mb-3">
                <span class="result-icon result-icon--info" id="resultModalIcon"></span>
            </div>
            <h5 class="fw-bold mb-2" id="resultModalTitle"></h5>
            <p class="text-muted mb-4" id="resultModalBody"></p>
            <button type="button" class="btn btn-primary px-4 mx-auto" data-bs-dismiss="modal">Tamam</button>
        </div>
    </div>
</div>
