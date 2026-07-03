(function () {
    'use strict';

    // Admin sayfalarında tracking yapma
    if (window.location.pathname.indexOf('/admin') === 0) {
        return;
    }

    // Do Not Track istekleri
    if (navigator.doNotTrack === '1' || window.doNotTrack === '1') {
        return;
    }

    var trackUrl = '/api/analytics/track';

    function sendTracking() {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!csrfMeta) {
            return;
        }

        var payload = {
            url: window.location.href,
            path: window.location.pathname,
            referrer: document.referrer || null,
            screen_width: window.screen ? window.screen.width : null,
            screen_height: window.screen ? window.screen.height : null
        };

        try {
            fetch(trackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta.content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
                keepalive: true
            }).catch(function () { /* fire-and-forget */ });
        } catch (e) { /* ignore */ }
    }

    // Sayfa tamamen yüklendiğinde hemen gönder (load event = tüm kaynaklar hazır)
    if (document.readyState === 'complete') {
        sendTracking();
    } else {
        window.addEventListener('load', sendTracking);
    }
})();
