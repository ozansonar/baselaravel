{{-- Editörün dosya seçicisi.

     TinyMCE'nin "Bir resim arayın" düğmesi buraya bağlanıyor: kullanıcı
     public/uploads içinde gezip dosya seçebiliyor, yeni dosya yükleyebiliyor ve
     silebiliyor. Editörün olduğu her ekranda aynı partial kullanılıyor. --}}
<div class="modal fade modal-custom" id="filePickerModal" tabindex="-1" aria-hidden="true"
     aria-labelledby="filePickerTitle">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-theme">
        <div class="modal-content modal-content-theme">
            <div class="modal-header">
                <h6 class="modal-title" id="filePickerTitle">
                    <i class="bi bi-folder2-open me-2 text-teal"></i>Dosya Seç
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <div class="modal-body">
                <div class="fp-toolbar">
                    <nav class="fp-breadcrumb" id="fpBreadcrumb" aria-label="Klasör yolu"></nav>

                    <div class="fp-toolbar__actions">
                        <label class="stg-label visually-hidden" for="fpSearch">Dosya ara</label>
                        <input type="search" class="stg-input stg-input--sm" id="fpSearch"
                               placeholder="Dosya ara..." data-fv-ignore autocomplete="off">

                        {{-- Girdi gizli: yükleme düğmesi kendi görünümünü taşıyor,
                             file-input.js böyle alanları zaten atlıyor. --}}
                        <input type="file" id="fpUploadInput" hidden data-fv-ignore>
                        <button type="button" class="btn-teal btn-sm" id="fpUploadBtn">
                            <i class="bi bi-upload"></i> Yükle
                        </button>
                    </div>
                </div>

                <div class="fp-status" id="fpStatus" role="status" aria-live="polite"></div>

                <div class="fp-grid" id="fpGrid"></div>

                <button type="button" class="btn-glass btn-sm w-100 mt-3 d-none" id="fpMore">
                    Daha fazla göster
                </button>
            </div>

            <div class="modal-footer">
                <span class="fp-selected" id="fpSelected">Dosya seçilmedi</span>
                <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn-teal" id="fpChoose" disabled>
                    <i class="bi bi-check-lg"></i> Seç
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        window.filePickerConfig = @js([
            'listUrl'   => route('admin.file-browser.index'),
            'uploadUrl' => route('admin.file-browser.store'),
            'deleteUrl' => route('admin.file-browser.destroy'),
            'canDelete' => auth()->user()?->can('deleteAny', App\Models\UploadedFile::class) ?? false,
            'canUpload' => auth()->user()?->can('create', App\Models\UploadedFile::class) ?? false,
        ]);
    </script>
    <script src="{{ versioned_asset('assets/admin/js/file-picker.js') }}"></script>
@endpush
