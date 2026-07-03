// ==================== CITY PAGES — Bulk Create ====================

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        updateBulkSelection();

        var form = document.getElementById('bulkCityForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                var checked = document.querySelectorAll('.bulk-city-checkbox:checked').length;
                if (checked === 0) {
                    e.preventDefault();
                    if (typeof AdminModal !== 'undefined') {
                        AdminModal.status({
                            title: 'Şehir Seçilmedi',
                            message: 'Lütfen en az bir şehir seçin.',
                            type: 'warning'
                        });
                    }
                }
            });
        }
    });

    /**
     * Toggle every (non-disabled) checkbox of a given tier.
     */
    window.toggleTier = function (tier) {
        var boxes = document.querySelectorAll('.bulk-city-checkbox[data-tier="' + tier + '"]:not(:disabled)');
        if (!boxes.length) return;

        var allChecked = Array.prototype.every.call(boxes, function (cb) { return cb.checked; });
        boxes.forEach(function (cb) { cb.checked = !allChecked; });
        updateBulkSelection();
    };

    /**
     * Sync hidden inputs to checkbox state and refresh counter.
     * Disabled hidden inputs are not submitted.
     */
    window.updateBulkSelection = function () {
        var boxes = document.querySelectorAll('.bulk-city-checkbox');
        var checked = 0;

        boxes.forEach(function (cb) {
            var hiddens = document.querySelectorAll('input[data-input-for="' + cb.id + '"]');
            hiddens.forEach(function (h) {
                h.disabled = !cb.checked;
            });
            if (cb.checked) checked++;
        });

        var counter = document.getElementById('selectedCounter');
        if (counter) counter.textContent = checked;

        var btn = document.getElementById('btnBulkCreate');
        if (btn) btn.disabled = checked === 0;

        // Bottom button mirror
        var allSubmits = document.querySelectorAll('button[type="submit"]');
        allSubmits.forEach(function (b) { b.disabled = checked === 0; });
    };
})();
