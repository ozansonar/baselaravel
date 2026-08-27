'use strict';

/**
 * /admin/aboneler — toplu liste işlemleri ve liste silme.
 *
 * Süzme ve sayfalama sunucuda; burada kalan iş satır seçimini yönetmek ve
 * silinecek listeyi forma bildirmek.
 */
(function () {
    /**
     * Satır seçimi: seçim varken toplu işlem çubuğu görünür olur.
     *
     * Kutular tablonun içinde ama form dışında duruyor (satır işlemleri de
     * form, iç içe form geçersiz); HTML5 form niteliğiyle bağlanıyorlar.
     */
    function initBulkSelection() {
        var bar = document.getElementById('bulkBar');
        var counter = document.getElementById('bulkCount');
        var selectAll = document.getElementById('bulkSelectAll');
        var clear = document.getElementById('bulkClear');
        var boxes = Array.prototype.slice.call(document.querySelectorAll('.js-bulk-row'));

        if (!bar || !boxes.length) {
            return;
        }

        function selected() {
            return boxes.filter(function (box) {
                return box.checked;
            });
        }

        function refresh() {
            var count = selected().length;

            bar.classList.toggle('d-none', count === 0);

            if (counter) {
                counter.textContent = String(count);
            }

            if (selectAll) {
                selectAll.checked = count > 0 && count === boxes.length;
                // Kısmi seçim: "tümünü seç" ne işaretli ne boş görünmeli.
                selectAll.indeterminate = count > 0 && count < boxes.length;
            }
        }

        boxes.forEach(function (box) {
            box.addEventListener('change', refresh);
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                boxes.forEach(function (box) {
                    box.checked = selectAll.checked;
                });

                refresh();
            });
        }

        if (clear) {
            clear.addEventListener('click', function () {
                boxes.forEach(function (box) {
                    box.checked = false;
                });

                refresh();
            });
        }

        refresh();
    }

    /**
     * Liste silme: hangi listenin silineceği seçim kutusundan geliyor, form
     * adresi ona göre yazılıyor.
     */
    function initListDelete() {
        var select = document.getElementById('deleteListSelect');
        var form = document.getElementById('deleteListForm');

        if (!select || !form) {
            return;
        }

        function apply() {
            form.action = select.dataset.urlTemplate.replace('LIST_ID', select.value);
        }

        select.addEventListener('change', apply);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            apply();

            var name = select.options[select.selectedIndex].text;

            AdminModal.confirm({
                title: 'Listeyi Sil',
                message: 'Liste silinecek. Abonelerin kaydı durur, yalnızca bu listeden çıkarılırlar.',
                detailTitle: name,
                type: 'danger',
                confirmText: 'Evet, Sil',
                confirmIcon: 'bi bi-trash3'
            }).then(function (confirmed) {
                if (confirmed) {
                    form.submit();
                }
            });
        });

        apply();
    }

    initBulkSelection();
    initListDelete();
})();
