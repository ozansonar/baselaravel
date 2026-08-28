/**
 * Dışa aktarma menüsü — PDF satır tavanı uyarısı.
 *
 * PDF tek dosyada sınırlı sayıda satır taşıyabiliyor. Sınır aşıldığında dosya
 * üretilip sessizce kırpılmıyor: kullanıcı indirme başlamadan uyarılıyor ve
 * Excel'e yönlendiriliyor. Sunucu tarafında da aynı kontrol var; burası sadece
 * boşuna beklemeyi önlüyor.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.js-export-pdf');

        if (!link) {
            return;
        }

        var count = parseInt(link.dataset.rowCount || '', 10);
        var limit = parseInt(link.dataset.rowLimit || '', 10);

        if (isNaN(count) || isNaN(limit) || limit <= 0 || count <= limit) {
            return;
        }

        event.preventDefault();

        var message = 'Bu listede ' + count.toLocaleString('tr-TR') + ' kayıt var; PDF en fazla '
            + limit.toLocaleString('tr-TR') + ' kayıt taşıyabiliyor. '
            + 'Süzgeçleri daraltın ya da listeyi Excel olarak indirin.';

        if (typeof AdminModal !== 'undefined') {
            AdminModal.status({ title: 'PDF sınırı aşıldı', message: message, type: 'warning' });
        }
    });
})();
