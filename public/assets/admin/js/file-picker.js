'use strict';

/**
 * Editörün dosya seçicisi.
 *
 * public/uploads dizinini gezip dosya seçmeyi, yüklemeyi ve silmeyi sağlıyor.
 * TinyMCE'nin file_picker_callback'i buraya bağlanıyor; daha önce yüklenmiş bir
 * görseli yeniden bulmanın yolu yoktu, her seferinde yeniden yükleniyordu.
 *
 * Dışarıya tek kapı:
 *   FilePicker.open({ type: 'image', onSelect: function (dosya) { ... } })
 *
 * onSelect'e verilen nesne: { url, path, name, is_image, size }
 */
(function () {
    var ayar = window.filePickerConfig;
    var modalEl = document.getElementById('filePickerModal');

    if (!ayar || !modalEl) {
        return;
    }

    var grid = document.getElementById('fpGrid');
    var breadcrumb = document.getElementById('fpBreadcrumb');
    var durum = document.getElementById('fpStatus');
    var arama = document.getElementById('fpSearch');
    var yukleGirdi = document.getElementById('fpUploadInput');
    var yukleDugme = document.getElementById('fpUploadBtn');
    var secDugme = document.getElementById('fpChoose');
    var secilenEtiket = document.getElementById('fpSelected');
    var dahaDugme = document.getElementById('fpMore');
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var csrf = tokenEl ? tokenEl.getAttribute('content') : '';

    var durumKlasor = '';
    var durumTur = '';
    var durumSayfa = 1;
    var secili = null;
    var onSelect = null;
    var aramaZaman = null;

    if (!ayar.canUpload) {
        yukleDugme.classList.add('d-none');
    }

    var BIRIMLER = ['B', 'KB', 'MB', 'GB'];

    function okunurBoyut(bytes) {
        var i = 0;

        while (bytes >= 1024 && i < BIRIMLER.length - 1) {
            bytes /= 1024;
            i++;
        }

        return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + BIRIMLER[i];
    }

    function bilgi(mesaj, tur) {
        durum.textContent = mesaj || '';
        durum.className = 'fp-status' + (tur ? ' fp-status--' + tur : '');
    }

    function seciliyiSifirla() {
        secili = null;
        secDugme.disabled = true;
        secilenEtiket.textContent = 'Dosya seçilmedi';
        grid.querySelectorAll('.fp-tile--secili').forEach(function (t) {
            t.classList.remove('fp-tile--secili');
        });
    }

    function yolCiz(klasor, ust) {
        breadcrumb.innerHTML = '';

        var parcalar = klasor === '' ? [] : klasor.split('/');
        var kokDugme = document.createElement('button');
        kokDugme.type = 'button';
        kokDugme.className = 'fp-crumb';
        kokDugme.innerHTML = '<i class="bi bi-house"></i> uploads';
        kokDugme.addEventListener('click', function () { yukle('', 1); });
        breadcrumb.appendChild(kokDugme);

        var birikim = '';

        parcalar.forEach(function (parca, i) {
            birikim = birikim === '' ? parca : birikim + '/' + parca;

            var ayrac = document.createElement('span');
            ayrac.className = 'fp-crumb__sep';
            ayrac.textContent = '/';
            breadcrumb.appendChild(ayrac);

            var dugme = document.createElement('button');
            dugme.type = 'button';
            dugme.className = 'fp-crumb';
            dugme.textContent = parca;

            if (i === parcalar.length - 1) {
                dugme.classList.add('fp-crumb--aktif');
            }

            var hedef = birikim;
            dugme.addEventListener('click', function () { yukle(hedef, 1); });
            breadcrumb.appendChild(dugme);
        });
    }

    function klasorKutusu(klasor) {
        var kutu = document.createElement('button');
        kutu.type = 'button';
        kutu.className = 'fp-tile fp-tile--klasor';
        kutu.innerHTML =
            '<span class="fp-tile__preview"><i class="bi bi-folder-fill"></i></span>' +
            '<span class="fp-tile__info">' +
                '<span class="fp-tile__name"></span>' +
                '<span class="fp-tile__meta">' + klasor.count + ' öğe</span>' +
            '</span>';
        // textContent ile yazılıyor: klasör adı diskten geliyor, HTML olarak
        // yorumlanmamalı.
        kutu.querySelector('.fp-tile__name').textContent = klasor.name;
        kutu.addEventListener('click', function () { yukle(klasor.path, 1); });

        return kutu;
    }

    function dosyaKutusu(dosya) {
        var kutu = document.createElement('div');
        kutu.className = 'fp-tile';

        var onizleme = document.createElement('span');
        onizleme.className = 'fp-tile__preview';

        if (dosya.is_image) {
            var img = document.createElement('img');
            img.src = dosya.thumb || dosya.url;
            img.alt = dosya.name;
            img.loading = 'lazy';
            img.decoding = 'async';
            onizleme.appendChild(img);
        } else {
            onizleme.innerHTML = '<i class="bi bi-file-earmark"></i>' +
                '<span class="fp-tile__ext"></span>';
            onizleme.querySelector('.fp-tile__ext').textContent = dosya.extension;
        }

        var bilgiKutu = document.createElement('span');
        bilgiKutu.className = 'fp-tile__info';
        var ad = document.createElement('span');
        ad.className = 'fp-tile__name';
        ad.textContent = dosya.name;
        ad.title = dosya.name;
        var meta = document.createElement('span');
        meta.className = 'fp-tile__meta';
        meta.textContent = okunurBoyut(dosya.size);
        bilgiKutu.appendChild(ad);
        bilgiKutu.appendChild(meta);

        kutu.appendChild(onizleme);
        kutu.appendChild(bilgiKutu);

        if (ayar.canDelete) {
            var sil = document.createElement('button');
            sil.type = 'button';
            sil.className = 'fp-tile__delete';
            sil.title = 'Sil';
            sil.setAttribute('aria-label', dosya.name + ' dosyasını sil');
            sil.innerHTML = '<i class="bi bi-trash"></i>';
            sil.addEventListener('click', function (event) {
                event.stopPropagation();
                silmeyiSor(dosya);
            });
            kutu.appendChild(sil);
        }

        kutu.addEventListener('click', function () {
            grid.querySelectorAll('.fp-tile--secili').forEach(function (t) {
                t.classList.remove('fp-tile--secili');
            });
            kutu.classList.add('fp-tile--secili');
            secili = dosya;
            secDugme.disabled = false;
            secilenEtiket.textContent = dosya.name;
        });

        // Çift tıklama doğrudan seçiyor: tek tık + "Seç" iki adım, alışkanlık bu.
        kutu.addEventListener('dblclick', function () {
            secili = dosya;
            sec();
        });

        return kutu;
    }

    function silmeyiSor(dosya) {
        AdminModal.confirm({
            title: 'Dosyayı Sil',
            message: 'Dosya sunucudan kalıcı olarak silinecek. Bu dosyayı kullanan sayfalarda görsel kırılır.',
            detailTitle: dosya.name,
            detailMeta: okunurBoyut(dosya.size),
            type: 'danger',
            confirmText: 'Evet, Sil',
            confirmIcon: 'bi bi-trash3'
        }).then(function (onay) {
            if (onay) {
                silTamam(dosya);
            }
        });
    }

    function silTamam(dosya) {
        var fd = new FormData();
        fd.append('_method', 'DELETE');
        fd.append('path', dosya.path);

        fetch(ayar.deleteUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: fd
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (sonuc) {
                if (!sonuc.ok) {
                    bilgi(sonuc.d.message || 'Dosya silinemedi.', 'hata');

                    return;
                }

                if (secili && secili.path === dosya.path) {
                    seciliyiSifirla();
                }

                bilgi(dosya.name + ' silindi.', 'iyi');
                yukle(durumKlasor, 1);
            })
            .catch(function () { bilgi('Dosya silinemedi, bağlantınızı kontrol edin.', 'hata'); });
    }

    function yukle(klasor, sayfa) {
        durumKlasor = klasor;
        durumSayfa = sayfa;

        if (sayfa === 1) {
            grid.innerHTML = '';
            seciliyiSifirla();
        }

        bilgi('Yükleniyor...');

        var q = new URLSearchParams({ folder: klasor, page: String(sayfa) });

        if (durumTur) {
            q.set('type', durumTur);
        }

        if (arama.value.trim()) {
            q.set('search', arama.value.trim());
        }

        fetch(ayar.listUrl + '?' + q.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (sonuc) {
                if (!sonuc.ok) {
                    bilgi(sonuc.d.message || 'Klasör okunamadı.', 'hata');

                    return;
                }

                ciz(sonuc.d);
            })
            .catch(function () { bilgi('Klasör okunamadı, bağlantınızı kontrol edin.', 'hata'); });
    }

    function ciz(veri) {
        yolCiz(veri.folder, veri.parent);

        (veri.folders || []).forEach(function (k) { grid.appendChild(klasorKutusu(k)); });
        (veri.files || []).forEach(function (d) { grid.appendChild(dosyaKutusu(d)); });

        if (grid.children.length === 0) {
            grid.innerHTML = '<p class="fp-empty">' +
                (arama.value.trim() ? 'Bu aramayla eşleşen dosya yok.' : 'Bu klasör boş.') +
                '</p>';
            bilgi('');
        } else {
            bilgi(veri.total + ' dosya' + (veri.truncated ? ' · ' + veri.shown + ' tanesi gösteriliyor' : ''));
        }

        dahaDugme.classList.toggle('d-none', !veri.truncated);
    }

    function sec() {
        if (!secili || typeof onSelect !== 'function') {
            return;
        }

        var dosya = secili;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        onSelect(dosya);
    }

    secDugme.addEventListener('click', sec);

    dahaDugme.addEventListener('click', function () { yukle(durumKlasor, durumSayfa + 1); });

    arama.addEventListener('input', function () {
        // Her tuşta istek atmamak için kısa bir bekleme.
        window.clearTimeout(aramaZaman);
        aramaZaman = window.setTimeout(function () { yukle(durumKlasor, 1); }, 300);
    });

    yukleDugme.addEventListener('click', function () { yukleGirdi.click(); });

    yukleGirdi.addEventListener('change', function () {
        if (!yukleGirdi.files || yukleGirdi.files.length === 0) {
            return;
        }

        var dosya = yukleGirdi.files[0];
        var fd = new FormData();
        fd.append('file', dosya);
        fd.append('folder', durumKlasor);

        yukleDugme.disabled = true;
        bilgi(dosya.name + ' yükleniyor...');

        fetch(ayar.uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: fd
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (sonuc) {
                yukleDugme.disabled = false;
                yukleGirdi.value = '';

                if (!sonuc.ok) {
                    bilgi(sonuc.d.message || (sonuc.d.errors && sonuc.d.errors.file && sonuc.d.errors.file[0]) || 'Dosya yüklenemedi.', 'hata');

                    return;
                }

                bilgi(sonuc.d.name + ' yüklendi.', 'iyi');
                // Yeni dosya en üstte görünsün diye liste tazeleniyor.
                yukle(durumKlasor, 1);
            })
            .catch(function () {
                yukleDugme.disabled = false;
                yukleGirdi.value = '';
                bilgi('Dosya yüklenemedi, bağlantınızı kontrol edin.', 'hata');
            });
    });

    // Katman sınıfı: seçici TinyMCE diyalogunun üstüne çıkarken perdesini de
    // yanında taşıyor. Sınıf yalnızca açıkken duruyor, diğer modallar etkilenmiyor.
    modalEl.addEventListener('show.bs.modal', function () {
        document.body.classList.add('fp-acik');
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('fp-acik');
    });

    window.FilePicker = {
        open: function (secenekler) {
            secenekler = secenekler || {};
            onSelect = secenekler.onSelect || null;
            durumTur = secenekler.type || '';
            arama.value = '';
            yukleGirdi.setAttribute('accept', durumTur === 'image' ? 'image/*' : '');

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            yukle(secenekler.folder || '', 1);
        }
    };
})();
