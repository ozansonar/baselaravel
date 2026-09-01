/**
 * Sosyal paylaşım bağlantılarını ayrı bir pencerede açar.
 *
 * Bağlantılar `onclick="window.open(...)"` ile yazılıydı; nitelik olarak
 * yazılan bir işleyici içerik güvenlik politikasında nonce taşıyamıyor ve
 * politikanın sıkılaşması için taşınması gerekiyordu.
 *
 * Pencere ölçüsü niteliğe yazılı (`data-share-window="600x400"`): Pinterest
 * kutusu ötekilerden uzun ve o fark tasarımdan geliyor, koddan değil.
 *
 * JavaScript kapalıysa bağlantı yine çalışıyor — yalnız aynı sekmede açılıyor.
 * Paylaşım penceresi bir kolaylık, işlevin şartı değil.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (olay) {
        var bag = olay.target.closest('[data-share-window]');

        if (!bag) {
            return;
        }

        var olcu = (bag.dataset.shareWindow || '').split('x');
        var en = parseInt(olcu[0], 10) || 600;
        var boy = parseInt(olcu[1], 10) || 400;

        var pencere = window.open(
            bag.href,
            'share',
            'width=' + en + ',height=' + boy + ',noopener,noreferrer'
        );

        /* Açılır pencere engellenmişse bağlantı olağan yolundan gitsin;
           tıklamayı yutup hiçbir şey yapmamak en kötü sonuç olurdu. */
        if (pencere) {
            olay.preventDefault();
        }
    });
})();
