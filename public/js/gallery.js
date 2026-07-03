'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── Tab Navigation ──
    var tabs = document.querySelectorAll('.gallery-tab');
    var contents = document.querySelectorAll('.gallery-content');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            this.classList.add('active');

            var tabId = this.getAttribute('data-tab');
            contents.forEach(function (content) {
                content.classList.remove('active');
                if (content.id === tabId) {
                    content.classList.add('active');
                }
            });
        });
    });

    // ── Category Filter ──
    var filterBtns = document.querySelectorAll('.filter-btn');
    var photoItems = document.querySelectorAll('.photo-item');

    var filterEmptyMsg = document.getElementById('photoFilterEmpty');

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');

            var filter = this.getAttribute('data-filter');
            var visibleCount = 0;

            photoItems.forEach(function (item) {
                if (filter === 'all' || item.getAttribute('data-category') === filter) {
                    item.classList.remove('gallery-hidden');
                    visibleCount++;
                } else {
                    item.classList.add('gallery-hidden');
                }
            });

            if (filterEmptyMsg) {
                if (visibleCount === 0 && photoItems.length > 0) {
                    filterEmptyMsg.classList.remove('d-none');
                } else {
                    filterEmptyMsg.classList.add('d-none');
                }
            }
        });
    });

    // ── Lightbox ──
    var lightbox = document.getElementById('lightbox');
    var lightboxImage = document.getElementById('lightboxImage');
    var lightboxTitle = document.getElementById('lightboxTitle');
    var lightboxDesc = document.getElementById('lightboxDesc');
    var currentPhotoIndex = 0;

    if (lightbox) {
        photoItems.forEach(function (photo, index) {
            photo.addEventListener('click', function () {
                currentPhotoIndex = index;
                openLightbox(this);
            });
        });

        document.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
        document.querySelector('.lightbox-prev').addEventListener('click', function () {
            navigateLightbox(-1);
        });
        document.querySelector('.lightbox-next').addEventListener('click', function () {
            navigateLightbox(1);
        });

        lightbox.addEventListener('click', function (e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
    }

    function openLightbox(photo) {
        var title = photo.getAttribute('data-title');
        var desc = photo.getAttribute('data-desc');
        var img = photo.querySelector('img');

        lightboxTitle.textContent = title || '';
        lightboxDesc.textContent = desc || '';

        if (img) {
            lightboxImage.innerHTML = '';
            var clonedImg = document.createElement('img');
            clonedImg.src = img.src;
            clonedImg.alt = img.alt || title;
            clonedImg.loading = 'eager';
            lightboxImage.appendChild(clonedImg);
        } else {
            lightboxImage.innerHTML = '<i class="fa-solid fa-image"></i>';
        }

        lightbox.classList.add('active');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    function navigateLightbox(direction) {
        var visiblePhotos = Array.from(photoItems).filter(function (p) {
            return !p.classList.contains('gallery-hidden');
        });

        if (visiblePhotos.length === 0) return;

        var currentVisible = visiblePhotos.indexOf(photoItems[currentPhotoIndex]);
        currentVisible += direction;

        if (currentVisible < 0) currentVisible = visiblePhotos.length - 1;
        if (currentVisible >= visiblePhotos.length) currentVisible = 0;

        currentPhotoIndex = Array.from(photoItems).indexOf(visiblePhotos[currentVisible]);
        openLightbox(photoItems[currentPhotoIndex]);
    }

    // ── Video Modal ──
    var videoModal = document.getElementById('videoModal');
    var videoContainer = document.getElementById('videoContainer');
    var videoThumbnails = document.querySelectorAll('.video-thumbnail');

    if (videoModal) {
        videoThumbnails.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var videoUrl = this.getAttribute('data-video-url');
                openVideoModal(videoUrl);
            });
        });

        document.querySelector('.video-modal-close').addEventListener('click', closeVideoModal);

        videoModal.addEventListener('click', function (e) {
            if (e.target === this) {
                closeVideoModal();
            }
        });
    }

    function openVideoModal(url) {
        if (url) {
            var embedUrl = convertToEmbedUrl(url);
            videoContainer.innerHTML = '<iframe src="' + embedUrl + '" allowfullscreen allow="autoplay"></iframe>';
        } else {
            videoContainer.innerHTML = '<i class="fa-solid fa-play-circle"></i>';
        }

        videoModal.classList.add('active');
        videoModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeVideoModal() {
        videoModal.classList.remove('active');
        videoModal.setAttribute('aria-hidden', 'true');
        videoContainer.innerHTML = '<i class="fa-solid fa-play-circle"></i>';
        document.body.classList.remove('overflow-hidden');
    }

    function convertToEmbedUrl(url) {
        // YouTube
        var ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
        if (ytMatch) {
            return 'https://www.youtube.com/embed/' + ytMatch[1] + '?autoplay=1';
        }
        // Vimeo
        var vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
        if (vimeoMatch) {
            return 'https://player.vimeo.com/video/' + vimeoMatch[1] + '?autoplay=1';
        }
        return url;
    }

    // ── Keyboard Navigation ──
    document.addEventListener('keydown', function (e) {
        if (lightbox && lightbox.classList.contains('active')) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') navigateLightbox(-1);
            if (e.key === 'ArrowRight') navigateLightbox(1);
        }

        if (videoModal && videoModal.classList.contains('active')) {
            if (e.key === 'Escape') closeVideoModal();
        }
    });

});
