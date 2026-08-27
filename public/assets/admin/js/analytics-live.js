/**
 * Live Visitors — polls the analytics endpoint and redraws the screen.
 *
 * Polling rather than websockets: the project runs on shared hosting with no
 * persistent process, so a timed fetch is the only thing that actually works
 * everywhere. The interval backs off when the tab is hidden so a forgotten tab
 * does not keep hitting the server all day.
 */
(function () {
    'use strict';

    var config = window.analyticsLive || {};
    var timer = null;
    var paused = false;
    var lastFeedId = null;
    var seenFeedIds = {};

    var el = {
        dot: document.getElementById('liveDot'),
        online: document.getElementById('onlineCount'),
        pages: document.getElementById('pageCount'),
        members: document.getElementById('memberCount'),
        mobile: document.getElementById('mobileCount'),
        rows: document.getElementById('visitorRows'),
        activePages: document.getElementById('activePages'),
        feed: document.getElementById('liveFeed'),
        serverTime: document.getElementById('serverTime'),
        windowLabel: document.getElementById('windowLabel'),
        windowSelect: document.getElementById('windowSelect'),
        includeBots: document.getElementById('includeBots'),
        pauseBtn: document.getElementById('pauseBtn'),
        pauseLabel: document.getElementById('pauseLabel'),
        freshness: document.getElementById('freshness'),
        connectionAlert: document.getElementById('connectionAlert'),
        staleSince: document.getElementById('staleSince')
    };

    /** Son başarılı yanıtın zamanı; "kaç saniye önce" bundan hesaplanıyor. */
    var lastSuccessAt = null;
    var freshnessTimer = null;
    var offline = false;

    if (!el.rows) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindControls();
        refresh();
        schedule();

        // Yaş göstergesi yoklamadan bağımsız işliyor: ekran donduğunda bunu
        // söyleyecek olan da o.
        freshnessTimer = setInterval(updateFreshness, 1000);
    });

    function bindControls() {
        if (el.windowSelect) {
            el.windowSelect.addEventListener('change', function () {
                config.window = parseInt(this.value, 10);
                if (el.windowLabel) el.windowLabel.textContent = config.window;
                rememberSettings();
                refresh();
            });
        }

        if (el.includeBots) {
            el.includeBots.addEventListener('change', function () {
                config.includeBots = this.checked;
                lastFeedId = null;
                seenFeedIds = {};
                rememberSettings();
                refresh();
            });
        }

        if (el.pauseBtn) {
            el.pauseBtn.addEventListener('click', togglePause);
        }

        // A hidden tab does not need second-by-second accuracy.
        document.addEventListener('visibilitychange', schedule);
    }

    function togglePause() {
        paused = !paused;

        if (el.pauseLabel) el.pauseLabel.textContent = paused ? 'Devam Et' : 'Duraklat';
        if (el.pauseBtn) el.pauseBtn.innerHTML = '<i class="bi bi-' + (paused ? 'play' : 'pause') + '-fill"></i> <span id="pauseLabel">' + (paused ? 'Devam Et' : 'Duraklat') + '</span>';
        if (el.dot) el.dot.classList.toggle('live-dot--paused', paused);

        el.pauseLabel = document.getElementById('pauseLabel');
        schedule();
        updateFreshness();

        if (!paused) refresh();
    }

    function schedule() {
        if (timer) clearInterval(timer);
        if (paused) return;

        var interval = document.hidden ? (config.intervalMs || 10000) * 6 : (config.intervalMs || 10000);
        timer = setInterval(refresh, interval);
    }

    function refresh() {
        var params = new URLSearchParams({
            window: config.window,
            include_bots: config.includeBots ? 1 : 0
        });

        if (lastFeedId) params.set('after_id', lastFeedId);

        fetch(config.url + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (data) {
                lastSuccessAt = Date.now();
                setOffline(false);
                render(data);
                updateFreshness();
            })
            .catch(function () {
                // Ekrandaki sayılar duruyor ama artık doğru değil; sessizce
                // eskimeleri, kullanıcının yanlış karar vermesi demek.
                setOffline(true);
            });
    }

    /**
     * Seçilen aralık ve bot tercihi adres satırında kalsın: sayfa yenilendiğinde
     * ya da bağlantı paylaşıldığında aynı görünüm açılmalı.
     */
    function rememberSettings() {
        if (!window.history || !window.history.replaceState) {
            return;
        }

        var url = new URL(window.location.href);
        url.searchParams.set('window', config.window);

        if (config.includeBots) {
            url.searchParams.set('include_bots', '1');
        } else {
            url.searchParams.delete('include_bots');
        }

        window.history.replaceState({}, '', url.toString());
    }

    function setOffline(value) {
        offline = value;

        if (el.connectionAlert) {
            el.connectionAlert.classList.toggle('d-none', !value);
        }

        if (el.dot && !paused) {
            el.dot.classList.toggle('live-dot--paused', value);
        }

        if (value && el.staleSince && lastSuccessAt) {
            el.staleSince.textContent = 'saat ' + new Date(lastSuccessAt).toLocaleTimeString('tr-TR') + ' durumunu';
        }

        updateFreshness();
    }

    /**
     * Verinin yaşı saniye saniye yazılıyor. Sunucu saati yalnızca yanıtın
     * anını söylüyordu; ekranın kaç dakikadır donduğunu göstermiyordu.
     */
    function updateFreshness() {
        if (!el.freshness) {
            return;
        }

        if (lastSuccessAt === null) {
            el.freshness.textContent = offline ? 'bağlanılamadı' : 'bağlanıyor…';
            el.freshness.classList.toggle('anl-freshness--stale', offline);

            return;
        }

        var seconds = Math.max(0, Math.round((Date.now() - lastSuccessAt) / 1000));
        var interval = Math.round((config.intervalMs || 10000) / 1000);

        // Bağlantı kopmuşken "az önce güncellendi" yazmak yanlış: veri
        // duruyor ama artık siteyi anlatmıyor.
        if (offline) {
            el.freshness.textContent = 'bağlantı yok · son veri ' + agoText(seconds);
        } else if (paused) {
            el.freshness.textContent = 'duraklatıldı · son veri ' + agoText(seconds);
        } else {
            el.freshness.textContent = agoText(seconds) + ' güncellendi';
        }

        // Bir tur kaçtıysa veri artık taze sayılmaz.
        el.freshness.classList.toggle('anl-freshness--stale', offline || seconds > interval * 2);
    }

    function agoText(seconds) {
        if (seconds < 5) return 'az önce';
        if (seconds < 60) return seconds + ' sn önce';

        var minutes = Math.floor(seconds / 60);

        return minutes < 60 ? minutes + ' dk önce' : Math.floor(minutes / 60) + ' sa önce';
    }

    function render(data) {
        if (el.dot && !paused) el.dot.classList.remove('live-dot--paused');
        if (el.serverTime) el.serverTime.textContent = data.server_time || '—';

        var visitors = data.visitors || [];

        setText(el.online, data.online || 0);
        setText(el.pages, (data.pages || []).length);
        setText(el.members, visitors.filter(function (v) { return v.user; }).length);
        setText(el.mobile, visitors.filter(function (v) { return v.device_type === 'mobile'; }).length);

        renderVisitors(visitors);
        renderActivePages(data.pages || []);
        appendFeed(data.feed || []);
    }

    function setText(node, value) {
        if (!node) {
            return;
        }

        var previous = node.textContent;
        node.textContent = value;

        // Sayı değiştiyse kısa bir vurgu: ekranda duran biri artışı kaçırmasın.
        if (previous !== String(value) && node.classList.contains('anl-count')) {
            node.classList.remove('anl-count--bump');
            // Sınıf yeniden eklenince animasyon baştan başlasın.
            void node.offsetWidth;
            node.classList.add('anl-count--bump');
            setTimeout(function () { node.classList.remove('anl-count--bump'); }, 400);
        }
    }

    function renderVisitors(visitors) {
        if (!visitors.length) {
            el.rows.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-clr-secondary">'
                + '<i class="bi bi-moon-stars d-block mb-2 anl-empty__icon"></i>'
                + 'Bu aralıkta sitede kimse yok.</td></tr>';
            return;
        }

        el.rows.innerHTML = visitors.map(function (v) {
            return '<tr>'
                + '<td>' + visitorCell(v) + '</td>'
                + '<td><a href="' + escapeAttr(v.url || '#') + '" target="_blank" rel="noopener" class="text-teal">'
                    + escapeHtml(v.url_path) + '</a></td>'
                + '<td class="d-none d-lg-table-cell">' + deviceCell(v) + '</td>'
                + '<td class="d-none d-xl-table-cell"><small class="text-clr-secondary">'
                    + escapeHtml(v.referrer_domain || 'doğrudan')
                    + '<br>giriş: ' + escapeHtml(v.entry_path || '—') + '</small></td>'
                + '<td class="d-none d-md-table-cell"><small class="text-clr-secondary">'
                    + duration(v.session_seconds) + ' · ' + v.page_count + ' sayfa</small></td>'
                + '<td><small class="text-clr-secondary">' + ago(v.seconds_ago) + '</small></td>'
                + '</tr>';
        }).join('');
    }

    function visitorCell(v) {
        if (v.is_bot) {
            return '<span class="menu-manage-tag menu-manage-tag--warning"><i class="bi bi-robot"></i> '
                + escapeHtml(v.bot_name || 'Bot') + '</span>';
        }

        if (v.user) {
            var name = [v.user.first_name, v.user.last_name].filter(Boolean).join(' ');
            return '<span class="fw-semibold">' + escapeHtml(name || v.user.email) + '</span>'
                + '<br><small class="text-clr-secondary">' + escapeHtml(v.user.email || '') + '</small>';
        }

        return '<span class="text-clr-secondary">Misafir</span>'
            + '<br><small class="visitor-session">' + escapeHtml(String(v.session_id).slice(0, 8)) + '</small>';
    }

    function deviceCell(v) {
        var icons = { desktop: 'bi-display', mobile: 'bi-phone', tablet: 'bi-tablet', bot: 'bi-robot' };
        var icon = icons[v.device_type] || 'bi-question-circle';

        return '<i class="bi ' + icon + ' me-1"></i>'
            + '<small class="text-clr-secondary">' + escapeHtml([v.browser, v.os].filter(Boolean).join(' · ')) + '</small>';
    }

    function renderActivePages(pages) {
        if (!pages.length) {
            el.activePages.innerHTML = '<p class="text-clr-secondary mb-0">Şu an açık sayfa yok.</p>';
            return;
        }

        var max = pages[0].count || 1;

        el.activePages.innerHTML = pages.map(function (p) {
            var pct = Math.round((p.count / max) * 100);
            return '<div class="mb-3">'
                + '<div class="d-flex justify-content-between mb-1">'
                    + '<small>' + escapeHtml(p.label) + '</small>'
                    + '<small class="text-teal fw-semibold">' + p.count + '</small>'
                + '</div>'
                + '<div class="progress anl-bar"><div class="progress-bar bg-teal anl-bar__fill" style="--anl-bar:' + pct + '%"></div></div>'
                + '</div>';
        }).join('');
    }

    /**
     * The feed grows rather than being redrawn, so a row the user is reading
     * does not jump away underneath them.
     */
    function appendFeed(items) {
        if (!items.length) {
            if (el.feed.dataset.filled !== '1') {
                el.feed.innerHTML = '<p class="text-clr-secondary mb-0">Henüz hareket yok.</p>';
            }
            return;
        }

        if (el.feed.dataset.filled !== '1') {
            el.feed.innerHTML = '';
            el.feed.dataset.filled = '1';
        }

        // The endpoint returns newest first; insert oldest first so the newest
        // ends up at the top.
        items.slice().reverse().forEach(function (item) {
            if (seenFeedIds[item.id]) return;
            seenFeedIds[item.id] = true;

            if (lastFeedId === null || item.id > lastFeedId) lastFeedId = item.id;

            var who = item.is_bot
                ? '<i class="bi bi-robot"></i> ' + escapeHtml(item.bot_name || 'Bot')
                : (item.user ? escapeHtml(item.user) : escapeHtml(item.session_id));

            var row = document.createElement('div');
            row.className = 'analytics-feed__row analytics-feed__row--new';
            row.innerHTML = '<span class="text-truncate">' + who + ' → <span class="text-teal">'
                + escapeHtml(item.url_path) + '</span></span>'
                + '<small class="text-clr-secondary flex-shrink-0">' + escapeHtml(item.at || '') + '</small>';

            el.feed.insertBefore(row, el.feed.firstChild);
        });

        // Keep the DOM from growing without bound on a long-running screen.
        while (el.feed.children.length > 60) {
            el.feed.removeChild(el.feed.lastChild);
        }
    }

    function duration(seconds) {
        seconds = parseInt(seconds, 10) || 0;
        if (seconds < 60) return seconds + ' sn';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' dk';
        return Math.floor(minutes / 60) + ' sa ' + (minutes % 60) + ' dk';
    }

    function ago(seconds) {
        seconds = parseInt(seconds, 10) || 0;
        if (seconds < 10) return 'şimdi';
        if (seconds < 60) return seconds + ' sn önce';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' dk önce';
        return Math.floor(minutes / 60) + ' sa önce';
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }
})();
