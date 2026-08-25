<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Slider;
use App\Services\FaqService;
use App\Services\LanguageService;
use App\Services\SliderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public site serves content in the visitor's language, falling back to the
 * default for anything not translated yet.
 *
 * The cache is the trap here: without the language in the key the first
 * visitor's language would be handed to everyone until it expired.
 */
class FrontLocaleContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(LanguageService::class)->clearCache();
    }

    private function faqPair(): Faq
    {
        $turkish = Faq::create([
            'question'  => 'Nasıl üye olurum?',
            'answer'    => 'Kayıt sayfasından.',
            'is_active' => true,
        ]);

        Faq::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'question'      => 'How do I sign up?',
            'answer'        => 'From the register page.',
            'is_active'     => true,
        ]);

        return $turkish;
    }

    public function test_content_is_served_in_the_visitors_language(): void
    {
        $this->faqPair();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/sikca-sorulan-sorular')
            ->assertOk()
            ->assertSee('How do I sign up?', false)
            ->assertDontSee('Nasıl üye olurum?', false);
    }

    public function test_untranslated_content_still_appears_in_the_default_language(): void
    {
        $this->faqPair();

        Faq::create([
            'question'  => 'Yalnızca Türkçe soru',
            'answer'    => 'Cevap.',
            'is_active' => true,
        ]);

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/sikca-sorulan-sorular')
            ->assertOk()
            ->assertSee('How do I sign up?', false)
            ->assertSee('Yalnızca Türkçe soru', false);
    }

    /**
     * The bug this guards against: one language's cached list being handed to
     * every other language.
     */
    public function test_the_cache_does_not_leak_one_language_into_another(): void
    {
        $this->faqPair();

        $faqs = app(FaqService::class);

        app()->setLocale('tr');
        $turkish = $faqs->allActive()->pluck('question')->all();

        app()->setLocale('en');
        $english = $faqs->allActive()->pluck('question')->all();

        $this->assertSame(['Nasıl üye olurum?'], $turkish);
        $this->assertSame(['How do I sign up?'], $english, 'Türkçe cache İngilizceye sızdı');
    }

    /**
     * Cache invalidation lives in the services, so this goes through the same
     * path the admin screens use rather than writing the model directly.
     */
    public function test_saving_through_the_service_clears_the_cache_for_every_language(): void
    {
        $faqs = app(FaqService::class);

        app()->setLocale('en');
        $this->assertCount(0, $faqs->allActive());

        app()->setLocale('tr');
        $this->assertCount(0, $faqs->allActive());

        $faqs->create([
            'question'  => 'Yeni soru',
            'answer'    => 'Yeni cevap',
            'is_active' => true,
        ]);

        app()->setLocale('tr');
        $this->assertCount(1, $faqs->allActive(), 'Türkçe cache temizlenmedi');

        // The Turkish row is the fallback for English until it is translated.
        app()->setLocale('en');
        $this->assertCount(1, $faqs->allActive(), 'İngilizce cache temizlenmedi');
    }

    public function test_a_page_resolves_by_its_slug_in_the_current_language(): void
    {
        $turkish = Page::create([
            'title'   => 'Hakkımızda',
            'content' => '<p>Türkçe metin</p>',
            'status'  => 'published',
        ]);

        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'About Us',
            'slug'          => 'about-us',
            'content'       => '<p>English text</p>',
            'status'        => 'published',
        ]);

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/about-us')
            ->assertOk()
            ->assertSee('English text', false);

        $this->withHeaders(['Accept-Language' => 'tr'])
            ->get('/hakkimizda')
            ->assertOk()
            ->assertSee('Türkçe metin', false);
    }

    public function test_sliders_follow_the_visitors_language(): void
    {
        $turkish = Slider::create([
            'title'      => 'Türkçe Slider',
            'image'      => 'sliders/tr.webp',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        Slider::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'English Slider',
            'image'         => 'sliders/en.webp',
            'is_active'     => true,
            'sort_order'    => 0,
        ]);

        $sliders = app(SliderService::class);

        app()->setLocale('en');
        $this->assertSame('English Slider', $sliders->allActive()->first()?->title);

        app()->setLocale('tr');
        $this->assertSame('Türkçe Slider', $sliders->allActive()->first()?->title);
    }

    /**
     * Blade reads @section('x', null) as the block form and opens an output
     * buffer nothing ever closes. A page with neither a meta description nor an
     * excerpt used to leak one on every view.
     */
    public function test_rendering_a_page_leaves_no_output_buffer_open(): void
    {
        Page::create([
            'title'   => 'Açıklamasız Sayfa',
            'content' => '<p>Metin</p>',
            'status'  => 'published',
        ]);

        $before = ob_get_level();

        $this->get('/aciklamasiz-sayfa')->assertOk();

        $this->assertSame($before, ob_get_level(), 'Sayfa render edilirken çıktı tamponu açık kaldı');
    }

    /**
     * A slider's artwork usually carries text, so each language keeps its own.
     */
    public function test_each_language_serves_its_own_slider_image(): void
    {
        $turkish = Slider::create([
            'title'      => 'Kampanya',
            'image'      => 'sliders/kampanya-tr.webp',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        Slider::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'Campaign',
            'image'         => 'sliders/campaign-en.webp',
            'is_active'     => true,
            'sort_order'    => 0,
        ]);

        $sliders = app(SliderService::class);

        app()->setLocale('en');
        $this->assertSame('sliders/campaign-en.webp', $sliders->allActive()->first()?->image);

        app()->setLocale('tr');
        $this->assertSame('sliders/kampanya-tr.webp', $sliders->allActive()->first()?->image);
    }
}
