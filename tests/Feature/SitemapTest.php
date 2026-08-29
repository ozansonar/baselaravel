<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Services\LanguageService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sitemap is the only place that describes the whole site at once, so it
 * has to be complete in every language and honest about what is translated.
 *
 * The rule search engines apply: a set of hreflang links is only trusted when
 * every URL in it points back at all the others, itself included. A one-sided
 * or invented alternate gets the entire group ignored, which is why the tests
 * check reciprocity rather than mere presence.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
        app(SitemapService::class)->clearCache();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function urls(): array
    {
        app(SitemapService::class)->clearCache();

        return app(SitemapService::class)->generateUrls();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entry(string $loc): ?array
    {
        foreach ($this->urls() as $url) {
            if ($url['loc'] === $loc) {
                return $url;
            }
        }

        return null;
    }

    private function translatedPagePair(): Page
    {
        $turkish = Page::create([
            'title'   => 'Hakkımızda',
            'slug'    => 'hakkimizda',
            'content' => '<p>Türkçe</p>',
            'status'  => 'published',
        ]);

        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'About Us',
            'slug'          => 'about-us',
            'content'       => '<p>English</p>',
            'status'        => 'published',
        ]);

        return $turkish;
    }

    public function test_the_sitemap_is_served_as_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_the_xml_is_well_formed(): void
    {
        $this->translatedPagePair();

        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent() ?: '');

        $this->assertNotFalse($xml, 'Sitemap geçerli XML değil');
        $this->assertGreaterThan(0, $xml->count());
    }

    public function test_every_language_gets_its_own_static_pages(): void
    {
        $locations = array_column($this->urls(), 'loc');

        foreach (['tr', 'en'] as $locale) {
            foreach (['home', 'blog.index', 'gallery', 'contact', 'faq'] as $name) {
                $this->assertContains(
                    route($name, ['locale' => $locale]),
                    $locations,
                    "{$name} ({$locale}) site haritasında yok",
                );
            }
        }
    }

    public function test_a_translated_page_lists_both_languages_on_both_urls(): void
    {
        $this->translatedPagePair();

        $turkishUrl = route('pages.show', ['locale' => 'tr', 'slug' => 'hakkimizda']);
        $englishUrl = route('pages.show', ['locale' => 'en', 'slug' => 'about-us']);

        $expected = ['tr' => $turkishUrl, 'en' => $englishUrl];

        $this->assertSame($expected, $this->entry($turkishUrl)['alternates'] ?? null);
        $this->assertSame($expected, $this->entry($englishUrl)['alternates'] ?? null);
    }

    /**
     * x-default names the version to serve a visitor whose language the site
     * does not publish, which is the site's own default language.
     */
    public function test_the_default_language_is_advertised_as_x_default(): void
    {
        $this->translatedPagePair();

        $englishUrl = route('pages.show', ['locale' => 'en', 'slug' => 'about-us']);

        $this->assertSame(
            route('pages.show', ['locale' => 'tr', 'slug' => 'hakkimizda']),
            $this->entry($englishUrl)['x_default'] ?? null,
        );
    }

    public function test_content_in_a_single_language_claims_no_translation(): void
    {
        Page::create([
            'title'   => 'Yalnızca Türkçe',
            'slug'    => 'yalnizca-turkce',
            'content' => '<p>Metin</p>',
            'status'  => 'published',
        ]);

        $entry = $this->entry(route('pages.show', ['locale' => 'tr', 'slug' => 'yalnizca-turkce']));

        $this->assertNotNull($entry);
        $this->assertSame([], $entry['alternates']);
        $this->assertNull($entry['x_default']);
    }

    /**
     * Slug and category both differ per language, so the alternate cannot be
     * built by swapping the prefix — it has to come from the translated row.
     */
    public function test_a_translated_post_points_at_its_own_category_slug(): void
    {
        $turkishCategory = BlogCategory::factory()->create(['locale' => 'tr', 'slug' => 'duyurular']);
        $englishCategory = BlogCategory::factory()->create([
            'locale'        => 'en',
            'lang_group_id' => $turkishCategory->lang_group_id,
            'slug'          => 'announcements',
        ]);

        $turkishPost = BlogPost::factory()->create([
            'locale'           => 'tr',
            'blog_category_id' => $turkishCategory->id,
            'slug'             => 'turkce-yazi',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
        ]);

        BlogPost::factory()->create([
            'locale'           => 'en',
            'lang_group_id'    => $turkishPost->lang_group_id,
            'blog_category_id' => $englishCategory->id,
            'slug'             => 'english-post',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
        ]);

        $turkishUrl = route('blog.show', ['locale' => 'tr', 'categorySlug' => 'duyurular', 'slug' => 'turkce-yazi']);
        $englishUrl = route('blog.show', ['locale' => 'en', 'categorySlug' => 'announcements', 'slug' => 'english-post']);

        $entry = $this->entry($turkishUrl);

        $this->assertNotNull($entry, 'Türkçe yazı site haritasında yok');
        $this->assertSame(['tr' => $turkishUrl, 'en' => $englishUrl], $entry['alternates']);
        $this->assertNotNull($this->entry($englishUrl), 'İngilizce yazı site haritasında yok');
    }

    public function test_unpublished_content_stays_out(): void
    {
        Page::create([
            'title'   => 'Taslak Sayfa',
            'slug'    => 'taslak-sayfa',
            'content' => '<p>Metin</p>',
            'status'  => 'draft',
        ]);

        $locations = array_column($this->urls(), 'loc');

        $this->assertNotContains(route('pages.show', ['locale' => 'tr', 'slug' => 'taslak-sayfa']), $locations);
    }

    public function test_gallery_photos_are_listed_as_images(): void
    {
        $category = GalleryCategory::factory()->create(['locale' => 'tr']);

        GalleryItem::factory()->create([
            'locale'              => 'tr',
            'gallery_category_id' => $category->id,
            'title'               => 'Türkçe Kare',
            'image'               => 'gallery/tr.webp',
        ]);

        $entry = $this->entry(route('gallery', ['locale' => 'tr']));

        $this->assertNotNull($entry);
        $this->assertSame(
            [['loc' => url('/uploads/gallery/tr.webp'), 'title' => 'Türkçe Kare']],
            $entry['images'],
        );
    }

    /**
     * A video has no still image to offer, so it is not an image entry.
     */
    public function test_gallery_videos_are_not_listed_as_images(): void
    {
        $category = GalleryCategory::factory()->create(['locale' => 'tr']);

        GalleryItem::factory()->video()->create([
            'locale'              => 'tr',
            'gallery_category_id' => $category->id,
            'title'               => 'Tanıtım Videosu',
        ]);

        $this->assertSame([], $this->entry(route('gallery', ['locale' => 'tr']))['images'] ?? null);
    }

    /**
     * Every hreflang set must be closed: each URL it names has to carry the
     * same set back.
     */
    public function test_alternate_links_are_reciprocal(): void
    {
        $this->translatedPagePair();

        $urls = $this->urls();
        $byLocation = [];

        foreach ($urls as $url) {
            $byLocation[$url['loc']] = $url['alternates'];
        }

        foreach ($urls as $url) {
            foreach ($url['alternates'] as $locale => $alternate) {
                $this->assertArrayHasKey($alternate, $byLocation, "Alternatif {$alternate} site haritasında yok");
                $this->assertSame(
                    $url['alternates'],
                    $byLocation[$alternate],
                    "{$url['loc']} ile {$alternate} arasındaki hreflang bağı tek yönlü",
                );
            }
        }
    }

    /**
     * Every address it publishes has to be one the site actually serves.
     */
    public function test_the_addresses_it_publishes_are_live(): void
    {
        $this->translatedPagePair();

        foreach ($this->urls() as $url) {
            $this->get($url['loc'])->assertOk();
        }
    }

    // ── Galeri: süzülmüş kategori adresleri ──

    /**
     * @return array{tr: GalleryCategory, en: GalleryCategory}
     */
    private function galleryCategoryPair(string $trSlug = 'sinama-ofis', string $enSlug = 'sinama-office'): array
    {
        $tr = GalleryCategory::create([
            'locale' => 'tr', 'name' => 'Sinama Ofis', 'slug' => $trSlug, 'is_active' => true,
        ]);

        $en = GalleryCategory::create([
            'locale' => 'en', 'lang_group_id' => $tr->lang_group_id,
            'name' => 'Sinama Office', 'slug' => $enSlug, 'is_active' => true,
        ]);

        return ['tr' => $tr, 'en' => $en];
    }

    private function photo(string $locale, string $title, GalleryCategory $category, int $sort = 0): GalleryItem
    {
        return GalleryItem::create([
            'locale'              => $locale,
            'gallery_category_id' => $category->id,
            'title'               => $title,
            'type'                => 'photo',
            'image'               => 'gallery/' . \Illuminate\Support\Str::slug($title) . '.webp',
            'is_active'           => true,
            'sort_order'          => $sort,
        ]);
    }

    /**
     * Kategori süzgeci ekranda gerçek bağlantılar üretiyor: kendi başlığı,
     * kendi H1'i ve kendini gösteren canonical'ı olan sayfalar. Sitemap
     * dışında kalsalardı arama motoru onlara ancak gezinerek ulaşırdı.
     */
    public function test_a_gallery_category_gets_its_own_address(): void
    {
        $pair = $this->galleryCategoryPair();
        $this->photo('tr', 'Ofis karesi', $pair['tr']);

        $this->assertNotNull(
            $this->entry(route('gallery', ['locale' => 'tr', 'kategori' => 'sinama-ofis'])),
            'Kategori süzgecinin adresi sitemap\'te yok',
        );
    }

    /** İki dildeki kategori aynı kategorinin iki sürümü; birbirlerini göstermeli. */
    public function test_the_two_language_versions_of_a_category_point_at_each_other(): void
    {
        $pair = $this->galleryCategoryPair();
        $this->photo('tr', 'Ofis karesi', $pair['tr']);
        $this->photo('en', 'Office frame', $pair['en']);

        $trUrl = route('gallery', ['locale' => 'tr', 'kategori' => 'sinama-ofis']);
        $enUrl = route('gallery', ['locale' => 'en', 'kategori' => 'sinama-office']);

        $tr = $this->entry($trUrl);
        $en = $this->entry($enUrl);

        $this->assertNotNull($tr);
        $this->assertNotNull($en);
        $this->assertSame($enUrl, $tr['alternates']['en'] ?? null);
        $this->assertSame($trUrl, $en['alternates']['tr'] ?? null);
    }

    /**
     * İçi boş kategori sitemap'e girmemeli: arama motoruna boş bir sayfa
     * göstermek soft-404 sayılıyor.
     */
    public function test_an_empty_category_is_not_advertised(): void
    {
        $this->galleryCategoryPair('bos-kategori', 'empty-category');

        $this->assertNull(
            $this->entry(route('gallery', ['locale' => 'tr', 'kategori' => 'bos-kategori'])),
            'Karesi olmayan kategori sitemap\'e girdi',
        );
    }

    public function test_a_passive_category_is_not_advertised(): void
    {
        $pair = $this->galleryCategoryPair('pasif-kategori', 'passive-category');
        $this->photo('tr', 'Bir kare', $pair['tr']);
        $pair['tr']->update(['is_active' => false]);
        $pair['en']->update(['is_active' => false]);

        $this->assertNull($this->entry(route('gallery', ['locale' => 'tr', 'kategori' => 'pasif-kategori'])));
    }

    /**
     * Galeri sayfası sayfalanıyor: /galeri adresinde yalnız ilk on kare var.
     * Sitemap bütün galeriyi o adresin altında ilan etseydi, işaret ettiği
     * sayfada olmayan görselleri duyururdu.
     */
    public function test_the_gallery_url_only_claims_the_images_on_its_first_page(): void
    {
        $pair = $this->galleryCategoryPair();

        foreach (range(1, 25) as $i) {
            $this->photo('tr', "Kare {$i}", $pair['tr'], $i);
        }

        $entry = $this->entry(route('gallery', ['locale' => 'tr']));

        $this->assertNotNull($entry);
        $this->assertCount(\App\Services\GalleryService::FRONT_PER_PAGE, $entry['images']);
    }

    /** Kategori adresi de yalnız kendi karelerini duyurmalı. */
    public function test_a_category_url_claims_only_its_own_images(): void
    {
        $ofis = $this->galleryCategoryPair('sinama-ofis', 'sinama-office');
        $ekip = $this->galleryCategoryPair('sinama-ekip', 'sinama-team');

        $this->photo('tr', 'Ofis karesi', $ofis['tr']);
        $this->photo('tr', 'Ekip karesi', $ekip['tr']);

        $entry = $this->entry(route('gallery', ['locale' => 'tr', 'kategori' => 'sinama-ofis']));

        $this->assertNotNull($entry);
        $this->assertCount(1, $entry['images']);
        $this->assertSame('Ofis karesi', $entry['images'][0]['title']);
    }

    /**
     * Kategori kimliği değil çeviri grubu taşınıyor. Kimliğe bakılsaydı,
     * İngilizceye çevrilmemiş olduğu için varsayılan dilden düşen kare —ki
     * sayfa onu gösteriyor— İngilizce kategori adresinin dışında kalırdı.
     */
    public function test_a_category_url_covers_the_frames_that_fell_back(): void
    {
        $pair = $this->galleryCategoryPair();
        $this->photo('tr', 'Cevrilmemis kare', $pair['tr']);

        $entry = $this->entry(route('gallery', ['locale' => 'en', 'kategori' => 'sinama-office']));

        $this->assertNotNull($entry, 'İngilizce kategori adresi hiç üretilmedi');
        $this->assertSame(['Cevrilmemis kare'], array_column($entry['images'], 'title'));
    }

    // ── x-default ──

    /**
     * Kök adres ziyaretçinin dilini kendisi seçip yönlendiriyor; Google'ın
     * x-default'tan beklediği tam olarak bu. Öteki sayfaların böyle bir adresi
     * yok, onlarda varsayılan dil sürümü x-default kalıyor.
     */
    public function test_the_home_page_hands_x_default_to_the_language_neutral_root(): void
    {
        $entry = $this->entry(route('home', ['locale' => 'tr']));

        $this->assertNotNull($entry);
        $this->assertSame(route('root'), $entry['x_default']);
    }

    public function test_an_inner_page_still_hands_x_default_to_the_default_language(): void
    {
        $this->translatedPagePair();

        $entry = $this->entry(route('pages.show', ['locale' => 'en', 'slug' => 'about-us']));

        $this->assertNotNull($entry);
        $this->assertSame(route('pages.show', ['locale' => 'tr', 'slug' => 'hakkimizda']), $entry['x_default']);
    }

    // ── Kapsam ──

    /**
     * Ziyaretçinin gezinebildiği her genel sayfa haritada olmalı. Modül
     * eklendikçe unutulan bir rota sessizce dizin dışında kalıyor.
     */
    public function test_every_public_section_of_the_site_is_on_the_map(): void
    {
        $this->translatedPagePair();

        $category = BlogCategory::create(['locale' => 'tr', 'name' => 'Duyurular', 'slug' => 'duyurular', 'is_active' => true]);

        BlogPost::create([
            'locale' => 'tr', 'blog_category_id' => $category->id,
            'title' => 'Yazı', 'slug' => 'yazi', 'body' => 'Gövde',
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $locs = array_column($this->urls(), 'loc');

        foreach ([
            route('home', ['locale' => 'tr']),
            route('blog.index', ['locale' => 'tr']),
            route('gallery', ['locale' => 'tr']),
            route('contact', ['locale' => 'tr']),
            route('faq', ['locale' => 'tr']),
            route('pages.show', ['locale' => 'tr', 'slug' => 'hakkimizda']),
            route('blog.category', ['locale' => 'tr', 'categorySlug' => 'duyurular']),
            route('blog.show', ['locale' => 'tr', 'categorySlug' => 'duyurular', 'slug' => 'yazi']),
        ] as $expected) {
            $this->assertContains($expected, $locs, "Haritada yok: {$expected}");
        }
    }

    /** Sorgu dizisi taşıyan adresler XML'de kaçırılmış olmalı. */
    public function test_filtered_addresses_are_escaped_in_the_xml(): void
    {
        $pair = $this->galleryCategoryPair();
        $this->photo('tr', 'Ofis karesi', $pair['tr']);

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'Süzülmüş adres eklenince XML bozuldu');
        $this->assertStringContainsString('kategori=sinama-ofis', $xml);
    }
}
