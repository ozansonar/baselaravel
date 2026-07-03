/**
 * Instagram Posts — INDEX page only.
 *
 * Sorumluluklar:
 *   - Toplu seçim (select all, row checkbox, clear)
 *   - Toplu işlem confirm modal
 *   - Tekil delete + publish-now confirm modal
 *
 * Form (create/edit) ile ilgili kod instagram-posts-form.js içinde.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // ── Bulk select / clear ─────────────────────────────────────
        var bulkToolbar = document.getElementById('igBulkToolbar');
        var bulkCount   = document.getElementById('igBulkCount');
        var bulkClear   = document.getElementById('igBulkClear');
        var selectAll   = document.getElementById('igSelectAll');
        var rowChecks   = document.querySelectorAll('.ig-row-check');

        function refreshBulkUI() {
            if (! bulkToolbar) return;
            var checked = document.querySelectorAll('.ig-row-check:checked');
            if (checked.length === 0) {
                bulkToolbar.style.display = 'none';
                return;
            }
            bulkToolbar.style.display = 'flex';
            if (bulkCount) bulkCount.textContent = String(checked.length);
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rowChecks.forEach(function (cb) { cb.checked = selectAll.checked; });
                refreshBulkUI();
            });
        }

        rowChecks.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (selectAll) {
                    var allChecked = Array.from(rowChecks).every(function (c) { return c.checked; });
                    selectAll.checked = allChecked;
                }
                refreshBulkUI();
            });
        });

        if (bulkClear) {
            bulkClear.addEventListener('click', function () {
                rowChecks.forEach(function (cb) { cb.checked = false; });
                if (selectAll) selectAll.checked = false;
                refreshBulkUI();
            });
        }

        // ── Bulk action confirm ─────────────────────────────────────
        document.querySelectorAll('.ig-bulk-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (! btn.dataset.confirm || typeof AdminModal === 'undefined') return;
                if (btn.dataset.confirmed === '1') return;
                e.preventDefault();
                AdminModal.confirm({
                    title: 'Toplu İşlem',
                    message: btn.dataset.confirm,
                    type: btn.classList.contains('ig-bulk-danger') ? 'danger' : 'warning',
                    confirmText: 'Evet, Devam Et',
                }).then(function (ok) {
                    if (ok) {
                        btn.dataset.confirmed = '1';
                        btn.click();
                    }
                });
            });
        });

        // ── Delete + Publish-Now confirm formları ───────────────────
        document.querySelectorAll('.ig-confirm-delete').forEach(function (formEl) {
            formEl.addEventListener('submit', function (e) {
                if (typeof AdminModal === 'undefined') return;
                e.preventDefault();
                AdminModal.confirm({
                    title: 'Gönderiyi Sil',
                    message: 'Bu gönderiyi silmek istediğine emin misin?',
                    type: 'danger',
                    confirmText: 'Evet, Sil',
                    confirmIcon: 'bi bi-trash',
                }).then(function (ok) {
                    if (ok) formEl.submit();
                });
            });
        });

        document.querySelectorAll('.ig-confirm-publish').forEach(function (formEl) {
            formEl.addEventListener('submit', function (e) {
                if (typeof AdminModal === 'undefined') return;
                e.preventDefault();
                AdminModal.confirm({
                    title: 'Hemen Yayınla',
                    message: 'Bu gönderi Instagram hesabında hemen yayınlanacak. Devam edilsin mi?',
                    type: 'warning',
                    confirmText: 'Evet, Yayınla',
                    confirmIcon: 'bi bi-send-fill',
                }).then(function (ok) {
                    if (ok) formEl.submit();
                });
            });
        });

        refreshBulkUI();

        // ── Cron Widget — canlı geri sayım ──────────────────────────
        // Sıradaki cron çalışmasına kalan süreyi mm:ss formatında gösterir.
        // 0'a inince sayfayı reload eder (yeni last_run / scheduled_due / vs).
        var cronWidget = document.querySelector('.ig-cron-widget');
        if (cronWidget) {
            var initialSeconds = parseInt(cronWidget.getAttribute('data-next-run-seconds') || '0', 10);
            if (initialSeconds > 0) {
                var countdownEl = cronWidget.querySelector('[data-cron-countdown]');
                var remaining = initialSeconds;
                var reloadGuard = false;

                function format(sec) {
                    var m = Math.floor(sec / 60);
                    var s = sec % 60;
                    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                }

                function tick() {
                    if (countdownEl) countdownEl.textContent = format(Math.max(0, remaining));
                    if (remaining <= 0 && ! reloadGuard) {
                        reloadGuard = true;
                        // Cron çalışmış olmalı — 5 sn daha bekle (heartbeat yazılsın)
                        // sonra sayfayı reload et ki yeni last_run / sayaç verileri gelsin
                        setTimeout(function () { window.location.reload(); }, 5000);
                    }
                    remaining--;
                }
                tick();
                setInterval(tick, 1000);
            }
        }

        // ── Galeri Lightbox ─────────────────────────────────────────
        // [data-ig-gallery] thumbnail'a tıklayınca modal açar.
        // data-ig-images JSON: [{url, type:'image'|'video', alt, poster?}]
        var galleryModalEl = document.getElementById('igGalleryModal');
        if (galleryModalEl && typeof bootstrap !== 'undefined') {
            var galleryBsModal  = bootstrap.Modal.getOrCreateInstance(galleryModalEl);
            var galleryStage    = document.getElementById('igGalleryStage');
            var galleryTitle    = document.getElementById('igGalleryTitle');
            var galleryCounter  = document.getElementById('igGalleryCounter');
            var galleryPrev     = document.getElementById('igGalleryPrev');
            var galleryNext     = document.getElementById('igGalleryNext');

            var currentItems = [];
            var currentIndex = 0;

            function renderItem(item) {
                if (! galleryStage) return;
                galleryStage.innerHTML = '';

                if (item.type === 'video') {
                    var video = document.createElement('video');
                    video.src = item.url;
                    if (item.poster) video.poster = item.poster;
                    video.controls = true;
                    video.autoplay = true;
                    video.preload = 'metadata';
                    video.className = 'ig-gallery-media';
                    galleryStage.appendChild(video);
                } else {
                    var img = document.createElement('img');
                    img.src = item.url;
                    img.alt = item.alt || 'Görsel';
                    img.className = 'ig-gallery-media';
                    galleryStage.appendChild(img);
                }
            }

            function updateCounter() {
                if (galleryCounter) {
                    galleryCounter.textContent = (currentIndex + 1) + ' / ' + currentItems.length;
                }
                var multiple = currentItems.length > 1;
                if (galleryPrev) galleryPrev.style.display = multiple ? '' : 'none';
                if (galleryNext) galleryNext.style.display = multiple ? '' : 'none';
            }

            function showIndex(i) {
                if (currentItems.length === 0) return;
                currentIndex = (i + currentItems.length) % currentItems.length;
                renderItem(currentItems[currentIndex]);
                updateCounter();
            }

            function openGallery(items, title) {
                if (! Array.isArray(items) || items.length === 0) return;
                currentItems = items;
                currentIndex = 0;
                if (galleryTitle && title) galleryTitle.textContent = title;
                showIndex(0);
                galleryBsModal.show();
            }

            function closeStage() {
                // Modal kapanırken video varsa durdur (otoplay leak önle)
                if (! galleryStage) return;
                var v = galleryStage.querySelector('video');
                if (v) {
                    try { v.pause(); v.removeAttribute('src'); v.load(); } catch (e) {}
                }
                galleryStage.innerHTML = '';
            }

            // Trigger'lara event delegate (tablo dinamik değişebilir)
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-ig-gallery]');
                if (! trigger) return;
                // Checkbox'a tıklamayı bypass et
                if (e.target.closest('input, button, a')) return;
                e.preventDefault();
                var raw = trigger.getAttribute('data-ig-images');
                var title = trigger.getAttribute('data-ig-gallery-title') || 'Görsel Galerisi';
                if (! raw) return;
                try {
                    var items = JSON.parse(raw);
                    openGallery(items, title);
                } catch (err) {
                    console.warn('Galeri JSON parse hatası:', err);
                }
            });

            // Keyboard: trigger'lara Enter/Space ile aç (a11y)
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var focused = document.activeElement;
                if (! focused || ! focused.matches('[data-ig-gallery]')) return;
                e.preventDefault();
                focused.click();
            });

            // Modal içi navigasyon
            if (galleryPrev) galleryPrev.addEventListener('click', function () { showIndex(currentIndex - 1); });
            if (galleryNext) galleryNext.addEventListener('click', function () { showIndex(currentIndex + 1); });

            // Klavye nav (sadece modal açıkken)
            document.addEventListener('keydown', function (e) {
                if (! galleryModalEl.classList.contains('show')) return;
                if (e.key === 'ArrowLeft')  { e.preventDefault(); showIndex(currentIndex - 1); }
                if (e.key === 'ArrowRight') { e.preventDefault(); showIndex(currentIndex + 1); }
            });

            // Modal kapanırken stage temizle (video pause)
            galleryModalEl.addEventListener('hidden.bs.modal', closeStage);
        }
    });
})();
