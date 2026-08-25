<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Role;
use App\Models\Slider;
use App\Models\User;
use App\Services\LanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The other content modules use the same language-tab machinery as pages.
 *
 * Every module posts translations[{locale}][field] and saves one row per
 * language, which is also what gives each language its own image.
 */
class TranslatedContentFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        $this->seedAuthorization();
        app(LanguageService::class)->clearCache();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
        $this->actingAs($admin);
    }

    // ── FAQ ──

    public function test_the_faq_form_renders_a_tab_per_language(): void
    {
        $html = $this->get('/admin/faqs/create')->getContent();

        $this->assertStringContainsString('name="translations[tr][question]"', $html);
        $this->assertStringContainsString('name="translations[en][question]"', $html);
        $this->assertStringContainsString('name="translations[tr][answer]"', $html);
        $this->assertStringContainsString('name="translations[en][answer]"', $html);
    }

    public function test_an_faq_saves_one_row_per_language(): void
    {
        $this->post('/admin/faqs', ['translations' => [
            'tr' => ['question' => 'Nasıl üye olurum?', 'answer' => 'Kayıt sayfasından.', 'is_active' => 1],
            'en' => ['question' => 'How do I sign up?', 'answer' => 'From the register page.', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $turkish = Faq::where('locale', 'tr')->firstOrFail();
        $english = Faq::where('locale', 'en')->firstOrFail();

        $this->assertSame($turkish->lang_group_id, $english->lang_group_id);
        $this->assertSame('How do I sign up?', $turkish->translation('en')?->question);
    }

    public function test_an_faq_needs_only_the_default_language(): void
    {
        $this->post('/admin/faqs', ['translations' => [
            'tr' => ['question' => 'Tek dil', 'answer' => 'Cevap', 'is_active' => 1],
            'en' => ['question' => '', 'answer' => ''],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(1, Faq::count());
    }

    public function test_an_faq_rejects_an_empty_default_language(): void
    {
        $this->from('/admin/faqs/create')
            ->post('/admin/faqs', ['translations' => [
                'tr' => ['question' => '', 'answer' => ''],
                'en' => ['question' => 'Only English', 'answer' => 'Answer'],
            ]])
            ->assertSessionHasErrors('translations.tr.question');

        $this->assertSame(0, Faq::count());
    }

    public function test_editing_an_faq_touches_only_the_language_edited(): void
    {
        $this->post('/admin/faqs', ['translations' => [
            'tr' => ['question' => 'Soru', 'answer' => 'Cevap', 'is_active' => 1],
            'en' => ['question' => 'Question', 'answer' => 'Answer', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $turkish = Faq::where('locale', 'tr')->firstOrFail();

        $this->put("/admin/faqs/{$turkish->id}", ['translations' => [
            'tr' => ['question' => 'Soru', 'answer' => 'Cevap', 'is_active' => 1],
            'en' => ['question' => 'Question — revised', 'answer' => 'Answer', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('Soru', $turkish->fresh()->question);
        $this->assertSame('Question — revised', $turkish->fresh()->translation('en')?->question);
    }

    // ── Slider ──

    public function test_the_slider_form_renders_an_image_field_per_language(): void
    {
        $html = $this->get('/admin/sliders/create')->getContent();

        $this->assertStringContainsString('name="translations[tr][image]"', $html);
        $this->assertStringContainsString('name="translations[en][image]"', $html);
        $this->assertStringContainsString('name="translations[tr][title]"', $html);
        $this->assertStringContainsString('name="translations[en][title]"', $html);
    }

    /**
     * Slider artwork almost always has the headline baked into it, so the two
     * languages must not end up sharing one file.
     */
    public function test_a_slider_stores_a_separate_image_per_language(): void
    {
        $this->post('/admin/sliders', ['translations' => [
            'tr' => [
                'title'      => 'Kampanya',
                'image'      => UploadedFile::fake()->image('kampanya-tr.jpg', 1200, 600),
                'is_active'  => 1,
                'sort_order' => 0,
            ],
            'en' => [
                'title'      => 'Campaign',
                'image'      => UploadedFile::fake()->image('campaign-en.jpg', 1200, 600),
                'is_active'  => 1,
                'sort_order' => 0,
            ],
        ]])->assertSessionHasNoErrors();

        $turkish = Slider::where('locale', 'tr')->firstOrFail();
        $english = Slider::where('locale', 'en')->firstOrFail();

        $this->assertSame($turkish->lang_group_id, $english->lang_group_id);
        $this->assertNotSame($turkish->image, $english->image, 'İki dil aynı slider görselini paylaşıyor');

        $this->assertFileExists(config('uploads.path') . '/' . $turkish->image);
        $this->assertFileExists(config('uploads.path') . '/' . $english->image);
    }

    /**
     * Editing without attaching a new file must not blank the artwork that
     * language already had.
     */
    public function test_editing_a_slider_without_a_new_file_keeps_the_existing_image(): void
    {
        $this->post('/admin/sliders', ['translations' => [
            'tr' => ['title' => 'Kampanya', 'image' => UploadedFile::fake()->image('tr.jpg', 1200, 600), 'is_active' => 1, 'sort_order' => 0],
            'en' => ['title' => 'Campaign', 'image' => UploadedFile::fake()->image('en.jpg', 1200, 600), 'is_active' => 1, 'sort_order' => 0],
        ]])->assertSessionHasNoErrors();

        $turkish = Slider::where('locale', 'tr')->firstOrFail();
        $originalTurkishImage = $turkish->image;
        $originalEnglishImage = $turkish->translation('en')?->image;

        $this->put("/admin/sliders/{$turkish->id}", ['translations' => [
            'tr' => ['title' => 'Kampanya — güncel', 'is_active' => 1, 'sort_order' => 0],
            'en' => ['title' => 'Campaign', 'is_active' => 1, 'sort_order' => 0],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('Kampanya — güncel', $turkish->fresh()->title);
        $this->assertSame($originalTurkishImage, $turkish->fresh()->image, 'Türkçe görsel silindi');
        $this->assertSame($originalEnglishImage, $turkish->fresh()->translation('en')?->image, 'İngilizce görsel silindi');
    }

    public function test_a_slider_requires_an_image_for_the_default_language_on_create(): void
    {
        $this->from('/admin/sliders/create')
            ->post('/admin/sliders', ['translations' => [
                'tr' => ['title' => 'Görselsiz'],
            ]])
            ->assertSessionHasErrors('translations.tr.image');

        $this->assertSame(0, Slider::count());
    }

    /**
     * A translation added later should not be blocked on artwork. It borrows
     * the default language's image so the slider still renders, and the
     * translated artwork can be uploaded whenever it is ready.
     */
    public function test_a_second_language_may_be_added_before_its_artwork_exists(): void
    {
        $this->post('/admin/sliders', ['translations' => [
            'tr' => ['title' => 'Kampanya', 'image' => UploadedFile::fake()->image('tr.jpg', 1200, 600), 'is_active' => 1, 'sort_order' => 0],
        ]])->assertSessionHasNoErrors();

        $turkish = Slider::where('locale', 'tr')->firstOrFail();

        $this->put("/admin/sliders/{$turkish->id}", ['translations' => [
            'tr' => ['title' => 'Kampanya', 'is_active' => 1, 'sort_order' => 0],
            'en' => ['title' => 'Campaign', 'is_active' => 1, 'sort_order' => 0],
        ]])->assertSessionHasNoErrors();

        $english = $turkish->fresh()->translation('en');

        $this->assertNotNull($english, 'İngilizce çeviri oluşmadı');
        $this->assertSame($turkish->image, $english->image, 'Varsayılan dilin görseli devralınmadı');
    }

    /**
     * Once the translated artwork arrives it replaces the borrowed one, and the
     * default language keeps its own.
     */
    public function test_uploading_artwork_later_replaces_the_borrowed_image(): void
    {
        $this->post('/admin/sliders', ['translations' => [
            'tr' => ['title' => 'Kampanya', 'image' => UploadedFile::fake()->image('tr.jpg', 1200, 600), 'is_active' => 1, 'sort_order' => 0],
            'en' => ['title' => 'Campaign', 'is_active' => 1, 'sort_order' => 0],
        ]])->assertSessionHasNoErrors();

        $turkish = Slider::where('locale', 'tr')->firstOrFail();
        $turkishImage = $turkish->image;

        $this->assertSame($turkishImage, $turkish->translation('en')?->image);

        $this->put("/admin/sliders/{$turkish->id}", ['translations' => [
            'tr' => ['title' => 'Kampanya', 'is_active' => 1, 'sort_order' => 0],
            'en' => ['title' => 'Campaign', 'image' => UploadedFile::fake()->image('en.jpg', 1200, 600), 'is_active' => 1, 'sort_order' => 0],
        ]])->assertSessionHasNoErrors();

        $english = $turkish->fresh()->translation('en');

        $this->assertNotSame($turkishImage, $english?->image, 'İngilizce görsel güncellenmedi');
        $this->assertSame($turkishImage, $turkish->fresh()->image, 'Türkçe görsel değişti');
    }
}
