/**
 * Admin paneli bildirim bell + sticky popup toast sistemi.
 *
 * Davranış:
 *   - Bell icon'a tıklayınca son 10 bildirim AJAX ile yüklenir
 *   - Her 15 saniyede bir backend'den unread refresh + yeni bildirim taraması
 *   - Tıklanan bildirim okundu olarak işaretlenir
 *   - Action URL varsa o sayfaya gider
 *
 * Popup toast (sticky):
 *   - Yeni gelen bildirim 'order_created'/'order_status_changed' tip
 *     veya warning/error/critical level ise sağ alt köşede sticky toast olur
 *   - Otomatik kapanma YOK — kullanıcı "Görüntüle" veya "Okundu" tıklayana
 *     kadar durur
 *   - "Görüntüle" → action_url'e git + okundu işaretle
 *   - "Okundu" → okundu işaretle + toast kalkar
 *   - Birden fazla popup üst üste stack
 *
 * Browser OS bildirim:
 *   - Notification API izin verilmişse, sekme arka plandayken OS bildirim
 *   - İlk kullanımda permission iste (sessizce)
 */
'use strict';

(function () {
    var dropdownList = document.getElementById('ntDropdownList');
    var bellBtn = document.getElementById('ntBellBtn');
    if (! dropdownList || ! bellBtn) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';
    var endpoint = '/admin/bildirimler/recent';

    var loaded = false;
    var seenIds = new Set();       // İlk yüklemede DB'deki bildirimleri "görüldü" sayıyoruz
    var firstPoll = true;          // İlk polling'de popup çıkarma (eski bildirimler için)
    var popupContainer = null;
    var POPUP_TYPES = ['order_created', 'order_status_changed'];
    var POPUP_LEVELS = ['warning', 'error', 'critical'];

    bellBtn.addEventListener('show.bs.dropdown', function () {
        if (loaded) return;
        loadNotifications();
    });

    function loadNotifications() {
        fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            renderList(data);
            updateBadge(data.unread_count);
            loaded = true;
        })
        .catch(function () {
            dropdownList.innerHTML = '<div class="text-center py-4 text-danger small">Bildirimler yüklenemedi</div>';
        });
    }

    function renderList(data) {
        if (! data.items || data.items.length === 0) {
            dropdownList.innerHTML =
                '<div class="text-center py-4 text-muted">' +
                '<i class="bi bi-bell-slash"></i> <small>Henüz bildirim yok</small>' +
                '</div>';
            return;
        }

        var html = '';
        data.items.forEach(function (n) {
            html += '<div class="nt-dd-item ' + (n.is_unread ? 'is-unread' : '') + '" data-id="' + n.id + '">';
            html += '<div class="nt-dd-icon nt-dd-icon--' + escAttr(n.level) + '">';
            html += '<i class="bi ' + escAttr(n.icon) + '"></i>';
            html += '</div>';
            html += '<div class="nt-dd-body">';
            html += '<div class="nt-dd-title">' + escapeHtml(n.title) + '</div>';
            if (n.message) {
                html += '<div class="nt-dd-message">' + escapeHtml(n.message) + '</div>';
            }
            html += '<div class="nt-dd-time">' + escapeHtml(n.time) + '</div>';
            html += '</div>';
            html += '</div>';
        });

        dropdownList.innerHTML = html;

        dropdownList.querySelectorAll('.nt-dd-item').forEach(function (item, idx) {
            item.addEventListener('click', function () {
                var id = this.dataset.id;
                var notifData = data.items[idx];
                if (notifData.is_unread) {
                    markRead(id);
                    item.classList.remove('is-unread');
                }
                if (notifData.action_url) {
                    window.location.href = notifData.action_url;
                }
            });
        });
    }

    function updateBadge(count) {
        var badge = document.getElementById('ntBellBadge');
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 99 ? '99+' : String(count);
            } else {
                var span = document.createElement('span');
                span.id = 'ntBellBadge';
                span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                span.textContent = count > 99 ? '99+' : String(count);
                bellBtn.appendChild(span);
            }
        } else if (badge) {
            badge.remove();
        }
    }

    function markRead(id) {
        fetch('/admin/bildirimler/' + id + '/okundu', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
    }

    // ── Sticky Popup Toast ─────────────────────────────────────

    function ensurePopupContainer() {
        if (popupContainer) return popupContainer;
        popupContainer = document.getElementById('ntPopupContainer');
        if (! popupContainer) {
            popupContainer = document.createElement('div');
            popupContainer.id = 'ntPopupContainer';
            popupContainer.className = 'nt-popup-container';
            document.body.appendChild(popupContainer);
        }
        return popupContainer;
    }

    function shouldPopup(notif) {
        if (! notif.is_unread) return false;
        if (POPUP_TYPES.indexOf(notif.type) !== -1) return true;
        if (POPUP_LEVELS.indexOf(notif.level) !== -1) return true;
        return false;
    }

    function showPopup(notif) {
        var container = ensurePopupContainer();
        if (container.querySelector('[data-popup-id="' + notif.id + '"]')) return;

        var card = document.createElement('div');
        card.className = 'nt-popup-toast nt-popup-toast--' + escAttr(notif.level);
        card.dataset.popupId = String(notif.id);

        var html = '';
        html += '<div class="nt-popup-icon"><i class="bi ' + escAttr(notif.icon) + '"></i></div>';
        html += '<div class="nt-popup-body">';
        html += '<div class="nt-popup-title">' + escapeHtml(notif.title) + '</div>';
        if (notif.message) {
            html += '<div class="nt-popup-message">' + escapeHtml(notif.message) + '</div>';
        }
        html += '<div class="nt-popup-time"><i class="bi bi-clock"></i> ' + escapeHtml(notif.time) + '</div>';
        html += '<div class="nt-popup-actions">';
        if (notif.action_url) {
            html += '<button type="button" class="nt-popup-btn nt-popup-btn--primary" data-action="view">';
            html += '<i class="bi bi-arrow-right me-1"></i>Görüntüle</button>';
        }
        html += '<button type="button" class="nt-popup-btn nt-popup-btn--ghost" data-action="dismiss">';
        html += '<i class="bi bi-check me-1"></i>Okundu</button>';
        html += '</div>';
        html += '</div>';
        html += '<button type="button" class="nt-popup-close" data-action="dismiss" aria-label="Kapat">';
        html += '<i class="bi bi-x"></i></button>';
        card.innerHTML = html;

        card.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var action = btn.dataset.action;
                markRead(notif.id);
                if (action === 'view' && notif.action_url) {
                    window.location.href = notif.action_url;
                    return;
                }
                card.classList.add('nt-popup-toast--leaving');
                setTimeout(function () { card.remove(); }, 200);
            });
        });

        container.insertBefore(card, container.firstChild);
        requestAnimationFrame(function () {
            card.classList.add('nt-popup-toast--in');
        });
    }

    // ── Browser OS Notification ────────────────────────────────

    var osNotificationAllowed = false;
    function initOsNotification() {
        if (! ('Notification' in window)) return;
        if (Notification.permission === 'granted') {
            osNotificationAllowed = true;
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(function (perm) {
                osNotificationAllowed = (perm === 'granted');
            });
        }
    }
    initOsNotification();

    function showOsNotification(notif) {
        if (! osNotificationAllowed) return;
        // Sayfa görünürse OS bildirim çift kayıt olmasın — toast yeterli
        if (! document.hidden) return;
        try {
            var osNotif = new Notification(notif.title, {
                body: notif.message || '',
                icon: '/favicon.ico',
                tag: 'admin-notif-' + notif.id,
            });
            osNotif.onclick = function () {
                window.focus();
                if (notif.action_url) window.location.href = notif.action_url;
                markRead(notif.id);
                osNotif.close();
            };
        } catch (e) { /* ignore */ }
    }

    // ── Periyodik refresh (15sn) ───────────────────────────────

    function poll() {
        fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            updateBadge(data.unread_count);

            if (data.items && data.items.length > 0) {
                if (firstPoll) {
                    // İlk poll: mevcut bildirimleri "görüldü" işaretle (popup çıkarma)
                    data.items.forEach(function (n) { seenIds.add(n.id); });
                    firstPoll = false;
                } else {
                    // Sonraki poll: yeni gelen okunmamışlar için popup
                    data.items.forEach(function (n) {
                        if (! seenIds.has(n.id)) {
                            seenIds.add(n.id);
                            if (shouldPopup(n)) {
                                showPopup(n);
                                showOsNotification(n);
                            }
                        }
                    });
                }
            }

            // Dropdown açıksa içeriği yenile
            if (bellBtn.getAttribute('aria-expanded') === 'true') {
                renderList(data);
            } else {
                loaded = false;
            }
        })
        .catch(function () { /* silently */ });
    }

    poll();
    setInterval(poll, 15000);

    // ── Helpers ────────────────────────────────────────────────

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function escAttr(s) {
        return String(s == null ? '' : s).replace(/[<>"]/g, function (c) {
            return { '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }
})();
