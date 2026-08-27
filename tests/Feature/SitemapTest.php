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
}
