// ==================== CITY PAGES — Admin List ====================

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        animateCounters();

        var search = document.querySelector('input[name="search"]');
        if (search) {
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('filterForm').submit();
                }
            });
        }
    });

    function animateCounters() {
        document.querySelectorAll('.usr-stat-value').forEach(function (el) {
            var target = parseInt(el.getAttribute('data-count'), 10);
            if (isNaN(target) || target === 0) return;
            var duration = 1400;
            var startTime = null;
            function step(ts) {
                if (!startTime) startTime = ts;
                var progress = Math.min((ts - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(eased * target).toLocaleString('tr-TR');
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = target.toLocaleString('tr-TR');
            }
            requestAnimationFrame(step);
        });
    }

    // ── Delete Confirmation Modal ──
    window.openDeleteCityModal = function (cityId, cityName) {
        if (typeof AdminModal === 'undefined') return;

        AdminModal.confirm({
            title: 'Şehir Sayfası Silme',
            message: 'Bu sayfayı silmek istediğinize emin misiniz?',
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3',
            detailTitle: cityName,
            warning: 'Bu işlem geri alınabilir (silinen sayfalar sekmesinden geri yükleyebilirsiniz).'
        }).then(function (confirmed) {
            if (!confirmed) return;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '/admin/city-pages/' + cityId;
            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            form.innerHTML = '<input type="hidden" name="_token" value="' + token + '">'
                + '<input type="hidden" name="_method" value="DELETE">';
            document.body.appendChild(form);
            form.submit();
        });
    };
})();
