<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Panel içi yardım
|--------------------------------------------------------------------------
| Yardım ekranının içeriği burada duruyor, koda gömülü değil: bu kit'ten
| türeyen her proje modüllerini kendine göre anlatabilsin ve metni
| değiştirmek için dağıtım yapmak gerekmesin.
|
| Panel tek dilde (SetAdminLocale::LOCALE = 'tr'), o yüzden metinler burada
| Türkçe; çeviri dosyasına taşımanın karşılığı yok.
|
| Kılavuz listesi sidebar'daki her modülü kapsamak zorunda: eksik bir modül
| AdminHelpTest'i düşürüyor. Panelde otuzdan fazla ekran var ve devralan
| kişinin bunların ne olduğunu koddan çıkarması beklenemez.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Kılavuzlar
    |--------------------------------------------------------------------------
    | route  → modülün açılış rotası (Route::has ile kontrol ediliyor)
    | badge  → kartın üstündeki etiket
    | cover  → tasarımdaki altı kapak renginden biri
    */

    'guides' => [
        [
            'route' => 'admin.dashboard',
            'title' => 'Panele Giriş',
            'description' => 'Gösterge panosundaki sayılar, hızlı bağlantılar ve son hareketler. Panelin genel yapısı: solda modüller, üstte bildirimler ve profil.',
            'badge' => 'Başlangıç',
            'cover' => 'teal',
            'icon'  => 'bi-speedometer2',
        ],
        [
            'route' => 'admin.blog-posts.index',
            'title' => 'İçerik Yönetimi',
            'description' => 'Blog yazılarını ekleme, düzenleme ve yayınlama. Her yazı bir kategoriye bağlı; çok dilli sitede her dil için ayrı bir kayıt tutulur ve diller birbirine bağlanır.',
            'badge' => 'Temel',
            'cover' => 'blue',
            'icon'  => 'bi-file-earmark-text',
        ],
        [
            'route' => 'admin.blog-categories.index',
            'title' => 'İçerik Kategorileri',
            'description' => 'Blog kategorileri. Kategori silindiğinde altındaki yazılar silinmez; önce başka bir kategoriye taşımanız gerekir.',
            'badge' => 'Temel',
            'cover' => 'blue',
            'icon'  => 'bi-bookmark',
        ],
        [
            'route' => 'admin.blog-comments.index',
            'title' => 'Yorum Moderasyonu',
            'description' => 'Gelen yorumlar onay bekler; onaylanana kadar sitede görünmez. Onaylandığında yorumu yazan kişiye bildirim e-postası gider.',
            'badge' => 'Temel',
            'cover' => 'green',
            'icon'  => 'bi-chat-dots',
        ],
        [
            'route' => 'admin.pages.index',
            'title' => 'Sayfalar',
            'description' => 'Hakkımızda, gizlilik politikası gibi sabit sayfalar. Adresleri panelden değiştirilebilir; eski adres otomatik olarak yenisine yönlendirilir.',
            'badge' => 'Temel',
            'cover' => 'blue',
            'icon'  => 'bi-file-earmark',
        ],
        [
            'route' => 'admin.content-list.index',
            'title' => 'Genel İçerik Listesi',
            'description' => 'Blog, sayfa, galeri ve SSS içeriklerinin tamamı tek listede. "Geçen ay ne yayınladık" sorusunun tek ekranlık cevabı.',
            'badge' => 'Temel',
            'cover' => 'teal',
            'icon'  => 'bi-collection',
        ],
        [
            'route' => 'admin.sliders.index',
            'title' => 'Sliderlar',
            'description' => 'Ana sayfadaki kayan görseller. Sıralama sürükle-bırak ile değişir; her slider için ayrı bir çağrı düğmesi tanımlanabilir.',
            'badge' => 'Temel',
            'cover' => 'purple',
            'icon'  => 'bi-images',
        ],
        [
            'route' => 'admin.popups.index',
            'title' => 'Duyuru / Popup',
            'description' => 'Ziyaretçiye gösterilen duyuru pencereleri. Hangi sayfada, kaç kez ve ne kadar süreyle görüneceği tek tek ayarlanır.',
            'badge' => 'İleri',
            'cover' => 'orange',
            'icon'  => 'bi-window-stack',
        ],
        [
            'route' => 'admin.gallery-items.index',
            'title' => 'Galeri',
            'description' => 'Fotoğraf ve video galerisi. Toplu yükleme ekranıyla çok sayıda görsel tek seferde eklenebilir; görseller otomatik olarak WebP\'ye çevrilir.',
            'badge' => 'Temel',
            'cover' => 'pink',
            'icon'  => 'bi-image',
        ],
        [
            'route' => 'admin.gallery-categories.index',
            'title' => 'Galeri Kategorileri',
            'description' => 'Galeri öğelerinin gruplandığı kategoriler. Ön yüzdeki süzgeç bu kategorilerden üretilir.',
            'badge' => 'Temel',
            'cover' => 'pink',
            'icon'  => 'bi-folder',
        ],
        [
            'route' => 'admin.faqs.index',
            'title' => 'Sık Sorulan Sorular',
            'description' => 'Ön yüzdeki SSS sayfasının içeriği. Sıralama panelden değişir; pasife alınan soru sitede görünmez.',
            'badge' => 'Temel',
            'cover' => 'green',
            'icon'  => 'bi-patch-question',
        ],
        [
            'route' => 'admin.menus.index',
            'title' => 'Menü Yönetimi',
            'description' => 'Üst menü ve alt bilgi bağlantıları. Menü öğesi bir sayfaya, bir kategoriye ya da elle yazılan bir adrese bağlanabilir; dil değiştiğinde bağlantı da o dilin adresine gider.',
            'badge' => 'İleri',
            'cover' => 'teal',
            'icon'  => 'bi-list-ul',
        ],
        [
            'route' => 'admin.files.index',
            'title' => 'Dosya Yöneticisi',
            'description' => 'Yüklenen bütün görsel ve belgeler. Bir dosyanın nerede kullanıldığı listede görünür; kullanımda olan dosya silinmeden önce uyarı verilir.',
            'badge' => 'Temel',
            'cover' => 'blue',
            'icon'  => 'bi-folder2-open',
        ],
        [
            'route' => 'admin.contact-messages.index',
            'title' => 'İletişim Mesajları',
            'description' => 'Ön yüzdeki iletişim formundan gelen mesajlar. Panelden yanıtlandığında e-posta gönderilir ve yanıt mesajın altında saklanır.',
            'badge' => 'Temel',
            'cover' => 'green',
            'icon'  => 'bi-envelope',
        ],
        [
            'route' => 'admin.subscribers.index',
            'title' => 'Bülten Aboneleri',
            'description' => 'Bülten listeleri ve aboneler. Excel ile toplu abone içe aktarılabilir; abonelikten çıkanlar listede kalır ama gönderim almaz.',
            'badge' => 'İleri',
            'cover' => 'purple',
            'icon'  => 'bi-people',
        ],
        [
            'route' => 'admin.campaigns.index',
            'title' => 'Kampanyalar',
            'description' => 'Toplu e-posta gönderimi. Gönderim saatlik limite göre parça parça yapılır; bir kampanya başlatıldıktan sonra durdurulabilir ama geri alınamaz.',
            'badge' => 'İleri',
            'cover' => 'orange',
            'icon'  => 'bi-megaphone',
        ],
        [
            'route' => 'admin.push-notifications.index',
            'title' => 'Push Duyuruları',
            'description' => 'Mobil uygulamaya duyuru bildirimi gönderme. Gönderim birkaç dakikada bir çalışan görevle parça parça yapılır; başlamış bir duyuru geri alınamaz, yalnızca sıradayken iptal edilebilir.',
            'badge' => 'İleri',
            'cover' => 'orange',
            'icon'  => 'bi-bell',
        ],
        [
            'route' => 'admin.mail-templates.index',
            'title' => 'Mail Şablonları',
            'description' => 'Sistemin gönderdiği e-postaların metinleri. Şablondaki {degisken} yazımları gönderim anında gerçek değerlerle değiştirilir.',
            'badge' => 'İleri',
            'cover' => 'orange',
            'icon'  => 'bi-envelope-paper',
        ],
        [
            'route' => 'admin.mail-logs.index',
            'title' => 'Mail Kayıtları',
            'description' => 'Gönderilen her e-postanın kaydı: kime gitti, ulaştı mı, ulaşmadıysa sebebi ne. Başarısız bir gönderim buradan yeniden denenebilir.',
            'badge' => 'İleri',
            'cover' => 'orange',
            'icon'  => 'bi-envelope-check',
        ],
        [
            'route' => 'admin.users.index',
            'title' => 'Kullanıcılar',
            'description' => 'Site ve panel kullanıcıları. Pasife alınan kullanıcının açık oturumları o anda düşer; silinen kullanıcının e-posta adresi yeniden kullanılabilir hâle gelir.',
            'badge' => 'Temel',
            'cover' => 'blue',
            'icon'  => 'bi-person-gear',
        ],
        [
            'route' => 'admin.roles.index',
            'title' => 'Roller ve İzinler',
            'description' => 'Kimin neyi görebileceği. İzinler role verilir, kullanıcıya değil; bir kişinin yetkisini değiştirmek için rolünü değiştirin.',
            'badge' => 'İleri',
            'cover' => 'purple',
            'icon'  => 'bi-shield-lock',
        ],
        [
            'route' => 'admin.notifications.index',
            'title' => 'Bildirimler',
            'description' => 'Panel içi bildirimler: yeni mesaj, yeni yorum, başarısız iş. Okunanlar listede kalır, toplu olarak temizlenebilir.',
            'badge' => 'Temel',
            'cover' => 'teal',
            'icon'  => 'bi-bell',
        ],
        [
            'route' => 'admin.settings.index',
            'title' => 'Ayarlar',
            'description' => 'Site adı, logo, iletişim bilgileri, SEO etiketleri, e-posta sunucusu, bakım modu ve uygulama (PWA) ayarları. Değişiklikler kaydedildiği anda geçerli olur.',
            'badge' => 'Temel',
            'cover' => 'teal',
            'icon'  => 'bi-sliders',
        ],
        [
            'route' => 'admin.service-credentials.index',
            'title' => 'API ve Servisler',
            'description' => 'Google ile giriş, Apple ile giriş, Firebase bildirimleri ve reCAPTCHA anahtarları. '
                . 'Her alanın altında anahtarın hangi konsoldan alınacağı yazıyor. Girilen değer kaydedildiği '
                . 'anda geçerli olur — sunucudaki .env dosyasına dokunmak gerekmez; panelde boş bırakılan alan '
                . 'için .env geçerli kalır. Gizli anahtarlar şifreli saklanır ve bir daha ekrana basılmaz.',
            'badge' => 'İleri',
            'cover' => 'purple',
            'icon'  => 'bi-key-fill',
        ],
        [
            'route' => 'admin.languages.index',
            'title' => 'Diller',
            'description' => 'Sitenin yayınlandığı diller. Varsayılan dil silinemez; bir dil kapatıldığında o dildeki içerikler ziyaretçiye görünmez ama silinmez.',
            'badge' => 'İleri',
            'cover' => 'green',
            'icon'  => 'bi-translate',
        ],
        [
            'route' => 'admin.translations.index',
            'title' => 'Dil Yazıları',
            'description' => 'Ön yüzdeki sabit metinler: buton yazıları, form uyarıları, başlıklar. Değiştirilen metin dosyaya değil veritabanına yazılır, güncellemede kaybolmaz.',
            'badge' => 'İleri',
            'cover' => 'green',
            'icon'  => 'bi-fonts',
        ],
        [
            'route' => 'admin.redirects.index',
            'title' => 'Yönlendirmeler',
            'description' => 'Eski adresleri yenisine yönlendirme. Taşınan bir sayfanın arama motorlarındaki sırasını korumanın yolu budur.',
            'badge' => 'İleri',
            'cover' => 'blue',
            'icon'  => 'bi-signpost-split',
        ],
        [
            'route' => 'admin.custom-routes.index',
            'title' => 'Özel Adresler',
            'description' => 'Yerleşik sayfalara ikinci bir adres tanımlama. Örneğin /iletisim sayfasının İngilizce adresi /en/contact buradan açılır.',
            'badge' => 'İleri',
            'cover' => 'blue',
            'icon'  => 'bi-link-45deg',
        ],
        [
            'route' => 'admin.seo.index',
            'title' => 'SEO Denetimi',
            'description' => 'Bütün sayfa ve yazıların SEO durumu tek listede, en düşük puanlı başta. Eksik meta açıklama, fazladan H1 başlığı, alt metni olmayan görsel ve kırık iç bağlantı burada görünür. Aynı denetim içerik formunda da çalışıyor: kaydetmeden önce uyarıyor ve kaydetmeyi engellemiyor.',
            'badge' => 'İleri',
            'cover' => 'blue',
            'icon'  => 'bi-search-heart',
        ],
        [
            'route' => 'admin.analytics.index',
            'title' => 'Analitik',
            'description' => 'Ziyaret istatistikleri: hangi sayfa kaç kez açıldı, ziyaretçiler nereden geldi, hangi cihazı kullandı. Panel kullanıcılarının gezinmesi sayılmaz.',
            'badge' => 'İleri',
            'cover' => 'purple',
            'icon'  => 'bi-graph-up',
        ],
        [
            'route' => 'admin.analytics.live',
            'title' => 'Anlık Ziyaretçiler',
            'description' => 'Şu anda sitede olan ziyaretçiler ve baktıkları sayfalar. Liste kendiliğinden tazelenir.',
            'badge' => 'İleri',
            'cover' => 'purple',
            'icon'  => 'bi-broadcast',
        ],
        [
            'route' => 'admin.reports.index',
            'title' => 'Rapor Merkezi',
            'description' => 'Trafik, içerik, kullanıcı ve gönderim raporları. Rapor Excel ya da PDF olarak indirilebilir, düzenli aralıklarla e-postayla da gönderilebilir.',
            'badge' => 'İleri',
            'cover' => 'teal',
            'icon'  => 'bi-file-earmark-bar-graph',
        ],
        [
            'route' => 'admin.audit-logs.index',
            'title' => 'Aktivite Logları',
            'description' => 'Panelde kim ne yaptı: giriş, çıkış, kayıt değişikliği, toplu işlem ve dışa aktarma. Kayıtlar bir süre sonra kendiliğinden temizlenir.',
            'badge' => 'İleri',
            'cover' => 'orange',
            'icon'  => 'bi-clock-history',
        ],
        [
            'route' => 'admin.queue.index',
            'title' => 'Kuyruk',
            'description' => 'Arka planda çalışan işler: e-posta gönderimi, rapor üretimi. Başarısız bir iş buradan yeniden denenebilir.',
            'badge' => 'İleri',
            'cover' => 'orange',
            'icon'  => 'bi-stack',
        ],
        [
            'route' => 'admin.backups.index',
            'title' => 'Yedekler',
            'description' => 'Veritabanı ve dosya yedekleri. Yedek indirilebilir, dışarıdan yüklenebilir ve panelden geri yüklenebilir; geri yükleme mevcut veriyi değiştirir.',
            'badge' => 'İleri',
            'cover' => 'green',
            'icon'  => 'bi-archive',
        ],
        [
            'route' => 'admin.system-health.index',
            'title' => 'Sistem Sağlık',
            'description' => 'Sunucunun durumu: disk, veritabanı, kuyruk, zamanlanmış görevler ve log dosyası. Bir şey yolunda değilse burada kırmızı görünür.',
            'badge' => 'İleri',
            'cover' => 'green',
            'icon'  => 'bi-heart-pulse',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sık sorulan sorular
    |--------------------------------------------------------------------------
    | Kategoriler tasarımdaki sekmelere karşılık geliyor.
    */

    'faq_categories' => [
        'content' => 'İçerik',
        'users'   => 'Kullanıcılar',
        'system'  => 'Sistem',
    ],

    'faqs' => [
        [
            'category' => 'content',
            'question' => 'Yazdığım içerik sitede neden görünmüyor?',
            'answer'   => 'Üç sebep olabilir: içerik taslak durumundadır, yayın tarihi ileri bir güne ayarlanmıştır ya da bulunduğu kategori pasiftir. İçeriğin durumunu ve yayın tarihini kontrol edin.',
            'route'    => 'admin.content-list.index',
        ],
        [
            'category' => 'content',
            'question' => 'Aynı içeriği ikinci bir dilde nasıl yayınlarım?',
            'answer'   => 'İçerik formundaki dil sekmelerinden ilgili dili seçip alanları doldurun. Diller birbirine bağlı kaydedilir; ziyaretçi dil değiştirdiğinde aynı içeriğin çevirisine gider, çeviri yoksa listeye döner.',
            'route'    => 'admin.languages.index',
        ],
        [
            'category' => 'content',
            'question' => 'Yüklediğim görseller neden .webp oluyor?',
            'answer'   => 'Yüklenen her görsel otomatik olarak WebP biçimine çevrilir ve dört ayrı boyutu üretilir. Sayfa, ziyaretçinin ekranına uygun olanı indirir; bu, mobil veride ciddi fark yaratır.',
            'route'    => 'admin.files.index',
        ],
        [
            'category' => 'users',
            'question' => 'Bir kullanıcının panele girişini nasıl kapatırım?',
            'answer'   => 'Kullanıcıyı pasife almak yeterli: açık oturumları ve uygulama jetonları o anda düşer. Yalnız panel yetkisini almak istiyorsanız rolünü değiştirin.',
            'route'    => 'admin.users.index',
        ],
        [
            'category' => 'users',
            'question' => 'Yeni bir izin verdim ama kullanıcı hâlâ göremiyor.',
            'answer'   => 'İzinler role verilir. Kullanıcının o role bağlı olduğundan emin olun; roldeki değişiklik bir sonraki istekte geçerli olur.',
            'route'    => 'admin.roles.index',
        ],
        [
            'category' => 'users',
            'question' => 'Yöneticiler için iki adımlı doğrulamayı nasıl zorunlu kılarım?',
            'answer'   => 'Ayarlar → Genel Tercihler bölümündeki anahtarı açın. Açıldığı andan itibaren panele erişebilen her hesap, iki adımlı doğrulamayı kurmadan panele giremez.',
            'route'    => 'admin.settings.index',
        ],
        [
            'category' => 'system',
            'question' => 'Google ya da Apple ile giriş anahtarını nereden alırım?',
            'answer'   => 'API ve Servisler ekranındaki her alanın altında adım adım yazıyor: hangi konsolun '
                . 'hangi bölümünden, hangi düğmeyle. Alanın yanındaki bağlantı sizi doğrudan o konsola götürür. '
                . 'Anahtarı yapıştırıp kaydettiğinizde hemen geçerli olur.',
            'route'    => 'admin.service-credentials.index',
        ],
        [
            'category' => 'system',
            'question' => 'Bir alanda ".env" rozeti görüyorum, sorun mu?',
            'answer'   => 'Hayır. Değerin sunucunun .env dosyasından okunduğunu söylüyor. Aynı alanı panelden '
                . 'doldurursanız artık paneldeki geçerli olur; boşaltırsanız yeniden .env devreye girer.',
            'route'    => 'admin.service-credentials.index',
        ],
        [
            'category' => 'system',
            'question' => 'E-postalar gitmiyor, nereden bakmalıyım?',
            'answer'   => 'Önce Mail Kayıtları ekranına bakın: gönderim denendiyse hata mesajı orada yazar. Kayıt hiç yoksa iş kuyrukta bekliyordur; Kuyruk ekranını ve sunucudaki cron tanımını kontrol edin.',
            'route'    => 'admin.mail-logs.index',
        ],
        [
            'category' => 'system',
            'question' => 'Zamanlanmış işler çalışmıyor.',
            'answer'   => 'Sunucuda dakikada bir çalışan bir cron tanımı olmalı. Sistem Sağlık ekranı son çalışma zamanını gösterir; uzun süredir çalışmıyorsa tanım eksik ya da hatalıdır.',
            'route'    => 'admin.system-health.index',
        ],
        [
            'category' => 'system',
            'question' => 'Yedekten nasıl geri dönerim?',
            'answer'   => 'Yedekler ekranından ilgili yedeği seçip geri yükleyin. Geri yükleme mevcut veritabanını değiştirir; işlemden önce güncel bir yedek almanız önerilir.',
            'route'    => 'admin.backups.index',
        ],
    ],

];
