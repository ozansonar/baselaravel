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

    /**
     * Abone düzenleme: ekleme modalı düzenleme kipine geçiyor.
     *
     * Ayrı bir düzenleme sayfası yerine aynı modal kullanılıyor; alanlar iki
     * yerde tanımlanırsa biri güncellenip diğeri unutuluyor.
     */
    function initEdit() {
        var form = document.getElementById('subscriberForm');

        if (!form) {
            return;
        }

        var methodField = document.getElementById('subscriberFormMethod');
        var title = document.getElementById('subscriberModalTitle');
        var submit = document.getElementById('subscriberSubmit');
        var statusField = document.getElementById('subscriberStatusField');
        var listsHint = document.getElementById('subscriberListsHint');
        var storeUrl = form.dataset.storeUrl;
        var modal = document.getElementById('addModal');

        function alan(ad) {
            return form.querySelector('[name="' + ad + '"]');
        }

        function listeleriIsaretle(idler) {
            form.querySelectorAll('[name="list_ids[]"]').forEach(function (kutu) {
                kutu.checked = idler.indexOf(kutu.value) !== -1;
            });
        }

        /** Modal her açılışta bilinen bir durumdan başlamalı. */
        function eklemeKipi() {
            form.setAttribute('action', storeUrl);
            methodField.value = 'POST';
            title.innerHTML = '<i class="bi bi-plus-lg me-2 text-teal"></i>Abone Ekle';
            submit.textContent = 'Ekle';
            statusField.classList.add('d-none');
            listsHint.classList.add('d-none');

            ['email', 'first_name', 'last_name'].forEach(function (ad) {
                alan(ad).value = '';
            });

            alan('locale').value = '';
        }

        document.querySelectorAll('.js-edit-subscriber').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.setAttribute('action', btn.dataset.url);
                methodField.value = 'PUT';
                title.innerHTML = '<i class="bi bi-pencil me-2 text-teal"></i>Aboneyi Düzenle';
                submit.textContent = 'Kaydet';
                statusField.classList.remove('d-none');
                listsHint.classList.remove('d-none');

                alan('email').value = btn.dataset.email || '';
                alan('first_name').value = btn.dataset.firstName || '';
                alan('last_name').value = btn.dataset.lastName || '';
                alan('locale').value = btn.dataset.locale || '';
                alan('status').value = btn.dataset.status || 'subscribed';

                listeleriIsaretle((btn.dataset.lists || '').split(',').filter(Boolean));

                bootstrap.Modal.getOrCreateInstance(modal).show();
            });
        });

        // "Abone Ekle" düğmesiyle açıldığında düzenlemeden kalan veri durmasın.
        document.querySelectorAll('[data-bs-target="#addModal"]').forEach(function (btn) {
            btn.addEventListener('click', eklemeKipi);
        });
    }

    /**
     * Excel/CSV içe aktarımı: dosya önce okunup ekrana dökülüyor, kullanıcı
     * düzeltiyor, kaydedilen bu hâli oluyor.
     *
     * Eskiden dosya doğrudan içeri alınıyor ve "12 geçersiz adres atlandı"
     * deniyordu; hangi satırın neden atlandığı görünmüyordu.
     */
    function initImport() {
        var form = document.getElementById('importForm');

        if (!form) {
            return;
        }

        var dialog = document.getElementById('importDialog');
        var fileField = document.getElementById('importFileField');
        var file = document.getElementById('import_file');
        var preview = document.getElementById('importPreview');
        var rowsBody = document.getElementById('importRows');
        var summary = document.getElementById('importSummary');
        var truncated = document.getElementById('importTruncated');
        var previewBtn = document.getElementById('importPreviewBtn');
        var saveBtn = document.getElementById('importSaveBtn');
        var resetBtn = document.getElementById('importReset');
        var token = document.querySelector('meta[name="csrf-token"]');

        function hata(mesaj) {
            AdminModal.status({ title: 'Dosya okunamadı', message: mesaj, type: 'danger' });
        }

        function numaralandir() {
            var satirlar = rowsBody.querySelectorAll('tr');

            satirlar.forEach(function (tr, i) {
                tr.querySelector('.sub-preview__num').textContent = String(i + 1);
                tr.querySelectorAll('input').forEach(function (input) {
                    input.name = input.name.replace(/rows\[\d*\]/, 'rows[' + i + ']');
                });
            });

            ozetiYaz();
        }

        function ozetiYaz() {
            var toplam = rowsBody.querySelectorAll('tr').length;
            summary.textContent = toplam + ' kayıt aktarılacak';
            saveBtn.disabled = toplam === 0;
        }

        function satirCiz(satir, i) {
            var tr = document.createElement('tr');

            if (!satir.valid) {
                tr.className = 'sub-preview__row--hatali';
            }

            var no = document.createElement('td');
            no.className = 'sub-preview__num';
            no.textContent = String(i + 1);
            tr.appendChild(no);

            [['email', satir.email], ['first_name', satir.first_name], ['last_name', satir.last_name]]
                .forEach(function (alan) {
                    var td = document.createElement('td');
                    var input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'stg-input stg-input--sm';
                    input.name = 'rows[' + i + '][' + alan[0] + ']';
                    input.value = alan[1] || '';

                    if (alan[0] === 'email') {
                        // Sunucu da aynı kuralı uyguluyor; buradaki amaç
                        // kullanıcıyı kaydete basmadan uyarmak.
                        input.setAttribute('data-validation-engine', 'validate[required,custom[email],maxSize[191]]');

                        if (!satir.valid) {
                            input.title = satir.reason || 'Geçersiz kayıt';
                        }
                    } else {
                        input.setAttribute('data-validation-engine', 'validate[maxSize[191]]');
                    }

                    td.appendChild(input);

                    if (alan[0] === 'email' && !satir.valid) {
                        var neden = document.createElement('small');
                        neden.className = 'sub-preview__reason';
                        neden.textContent = satir.reason || '';
                        td.appendChild(neden);
                    }

                    tr.appendChild(td);
                });

            var islem = document.createElement('td');
            var sil = document.createElement('button');
            sil.type = 'button';
            sil.className = 'usr-action-btn danger';
            sil.title = 'Bu satırı aktarma';
            sil.innerHTML = '<i class="bi bi-x-lg"></i>';
            sil.addEventListener('click', function () {
                tr.remove();
                numaralandir();
            });
            islem.appendChild(sil);
            tr.appendChild(islem);

            return tr;
        }

        function onizlemeyiGoster(veri) {
            rowsBody.innerHTML = '';
            veri.rows.forEach(function (satir, i) {
                rowsBody.appendChild(satirCiz(satir, i));
            });

            summary.textContent = veri.total + ' kayıt okundu · ' +
                veri.valid + ' geçerli' +
                (veri.invalid > 0 ? ' · ' + veri.invalid + ' düzeltilmeli' : '');

            truncated.hidden = !veri.truncated;
            preview.classList.remove('d-none');
            fileField.classList.add('d-none');
            dialog.classList.add('modal-xl');
            previewBtn.classList.add('d-none');
            saveBtn.classList.remove('d-none');

            // Dosya artık gönderilmiyor: kaydedilecek olan ekrandaki satırlar.
            file.value = '';
            file.removeAttribute('data-validation-engine');
            file.setAttribute('data-fv-ignore', '');

            // ozetiYaz() burada çağrılmıyor: dosyanın okunma sonucunu ("3
            // düzeltilmeli") hemen ezerdi. Özet yalnızca satır silinince tazeleniyor.
            saveBtn.disabled = veri.rows.length === 0;
        }

        function bastanBasla() {
            preview.classList.add('d-none');
            fileField.classList.remove('d-none');
            dialog.classList.remove('modal-xl');
            previewBtn.classList.remove('d-none');
            saveBtn.classList.add('d-none');
            rowsBody.innerHTML = '';
            file.value = '';
            file.setAttribute('data-validation-engine', 'validate[required]');
            file.removeAttribute('data-fv-ignore');
        }

        previewBtn.addEventListener('click', function () {
            if (!file.files || file.files.length === 0) {
                hata('Önce bir dosya seçin.');

                return;
            }

            var veri = new FormData();
            veri.append('file', file.files[0]);

            previewBtn.disabled = true;
            previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Okunuyor...';

            fetch(form.dataset.previewUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: veri
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (sonuc) {
                    previewBtn.disabled = false;
                    previewBtn.innerHTML = '<i class="bi bi-eye"></i> Önizle';

                    if (!sonuc.ok) {
                        hata(sonuc.d.message || 'Dosya okunamadı.');

                        return;
                    }

                    onizlemeyiGoster(sonuc.d);
                })
                .catch(function () {
                    previewBtn.disabled = false;
                    previewBtn.innerHTML = '<i class="bi bi-eye"></i> Önizle';
                    hata('Dosya gönderilemedi, bağlantınızı kontrol edin.');
                });
        });

        resetBtn.addEventListener('click', bastanBasla);
    }

    initBulkSelection();
    initListDelete();
    initEdit();
    initImport();
})();
