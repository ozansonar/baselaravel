'use strict';

/**
 * Editörün dosya seçicisi.
 *
 * public/uploads dizinini gezip dosya seçmeyi, yüklemeyi ve silmeyi sağlıyor.
 * TinyMCE'nin file_picker_callback'i buraya bağlanıyor; daha önce yüklenmiş bir
 * dosyayı yeniden bulmanın yolu yoktu, her seferinde yeniden yükleniyordu.
 *
 * Dışarıya tek kapı:
 *   FilePicker.open({ type: '', onSelect: function (dosya) { ... } })
 *
 * type boş bırakılırsa hiçbir tür elenmez; kullanıcı isterse ekrandaki tür
 * düğmeleriyle daraltır. Eskiden editör her açılışta "image" gönderdiği için
 * PDF, video, zip gibi dosyalar seçicide hiç görünmüyordu.
 *
 * onSelect'e verilen nesne: { url, path, name, is_image, size, category }
 */
(function () {
    var ayar = window.filePickerConfig;
    var modalEl = document.getElementById('filePickerModal');

    if (!ayar || !modalEl) {
        return;
    }

    var VIEW_KEY = 'fp.view';

    var govde = document.getElementById('fpBody');
    var grid = document.getElementById('fpGrid');
    var klasorSerit = document.getElementById('fpFolders');
    var breadcrumb = document.getElementById('fpBreadcrumb');
    var durum = document.getElementById('fpStatus');
    var arama = document.getElementById('fpSearch');
    var yukleGirdi = document.getElementById('fpUploadInput');
    var yukleDugme = document.getElementById('fpUploadBtn');
    var secDugme = document.getElementById('fpChoose');
    var secilenKutu = document.getElementById('fpSelected');
    var dahaDugme = document.getElementById('fpMore');
    var birakPerde = document.getElementById('fpDrop');
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var csrf = tokenEl ? tokenEl.getAttribute('content') : '';

    var durumKlasor = '';
    var durumTur = '';
    var durumSayfa = 1;
    var secili = null;
    var onSelect = null;
    var aramaZaman = null;
    var surukleSayac = 0;

    if (!ayar.canUpload) {
        yukleDugme.classList.add('d-none');
    }

    var BIRIMLER = ['B', 'KB', 'MB', 'GB'];

    var KATEGORI_ETIKET = {
        image: 'Görsel',
        document: 'Belge',
        video: 'Video',
        audio: 'Ses',
        archive: 'Arşiv',
        other: 'Dosya'
    };

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

    /* ---------- Görünüm ---------- */

    function kayitliGorunum() {
        try {
            return window.localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'grid';
        } catch (e) {
            return 'grid';
        }
    }

    function gorunumUygula(gorunum) {
        var liste = gorunum === 'list';
        grid.classList.toggle('fp-grid--list', liste);

        document.querySelectorAll('[data-fp-view]').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-fp-view') === (liste ? 'list' : 'grid'));
        });
    }

    /* ---------- Seçim ---------- */

    function seciliyiSifirla() {
        secili = null;
        secDugme.disabled = true;
        secilenGoster(null);
        grid.querySelectorAll('.fp-tile--secili').forEach(function (t) {
            t.classList.remove('fp-tile--secili');
        });
    }

    function secilenGoster(dosya) {
        var kucuk = secilenKutu.querySelector('.fp-selected__thumb');
        var ad = secilenKutu.querySelector('.fp-selected__name');
        var meta = secilenKutu.querySelector('.fp-selected__meta');

        if (!dosya) {
            secilenKutu.classList.remove('is-dolu');
            kucuk.innerHTML = '<i class="bi bi-hand-index"></i>';
            ad.textContent = 'Dosya seçilmedi';
            meta.textContent = 'Listeden bir dosyaya tıkla';

            return;
        }

        secilenKutu.classList.add('is-dolu');
        kucuk.innerHTML = '';

        if (dosya.is_image) {
            var img = document.createElement('img');
            img.src = dosya.thumb || dosya.url;
            img.alt = '';
            kucuk.appendChild(img);
        } else {
            kucuk.innerHTML = '<i class="bi ' + (dosya.icon || 'bi-file-earmark') + '"></i>';
        }

        ad.textContent = dosya.name;
        meta.textContent = (KATEGORI_ETIKET[dosya.category] || 'Dosya')
            + ' · ' + okunurBoyut(dosya.size)
            + ' · ' + dosya.path;
    }

    /* ---------- Yol ve klasörler ---------- */

    function yolCiz(klasor) {
        breadcrumb.innerHTML = '';

        var parcalar = klasor === '' ? [] : klasor.split('/');
        var kokDugme = document.createElement('button');
        kokDugme.type = 'button';
        kokDugme.className = 'fp-crumb';
        kokDugme.innerHTML = '<i class="bi bi-house-door"></i> uploads';

        if (parcalar.length === 0) {
            kokDugme.classList.add('fp-crumb--aktif');
        }

        kokDugme.addEventListener('click', function () { yukle('', 1); });
        breadcrumb.appendChild(kokDugme);

        var birikim = '';

        parcalar.forEach(function (parca, i) {
            birikim = birikim === '' ? parca : birikim + '/' + parca;

            var ayrac = document.createElement('span');
            ayrac.className = 'fp-crumb__sep';
            ayrac.innerHTML = '<i class="bi bi-chevron-right"></i>';
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

    function klasorleriCiz(klasorler, ustKlasor) {
        klasorSerit.innerHTML = '';

        var geriVar = durumKlasor !== '';

        if (!geriVar && klasorler.length === 0) {
            klasorSerit.classList.add('d-none');

            return;
        }

        klasorSerit.classList.remove('d-none');

        if (geriVar) {
            var geri = document.createElement('button');
            geri.type = 'button';
            geri.className = 'fp-folder fp-folder--geri';
            geri.innerHTML = '<i class="bi bi-arrow-90deg-up"></i><span class="fp-folder__name">Üst klasör</span>';
            geri.addEventListener('click', function () { yukle(ustKlasor || '', 1); });
            klasorSerit.appendChild(geri);
        }

        klasorler.forEach(function (klasor) {
            var kutu = document.createElement('button');
            kutu.type = 'button';
            kutu.className = 'fp-folder';
            kutu.innerHTML =
                '<i class="bi bi-folder-fill"></i>' +
                '<span class="fp-folder__name"></span>' +
                '<span class="fp-folder__count"></span>';
            // textContent ile yazılıyor: klasör adı diskten geliyor, HTML olarak
            // yorumlanmamalı.
            kutu.querySelector('.fp-folder__name').textContent = klasor.name;
            kutu.querySelector('.fp-folder__count').textContent = klasor.count;
            kutu.addEventListener('click', function () { yukle(klasor.path, 1); });
            klasorSerit.appendChild(kutu);
        });
    }

    /* ---------- Dosya kutusu ---------- */

    function dosyaKutusu(dosya) {
        var kutu = document.createElement('div');
        kutu.className = 'fp-tile';
        kutu.setAttribute('role', 'button');
        kutu.tabIndex = 0;

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
            onizleme.innerHTML = '<i class="bi ' + (dosya.icon || 'bi-file-earmark') + '"></i>';
        }

        var rozet = document.createElement('span');
        rozet.className = 'fp-tile__ext';
        rozet.textContent = dosya.extension || KATEGORI_ETIKET[dosya.category] || '';
        onizleme.appendChild(rozet);

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

        function isaretle() {
            grid.querySelectorAll('.fp-tile--secili').forEach(function (t) {
                t.classList.remove('fp-tile--secili');
            });
            kutu.classList.add('fp-tile--secili');
            secili = dosya;
            secDugme.disabled = false;
            secilenGoster(dosya);
        }

        kutu.addEventListener('click', isaretle);

        // Çift tıklama doğrudan seçiyor: tek tık + "Seç" iki adım, alışkanlık bu.
        kutu.addEventListener('dblclick', function () {
            secili = dosya;
            sec();
        });

        kutu.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                isaretle();
            }
        });

        return kutu;
    }

    /* ---------- Silme ---------- */

    function silmeyiSor(dosya) {
        AdminModal.confirm({
            title: 'Dosyayı Sil',
            message: 'Dosya sunucudan kalıcı olarak silinecek. Bu dosyayı kullanan sayfalarda bağlantı kırılır.',
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

    /* ---------- Listeleme ---------- */

    function yukle(klasor, sayfa) {
        durumKlasor = klasor;
        durumSayfa = sayfa;

        if (sayfa === 1) {
            grid.innerHTML = '';
            seciliyiSifirla();
            iskeletCiz();
        }

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
                    grid.innerHTML = '';
                    bilgi(sonuc.d.message || 'Klasör okunamadı.', 'hata');

                    return;
                }

                ciz(sonuc.d);
            })
            .catch(function () {
                grid.innerHTML = '';
                bilgi('Klasör okunamadı, bağlantınızı kontrol edin.', 'hata');
            });
    }

    /** İstek dönene kadar boş kutular: ızgara bir anda zıplamasın. */
    function iskeletCiz() {
        var parca = '';

        for (var i = 0; i < 12; i++) {
            parca += '<div class="fp-tile fp-tile--iskelet"></div>';
        }

        grid.innerHTML = parca;
        bilgi('Yükleniyor...');
    }

    function ciz(veri) {
        yolCiz(veri.folder);
        klasorleriCiz(veri.folders || [], veri.parent);

        grid.innerHTML = '';
        (veri.files || []).forEach(function (d) { grid.appendChild(dosyaKutusu(d)); });

        if (grid.children.length === 0) {
            var mesaj = arama.value.trim()
                ? 'Bu aramayla eşleşen dosya yok.'
                : (durumTur ? 'Bu klasörde bu türde dosya yok.' : 'Bu klasör boş.');

            grid.innerHTML =
                '<div class="fp-empty">' +
                    '<i class="bi bi-folder2-open"></i>' +
                    '<p></p>' +
                '</div>';
            grid.querySelector('.fp-empty p').textContent = mesaj;
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

    /* ---------- Yükleme ---------- */

    function dosyalariYukle(dosyalar) {
        if (!ayar.canUpload || !dosyalar || dosyalar.length === 0) {
            return;
        }

        var kuyruk = Array.prototype.slice.call(dosyalar);
        var basarili = 0;
        var hatali = 0;

        yukleDugme.disabled = true;

        function sonraki() {
            if (kuyruk.length === 0) {
                yukleDugme.disabled = false;
                yukleGirdi.value = '';

                bilgi(
                    basarili + ' dosya yüklendi' + (hatali ? ', ' + hatali + ' dosya başarısız.' : '.'),
                    hatali ? 'hata' : 'iyi'
                );

                if (basarili > 0) {
                    // Yeni dosya en üstte görünsün diye liste tazeleniyor.
                    yukle(durumKlasor, 1);
                }

                return;
            }

            var dosya = kuyruk.shift();
            var fd = new FormData();
            fd.append('file', dosya);
            fd.append('folder', durumKlasor);

            bilgi(dosya.name + ' yükleniyor... (' + (kuyruk.length + 1) + ' kaldı)');

            fetch(ayar.uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: fd
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (sonuc) {
                    if (sonuc.ok) {
                        basarili++;
                    } else {
                        hatali++;
                    }

                    sonraki();
                })
                .catch(function () {
                    hatali++;
                    sonraki();
                });
        }

        sonraki();
    }

    /* ---------- Olaylar ---------- */

    secDugme.addEventListener('click', sec);

    dahaDugme.addEventListener('click', function () { yukle(durumKlasor, durumSayfa + 1); });

    arama.addEventListener('input', function () {
        // Her tuşta istek atmamak için kısa bir bekleme.
        window.clearTimeout(aramaZaman);
        aramaZaman = window.setTimeout(function () { yukle(durumKlasor, 1); }, 300);
    });

    document.querySelectorAll('[data-fp-type]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            durumTur = chip.getAttribute('data-fp-type');

            document.querySelectorAll('[data-fp-type]').forEach(function (c) {
                c.classList.toggle('is-active', c === chip);
            });

            yukle(durumKlasor, 1);
        });
    });

    document.querySelectorAll('[data-fp-view]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var gorunum = btn.getAttribute('data-fp-view');
            gorunumUygula(gorunum);

            try {
                window.localStorage.setItem(VIEW_KEY, gorunum);
            } catch (e) {
                // Gizli sekmede depolama kapalı olabilir — görünüm yine değişir.
            }
        });
    });

    yukleDugme.addEventListener('click', function () { yukleGirdi.click(); });

    yukleGirdi.addEventListener('change', function () {
        dosyalariYukle(yukleGirdi.files);
    });

    // Sürükle-bırak: gövdenin tamamı hedef. dragenter/dragleave iç öğelerde de
    // tetiklendiği için sayaçla izleniyor, yoksa perde titriyor.
    ['dragenter', 'dragover'].forEach(function (olay) {
        govde.addEventListener(olay, function (e) {
            if (!ayar.canUpload) {
                return;
            }

            e.preventDefault();

            if (olay === 'dragenter') {
                surukleSayac++;
            }

            govde.classList.add('fp-body--birakma');
        });
    });

    govde.addEventListener('dragleave', function () {
        surukleSayac--;

        if (surukleSayac <= 0) {
            surukleSayac = 0;
            govde.classList.remove('fp-body--birakma');
        }
    });

    govde.addEventListener('drop', function (e) {
        if (!ayar.canUpload) {
            return;
        }

        e.preventDefault();
        surukleSayac = 0;
        govde.classList.remove('fp-body--birakma');
        dosyalariYukle(e.dataTransfer && e.dataTransfer.files);
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

            document.querySelectorAll('[data-fp-type]').forEach(function (c) {
                c.classList.toggle('is-active', c.getAttribute('data-fp-type') === durumTur);
            });

            // Süzgeç yoksa yükleme kutusu da tür dayatmıyor.
            if (durumTur === 'image') {
                yukleGirdi.setAttribute('accept', 'image/*');
            } else {
                yukleGirdi.removeAttribute('accept');
            }

            gorunumUygula(kayitliGorunum());
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            yukle(secenekler.folder || '', 1);
        }
    };
})();
