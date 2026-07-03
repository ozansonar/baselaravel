{{-- Vertex Galeri Seçici Modal --}}
<div class="modal fade" id="vertexGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark-card">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-clr-white">
                    <i class="bi bi-grid-3x3-gap text-teal me-2"></i>
                    <span id="vtxGalleryTitle">Vertex Galeri — Görsel Seç</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <div class="modal-body">
                {{-- Filters --}}
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select id="vtxGalleryAspect" class="form-select form-select-dark form-select-sm">
                            <option value="1:1">Feed (1:1)</option>
                            <option value="9:16">Story (9:16)</option>
                            <option value="16:9">Landscape (16:9)</option>
                            <option value="">Tümü</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select id="vtxGalleryPrompt" class="form-select form-select-dark form-select-sm">
                            <option value="">Tüm Şablonlar</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="vtxGallerySearch" class="form-control form-control-dark form-control-sm" placeholder="Ara...">
                    </div>
                </div>

                {{-- Multi-select info --}}
                <div id="vtxGalleryMultiInfo" class="d-none mb-2">
                    <small class="text-teal"><i class="bi bi-info-circle me-1"></i><span id="vtxGallerySelectedCount">0</span> görsel seçildi (max 9)</small>
                </div>

                {{-- Gallery Grid --}}
                <div id="vtxGalleryGrid" class="vtx-gallery-picker-grid"></div>

                {{-- Loading --}}
                <div id="vtxGalleryLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-teal spinner-border-sm" role="status"></div>
                    <span class="text-clr-muted ms-2">Yükleniyor...</span>
                </div>

                {{-- Empty --}}
                <div id="vtxGalleryEmpty" class="text-center py-5 d-none">
                    <i class="bi bi-images vtx-empty-icon"></i>
                    <p class="text-clr-muted mt-3">Bu filtrede görsel bulunamadı.</p>
                </div>

                {{-- Pagination --}}
                <div id="vtxGalleryPagination" class="d-flex justify-content-center gap-2 mt-3"></div>
            </div>

            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn-glass" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn-teal" id="vtxGallerySelectBtn" disabled>
                    <i class="bi bi-check-lg me-1"></i> Seç ve Kullan
                </button>
            </div>
        </div>
    </div>
</div>
