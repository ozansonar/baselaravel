<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'title'            => 'Hakkımızda',
                'slug'             => 'hakkimizda',
                'excerpt'          => 'Bolu\'nun bereketli topraklarında, üç nesil boyunca süregelen doğal üretim geleneğimizi keşfedin.',
                'content'          => $this->aboutContent(),
                'sections'         => $this->aboutSections(),
                'status'           => 'published',
                'sort_order'       => 1,
                'meta_title'       => 'Hakkımızda | Orhan Baba\'nın Çiftliği — Doğal Köy Ürünleri',
                'meta_description' => 'Bolu yaylalarında üç kuşaktır doğal üretim yapan Orhan Baba\'nın Çiftliği\'nin hikayesi, misyonu ve değerleri.',
                'published_at'     => now(),
            ],
            [
                'title'            => 'Teslimat Koşulları',
                'slug'             => 'teslimat-kosullari',
                'excerpt'          => 'Ürünlerimiz soğuk zincir korunarak, özenli paketlemeyle kapınıza kadar ulaştırılır.',
                'content'          => $this->shippingContent(),
                'status'           => 'published',
                'sort_order'       => 2,
                'meta_title'       => 'Teslimat Koşulları | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Kargo ücreti, teslimat süreleri, soğuk zincir paketleme ve ücretsiz kargo koşulları hakkında detaylı bilgi.',
                'published_at'     => now(),
            ],
            [
                'title'            => 'İade ve Değişim',
                'slug'             => 'iade-ve-degisim',
                'excerpt'          => 'Müşteri memnuniyeti odaklı iade ve değişim politikamız hakkında bilgi alın.',
                'content'          => $this->returnContent(),
                'status'           => 'published',
                'sort_order'       => 3,
                'meta_title'       => 'İade ve Değişim Politikası | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Hasarlı ürün bildirimi, iade koşulları, değişim süreci ve müşteri haklarınız hakkında bilgi.',
                'published_at'     => now(),
            ],
            [
                'title'            => 'Gizlilik Politikası',
                'slug'             => 'gizlilik-politikasi',
                'excerpt'          => 'Kişisel verilerinizin korunması ve gizliliğiniz bizim için en büyük önceliktir.',
                'content'          => $this->privacyContent(),
                'status'           => 'published',
                'sort_order'       => 4,
                'meta_title'       => 'Gizlilik Politikası | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'KVKK kapsamında kişisel verilerin korunması, çerez politikası ve kullanıcı hakları hakkında bilgi.',
                'published_at'     => now(),
            ],
            [
                'title'            => 'Kullanım Koşulları',
                'slug'             => 'kullanim-kosullari',
                'excerpt'          => 'Web sitemizi kullanmadan önce lütfen kullanım koşullarımızı okuyun.',
                'content'          => $this->termsContent(),
                'status'           => 'published',
                'sort_order'       => 5,
                'meta_title'       => 'Kullanım Koşulları | Orhan Baba\'nın Çiftliği',
                'meta_description' => 'Orhan Baba\'nın Çiftliği web sitesi kullanım koşulları, üyelik, sipariş ve fikri mülkiyet hakları.',
                'published_at'     => now(),
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                $page,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function aboutSections(): array
    {
        return [
            'story' => [
                'tag' => 'Hikayemiz',
                'title' => '1975\'ten Bu Yana Doğallığın Adresi',
                'experience_year' => '50+',
                'paragraphs' => "Orhan Baba'nın Çiftliği, 1975 yılında Çorum'nun bereketli topraklarında, Orhan Dede'nin azmi ve doğaya olan tutkusuyla kuruldu. Başlangıçta sadece birkaç inek ve küçük bir arazi ile başlayan hikayemiz, bugün üç kuşaktır sürdürülen bir gelenek haline geldi.\n\nDedemizden babamıza, babamızdan bize aktarılan bu miras, sadece bir çiftlik değil; doğaya saygı, geleneksel üretim yöntemleri ve kaliteden asla ödün vermeme felsefesinin ta kendisidir.",
                'highlight' => 'Doğanın bize verdiği her şeyin en iyisini, en doğal haliyle insanlara ulaştırmak bizim için sadece bir iş değil, yaşam biçimi.',
                'closing' => 'Bugün, modern hijyen standartlarını geleneksel üretim yöntemleriyle harmanlayarak, Türkiye\'nin dört bir yanına taze ve doğal ürünler ulaştırıyoruz.',
            ],
            'values' => [
                [
                    'icon' => 'fa-solid fa-seedling',
                    'title' => '%100 Doğal',
                    'description' => 'Hiçbir ürünümüzde katkı maddesi, koruyucu veya yapay tatlandırıcı kullanmıyoruz.',
                ],
                [
                    'icon' => 'fa-solid fa-hands-helping',
                    'title' => 'Geleneksel Üretim',
                    'description' => 'Dedelerimizden öğrendiğimiz geleneksel yöntemlerle, el emeği ile üretim yapıyoruz.',
                ],
                [
                    'icon' => 'fa-solid fa-shield-halved',
                    'title' => 'Güvenilir Kalite',
                    'description' => 'Tüm ürünlerimiz hijyen ve kalite standartlarına uygun olarak üretilmektedir.',
                ],
                [
                    'icon' => 'fa-solid fa-truck',
                    'title' => 'Taze Teslimat',
                    'description' => 'Soğuk zincir kargo ile ürünlerimizi tazeliğini koruyarak kapınıza ulaştırıyoruz.',
                ],
            ],
            'timeline' => [
                [
                    'year' => '1975',
                    'title' => 'Kuruluş',
                    'description' => 'Orhan Dede, Çorum\'da küçük bir çiftlik kurarak süt üretimine başladı. İlk müşterilerimiz köyümüzün sakinleriydi.',
                ],
                [
                    'year' => '1990',
                    'title' => 'Genişleme',
                    'description' => 'İkinci kuşak devralarak peynir ve yoğurt üretimine başladık. Çiftliğimizi büyüttük ve modern hijyen standartlarını benimsedik.',
                ],
                [
                    'year' => '2010',
                    'title' => 'Ürün Çeşitliliği',
                    'description' => 'Bal, reçel ve zeytinyağı gibi yeni ürünleri yelpazemize ekledik. Bölgesel satışlarımız arttı.',
                ],
                [
                    'year' => '2020',
                    'title' => 'Online Satış',
                    'description' => 'E-ticaret platformumuzu kurarak Türkiye\'nin her yerine ulaşmaya başladık. Soğuk zincir kargo sistemi ile tazeliği garantiledik.',
                ],
                [
                    'year' => '2026',
                    'title' => 'Bugün',
                    'description' => '50 yılı aşkın tecrübemizle binlerce aileye doğal ve taze ürünler ulaştırıyoruz. Üçüncü kuşak olarak geleneği sürdürüyoruz.',
                ],
            ],
            'stats' => [
                ['number' => 50, 'suffix' => '+', 'label' => 'Yıllık Deneyim', 'format' => ''],
                ['number' => 15000, 'suffix' => '+', 'label' => 'Mutlu Müşteri', 'format' => 'K'],
                ['number' => 30, 'suffix' => '+', 'label' => 'Ürün Çeşidi', 'format' => ''],
                ['number' => 81, 'suffix' => '', 'label' => 'İl\'e Teslimat', 'format' => ''],
            ],
            'team' => [
                [
                    'name' => 'Orhan Yılmaz',
                    'role' => 'Kurucu Baba',
                    'description' => '1975\'te çiftliğimizi kuran ve bize doğaya saygıyı öğreten büyüğümüz.',
                    'photo' => null,
                ],
                [
                    'name' => 'Mehmet Yılmaz',
                    'role' => 'İşletme Müdürü',
                    'description' => 'İkinci kuşak temsilcimiz. Üretim ve kalite kontrolden sorumlu.',
                    'photo' => null,
                ],
                [
                    'name' => 'Ayşe Yılmaz',
                    'role' => 'Pazarlama & Satış',
                    'description' => 'Üçüncü kuşak. Online satış ve müşteri ilişkilerinden sorumlu.',
                    'photo' => null,
                ],
            ],
            'cta' => [
                'title' => 'Doğallığı Tatmaya Hazır mısınız?',
                'description' => '50 yıllık tecrübemizle hazırladığımız ürünlerimizi keşfedin ve doğallığın tadını çıkarın.',
            ],
        ];
    }

    private function aboutContent(): string
    {
        return <<<'HTML'
<h2>Hikayemiz</h2>
<p>Her şey bundan yıllar önce, Bolu'nun yemyeşil yaylalarında başladı. Dedemiz Orhan Baba, elindeki birkaç inek ve küçük bir arazi ile hayalini kurduğu çiftliğin temellerini attı. O zamanlar ne süslü paketlemeler vardı ne de internet siparişleri; sadece toprağa duyulan derin bir saygı ve alnının teriyle ürettiği ürünlerin gururu vardı.</p>
<p>Bugün üçüncü nesil olarak aynı topraklarda, aynı tutkuyla üretim yapıyoruz. Dedemizden babamıza, babamızdan bize aktarılan geleneksel üretim bilgisini modern hijyen standartlarıyla birleştirerek sofranıza en doğal ürünleri ulaştırıyoruz.</p>

<h2>Misyonumuz</h2>
<p>Şehir hayatının koşuşturmasında sofrasına sağlıklı, doğal ve güvenilir ürünler koymak isteyen herkese ulaşmak. Çiftliğimizden sofranıza uzanan bu yolda hiçbir aracı yok — sadece biz, toprak ve sizin için en iyisini üretme arzumuz var.</p>
<p>Her ürünümüz, sanki kendi ailemizin sofrasına koyacakmışız gibi özenle hazırlanır. Çünkü aslında öyle: biz de aynı ürünleri yiyor, aynı sütü içiyor, aynı peyniri kahvaltımızda tüketiyoruz.</p>

<h2>Değerlerimiz</h2>
<ul>
<li><strong>Doğallık:</strong> Hiçbir ürünümüzde katkı maddesi, koruyucu, renklendirici veya hormon kullanmıyoruz. Doğanın bize sunduğu lezzeti olduğu gibi sofranıza taşıyoruz.</li>
<li><strong>Şeffaflık:</strong> Üretimden paketlemeye, kargolama sürecinden müşteri hizmetlerine kadar her aşamada tam şeffaflık ilkesiyle çalışıyoruz.</li>
<li><strong>Sürdürülebilirlik:</strong> Toprağımızı tüketmek değil, gelecek nesillere daha verimli bırakmak istiyoruz. Mevsiminde üretim yapar, doğal döngüye saygı duyarız.</li>
<li><strong>Tazelik:</strong> Ürünlerimiz sipariş üzerine hazırlanır. Aylarca rafta bekleyen değil, bugün üretilip yarın kapınıza ulaşan ürünler sunarız.</li>
<li><strong>Güven:</strong> Her ürünün arkasında adımızı ve itibarımızı koyuyoruz. Memnun kalmadığınız tek bir ürün bile bizim için kabul edilemez.</li>
</ul>

<h2>Çiftliğimiz</h2>
<p>Bolu'nun serin yaylalarında, deniz seviyesinden 1.200 metre yükseklikte yer alan çiftliğimiz, temiz hava ve berrak su kaynaklarıyla çevrili eşsiz bir coğrafyada konumlanmıştır. 50 dönümlük arazimizde hem hayvancılık hem de tarımsal üretim yapıyoruz.</p>
<p>Hayvanlarımız gün boyu serbest otlatma alanlarında dolaşır, doğal otlarla beslenir. Stressiz ve mutlu hayvanların çok daha kaliteli süt verdiğine inanıyor ve bunu her gün gözlemliyoruz.</p>

<h2>Neden Biz?</h2>
<ul>
<li><strong>Üç kuşaklık deneyim:</strong> Dedemizden bugüne aktarılan bilgi birikimi.</li>
<li><strong>Aracısız satış:</strong> Çiftlikten doğrudan sofranıza, hiçbir aracı olmadan.</li>
<li><strong>Soğuk zincir:</strong> Ürünlerimiz soğuk zincir korunarak kapınıza ulaşır.</li>
<li><strong>Koşulsuz memnuniyet:</strong> Beğenmediğiniz ürünü iade alır, yenisini göndeririz.</li>
</ul>
HTML;
    }

    private function shippingContent(): string
    {
        return <<<'HTML'
<h2>Kargo ve Teslimat Bilgileri</h2>
<p>Siparişleriniz, ödeme onayının ardından <strong>1-2 iş günü</strong> içinde özenle hazırlanarak kargoya teslim edilir. Doğal ürünlerin tazeliğini korumak için özel soğuk zincir paketleme yöntemi kullanıyoruz.</p>

<h2>Kargo Ücreti</h2>
<ul>
<li><strong>200₺ ve üzeri siparişlerde kargo tamamen ücretsizdir.</strong></li>
<li>200₺ altındaki siparişlerde kargo ücreti <strong>25₺</strong>'dir.</li>
</ul>
<p>Kargo ücretini sipariş özetinizde, ödeme yapmadan önce net olarak görebilirsiniz.</p>

<h2>Soğuk Zincir Paketleme</h2>
<p>Süt, peynir, tereyağı gibi soğuk muhafaza gerektiren ürünlerimiz <strong>özel strafor kutularda</strong> ve <strong>buz aküsüyle</strong> paketlenir. Bu sayede ürünler teslimat süresince ideal sıcaklıkta korunur.</p>
<ul>
<li>Strafor kutu + buz aküsü ile soğuk muhafaza</li>
<li>Darbeye dayanıklı özel ambalaj</li>
<li>Sızdırmaz iç paketleme</li>
<li>Ürün bilgi etiketi ve son kullanma tarihi</li>
</ul>

<h2>Teslimat Süreleri</h2>
<p>Kargoya verildikten sonra teslimat süreleri bölgenize göre değişiklik gösterebilir:</p>
<ul>
<li><strong>Marmara, İç Anadolu:</strong> 1-2 iş günü</li>
<li><strong>Ege, Akdeniz, Karadeniz:</strong> 2-3 iş günü</li>
<li><strong>Doğu, Güneydoğu Anadolu:</strong> 3-4 iş günü</li>
</ul>

<h2>Kargo Takibi</h2>
<p>Siparişiniz kargoya verildiğinde, <strong>kargo takip numarası</strong> e-posta ve SMS ile tarafınıza iletilir. Hesabınızdaki <strong>"Siparişlerim"</strong> bölümünden de kargonuzu anlık olarak takip edebilirsiniz.</p>

<h2>Teslimat Sırasında Dikkat Edilecekler</h2>
<ul>
<li>Paketi teslim alırken dış ambalajı kontrol edin.</li>
<li>Hasar veya ezilme varsa kargo görevlisi huzurunda <strong>tutanak tutturun</strong>.</li>
<li>Ürünleri teslim aldıktan sonra en kısa sürede buzdolabına yerleştirin.</li>
</ul>
HTML;
    }

    private function returnContent(): string
    {
        return <<<'HTML'
<h2>İade Politikamız</h2>
<p>Müşteri memnuniyeti bizim için her şeyin önünde gelir. Ürünlerimizle ilgili herhangi bir sorun yaşamanız durumunda yanınızdayız. Amacımız, her müşterimizin gönül rahatlığıyla alışveriş yapabilmesidir.</p>

<h2>Hangi Durumlarda İade Yapılır?</h2>
<ul>
<li><strong>Hasarlı ürün:</strong> Kargo sürecinde zarar görmüş, ezilmiş veya ambalajı açılmış ürünler.</li>
<li><strong>Hatalı gönderim:</strong> Sipariş ettiğinizden farklı bir ürün gönderilmişse.</li>
<li><strong>Bozuk ürün:</strong> Son kullanma tarihi geçmiş veya kalite standartlarımıza uymayan ürünler.</li>
<li><strong>Eksik teslimat:</strong> Sipariş ettiğiniz ürünlerden biri veya birkaçı pakette yoksa.</li>
</ul>

<h2>İade Süreci</h2>
<ol>
<li><strong>Bildirim:</strong> Teslimat tarihinden itibaren <strong>24 saat</strong> içinde bize ulaşın.</li>
<li><strong>Fotoğraf:</strong> Hasarlı veya hatalı ürünün fotoğrafını gönderin.</li>
<li><strong>Değerlendirme:</strong> Ekibimiz bildiriminizi en geç <strong>2 saat</strong> içinde değerlendirir.</li>
<li><strong>Çözüm:</strong> Onay sonrası ürününüz <strong>ücretsiz olarak yeniden gönderilir</strong> veya ödemeniz iade edilir.</li>
</ol>

<h2>Önemli Bilgiler</h2>
<ul>
<li>Ürünlerimiz gıda maddesi olduğundan, <strong>açılmış ve kullanılmış</strong> ürünlerde cayma hakkı bulunmamaktadır (6502 sayılı Tüketici Kanunu, Madde 15/ğ).</li>
<li>İade kargo ücreti hasarlı ve hatalı ürünlerde <strong>tarafımıza aittir</strong>.</li>
<li>İade ödemeleri, onaydan sonra <strong>3-5 iş günü</strong> içinde aynı ödeme yöntemiyle gerçekleştirilir.</li>
</ul>

<h2>Bize Ulaşın</h2>
<p>İade ve değişim talepleriniz için aşağıdaki kanallardan bize ulaşabilirsiniz:</p>
<ul>
<li><strong>E-posta:</strong> info@orhanbabaninciftligi.com</li>
<li><strong>WhatsApp:</strong> 0505 942 41 24</li>
</ul>
<p>Ekibimiz hafta içi <strong>09:00 - 18:00</strong> saatleri arasında taleplerinize en hızlı şekilde dönüş yapmaktadır.</p>
HTML;
    }

    private function privacyContent(): string
    {
        return <<<'HTML'
<h2>Gizlilik Politikası</h2>
<p>Orhan Baba'nın Çiftliği olarak kişisel verilerinizin korunmasına büyük önem veriyoruz. Bu politika, hangi verileri topladığımızı, nasıl kullandığımızı ve haklarınızı açıklamaktadır.</p>

<h2>Toplanan Veriler</h2>
<p>Hizmetlerimizi sunabilmek için aşağıdaki kişisel verileri toplayabiliriz:</p>
<ul>
<li><strong>Kimlik bilgileri:</strong> Ad, soyad</li>
<li><strong>İletişim bilgileri:</strong> E-posta adresi, telefon numarası</li>
<li><strong>Adres bilgileri:</strong> Teslimat ve fatura adresi</li>
<li><strong>Sipariş bilgileri:</strong> Sipariş geçmişi, ödeme bilgileri</li>
<li><strong>Teknik veriler:</strong> IP adresi, tarayıcı türü, ziyaret edilen sayfalar</li>
</ul>

<h2>Verilerin Kullanım Amaçları</h2>
<p>Toplanan kişisel veriler yalnızca aşağıdaki amaçlarla kullanılmaktadır:</p>
<ul>
<li>Siparişlerinizin hazırlanması ve teslimatı</li>
<li>Müşteri hizmetleri ve destek sağlanması</li>
<li>Yasal yükümlülüklerin yerine getirilmesi</li>
<li>Hizmet kalitemizin iyileştirilmesi</li>
<li>İzniniz dahilinde kampanya ve duyuru bildirimleri</li>
</ul>

<h2>Verilerin Paylaşımı</h2>
<p>Kişisel verileriniz <strong>üçüncü taraflarla kesinlikle paylaşılmaz</strong>. Yalnızca aşağıdaki durumlarda sınırlı paylaşım yapılır:</p>
<ul>
<li><strong>Kargo firmaları:</strong> Teslimat için gerekli ad, adres ve telefon bilgisi</li>
<li><strong>Ödeme altyapısı:</strong> Güvenli ödeme işlemi için (kart bilgileri tarafımızca saklanmaz)</li>
<li><strong>Yasal zorunluluklar:</strong> Mahkeme kararı veya resmi makam talebi halinde</li>
</ul>

<h2>Çerez Politikası</h2>
<p>Sitemiz, kullanıcı deneyimini iyileştirmek amacıyla çerezler (cookies) kullanmaktadır:</p>
<ul>
<li><strong>Zorunlu çerezler:</strong> Sitenin temel işlevleri için gereklidir (oturum, sepet).</li>
<li><strong>Analitik çerezler:</strong> Ziyaretçi istatistiklerini anlamamıza yardımcı olur.</li>
</ul>
<p>Tarayıcı ayarlarınızdan çerezleri devre dışı bırakabilirsiniz; ancak bu durumda bazı site özellikleri düzgün çalışmayabilir.</p>

<h2>Veri Güvenliği</h2>
<p>Kişisel verilerinizin güvenliğini sağlamak için aşağıdaki önlemleri alıyoruz:</p>
<ul>
<li>SSL/TLS şifreleme ile güvenli veri iletimi</li>
<li>Güçlü şifreleme algoritmaları ile parola koruması</li>
<li>Düzenli güvenlik güncellemeleri ve denetimleri</li>
<li>Yetkisiz erişime karşı sunucu güvenlik duvarları</li>
</ul>

<h2>KVKK Kapsamındaki Haklarınız</h2>
<p>6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında aşağıdaki haklara sahipsiniz:</p>
<ul>
<li>Kişisel verilerinizin işlenip işlenmediğini öğrenme</li>
<li>İşlenen verileriniz hakkında bilgi talep etme</li>
<li>Verilerin eksik veya yanlış işlenmişse düzeltilmesini isteme</li>
<li>Verilerin silinmesini veya yok edilmesini isteme</li>
<li>Verilerin üçüncü kişilere aktarılıp aktarılmadığını öğrenme</li>
<li>İşlenen verilerin aleyhine bir sonuç çıkması halinde itiraz etme</li>
</ul>
<p>Haklarınızı kullanmak için <strong>info@orhanbabaninciftligi.com</strong> adresine başvurabilirsiniz. Başvurunuz en geç <strong>30 gün</strong> içinde yanıtlanacaktır.</p>
HTML;
    }

    private function termsContent(): string
    {
        return <<<'HTML'
<h2>Genel Bilgiler</h2>
<p>Bu web sitesi (<strong>orhanbabaninciftligi.com</strong>) Orhan Baba'nın Çiftliği tarafından işletilmektedir. Siteyi kullanarak aşağıdaki koşulları kabul etmiş sayılırsınız. Lütfen bu koşulları dikkatlice okuyunuz.</p>

<h2>Üyelik ve Hesap Güvenliği</h2>
<ul>
<li>Üyelik oluşturmak için <strong>18 yaşından büyük</strong> olmanız gerekmektedir.</li>
<li>Kayıt sırasında verdiğiniz bilgilerin doğru ve güncel olması sizin sorumluluğunuzdadır.</li>
<li>Hesap şifrenizin gizliliğinden ve hesabınız üzerinden gerçekleştirilen tüm işlemlerden <strong>siz sorumlusunuz</strong>.</li>
<li>Hesabınızda yetkisiz bir kullanım fark ederseniz derhal bizimle iletişime geçin.</li>
</ul>

<h2>Sipariş ve Ödeme</h2>
<ul>
<li>Sitemizdeki ürün fiyatları <strong>KDV dahil</strong> olarak gösterilmektedir.</li>
<li>Fiyatlar önceden haber verilmeksizin değiştirilebilir; ancak sipariş onaylandıktan sonra fiyat değişikliği uygulanmaz.</li>
<li>Sipariş verdikten sonra tarafınıza <strong>sipariş onay e-postası</strong> gönderilir.</li>
<li>Stok durumuna göre siparişinizin bir kısmı veya tamamı iptal edilebilir; bu durumda ödemeniz eksiksiz iade edilir.</li>
<li>Ödeme bilgileriniz SSL şifreleme ile korunur ve <strong>kart bilgileri sistemimizde saklanmaz</strong>.</li>
</ul>

<h2>Ürün Bilgileri</h2>
<ul>
<li>Sitemizdeki ürün görselleri temsilidir; doğal ürünler olması sebebiyle renk, boyut ve gramajda küçük farklılıklar olabilir.</li>
<li>Ürün açıklamalarında belirtilen bilgiler mümkün olduğunca doğru tutulmaya çalışılır; ancak yazım hataları için sorumluluk kabul edilmez.</li>
<li>Alerjen bilgileri ürün sayfalarında belirtilmektedir. Gıda alerjiniz varsa <strong>ürün açıklamalarını dikkatle okumanızı</strong> öneririz.</li>
</ul>

<h2>Fikri Mülkiyet Hakları</h2>
<p>Bu sitedeki tüm içerikler (yazılar, görseller, logolar, tasarım, yazılım) Orhan Baba'nın Çiftliği'ne aittir ve telif hakkı ile korunmaktadır.</p>
<ul>
<li>İçeriklerin izinsiz kopyalanması, çoğaltılması veya dağıtılması <strong>yasaktır</strong>.</li>
<li>Kişisel ve ticari olmayan kullanım için kaynak belirterek alıntı yapılabilir.</li>
</ul>

<h2>Sorumluluk Sınırlaması</h2>
<ul>
<li>Teknik arızalar, bakım çalışmaları veya mücbir sebepler nedeniyle sitede yaşanabilecek kesintilerden dolayı sorumluluk kabul edilmez.</li>
<li>Sitemizdeki harici bağlantıların (üçüncü taraf siteler) içeriklerinden sorumlu değiliz.</li>
<li>Doğal afet, salgın, yasal düzenleme gibi mücbir sebep hallerinde teslimat süreleri uzayabilir.</li>
</ul>

<h2>Değişiklikler</h2>
<p>Bu kullanım koşullarını önceden bildirim yapmaksızın güncelleme hakkımız saklıdır. Güncellemeler sitede yayınlandığı tarihte yürürlüğe girer. Siteyi kullanmaya devam etmeniz, güncellenmiş koşulları kabul ettiğiniz anlamına gelir.</p>

<h2>İletişim</h2>
<p>Bu kullanım koşullarıyla ilgili sorularınız için bizimle iletişime geçebilirsiniz:</p>
<ul>
<li><strong>E-posta:</strong> info@orhanbabaninciftligi.com</li>
<li><strong>WhatsApp:</strong> 0505 942 41 24</li>
</ul>
HTML;
    }
}
