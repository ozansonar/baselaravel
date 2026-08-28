{{-- Editörün dosya seçicisi.

     TinyMCE'nin "Gözat" düğmesi buraya bağlanıyor: kullanıcı public/uploads
     içinde gezip dosya seçebiliyor, yeni dosya yükleyebiliyor ve silebiliyor.
     Editörün olduğu her ekranda aynı partial kullanılıyor.

     Klasörler ızgarada değil kendi şeridinde duruyor: dosya kutularıyla aynı
     boyutta çizildiklerinde on bir klasör pencerenin tamamını kaplıyor ve
     asıl aranan dosyalar ekrana hiç sığmıyordu. --}}
<div class="modal fade modal-custom" id="filePickerModal" tabindex="-1" aria-hidden="true"
     aria-labelledby="filePickerTitle">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-theme">
        <div class="modal-content modal-content-theme fp-modal">
            <div class="modal-header">
                <h6 class="modal-title" id="filePickerTitle">
                    <i class="bi bi-folder2-open me-2 text-teal"></i>Dosya Seç
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <div class="modal-body fp-body" id="fpBody">

                {{-- Üst çubuk: yol + arama + yükleme --}}
                <div class="fp-toolbar">
                    <nav class="fp-breadcrumb" id="fpBreadcrumb" aria-label="Klasör yolu"></nav>

                    <div class="fp-toolbar__actions">
                        <label class="visually-hidden" for="fpSearch">Dosya ara</label>
                        <div class="fp-search">
                            <i class="bi bi-search"></i>
                            <input type="search" id="fpSearch" placeholder="Dosya ara..."
                                   data-fv-ignore autocomplete="off">
                        </div>

                        {{-- Girdi gizli: yükleme düğmesi kendi görünümünü taşıyor,
                             file-input.js böyle alanları zaten atlıyor. --}}
                        <input type="file" id="fpUploadInput" hidden multiple data-fv-ignore>
                        <button type="button" class="btn-teal btn-sm" id="fpUploadBtn">
                            <i class="bi bi-upload"></i> Yükle
                        </button>
                    </div>
                </div>

                {{-- Tür süzgeci ve görünüm anahtarı --}}
                <div class="fp-filters">
                    <div class="fp-chips" role="group" aria-label="Dosya türü">
                        <button type="button" class="fp-chip is-active" data-fp-type="">
                            <i class="bi bi-collection"></i>Tümü
                        </button>
                        <button type="button" class="fp-chip" data-fp-type="image">
                            <i class="bi bi-image"></i>Görsel
                        </button>
                        <button type="button" class="fp-chip" data-fp-type="document">
                            <i class="bi bi-file-earmark-text"></i>Belge
                        </button>
                        <button type="button" class="fp-chip" data-fp-type="video">
                            <i class="bi bi-camera-reels"></i>Video
                        </button>
                        <button type="button" class="fp-chip" data-fp-type="audio">
                            <i class="bi bi-music-note-beamed"></i>Ses
                        </button>
                        <button type="button" class="fp-chip" data-fp-type="archive">
                            <i class="bi bi-file-earmark-zip"></i>Arşiv
                        </button>
                    </div>

                    <div class="fp-view-switch" role="group" aria-label="Görünüm">
                        <button type="button" class="fp-view-switch__btn is-active" data-fp-view="grid"
                                title="Izgara" aria-label="Izgara görünümü">
                            <i class="bi bi-grid-3x3-gap-fill"></i>
                        </button>
                        <button type="button" class="fp-view-switch__btn" data-fp-view="list"
                                title="Liste" aria-label="Liste görünümü">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </div>
                </div>

                {{-- Klasör şeridi --}}
                <div class="fp-folders d-none" id="fpFolders"></div>

                <div class="fp-status" id="fpStatus" role="status" aria-live="polite"></div>

                <div class="fp-grid" id="fpGrid"></div>

                <button type="button" class="btn-glass btn-sm w-100 mt-3 d-none" id="fpMore">
                    Daha fazla göster
                </button>

                {{-- Sürükle-bırak perdesi: gövdenin tamamı hedef --}}
                <div class="fp-drop" id="fpDrop" aria-hidden="true">
                    <div class="fp-drop__inner">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <span>Bırak, bu klasöre yüklensin</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer fp-footer">
                <div class="fp-selected" id="fpSelected">
                    <div class="fp-selected__thumb"><i class="bi bi-hand-index"></i></div>
                    <div class="fp-selected__info">
                        <span class="fp-selected__name">Dosya seçilmedi</span>
                        <span class="fp-selected__meta">Listeden bir dosyaya tıkla</span>
                    </div>
                </div>

                <div class="fp-footer__actions">
                    <button type="button" class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="button" class="btn-teal" id="fpChoose" disabled>
                        <i class="bi bi-check-lg"></i> Seç
                    </button>
                </div>
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
            'maxUploadMb' => 10,
        ]);
    </script>
    <script src="{{ versioned_asset('assets/admin/js/file-picker.js') }}"></script>
@endpush
