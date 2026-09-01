/**
 * Push duyurusu formu.
 *
 * Üç iş yapıyor ve üçü de aynı sebeple burada: yazılanın cihazda ne olacağını
 * göndermeden önce göstermek.
 *
 *  - Hedef seçimi: "rol" ya da "tek kullanıcı" seçildiğinde ilgili panel açılır
 *    ve seçim tek bir gizli alana (audience_id) yazılır.
 *  - Cihaz sayısı: hedef değiştikçe sunucudan sorulur. Sayı ekranda yok —
 *    kullanıcının bildirim tercihi ve hesabının açık olması sunucuda biliniyor.
 *  - Önizleme ve karakter sayacı: başlık ile metnin cihazda hangi sırayla
 *    okunacağı ve kırpmanın nereye düştüğü formda görünmüyordu.
 */
(function () {
    'use strict';

    var ayarlar = window.pushForm || {};

    function jeton() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function sayiBicimle(sayi) {
        return Number(sayi).toLocaleString('tr-TR');
    }

    /* ==================== Hedef seçimi ==================== */

    function hedefKur() {
        var radios = document.querySelectorAll('.js-push-audience');
        var gizli = document.getElementById('audienceId');
        var rolSecim = document.getElementById('roleSelect');

        if (!radios.length || !gizli) {
            return null;
        }

        function secili() {
            var el = document.querySelector('.js-push-audience:checked');

            return el ? el.value : null;
        }

        function panelleriUygula() {
            var deger = secili();

            document.querySelectorAll('.js-push-panel').forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-audience') !== deger;
            });
        }

        function kimligiTazele() {
            var deger = secili();

            // "Herkes" seçildiğinde eski seçim taşınmıyor: sunucu da onu
            // temizliyor ama ekrandaki sayının doğru olması için burada da
            // silinmeli.
            if (deger === 'all') {
                gizli.value = '';

                return;
            }

            if (deger === 'role') {
                gizli.value = rolSecim ? rolSecim.value : '';
            }

            // "user" durumunda değer arama sonucundan geliyor; burada
            // dokunulmuyor ki hata sonrası geri gelen seçim kaybolmasın.
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                panelleriUygula();
                kimligiTazele();
                sayiyiTazele();
            });
        });

        if (rolSecim) {
            rolSecim.addEventListener('change', function () {
                gizli.value = rolSecim.value;
                sayiyiTazele();
            });
        }

        panelleriUygula();

        return { secili: secili, gizli: gizli };
    }

    /* ==================== Cihaz sayısı ==================== */

    var hedef = null;
    var zamanlayici = null;

    function sayiyiTazele() {
        var kutu = document.getElementById('audienceCount');

        if (!kutu || !hedef || !ayarlar.sizeUrl) {
            return;
        }

        var kitle = hedef.secili();

        if (!kitle) {
            return;
        }

        var kimlik = hedef.gizli.value;

        if (kitle !== 'all' && !kimlik) {
            kutu.textContent = '—';

            return;
        }

        // Rol kutusu art arda değiştirildiğinde her değişiklik bir istek
        // demekti; son seçim beklenmeden gönderilen istekler birbirini
        // geçebiliyor ve ekranda yanlış sayı kalabiliyordu.
        window.clearTimeout(zamanlayici);

        zamanlayici = window.setTimeout(function () {
            fetch(ayarlar.sizeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': jeton(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ audience: kitle, audience_id: kimlik })
            })
                .then(function (yanit) { return yanit.ok ? yanit.json() : null; })
                .then(function (veri) {
                    if (!veri) {
                        return;
                    }

                    kutu.textContent = veri.pending ? '—' : sayiBicimle(veri.count);
                })
                .catch(function () {
                    // Sayı bilgilendirme amaçlı: alınamadığında form yine
                    // gönderilebilmeli, ekranı hata mesajıyla doldurmaya değmez.
                    kutu.textContent = '—';
                });
        }, 250);
    }

    /* ==================== Kullanıcı arama ==================== */

    function kullaniciAramaKur() {
        var girdi = document.getElementById('userSearch');
        var sonuclar = document.getElementById('userResults');
        var secilen = document.getElementById('userChosen');
        var gizli = document.getElementById('audienceId');

        if (!girdi || !sonuclar || !gizli || !ayarlar.searchUrl) {
            return;
        }

        var aramaZamani = null;

        function temizle() {
            sonuclar.innerHTML = '';
            sonuclar.hidden = true;
        }

        function ciz(liste) {
            sonuclar.innerHTML = '';

            if (!liste.length) {
                var bos = document.createElement('small');
                bos.className = 'stg-hint';
                bos.textContent = 'Eşleşen kullanıcı yok.';
                sonuclar.appendChild(bos);
                sonuclar.hidden = false;

                return;
            }

            liste.forEach(function (kullanici) {
                var dugme = document.createElement('button');
                dugme.type = 'button';
                dugme.className = 'cmp-check';
                dugme.textContent = kullanici.name + ' — ' + kullanici.email;

                dugme.addEventListener('click', function () {
                    gizli.value = String(kullanici.id);
                    if (secilen) {
                        secilen.textContent = 'Seçili: ' + kullanici.name + ' (' + kullanici.email + ')';
                    }
                    girdi.value = kullanici.name;
                    temizle();
                    sayiyiTazele();
                });

                sonuclar.appendChild(dugme);
            });

            sonuclar.hidden = false;
        }

        girdi.addEventListener('input', function () {
            // Yazarken önceki seçim geçersiz: kutuda bir ad, gizli alanda
            // başka bir kimlik durmamalı.
            gizli.value = '';
            if (secilen) {
                secilen.textContent = 'Henüz kullanıcı seçilmedi.';
            }

            var terim = girdi.value.trim();

            window.clearTimeout(aramaZamani);

            if (terim.length < 2) {
                temizle();

                return;
            }

            aramaZamani = window.setTimeout(function () {
                fetch(ayarlar.searchUrl + '?q=' + encodeURIComponent(terim), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (yanit) { return yanit.ok ? yanit.json() : null; })
                    .then(function (veri) {
                        ciz(veri && veri.results ? veri.results : []);
                    })
                    .catch(function () { temizle(); });
            }, 300);
        });
    }

    /* ==================== Önizleme ve sayaç ==================== */

    function onizlemeKur() {
        var baslik = document.getElementById('title');
        var metin = document.getElementById('body');

        function bagla(girdi, onizlemeId, sayacId, yerTutucu) {
            if (!girdi) {
                return;
            }

            var onizleme = document.getElementById(onizlemeId);
            var sayac = document.getElementById(sayacId);
            var enFazla = parseInt(girdi.getAttribute('maxlength'), 10);

            function uygula() {
                var deger = girdi.value;

                if (onizleme) {
                    onizleme.textContent = deger.trim() === '' ? yerTutucu : deger;
                }

                if (sayac) {
                    sayac.textContent = String(deger.length);
                    // Alan maxlength taşıyor ama yapıştırma ile aşılabiliyor.
                    sayac.classList.toggle('text-danger', enFazla > 0 && deger.length > enFazla);
                }
            }

            girdi.addEventListener('input', uygula);
            uygula();
        }

        bagla(baslik, 'previewTitle', 'titleCount', 'Başlık');
        bagla(metin, 'previewBody', 'bodyCount', 'Duyuru metni burada görünür.');
    }

    document.addEventListener('DOMContentLoaded', function () {
        hedef = hedefKur();
        kullaniciAramaKur();
        onizlemeKur();
        sayiyiTazele();
    });
})();
