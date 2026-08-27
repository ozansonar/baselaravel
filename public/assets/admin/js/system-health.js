'use strict';

/**
 * /admin/sistem-saglik — kontrolleri yeniden çalıştırma.
 *
 * Kontroller sunucuda çalıştığı için düğme JSON ucunu çağırır ve sonuç
 * geldiğinde sayfayı tazeler; kartları burada yeniden çizmek, aynı işi iki
 * yerde yazmak olurdu.
 */
(function () {
    var button = document.getElementById('shRefreshBtn');

    if (!button) {
        return;
    }

    function setLoading(isLoading) {
        var defaultLabel = button.querySelector('[data-default]');
        var loadingLabel = button.querySelector('[data-loading]');

        button.disabled = isLoading;

        if (defaultLabel) defaultLabel.classList.toggle('d-none', isLoading);
        if (loadingLabel) loadingLabel.classList.toggle('d-none', !isLoading);
    }

    button.addEventListener('click', function () {
        setLoading(true);

        fetch(button.dataset.url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Sunucu ' + response.status + ' döndü');
                }

                return response.json();
            })
            .then(function () {
                window.location.reload();
            })
            .catch(function (error) {
                AdminModal.status({
                    title: 'Kontrol başarısız',
                    message: error.message,
                    type: 'danger'
                });

                setLoading(false);
            });
    });
})();
