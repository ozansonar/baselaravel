<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GalleryType;
use App\Enums\PopupPage;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Popup;
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

    // ── Kategoriler ──

    /**
     * Categories are translated too, which is what lets an English item point
     * at an English category.
     */
    public function test_a_blog_category_saves_one_row_per_language(): void
    {
        $this->post('/admin/blog-categories', ['translations' => [
            'tr' => ['name' => 'Duyurular', 'is_active' => 1],
            'en' => ['name' => 'Announcements', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $turkish = BlogCategory::where('locale', 'tr')->firstOrFail();

        $this->assertSame('Announcements', $turkish->translation('en')?->name);
        $this->assertSame('duyurular', $turkish->slug);
        $this->assertSame('announcements', $turkish->translation('en')?->slug);
    }

    public function test_a_gallery_category_saves_one_row_per_language(): void
    {
        $this->post('/admin/gallery-categories', ['translations' => [
            'tr' => ['name' => 'Etkinlikler', 'is_active' => 1],
            'en' => ['name' => 'Events', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        // A migration already seeds gallery categories, so this is scoped to
        // the row the test created.
        $turkish = GalleryCategory::where('locale', 'tr')->where('name', 'Etkinlikler')->latest('id')->firstOrFail();

        $this->assertSame('Events', $turkish->translation('en')?->name);
    }

    // ── Galeri ──

    public function test_a_gallery_item_stores_a_separate_image_per_language(): void
    {
        $turkishCategory = GalleryCategory::factory()->create(['locale' => 'tr']);
        $englishCategory = GalleryCategory::factory()->create(['locale' => 'en']);

        $this->post('/admin/gallery-items', ['translations' => [
            'tr' => [
                'title'               => 'Açılış',
                'type'                => GalleryType::Photo->value,
                'gallery_category_id' => $turkishCategory->id,
                'image'               => UploadedFile::fake()->image('acilis-tr.jpg', 800, 600),
                'is_active'           => 1,
            ],
            'en' => [
                'title'               => 'Opening',
                'type'                => GalleryType::Photo->value,
                'gallery_category_id' => $englishCategory->id,
                'image'               => UploadedFile::fake()->image('opening-en.jpg', 800, 600),
                'is_active'           => 1,
            ],
        ]])->assertSessionHasNoErrors();

        $turkish = GalleryItem::where('locale', 'tr')->firstOrFail();
        $english = $turkish->translation('en');

        $this->assertNotNull($english);
        $this->assertNotSame($turkish->image, $english->image);

        // Each language points at the category row of its own language.
        $this->assertSame($turkishCategory->id, $turkish->gallery_category_id);
        $this->assertSame($englishCategory->id, $english->gallery_category_id);
    }

    public function test_the_gallery_form_offers_only_same_language_categories(): void
    {
        $turkishCategory = GalleryCategory::factory()->create(['locale' => 'tr', 'name' => 'Türkçe Kategori']);
        $englishCategory = GalleryCategory::factory()->create(['locale' => 'en', 'name' => 'English Category']);

        $html = $this->get('/admin/gallery-items/create')->getContent();

        // Both appear, but each inside its own tab pane.
        $this->assertStringContainsString('Türkçe Kategori', $html);
        $this->assertStringContainsString('English Category', $html);

        $turkishPane = $this->paneFor($html, 'galleryItemLangTabs-tr');
        $englishPane = $this->paneFor($html, 'galleryItemLangTabs-en');

        $this->assertStringContainsString('Türkçe Kategori', $turkishPane);
        $this->assertStringNotContainsString('English Category', $turkishPane, 'Türkçe sekmede İngilizce kategori görünüyor');

        $this->assertStringContainsString('English Category', $englishPane);
        $this->assertStringNotContainsString('Türkçe Kategori', $englishPane, 'İngilizce sekmede Türkçe kategori görünüyor');
    }

    // ── Popup ──

    public function test_a_popup_saves_one_row_per_language(): void
    {
        $this->post('/admin/popups', ['translations' => [
            'tr' => ['title' => 'Duyuru', 'pages' => [PopupPage::Home->value], 'size' => 'md', 'is_active' => 1, 'sort_order' => 0],
            'en' => ['title' => 'Announcement', 'pages' => [PopupPage::Home->value], 'size' => 'md', 'is_active' => 1, 'sort_order' => 0],
        ]])->assertSessionHasNoErrors();

        $turkish = Popup::where('locale', 'tr')->firstOrFail();

        $this->assertSame('Announcement', $turkish->translation('en')?->title);
        $this->assertSame([PopupPage::Home->value], $turkish->translation('en')?->pages);
    }

    // ── Blog yazısı ──

    public function test_a_blog_post_saves_one_row_per_language_with_its_own_category(): void
    {
        $turkishCategory = BlogCategory::factory()->create(['locale' => 'tr']);
        $englishCategory = BlogCategory::factory()->create(['locale' => 'en']);

        $this->post('/admin/blog-posts', [
            'is_published' => 1,
            'translations' => [
                'tr' => ['title' => 'İlk Yazı', 'body' => '<p>Türkçe gövde</p>', 'blog_category_id' => $turkishCategory->id],
                'en' => ['title' => 'First Post', 'body' => '<p>English body</p>', 'blog_category_id' => $englishCategory->id],
            ],
        ])->assertSessionHasNoErrors();

        $turkish = BlogPost::where('locale', 'tr')->firstOrFail();
        $english = $turkish->translation('en');

        $this->assertNotNull($english);
        $this->assertSame($turkishCategory->id, $turkish->blog_category_id);
        $this->assertSame($englishCategory->id, $english->blog_category_id);
        $this->assertSame('first-post', $english->slug);
    }

    /**
     * Publishing is a decision about the post, so it reaches every language the
     * form saved.
     */
    public function test_publishing_a_post_applies_to_every_language(): void
    {
        $turkishCategory = BlogCategory::factory()->create(['locale' => 'tr']);
        $englishCategory = BlogCategory::factory()->create(['locale' => 'en']);

        $this->post('/admin/blog-posts', [
            'is_published' => 1,
            'translations' => [
                'tr' => ['title' => 'Yayında', 'body' => '<p>tr</p>', 'blog_category_id' => $turkishCategory->id],
                'en' => ['title' => 'Published', 'body' => '<p>en</p>', 'blog_category_id' => $englishCategory->id],
            ],
        ])->assertSessionHasNoErrors();

        $turkish = BlogPost::where('locale', 'tr')->firstOrFail();

        $this->assertTrue($turkish->is_published);
        $this->assertTrue($turkish->translation('en')?->is_published);
    }

    public function test_a_blog_post_records_the_author_on_every_language(): void
    {
        $turkishCategory = BlogCategory::factory()->create(['locale' => 'tr']);
        $englishCategory = BlogCategory::factory()->create(['locale' => 'en']);

        $this->post('/admin/blog-posts', [
            'is_published' => 1,
            'translations' => [
                'tr' => ['title' => 'Yazar', 'body' => '<p>tr</p>', 'blog_category_id' => $turkishCategory->id],
                'en' => ['title' => 'Author', 'body' => '<p>en</p>', 'blog_category_id' => $englishCategory->id],
            ],
        ])->assertSessionHasNoErrors();

        $turkish = BlogPost::where('locale', 'tr')->firstOrFail();

        $this->assertNotNull($turkish->user_id);
        $this->assertSame($turkish->user_id, $turkish->translation('en')?->user_id);
    }

    /**
     * Pull one tab pane out of the rendered form so a tab's contents can be
     * asserted on in isolation.
     */
    private function paneFor(string $html, string $paneId): string
    {
        $start = strpos($html, 'id="' . $paneId . '"');

        if ($start === false) {
            return '';
        }

        // Stop at the next pane rather than at this pane's own role attribute,
        // which sits immediately after the id.
        $next = strpos($html, 'class="tab-pane', $start + 1);

        return $next === false
            ? substr($html, $start)
            : substr($html, $start, $next - $start);
    }
}
