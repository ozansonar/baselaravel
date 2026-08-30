<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GalleryType;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Services\GalleryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ön yüz galerisi: kategori süzgeci, sayfa başına öğe sayısı, tembel yükleme.
 *
 * Galeri eskiden bütün kayıtları tek seferde belleğe çekip ekrana basıyordu:
 * kategori seçilemiyordu, iki yüz fotoğraflı bir sitede iki yüz kayıt okunup
 * hepsi tek sayfaya diziliyordu.
 */
final class GalleryFrontFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
    }

    private function category(string $locale, string $name, string $slug, ?string $group = null): GalleryCategory
    {
        return GalleryCategory::create([
            'locale'        => $locale,
            'lang_group_id' => $group ?? (string) Str::uuid(),
            'name'          => $name,
            'slug'          => $slug,
            'is_active'     => true,
            'sort_order'    => 0,
        ]);
    }

    private function item(string $locale, string $title, ?int $categoryId, GalleryType $type = GalleryType::Photo, int $sort = 0): GalleryItem
    {
        return GalleryItem::create([
            'locale'              => $locale,
            'lang_group_id'       => (string) Str::uuid(),
            'gallery_category_id' => $categoryId,
            'title'               => $title,
            'type'                => $type,
            'image'               => $type === GalleryType::Photo ? 'gallery/x.webp' : null,
            'video_url'           => $type === GalleryType::Video ? 'https://youtu.be/abc' : null,
            'is_active'           => true,
            'sort_order'          => $sort,
        ]);
    }

    public function test_a_page_holds_exactly_ten_items(): void
    {
        $category = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');

        foreach (range(1, 23) as $i) {
            $this->item('tr', "Kare {$i}", $category->id, sort: $i);
        }

        $ilk = $this->get(route('gallery', ['locale' => 'tr']))->assertOk();

        $this->assertSame(10, substr_count((string) $ilk->getContent(), 'gallery-item__img'));

        // Son sayfada kalan üç kare: bölünme gerçekten 10+10+3 olmalı.
        $son = $this->get(route('gallery', ['locale' => 'tr', 'page' => 3]))->assertOk();

        $this->assertSame(3, substr_count((string) $son->getContent(), 'gallery-item__img'));
    }

    public function test_ten_is_what_the_service_and_the_screen_agree_on(): void
    {
        $this->assertSame(10, GalleryService::FRONT_PER_PAGE);
    }

    public function test_choosing_a_category_hides_the_other_ones(): void
    {
        $ofis = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');
        $ekip = $this->category('tr', 'Sinama Ekip', 'sinama-ekip');

        $this->item('tr', 'Ofis karesi', $ofis->id);
        $this->item('tr', 'Ekip karesi', $ekip->id);

        $html = (string) $this->get(route('gallery', ['locale' => 'tr', 'kategori' => 'sinama-ofis']))->assertOk()->getContent();

        $this->assertStringContainsString('Ofis karesi', $html);
        $this->assertStringNotContainsString('Ekip karesi', $html);
    }

    public function test_showing_everything_is_still_an_option(): void
    {
        $ofis = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');
        $ekip = $this->category('tr', 'Sinama Ekip', 'sinama-ekip');

        $this->item('tr', 'Ofis karesi', $ofis->id);
        $this->item('tr', 'Ekip karesi', $ekip->id);

        $html = (string) $this->get(route('gallery', ['locale' => 'tr']))->assertOk()->getContent();

        $this->assertStringContainsString('Ofis karesi', $html);
        $this->assertStringContainsString('Ekip karesi', $html);
    }

    /**
     * Kategorinin her dilde ayrı satırı var. Süzgeç kimliğe göre çalışsaydı
     * ziyaretçinin dilindeki satır seçilir, o dile çevrilmediği için
     * varsayılan dilden düşen öğeler —ki sayfa onları gösteriyor— kategorinin
     * dışında kalır, kategori boş görünürdü.
     */
    public function test_a_category_finds_the_items_that_fell_back_to_the_default_language(): void
    {
        $grup = (string) Str::uuid();
        $tr = $this->category('tr', 'Sinama Ofis', 'sinama-ofis', $grup);
        $en = $this->category('en', 'Sinama Office', 'sinama-office', $grup);

        // Yalnız Türkçesi olan bir kare: İngilizce sayfa bunu geri düşerek gösterir.
        $this->item('tr', 'Ceviri yok karesi', $tr->id);
        $this->item('en', 'English frame', $en->id);

        $html = (string) $this->get(route('gallery', ['locale' => 'en', 'kategori' => 'sinama-office']))->assertOk()->getContent();

        $this->assertStringContainsString('English frame', $html);
        $this->assertStringContainsString('Ceviri yok karesi', $html, 'Çevrilmemiş kare kategorinin dışında kaldı');
    }

    public function test_the_type_filter_separates_photos_from_videos(): void
    {
        $category = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');

        $this->item('tr', 'Bir fotograf', $category->id);
        $this->item('tr', 'Bir video', $category->id, GalleryType::Video);

        $foto = (string) $this->get(route('gallery', ['locale' => 'tr', 'tur' => 'photo']))->assertOk()->getContent();
        $video = (string) $this->get(route('gallery', ['locale' => 'tr', 'tur' => 'video']))->assertOk()->getContent();

        $this->assertStringContainsString('Bir fotograf', $foto);
        $this->assertStringNotContainsString('Bir video', $foto);

        $this->assertStringContainsString('Bir video', $video);
        $this->assertStringNotContainsString('Bir fotograf', $video);
    }

    /**
     * Uydurulmuş bir slug boş galeri değil, bütün galeri vermeli — ve o adres
     * kendini göstermemeli, yoksa aynı içerik sonsuz sayıda adresle dizine
     * girer.
     */
    public function test_an_unknown_category_falls_back_to_everything_without_claiming_its_own_address(): void
    {
        $category = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');
        $this->item('tr', 'Ofis karesi', $category->id);

        $html = (string) $this->get(route('gallery', ['locale' => 'tr', 'kategori' => 'boyle-bir-sey-yok']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Ofis karesi', $html);
        $this->assertStringNotContainsString('boyle-bir-sey-yok', $html);
    }

    /** Süzgeç sayfalar arasında taşınmalı; ikinci sayfada seçim kaybolmamalı. */
    public function test_the_filter_survives_the_page_links(): void
    {
        $ofis = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');
        $ekip = $this->category('tr', 'Sinama Ekip', 'sinama-ekip');

        foreach (range(1, 15) as $i) {
            $this->item('tr', "Ofis {$i}", $ofis->id, sort: $i);
        }

        $this->item('tr', 'Ekip karesi', $ekip->id, sort: 99);

        $html = (string) $this->get(route('gallery', ['locale' => 'tr', 'kategori' => 'sinama-ofis']))->assertOk()->getContent();

        $this->assertStringContainsString('kategori=sinama-ofis', $html, 'Sayfa bağlantıları süzgeci taşımıyor');

        $ikinci = (string) $this->get(route('gallery', ['locale' => 'tr', 'kategori' => 'sinama-ofis', 'page' => 2]))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('Ekip karesi', $ikinci);
    }

    /**
     * Görseller görünür alana girene kadar inmemeli. Açılışta zaten ekranda
     * olan ilk iki kare bunun dışında: onları geciktirmek sayfanın en büyük
     * görselinin boyanmasını geciktirmek olurdu.
     *
     * srcset burada ölçülmüyor: UploadService varyantı ancak dosya diskte
     * varsa listeye koyuyor, testte yüklenmiş bir dosya yok. Çok çözünürlüklü
     * çıktı tarayıcıda doğrulandı.
     */
    public function test_images_below_the_fold_are_not_downloaded_up_front(): void
    {
        $category = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');

        foreach (range(1, 10) as $i) {
            $this->item('tr', "Kare {$i}", $category->id, sort: $i);
        }

        $html = (string) $this->get(route('gallery', ['locale' => 'tr']))->assertOk()->getContent();

        // Sayım galeri karelerine daraltılıyor: sayfadaki başka görseller de
        // (logo, büyütme penceresi) kuralı taşıyor ve toplam sayı onlarla
        // birlikte değişiyor — ölçülmek istenen ızgaranın kendisi.
        preg_match_all('/<img[^>]*gallery-item__img[^>]*>/s', $html, $kareler);

        $this->assertCount(10, $kareler[0]);
        $this->assertSame(8, substr_count(implode('', $kareler[0]), 'loading="lazy"'));
        $this->assertSame(2, substr_count(implode('', $kareler[0]), 'loading="eager"'));
    }

    /** İkinci sayfada "açılışta ekranda" diye bir şey yok; hepsi tembel. */
    public function test_nothing_is_eager_on_a_later_page(): void
    {
        $category = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');

        foreach (range(1, 15) as $i) {
            $this->item('tr', "Kare {$i}", $category->id, sort: $i);
        }

        $html = (string) $this->get(route('gallery', ['locale' => 'tr', 'page' => 2]))->assertOk()->getContent();

        $this->assertStringNotContainsString('loading="eager"', $html);
    }

    /** Pasif ve silinmiş kareler ziyaretçiye görünmemeli. */
    public function test_inactive_and_deleted_items_stay_out(): void
    {
        $category = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');

        $this->item('tr', 'Gorunur kare', $category->id);
        $this->item('tr', 'Pasif kare', $category->id)->update(['is_active' => false]);
        $this->item('tr', 'Silinmis kare', $category->id)->delete();

        $html = (string) $this->get(route('gallery', ['locale' => 'tr']))->assertOk()->getContent();

        $this->assertStringContainsString('Gorunur kare', $html);
        $this->assertStringNotContainsString('Pasif kare', $html);
        $this->assertStringNotContainsString('Silinmis kare', $html);
    }

    /**
     * Sayfa yalnız göstereceği kadarını okumalı. Eskiden bütün galeri belleğe
     * çekiliyordu; bu testin ölçtüğü şey sayfanın kaç kayıt gösterdiği değil,
     * kaç kayıt taşıdığı.
     */
    public function test_the_page_only_loads_what_it_shows(): void
    {
        $category = $this->category('tr', 'Sinama Ofis', 'sinama-ofis');

        foreach (range(1, 40) as $i) {
            $this->item('tr', "Kare {$i}", $category->id, sort: $i);
        }

        $sayfa = app(GalleryService::class)->paginateActive();

        $this->assertCount(10, $sayfa->items(), 'Sayfa göstereceğinden fazla kayıt okudu');
        $this->assertSame(40, $sayfa->total());
    }
}
