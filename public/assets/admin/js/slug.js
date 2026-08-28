'use strict';

/**
 * Slug üretimi ve anlık düzeltme — sunucudaki Str::slug() ile aynı sonucu
 * vermeyi hedefler.
 *
 * Neden elle yazılmış karakter haritası yok:
 * Aksanlı harflerin neredeyse tamamı Unicode'da "harf + birleşen işaret"
 * olarak ayrıştırılabiliyor. normalize('NFD') bu ayrıştırmayı yapıyor,
 * işaretleri atınca geriye düz ASCII harf kalıyor. Yüzlerce satırlık bir
 * harita yerine üç satır; üstelik haritada olmayan diller de kendiliğinden
 * çalışıyor (à â ä ā ă ą hepsi "a").
 *
 * Harita yalnızca AYRIŞMAYAN harfler için var: ı ß æ ø đ ł þ ð œ ve Kiril.
 * Bunların Unicode'da ayrık bir biçimi yok, normalize() dokunmuyor.
 *
 * Alan eşleşmesi çok dilli forma göre: title_tr → slug_tr, title_en → slug_en.
 * Eski sürüm hep document.getElementById('slug') arıyordu; dil sekmeleri
 * geldikten sonra o id hiçbir formda yok ve slug üretimi sessizce durmuştu.
 *
 * Dışarıya:
 *   Slug.from('Bahçeşehir Örnek')  → 'bahcesehir-ornek'
 */
window.Slug = (function () {
    /**
     * NFD ile ayrışmayan harfler. Küçük harfe çevirdikten sonra uygulandığı
     * için büyük harfli karşılıklarına gerek yok.
     */
    var AYRISMAYAN = {
        'ı': 'i', 'ß': 'ss', 'æ': 'ae', 'ø': 'o', 'đ': 'd', 'ł': 'l',
        'þ': 'th', 'ð': 'd', 'œ': 'oe', 'ħ': 'h', 'ŧ': 't', 'ĸ': 'k',
        // Kiril: panelden Rusça/Ukraynaca eklenirse istemci de sunucuyla
        // aynı sonucu versin. Latin diller için gereksiz ama maliyeti yok.
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'yo',
        'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'j', 'к': 'k', 'л': 'l', 'м': 'm',
        'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
        'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch', 'ш': 'sh', 'щ': 'sh',
        'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya',
        'є': 'ye', 'і': 'i', 'ї': 'yi', 'ґ': 'g'
    };

    var AYRISMAYAN_DESEN = new RegExp('[' + Object.keys(AYRISMAYAN).join('') + ']', 'g');

    /**
     * @param {string}  metin
     * @param {boolean} sonAyraciKoru  Yazarken sondaki tireyi silme; yoksa
     *                                 kullanıcı boşluğa bastığı anda tire
     *                                 uçar ve kelimeler birbirine yapışır.
     */
    function donustur(metin, sonAyraciKoru) {
        var sonuc = String(metin === null || metin === undefined ? '' : metin)
            // Küçük harf önce: harita ve desen yalnız küçük harf tanıyor.
            // Türkçe İ burada "i + birleşen nokta"ya dönüşüyor, noktayı
            // aşağıdaki işaret temizliği alıyor.
            .toLowerCase()
            .replace(AYRISMAYAN_DESEN, function (harf) { return AYRISMAYAN[harf]; })
            .normalize('NFD')
            // Birleşen işaretler (U+0300–U+036F)
            .replace(/[\u0300-\u036f]/g, '')
            // Harf ve rakam dışında kalan her dizi tek ayraca iner: hem
            // boşluk, hem noktalama, hem çevrilemeyen harfler.
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+/, '');

        return sonAyraciKoru ? sonuc.replace(/-{2,}$/, '-') : sonuc.replace(/-+$/, '');
    }

    /** Bitmiş slug: iki uçtaki ayraçlar da atılır. */
    function from(metin) {
        return donustur(metin, false);
    }

    /* ==================== Forma bağlanma ==================== */

    /**
     * Elle düzenlenmiş slug'lar. Kullanıcı bir kez dokunduysa başlık
     * değiştikçe üstüne yazmıyoruz.
     */
    var elleDuzenlenen = Object.create(null);

    /**
     * Alanı yerinde düzeltir ve imleci korur.
     *
     * İmlecin doğru yere gitmesi için imleçten önceki parça ayrıca
     * dönüştürülüyor: dönüşüm karakter sayısını değiştirdiği için (boşluk →
     * tire, "ş" → "s", "ß" → "ss") imleci eski konumda bırakmak yazıyı
     * karıştırıyor, sona atmak da ortadan düzeltmeyi imkânsız kılıyordu.
     */
    function alaniDuzelt(alan, sonAyraciKoru) {
        var imlecte = alan.selectionStart;
        var yeniDeger = donustur(alan.value, sonAyraciKoru);

        if (yeniDeger === alan.value) {
            return;
        }

        // Seçim yoksa imleç öncesini ayrıca dönüştürüp yeni konumu buluyoruz.
        var yeniKonum = (imlecte === null || imlecte !== alan.selectionEnd)
            ? yeniDeger.length
            : donustur(alan.value.slice(0, imlecte), true).length;

        alan.value = yeniDeger;

        try {
            alan.setSelectionRange(yeniKonum, yeniKonum);
        } catch (e) {
            // Bazı girdi türleri seçim aralığını desteklemiyor; değer yine doğru.
        }
    }

    document.addEventListener('input', function (olay) {
        var oge = olay.target;

        if (!oge || !oge.getAttribute) {
            return;
        }

        // 1) Başlık yazılıyor → slug'ı tazele
        if (oge.hasAttribute('data-slug-source')) {
            var hedefId = oge.getAttribute('data-slug-target');
            var hedef = hedefId ? document.getElementById(hedefId) : null;

            // Hedefe input olayı GÖNDERİLMİYOR: kendi dinleyicimize geri
            // düşer, alanı "elle düzenlendi" sayar ve ilk tuştan sonra
            // otomatik üretim kilitlenirdi.
            if (hedef && !elleDuzenlenen[hedef.id]) {
                hedef.value = from(oge.value);
            }

            return;
        }

        // 2) Slug alanına elle yazılıyor → anında düzelt, o alanı bir daha ezme
        if (oge.hasAttribute('data-slug-field')) {
            alaniDuzelt(oge, true);
            elleDuzenlenen[oge.id] = oge.value.trim() !== '';
        }
    });

    // Odak çıkışında sondaki ayraç da atılır: yazarken korunuyordu ki
    // kelimeler yapışmasın, ama kaydedilecek değerde işi yok.
    document.addEventListener('blur', function (olay) {
        var oge = olay.target;

        if (!oge || !oge.getAttribute || !oge.hasAttribute('data-slug-field')) {
            return;
        }

        if (oge.value.trim() === '') {
            // Boşaltıldıysa otomatik üretim yeniden devreye girsin.
            elleDuzenlenen[oge.id] = false;

            return;
        }

        oge.value = from(oge.value);
    }, true);

    // Düzenleme ekranında dolu gelen slug elle konmuş sayılır.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-slug-field]').forEach(function (alan) {
            if (alan.value.trim() !== '') {
                elleDuzenlenen[alan.id] = true;
            }
        });
    });

    return { from: from };
})();
