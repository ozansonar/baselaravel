<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Content is stored one row per language, translations of the same item sharing
 * a lang_group_id.
 *
 * Because a translation is a whole row, every column is per language — the
 * image included, which is what makes artwork with text on it work.
 */
class ContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
    }

    private function turkishPage(string $title = 'İletişim'): Page
    {
        return Page::create([
            'title'   => $title,
            'content' => '<p>Türkçe içerik</p>',
            'status'  => 'published',
        ]);
    }

    public function test_a_new_row_gets_the_default_locale_and_its_own_group(): void
    {
        $page = $this->turkishPage();

        $this->assertSame('tr', $page->locale);
        $this->assertNotNull($page->lang_group_id);
    }

    public function test_two_rows_created_separately_do_not_share_a_group(): void
    {
        $first = $this->turkishPage('Birinci');
        $second = $this->turkishPage('İkinci');

        $this->assertNotSame($first->lang_group_id, $second->lang_group_id);
    }

    public function test_a_translation_joins_the_same_group(): void
    {
        $turkish = $this->turkishPage();

        $english = Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'Contact',
            'content'       => '<p>English content</p>',
            'status'        => 'published',
        ]);

        $this->assertSame($turkish->lang_group_id, $english->lang_group_id);
        $this->assertEqualsCanonicalizing(['tr', 'en'], $turkish->translatedLocales());
        $this->assertSame('Contact', $turkish->translation('en')?->title);
        $this->assertSame('İletişim', $english->translation('tr')?->title);
        $this->assertTrue($turkish->hasTranslation('en'));
    }

    public function test_missing_languages_are_reported(): void
    {
        $turkish = $this->turkishPage();

        $this->assertSame(['en'], $turkish->missingLanguages()->pluck('code')->all());

        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'Contact',
            'content'       => '<p>x</p>',
            'status'        => 'published',
        ]);

        $this->assertSame([], $turkish->missingLanguages()->pluck('code')->all());
    }

    /**
     * The old schema made slugs globally unique, which would have stopped two
     * languages from both using a natural slug.
     */
    public function test_the_same_slug_may_be_used_in_two_languages(): void
    {
        $turkish = Page::create([
            'title'   => 'Contact',
            'content' => '<p>tr</p>',
            'status'  => 'published',
        ]);

        $english = Page::create([
            'locale'  => 'en',
            'title'   => 'Contact',
            'content' => '<p>en</p>',
            'status'  => 'published',
        ]);

        $this->assertSame('contact', $turkish->slug);
        $this->assertSame('contact', $english->slug, 'Slug dile göre benzersiz değil');
    }

    public function test_a_duplicate_slug_within_one_language_is_made_unique(): void
    {
        $first = $this->turkishPage('Hakkımızda');
        $second = $this->turkishPage('Hakkımızda');

        $this->assertSame('hakkimizda', $first->slug);
        $this->assertSame('hakkimizda-1', $second->slug);
    }

    public function test_the_database_refuses_two_rows_of_one_group_in_the_same_language(): void
    {
        $turkish = $this->turkishPage();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Page::create([
            'locale'        => 'tr',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'İkinci Türkçe',
            'content'       => '<p>x</p>',
            'status'        => 'published',
        ]);
    }

    public function test_the_locale_scope_returns_only_that_language(): void
    {
        $turkish = $this->turkishPage();

        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'Contact',
            'content'       => '<p>en</p>',
            'status'        => 'published',
        ]);

        $this->assertSame(1, Page::locale('tr')->count());
        $this->assertSame(1, Page::locale('en')->count());
        $this->assertSame('Contact', Page::locale('en')->first()->title);
    }

    /**
     * Content that has not been translated yet still has to appear, in the
     * default language, rather than vanishing from the site.
     */
    public function test_the_fallback_scope_fills_gaps_with_the_default_language(): void
    {
        $translated = $this->turkishPage('Çevrilmiş');
        Page::create([
            'locale'        => 'en',
            'lang_group_id' => $translated->lang_group_id,
            'title'         => 'Translated',
            'content'       => '<p>en</p>',
            'status'        => 'published',
        ]);

        $this->turkishPage('Yalnızca Türkçe');

        $titles = Page::localeWithFallback('en')->pluck('title')->all();

        $this->assertContains('Translated', $titles, 'Çevirisi olan içerik İngilizce gelmedi');
        $this->assertContains('Yalnızca Türkçe', $titles, 'Çevirisi olmayan içerik kayboldu');
        $this->assertNotContains('Çevrilmiş', $titles, 'Çevirisi varken Türkçesi de geldi');
        $this->assertCount(2, $titles);
    }

    public function test_the_fallback_scope_is_a_plain_filter_for_the_default_language(): void
    {
        $this->turkishPage('Yalnızca Türkçe');

        $this->assertSame(1, Page::localeWithFallback('tr')->count());
    }

    /**
     * Every language row carries its own image column, so artwork containing
     * text can differ per language.
     */
    public function test_each_language_keeps_its_own_image(): void
    {
        $turkish = Page::create([
            'title'   => 'Kampanya',
            'content' => '<p>tr</p>',
            'image'   => 'pages/kampanya-tr.webp',
            'status'  => 'published',
        ]);

        $english = Page::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'title'         => 'Campaign',
            'content'       => '<p>en</p>',
            'image'         => 'pages/campaign-en.webp',
            'status'        => 'published',
        ]);

        $this->assertSame('pages/kampanya-tr.webp', $turkish->fresh()->image);
        $this->assertSame('pages/campaign-en.webp', $english->fresh()->image);
        $this->assertNotSame($turkish->image, $english->image);
    }

    public function test_translations_work_for_a_model_without_a_slug(): void
    {
        $turkish = Faq::create([
            'question'  => 'Nasıl üye olurum?',
            'answer'    => 'Kayıt sayfasından.',
            'is_active' => true,
        ]);

        $english = Faq::create([
            'locale'        => 'en',
            'lang_group_id' => $turkish->lang_group_id,
            'question'      => 'How do I sign up?',
            'answer'        => 'From the register page.',
            'is_active'     => true,
        ]);

        $this->assertSame($turkish->lang_group_id, $english->lang_group_id);
        $this->assertSame('How do I sign up?', $turkish->translation('en')?->question);
    }

    public function test_existing_rows_were_backfilled_by_the_migration(): void
    {
        // Seeded content predates the multilingual columns; the migration has to
        // have given every row a locale and a group.
        $this->seed(\Database\Seeders\PageSeeder::class);

        $missing = DB::table('pages')
            ->whereNull('lang_group_id')
            ->orWhereNull('locale')
            ->count();

        $this->assertSame(0, $missing);
    }
}
