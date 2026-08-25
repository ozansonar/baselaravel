<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The page form carries one tab per language, and each tab saves its own row.
 *
 * Because a translation is a whole row, the image belongs to the language too —
 * which is the point when the artwork has text on it.
 */
class TranslatedPageFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seedAuthorization();
        app(LanguageService::class)->clearCache();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
        $this->actingAs($this->admin);
    }

    /**
     * @param array<string, array<string, mixed>> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $base = [
            'tr' => [
                'title'   => 'Hakkımızda',
                'content' => '<p>Türkçe içerik</p>',
                'status'  => 'published',
            ],
            'en' => [
                'title'   => 'About Us',
                'content' => '<p>English content</p>',
                'status'  => 'published',
            ],
        ];

        foreach ($overrides as $locale => $fields) {
            $base[$locale] = array_merge($base[$locale] ?? [], $fields);
        }

        return ['translations' => $base];
    }

    public function test_the_form_renders_a_tab_per_active_language(): void
    {
        $html = $this->get('/admin/pages/create')->getContent();

        $this->assertStringContainsString('name="translations[tr][title]"', $html);
        $this->assertStringContainsString('name="translations[en][title]"', $html);
        $this->assertStringContainsString('name="translations[tr][image]"', $html);
        $this->assertStringContainsString('name="translations[en][image]"', $html);

        // German is seeded but inactive, so it gets no tab.
        $this->assertStringNotContainsString('name="translations[de][title]"', $html);
    }

    public function test_a_newly_activated_language_gets_its_own_tab(): void
    {
        Language::where('code', 'de')->update(['is_active' => true]);
        app(LanguageService::class)->clearCache();

        $html = $this->get('/admin/pages/create')->getContent();

        $this->assertStringContainsString('name="translations[de][title]"', $html);
    }

    public function test_saving_creates_one_row_per_language_sharing_a_group(): void
    {
        $this->post('/admin/pages', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $turkish = Page::where('locale', 'tr')->where('title', 'Hakkımızda')->firstOrFail();
        $english = Page::where('locale', 'en')->where('title', 'About Us')->firstOrFail();

        $this->assertSame(
            $turkish->lang_group_id,
            $english->lang_group_id,
            'Diller ortak lang_group_id ile bağlanmadı',
        );

        $this->assertSame('hakkimizda', $turkish->slug);
        $this->assertSame('about-us', $english->slug);
    }

    /**
     * The point of the row-per-language layout: artwork containing text can
     * differ per language.
     */
    public function test_each_language_saves_its_own_image(): void
    {
        $this->post('/admin/pages', $this->payload([
            'tr' => ['image' => UploadedFile::fake()->image('kampanya-tr.jpg', 600, 400)],
            'en' => ['image' => UploadedFile::fake()->image('campaign-en.jpg', 600, 400)],
        ]))->assertSessionHasNoErrors();

        $turkish = Page::where('locale', 'tr')->firstOrFail();
        $english = Page::where('locale', 'en')->firstOrFail();

        $this->assertNotNull($turkish->image);
        $this->assertNotNull($english->image);
        $this->assertNotSame($turkish->image, $english->image, 'İki dil aynı görseli paylaşıyor');

        $this->assertFileExists(config('uploads.path') . '/' . $turkish->image);
        $this->assertFileExists(config('uploads.path') . '/' . $english->image);
    }

    /**
     * A translator should be able to fill the default language now and come
     * back for the rest later.
     */
    public function test_only_the_default_language_is_required(): void
    {
        $this->post('/admin/pages', ['translations' => [
            'tr' => ['title' => 'Yalnızca Türkçe', 'content' => '<p>tr</p>', 'status' => 'published'],
            'en' => ['title' => '', 'content' => ''],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(1, Page::count());
        $this->assertSame('tr', Page::first()->locale);
    }

    public function test_the_default_language_cannot_be_left_empty(): void
    {
        $this->from('/admin/pages/create')
            ->post('/admin/pages', ['translations' => [
                'tr' => ['title' => '', 'content' => ''],
                'en' => ['title' => 'Only English', 'content' => '<p>en</p>'],
            ]])
            ->assertSessionHasErrors('translations.tr.title');

        $this->assertSame(0, Page::count());
    }

    public function test_a_missing_translation_can_be_added_later(): void
    {
        $this->post('/admin/pages', ['translations' => [
            'tr' => ['title' => 'Sonradan', 'content' => '<p>tr</p>', 'status' => 'published'],
        ]])->assertSessionHasNoErrors();

        $turkish = Page::where('locale', 'tr')->firstOrFail();
        $this->assertFalse($turkish->hasTranslation('en'));

        $this->put("/admin/pages/{$turkish->id}", ['translations' => [
            'tr' => ['title' => 'Sonradan', 'content' => '<p>tr</p>', 'status' => 'published'],
            'en' => ['title' => 'Later', 'content' => '<p>en</p>', 'status' => 'published'],
        ]])->assertSessionHasNoErrors();

        $this->assertTrue($turkish->fresh()->hasTranslation('en'));
        $this->assertSame('Later', $turkish->fresh()->translation('en')?->title);
    }

    public function test_editing_updates_the_right_language_only(): void
    {
        $this->post('/admin/pages', $this->payload())->assertSessionHasNoErrors();

        $turkish = Page::where('locale', 'tr')->firstOrFail();

        $this->put("/admin/pages/{$turkish->id}", $this->payload([
            'en' => ['title' => 'About Us — Revised', 'content' => '<p>revised</p>'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Hakkımızda', $turkish->fresh()->title, 'Türkçe satır değişmemeliydi');
        $this->assertSame('About Us — Revised', $turkish->fresh()->translation('en')?->title);
    }

    /**
     * An empty tab on edit means "not translated yet", never "wipe what is
     * already there".
     */
    public function test_an_empty_tab_does_not_erase_an_existing_translation(): void
    {
        $this->post('/admin/pages', $this->payload())->assertSessionHasNoErrors();

        $turkish = Page::where('locale', 'tr')->firstOrFail();

        $this->put("/admin/pages/{$turkish->id}", ['translations' => [
            'tr' => ['title' => 'Hakkımızda', 'content' => '<p>Türkçe içerik</p>', 'status' => 'published'],
            'en' => ['title' => '', 'content' => ''],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('About Us', $turkish->fresh()->translation('en')?->title);
    }

    public function test_the_same_slug_is_allowed_in_two_languages(): void
    {
        $this->post('/admin/pages', ['translations' => [
            'tr' => ['title' => 'Kontak', 'slug' => 'contact', 'content' => '<p>tr</p>', 'status' => 'published'],
            'en' => ['title' => 'Contact', 'slug' => 'contact', 'content' => '<p>en</p>', 'status' => 'published'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('contact', Page::where('locale', 'tr')->firstOrFail()->slug);
        $this->assertSame('contact', Page::where('locale', 'en')->firstOrFail()->slug);
    }

    public function test_a_slug_already_used_in_the_same_language_is_rejected(): void
    {
        $this->post('/admin/pages', ['translations' => [
            'tr' => ['title' => 'İlk', 'slug' => 'ayni-slug', 'content' => '<p>tr</p>', 'status' => 'published'],
        ]])->assertSessionHasNoErrors();

        $this->from('/admin/pages/create')
            ->post('/admin/pages', ['translations' => [
                'tr' => ['title' => 'İkinci', 'slug' => 'ayni-slug', 'content' => '<p>tr</p>', 'status' => 'published'],
            ]])
            ->assertSessionHasErrors('translations.tr.slug');
    }

    public function test_the_edit_form_shows_each_language_its_own_values(): void
    {
        $this->post('/admin/pages', $this->payload())->assertSessionHasNoErrors();

        $turkish = Page::where('locale', 'tr')->firstOrFail();

        $html = $this->get("/admin/pages/{$turkish->id}/edit")->getContent();

        $this->assertStringContainsString('Hakkımızda', $html);
        $this->assertStringContainsString('About Us', $html);
    }

    public function test_an_untranslated_language_is_flagged_on_the_edit_form(): void
    {
        $this->post('/admin/pages', ['translations' => [
            'tr' => ['title' => 'Tek Dil', 'content' => '<p>tr</p>', 'status' => 'published'],
        ]])->assertSessionHasNoErrors();

        $turkish = Page::where('locale', 'tr')->firstOrFail();

        $html = $this->get("/admin/pages/{$turkish->id}/edit")->getContent();

        $this->assertStringContainsString('Çeviri yok', $html);
    }
}
