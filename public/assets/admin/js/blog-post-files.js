/**
 * İçerik ekleri — dil sekmesi başına bir Dropzone.
 *
 * Dropzone kütüphanesi admin layout'ta zaten yüklü
 * (assets/vendor/dropzone/dropzone.min.js). Bu dosya içerik formundaki her dil
 * sekmesinin bırakma alanını ve ek listesini kuruyor.
 *
 * Neden dosyalar formla gitmiyor: on dosya seçilince gövde post_max_size'ı
 * aşıyor, PHP gövdeyi komple atıyor ve CSRF alanı da onunla gittiği için istek
 * 419 dönüyor — kullanıcı yazdığı içeriği, kategorisini, hepsini kaybediyor.
 * Her dosya kendi küçük isteğiyle gidince o tavana hiç yaklaşılmıyor, yavaş
 * bağlantıda ilerleme görünüyor ve bir dosyanın başarısızlığı yalnız o dosyayı
 * etkiliyor.
 *
 * İki bağlanma yolu var:
 *   - Çevirisi kayıtlı dilde (data-post-id dolu) ek doğrudan o satıra bağlanır;
 *     kaydet'e basılmasa bile dosya yerindedir.
 *   - Satırı olmayan dilde sunucu belirteç döner, belirteç gizli alan olarak
 *     forma binerek satır doğduğunda iliştirilir. Yol istemciye hiç verilmiyor;
 *     verilseydi kaydederken başka bir yol gönderip sunucudaki herhangi bir
 *     dosyayı içeriğe iliştirmek mümkün olurdu.
 *
 * CLAUDE.md uyumu: vanilla JS, alert/confirm yerine AdminModal, CSRF meta'dan.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Dropzone === 'undefined') {
        return;
    }

    Dropzone.autoDiscover = false;

    document.querySelectorAll('[data-bpf]').forEach(kurulum);
});

/**
 * Yükleme sırasındaki satır. Kayıtlı ek satırıyla aynı iskelet
 * (admin/blog-posts/_file-row.blade.php): kullanıcı hangisinin kaydedildiğini
 * satırın şeklinden çıkarmaya çalışmasın diye ikisi aynı görünüyor.
 */
var ONIZLEME = [
    '<div class="bpf-file bpf-file--uploading" data-bpf-item>',
    '  <span class="bpf-file__icon">',
    '    <img data-dz-thumbnail alt="" class="d-none">',
    '    <i class="bi bi-arrow-up-circle"></i>',
    '  </span>',
    '  <div class="bpf-file__body">',
    '    <div class="bpf-file__top">',
    '      <span class="bpf-file__name" data-dz-name></span>',
    '      <span class="bpf-file__badge" data-bpf-badge>Yükleniyor</span>',
    '    </div>',
    '    <div class="bpf-file__meta">',
    '      <span class="bpf-file__ext" data-bpf-ext></span>',
    '      <span data-dz-size></span>',
    '    </div>',
    '    <div class="bpf-file__track"><span class="bpf-file__fill" data-dz-uploadprogress></span></div>',
    '    <div class="bpf-file__msg" data-dz-errormessage></div>',
    '  </div>',
    '  <div class="bpf-file__actions">',
    '    <a class="usr-action-btn d-none" target="_blank" rel="noopener" title="Yeni sekmede aç" data-bpf-open>',
    '      <i class="bi bi-box-arrow-up-right"></i>',
    '    </a>',
    '    <button type="button" class="usr-action-btn danger" data-bpf-remove title="Kaldır">',
    '      <i class="bi bi-trash3"></i>',
    '    </button>',
    '  </div>',
    '</div>'
].join('');

function kurulum(kapsayici) {
    var alan = kapsayici.querySelector('[data-bpf-dropzone]');
    var liste = kapsayici.querySelector('[data-bpf-list]');
    var bosluk = kapsayici.querySelector('[data-bpf-empty]');

    if (!alan || !liste) {
        return;
    }

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = meta ? meta.getAttribute('content') : '';

    var ayar = {
        locale:     kapsayici.dataset.locale || '',
        postId:     kapsayici.dataset.postId || '',
        uploadUrl:  kapsayici.dataset.uploadUrl,
        discardUrl: kapsayici.dataset.discardUrl,
        destroyUrl: kapsayici.dataset.destroyUrl,
        maxBytes:   parseInt(kapsayici.dataset.maxBytes, 10) || 0,
        maxLabel:   kapsayici.dataset.maxLabel || '',
        accept:     kapsayici.dataset.accept || ''
    };

    function bosluguTazele() {
        if (!bosluk) {
            return;
        }

        bosluk.classList.toggle('d-none', liste.querySelector('[data-bpf-item]') !== null);
    }

    /**
     * Sorunlar biriktirilip tek pencerede bildiriliyor.
     *
     * Her dosya için ayrı pencere açmak işe yaramıyordu: pencere bir öncekini
     * eziyor ve kullanıcı yalnızca son dosyanın mesajını görüyordu. On dosya
     * bırakıp yedisini listede bulan biri, üçünün neden düştüğünü öğrenemezdi.
     */
    var sorunlar = [];
    var bildirimZamani = null;

    function sorunEkle(mesaj) {
        sorunlar.push(mesaj);

        // Sunucudan dönen hatalar arka arkaya geliyor; son hatadan kısa süre
        // sonra hepsi tek pencerede bildiriliyor.
        window.clearTimeout(bildirimZamani);
        bildirimZamani = window.setTimeout(sorunlariBildir, 400);
    }

    function sorunlariBildir() {
        if (sorunlar.length === 0) {
            return;
        }

        var bildirilecek = sorunlar.slice();
        sorunlar = [];

        var baslik = bildirilecek.length === 1
            ? 'Dosya eklenemedi'
            : bildirilecek.length + ' dosya eklenemedi';

        if (window.AdminModal && typeof window.AdminModal.status === 'function') {
            window.AdminModal.status({
                title: baslik,
                message: bildirilecek.join('\n'),
                type: 'danger'
            });

            return;
        }

        var kutu = document.createElement('div');
        kutu.className = 'invalid-feedback d-block';
        kutu.textContent = bildirilecek.join(' ');
        liste.appendChild(kutu);

        window.setTimeout(function () { kutu.remove(); }, 8000);
    }

    // ==================== KALDIRMA ====================

    function satiriKaldir(satir) {
        satir.remove();
        bosluguTazele();
    }

    /**
     * Sunucudan silinmesi gereken ek için adres. Kayıtlı ek kimliğiyle,
     * bekleyen ek belirteciyle siliniyor; ikisi de yoksa satır yalnızca
     * ekranda (yükleme başarısız olmuş), doğrudan kaldırılıyor.
     */
    function silmeAdresi(satir) {
        if (satir.dataset.fileId) {
            return ayar.destroyUrl.replace('FILE_ID', satir.dataset.fileId);
        }

        if (satir.dataset.token) {
            return ayar.discardUrl.replace('TOKEN', satir.dataset.token);
        }

        return null;
    }

    function kaldirmayaBagla(satir) {
        var dugme = satir.querySelector('[data-bpf-remove]');

        if (!dugme || dugme.dataset.bpfBound) {
            return;
        }

        dugme.dataset.bpfBound = '1';

        dugme.addEventListener('click', function () {
            var adres = silmeAdresi(satir);

            if (!adres) {
                satiriKaldir(satir);

                return;
            }

            var ad = satir.querySelector('.bpf-file__name');

            onayAl(ad ? ad.textContent : 'Bu dosya').then(function (onaylandi) {
                if (!onaylandi) {
                    return;
                }

                dugme.disabled = true;

                fetch(adres, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin'
                }).then(function (cevap) {
                    if (!cevap.ok) {
                        throw new Error('silinemedi');
                    }

                    satiriKaldir(satir);
                }).catch(function () {
                    dugme.disabled = false;
                    sorunEkle('Dosya kaldırılamadı, tekrar deneyin.');
                });
            });
        });
    }

    function onayAl(ad) {
        if (window.AdminModal && typeof window.AdminModal.confirm === 'function') {
            return window.AdminModal.confirm({
                title: 'Dosyayı kaldır',
                message: '"' + ad + '" kalıcı olarak silinecek. Bu işlem geri alınamaz.',
                type: 'danger',
                confirmText: 'Evet, kaldır',
                confirmIcon: 'bi bi-trash3'
            });
        }

        return Promise.resolve(true);
    }

    // Kayıtlı ekler sunucuda çizildi; kaldırma düğmeleri burada bağlanıyor.
    liste.querySelectorAll('[data-bpf-item]').forEach(kaldirmayaBagla);
    bosluguTazele();

    // ==================== YÜKLEME ====================

    var dz = new Dropzone(alan, {
        url: ayar.uploadUrl,
        method: 'POST',
        paramName: 'file',
        // Sayı sınırı yok: bir habere kırk görsel, beş tablo, üç PDF
        // iliştirmek isteyen kullanıcı sayaç yüzünden yarıda kalmasın.
        maxFiles: null,
        maxFilesize: ayar.maxBytes > 0 ? ayar.maxBytes / 1048576 : null,
        acceptedFiles: ayar.accept || null,
        parallelUploads: 3,
        uploadMultiple: false,          // her dosya ayrı istek — ZORUNLU
        autoProcessQueue: true,
        addRemoveLinks: false,          // kaldırma düğmesi şablonda
        previewsContainer: liste,       // önizlemeler bırakma alanının dışında
        previewTemplate: ONIZLEME,
        createImageThumbnails: true,
        thumbnailWidth: 96,
        thumbnailHeight: 96,
        thumbnailMethod: 'crop',
        timeout: 600000,                // 10 dk — büyük video dosyaları için
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },

        dictFileTooBig: 'Dosya çok büyük ({{filesize}} MB). En fazla ' + ayar.maxLabel + '.',
        dictInvalidFileType: 'Bu dosya türü desteklenmiyor.',
        dictResponseError: 'Sunucu hatası: {{statusCode}}',
        dictCancelUpload: 'İptal',
        dictRemoveFile: 'Kaldır'
    });

    dz.on('sending', function (dosya, xhr, veri) {
        veri.append('_token', csrf);

        // Çevirisi kayıtlı dilde ek doğrudan o satıra bağlanıyor; boşsa sunucu
        // belirteçle bekletiyor.
        if (ayar.postId) {
            veri.append('blog_post_id', ayar.postId);
        }
    });

    dz.on('addedfile', function (dosya) {
        if (!dosya.previewElement) {
            return;
        }

        var uzanti = (dosya.name.split('.').pop() || '').toLowerCase();
        var uzantiAlani = dosya.previewElement.querySelector('[data-bpf-ext]');

        if (uzantiAlani) {
            uzantiAlani.textContent = '.' + uzanti;
        }

        bosluguTazele();
    });

    dz.on('thumbnail', function (dosya, veriUrl) {
        if (!dosya.previewElement) {
            return;
        }

        var gorsel = dosya.previewElement.querySelector('[data-dz-thumbnail]');
        var ikon = dosya.previewElement.querySelector('.bpf-file__icon i');

        if (gorsel && veriUrl) {
            gorsel.classList.remove('d-none');

            if (ikon) {
                ikon.classList.add('d-none');
            }
        }
    });

    dz.on('success', function (dosya, cevap) {
        var satir = dosya.previewElement;

        if (!satir || !cevap) {
            return;
        }

        satir.classList.remove('bpf-file--uploading');
        satir.classList.add('bpf-file--' + cevap.color);

        var ilerleme = satir.querySelector('.bpf-file__track');
        if (ilerleme) {
            ilerleme.remove();
        }

        var rozet = satir.querySelector('[data-bpf-badge]');
        if (rozet) {
            rozet.textContent = cevap.kind_label;
        }

        var uzanti = satir.querySelector('[data-bpf-ext]');
        if (uzanti) {
            uzanti.textContent = '.' + cevap.extension;
        }

        // Boyutu Dropzone "18 b" diye yazıyor, sunucu "18 B"; iki satır yan
        // yana durduğu için fark göze batıyor. Sunucununki geçerli.
        var boyut = satir.querySelector('[data-dz-size]');
        if (boyut) {
            boyut.textContent = cevap.size;
        }

        var ac = satir.querySelector('[data-bpf-open]');
        if (ac) {
            ac.href = cevap.url;
            ac.classList.remove('d-none');
        }

        var ikon = satir.querySelector('.bpf-file__icon i');
        if (ikon && !cevap.is_image) {
            ikon.className = 'bi ' + cevap.icon;
        }

        // Önizleme küçük resmi veri URI'siydi; kırk dosyada DOM'u şişirmesin
        // diye sunucudaki adresle değiştiriliyor.
        var gorsel = satir.querySelector('[data-dz-thumbnail]');
        if (gorsel && cevap.is_image) {
            gorsel.src = cevap.url;
            gorsel.classList.remove('d-none');

            if (ikon) {
                ikon.classList.add('d-none');
            }
        }

        if (cevap.id) {
            // Doğrudan içeriğe bağlandı: form kaydedilmese bile ek yerinde.
            satir.dataset.fileId = String(cevap.id);
        } else if (cevap.token) {
            satir.dataset.token = cevap.token;

            var gizli = document.createElement('input');
            gizli.type = 'hidden';
            gizli.name = 'translations[' + ayar.locale + '][file_tokens][]';
            gizli.value = cevap.token;
            gizli.setAttribute('data-fv-ignore', '');
            satir.appendChild(gizli);
        }

        kaldirmayaBagla(satir);
        bosluguTazele();
    });

    dz.on('error', function (dosya, mesaj) {
        var satir = dosya.previewElement;

        // Sunucu doğrulama hatasını {message, errors} olarak döner; nesne
        // olduğu gibi basılsaydı kullanıcı "[object Object]" görürdü.
        var metin = mesaj;

        if (mesaj && typeof mesaj === 'object') {
            metin = (mesaj.errors && mesaj.errors.file && mesaj.errors.file[0])
                || mesaj.message
                || 'Dosya yüklenemedi.';
        }

        sorunEkle(dosya.name + ': ' + metin);

        if (satir) {
            satir.remove();
        }

        bosluguTazele();
    });

    dz.on('removedfile', bosluguTazele);
}
