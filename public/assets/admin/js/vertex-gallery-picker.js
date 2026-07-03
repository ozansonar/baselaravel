(function () {
    'use strict';

    var modal = document.getElementById('vertexGalleryModal');
    if (!modal) return;

    var galleryUrl = '/admin/vertex/gallery-api';
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    var grid = document.getElementById('vtxGalleryGrid');
    var loading = document.getElementById('vtxGalleryLoading');
    var empty = document.getElementById('vtxGalleryEmpty');
    var pagination = document.getElementById('vtxGalleryPagination');
    var selectBtn = document.getElementById('vtxGallerySelectBtn');
    var aspectSelect = document.getElementById('vtxGalleryAspect');
    var promptSelect = document.getElementById('vtxGalleryPrompt');
    var searchInput = document.getElementById('vtxGallerySearch');
    var titleEl = document.getElementById('vtxGalleryTitle');
    var multiInfo = document.getElementById('vtxGalleryMultiInfo');
    var selectedCountEl = document.getElementById('vtxGallerySelectedCount');

    var openBtn = document.getElementById('igVertexGalleryBtn');
    var carouselBtn = document.getElementById('igVertexCarouselBtn');

    var MODE_SINGLE = 'single';
    var MODE_MULTI = 'multi';
    var MAX_CAROUSEL = 9;

    var currentMode = MODE_SINGLE;
    var selectedItem = null;
    var selectedItems = [];
    var currentPage = 1;
    var searchTimeout = null;

    // Single mode: ana görsel seçimi
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            openModal(MODE_SINGLE);
        });
    }

    // Multi mode: carousel seçimi
    if (carouselBtn) {
        carouselBtn.addEventListener('click', function () {
            openModal(MODE_MULTI);
        });
    }

    function openModal(mode) {
        currentMode = mode;
        selectedItem = null;
        selectedItems = [];
        selectBtn.disabled = true;

        if (mode === MODE_MULTI) {
            titleEl.textContent = 'Vertex Galeri — Carousel Görselleri Seç';
            multiInfo.classList.remove('d-none');
            updateMultiCount();
        } else {
            titleEl.textContent = 'Vertex Galeri — Görsel Seç';
            multiInfo.classList.add('d-none');
        }

        var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();
        loadGallery(1);
    }

    aspectSelect.addEventListener('change', function () { loadGallery(1); });
    promptSelect.addEventListener('change', function () { loadGallery(1); });
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () { loadGallery(1); }, 400);
    });

    selectBtn.addEventListener('click', function () {
        if (currentMode === MODE_MULTI) {
            if (selectedItems.length === 0) return;
            applyCarouselSelection(selectedItems);
        } else {
            if (!selectedItem) return;
            applySingleSelection(selectedItem);
        }
        bootstrap.Modal.getInstance(modal)?.hide();
    });

    function loadGallery(page) {
        currentPage = page;
        if (currentMode === MODE_SINGLE) {
            selectedItem = null;
        }
        selectBtn.disabled = currentMode === MODE_SINGLE ? true : selectedItems.length === 0;
        grid.innerHTML = '';
        empty.classList.add('d-none');
        loading.classList.remove('d-none');

        var params = new URLSearchParams();
        if (aspectSelect.value) params.set('aspect_ratio', aspectSelect.value);
        if (promptSelect.value) params.set('prompt_id', promptSelect.value);
        if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
        params.set('page', String(page));

        fetch(galleryUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            loading.classList.add('d-none');
            renderGrid(data.items || []);
            renderPagination(data.current_page, data.last_page);
            loadPrompts(data);
            if (!data.items || data.items.length === 0) {
                empty.classList.remove('d-none');
            }
        })
        .catch(function () {
            loading.classList.add('d-none');
            empty.classList.remove('d-none');
        });
    }

    function renderGrid(items) {
        grid.innerHTML = '';
        items.forEach(function (item) {
            var el = document.createElement('div');
            el.className = 'vtx-gallery-picker-item';
            el.dataset.id = item.id;
            el.dataset.imagePath = item.image_path;
            el.dataset.fullUrl = item.full_url;

            var isSelected = currentMode === MODE_MULTI && selectedItems.some(function (s) { return s.id === item.id; });
            if (isSelected) el.classList.add('selected');

            var hasContent = item.ig_caption || item.ig_hashtags;
            el.innerHTML =
                '<img src="' + item.thumb_url + '" alt="' + (item.prompt_name || 'Vertex') + '" loading="lazy">' +
                '<div class="vtx-gallery-picker-overlay">' +
                    '<small>' + (item.prompt_name || '') + (hasContent ? ' <i class="bi bi-chat-square-text-fill text-teal"></i>' : '') + '</small>' +
                    '<small>' + item.aspect_ratio + ' · ' + item.created_at + '</small>' +
                '</div>' +
                '<div class="vtx-gallery-picker-check"><i class="bi bi-check-lg"></i></div>';

            el.addEventListener('click', function () {
                if (currentMode === MODE_MULTI) {
                    handleMultiClick(el, item);
                } else {
                    handleSingleClick(el, item);
                }
            });

            grid.appendChild(el);
        });
    }

    function handleSingleClick(el, item) {
        document.querySelectorAll('.vtx-gallery-picker-item.selected').forEach(function (s) {
            s.classList.remove('selected');
        });
        el.classList.add('selected');
        selectedItem = item;
        selectBtn.disabled = false;
    }

    function handleMultiClick(el, item) {
        var idx = selectedItems.findIndex(function (s) { return s.id === item.id; });
        if (idx >= 0) {
            selectedItems.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            if (selectedItems.length >= MAX_CAROUSEL) return;
            selectedItems.push(item);
            el.classList.add('selected');
        }
        updateMultiCount();
        selectBtn.disabled = selectedItems.length === 0;
    }

    function updateMultiCount() {
        if (selectedCountEl) {
            selectedCountEl.textContent = String(selectedItems.length);
        }
    }

    function renderPagination(current, last) {
        pagination.innerHTML = '';
        if (last <= 1) return;

        for (var p = 1; p <= last; p++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-glass btn-glass-sm' + (p === current ? ' active' : '');
            btn.textContent = String(p);
            btn.dataset.page = p;
            btn.addEventListener('click', function () {
                loadGallery(parseInt(this.dataset.page));
            });
            pagination.appendChild(btn);
        }
    }

    function loadPrompts(data) {
        if (promptSelect.options.length > 1) return;
        (data.prompts || []).forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            promptSelect.appendChild(opt);
        });
    }

    function applySingleSelection(item) {
        var aiImagePathInput = document.getElementById('aiImagePath');
        if (aiImagePathInput) {
            aiImagePathInput.value = item.image_path;
        }

        var existingPreview = document.querySelector('.ig-existing-image');
        var dropzoneContent = document.getElementById('mainDropzoneContent');

        if (dropzoneContent) {
            dropzoneContent.innerHTML =
                '<img src="' + item.full_url + '" class="img-fluid rounded vtx-picker-preview">' +
                '<small class="d-block mt-2 text-teal"><i class="bi bi-check-circle me-1"></i>Vertex galerisinden seçildi</small>';
        }

        if (existingPreview) {
            existingPreview.src = item.full_url;
        }

        var previewImg = document.querySelector('[data-ig-preview-image]');
        if (previewImg) {
            previewImg.src = item.full_url;
        }

        if (item.ig_caption) {
            var captionEl = document.getElementById('caption') || document.querySelector('[name="caption"]');
            if (captionEl && captionEl.value === '') {
                captionEl.value = item.ig_caption;
            }
        }
        if (item.ig_hashtags) {
            var hashtagsEl = document.getElementById('hashtags') || document.querySelector('[name="hashtags"]');
            if (hashtagsEl && hashtagsEl.value === '') {
                hashtagsEl.value = item.ig_hashtags;
            }
        }
    }

    function applyCarouselSelection(items) {
        var pathsContainer = document.getElementById('vtxCarouselPaths');
        var previewContainer = document.getElementById('vtxCarouselPreview');
        if (!pathsContainer || !previewContainer) return;

        pathsContainer.innerHTML = '';
        previewContainer.innerHTML = '';

        items.forEach(function (item, idx) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'vertex_carousel_paths[]';
            hidden.value = item.image_path;
            pathsContainer.appendChild(hidden);

            var card = document.createElement('div');
            card.className = 'ig-carousel-item';
            card.innerHTML =
                '<img src="' + item.thumb_url + '" alt="Carousel #' + (idx + 1) + '">' +
                '<small class="d-block text-center text-clr-muted mt-1">#' + (idx + 1) + '</small>';
            previewContainer.appendChild(card);
        });

        previewContainer.classList.remove('d-none');
    }
})();
