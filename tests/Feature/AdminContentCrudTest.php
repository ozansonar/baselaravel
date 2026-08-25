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
use App\Models\Page;
use App\Models\Popup;
use App\Models\Role;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Write paths for the content modules.
 *
 * The suite already covers reading and authorization; store, update, destroy
 * and restore were untested, which is exactly where a broken service call hides
 * (the FaqService scope bug reached production-shaped code that way).
 */
class AdminContentCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Yönetici']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role);

        $this->actingAs($this->admin);
    }

    private function image(string $name = 'gorsel.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 400);
    }

    // ── Pages ──

    public function test_a_page_can_be_created_updated_and_removed(): void
    {
        // Pages are multilingual: the form posts one block of fields per
        // language and the default language is the required one.
        $this->post('/admin/pages', ['translations' => [
            'tr' => ['title' => 'Hakkımızda', 'content' => '<p>Kurumsal metin</p>', 'status' => 'published'],
        ]])->assertSessionHasNoErrors()->assertRedirect();

        $page = Page::where('title', 'Hakkımızda')->firstOrFail();
        $this->assertSame('hakkimizda', $page->slug, 'Slug otomatik üretilmedi');

        $this->put("/admin/pages/{$page->id}", ['translations' => [
            'tr' => ['title' => 'Kurumsal', 'content' => '<p>Güncellendi</p>', 'status' => 'published'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('Kurumsal', $page->fresh()->title);

        $this->delete("/admin/pages/{$page->id}")->assertRedirect();
        $this->assertSoftDeleted('pages', ['id' => $page->id]);

        $this->patch("/admin/pages/{$page->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted('pages', ['id' => $page->id]);
    }

    public function test_a_page_without_a_title_is_rejected(): void
    {
        $this->from('/admin/pages/create')
            ->post('/admin/pages', ['translations' => [
                'tr' => ['title' => '', 'content' => '<p>Başlıksız</p>'],
            ]])
            ->assertSessionHasErrors('translations.tr.title');

        $this->assertSame(0, Page::count());
    }

    // ── Blog ──

    public function test_a_blog_category_and_post_can_be_created_and_removed(): void
    {
        $this->post('/admin/blog-categories', ['translations' => [
            'tr' => ['name' => 'Duyurular', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $category = BlogCategory::where('name', 'Duyurular')->firstOrFail();

        $this->post('/admin/blog-posts', [
            'is_published' => 1,
            'translations' => [
                'tr' => [
                    'blog_category_id' => $category->id,
                    'title'            => 'İlk Yazı',
                    'body'             => '<p>İçerik gövdesi</p>',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $post = BlogPost::where('title', 'İlk Yazı')->firstOrFail();
        $this->assertSame($category->id, $post->blog_category_id);

        $this->delete("/admin/blog-posts/{$post->id}")->assertRedirect();
        $this->assertSoftDeleted('blog_posts', ['id' => $post->id]);
    }

    public function test_a_blog_post_needs_an_existing_category(): void
    {
        $this->from('/admin/blog-posts/create')
            ->post('/admin/blog-posts', ['translations' => [
                'tr' => [
                    'blog_category_id' => 9999,
                    'title'            => 'Sahipsiz',
                    'body'             => '<p>Gövde</p>',
                ],
            ]])
            ->assertSessionHasErrors('translations.tr.blog_category_id');

        $this->assertSame(0, BlogPost::count());
    }

    /**
     * Renaming a post creates a redirect from the old address, which is the
     * behaviour BlogPostObserver exists for.
     */
    public function test_renaming_a_post_leaves_a_redirect_behind(): void
    {
        $post = BlogPost::factory()->create(['slug' => 'eski-baslik']);

        $this->put("/admin/blog-posts/{$post->id}", [
            'is_published' => 1,
            'translations' => [
                'tr' => [
                    'blog_category_id' => $post->blog_category_id,
                    'title'            => 'Yeni Başlık',
                    'slug'             => 'yeni-baslik',
                    'body'             => '<p>Gövde</p>',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('redirects', [
            'old_url' => '/blog/' . $post->category->slug . '/eski-baslik',
        ]);
    }

    // ── FAQ ──

    public function test_a_faq_can_be_created_updated_and_removed(): void
    {
        $this->post('/admin/faqs', ['translations' => [
            'tr' => ['question' => 'Nasıl üye olurum?', 'answer' => 'Kayıt sayfasından.', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $faq = Faq::where('question', 'Nasıl üye olurum?')->firstOrFail();

        $this->put("/admin/faqs/{$faq->id}", ['translations' => [
            'tr' => ['question' => 'Nasıl kayıt olurum?', 'answer' => 'Kayıt sayfasından.', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('Nasıl kayıt olurum?', $faq->fresh()->question);

        $this->delete("/admin/faqs/{$faq->id}")->assertRedirect();
        $this->assertSoftDeleted('faqs', ['id' => $faq->id]);
    }

    /**
     * The front page reads FAQs through FaqService::allActive(); a broken scope
     * there once turned the page into a 500.
     */
    public function test_a_created_faq_shows_up_on_the_public_page(): void
    {
        $this->post('/admin/faqs', ['translations' => [
            'tr' => ['question' => 'Kargo ne zaman gelir?', 'answer' => 'İki iş günü içinde.', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $this->get('/sikca-sorulan-sorular')
            ->assertOk()
            ->assertSee('Kargo ne zaman gelir?', false);
    }

    // ── Gallery ──

    public function test_a_gallery_item_can_be_created_with_an_upload(): void
    {
        $this->post('/admin/gallery-categories', ['translations' => [
            'tr' => ['name' => 'Etkinlikler', 'is_active' => 1],
        ]])->assertSessionHasNoErrors();

        $category = GalleryCategory::where('name', 'Etkinlikler')->firstOrFail();

        $this->post('/admin/gallery-items', ['translations' => [
            'tr' => [
                'title'               => 'Açılış',
                'type'                => GalleryType::Photo->value,
                'gallery_category_id' => $category->id,
                'image'               => $this->image(),
                'is_active'           => 1,
            ],
        ]])->assertSessionHasNoErrors();

        $item = GalleryItem::where('title', 'Açılış')->firstOrFail();

        $this->assertNotNull($item->image, 'Görsel kaydedilmedi');
        $this->assertSame(GalleryType::Photo, $item->type);

        $this->delete("/admin/gallery-items/{$item->id}")->assertRedirect();
        $this->assertSoftDeleted('gallery_items', ['id' => $item->id]);
    }

    public function test_a_video_gallery_item_requires_a_video_url(): void
    {
        $category = GalleryCategory::factory()->create();

        $this->from('/admin/gallery-items/create')
            ->post('/admin/gallery-items', ['translations' => [
                'tr' => [
                    'title'               => 'Tanıtım',
                    'type'                => GalleryType::Video->value,
                    'gallery_category_id' => $category->id,
                    'image'               => $this->image(),
                ],
            ]])
            ->assertSessionHasErrors('translations.tr.video_url');
    }

    // ── Slider ──

    public function test_a_slider_can_be_created_and_removed(): void
    {
        $this->post('/admin/sliders', ['translations' => [
            'tr' => [
                'title'      => 'Kampanya',
                'image'      => $this->image('slider.jpg'),
                'is_active'  => 1,
                'sort_order' => 0,
            ],
        ]])->assertSessionHasNoErrors();

        $slider = Slider::where('title', 'Kampanya')->firstOrFail();
        $this->assertNotNull($slider->image);

        $this->delete("/admin/sliders/{$slider->id}")->assertRedirect();
        $this->assertSoftDeleted('sliders', ['id' => $slider->id]);
    }

    public function test_a_slider_without_an_image_is_rejected(): void
    {
        $this->from('/admin/sliders/create')
            ->post('/admin/sliders', ['translations' => [
                'tr' => ['title' => 'Görselsiz'],
            ]])
            ->assertSessionHasErrors('translations.tr.image');

        $this->assertSame(0, Slider::count());
    }

    // ── Popup ──

    public function test_a_popup_can_be_created_and_targets_the_chosen_pages(): void
    {
        $this->post('/admin/popups', ['translations' => [
            'tr' => [
                'title'      => 'Duyuru',
                'pages'      => [PopupPage::Home->value],
                'size'       => 'md',
                'is_active'  => 1,
                'sort_order' => 0,
            ],
        ]])->assertSessionHasNoErrors();

        $popup = Popup::where('title', 'Duyuru')->firstOrFail();

        $this->assertSame([PopupPage::Home->value], $popup->pages);

        $this->delete("/admin/popups/{$popup->id}")->assertRedirect();
        $this->assertSoftDeleted('popups', ['id' => $popup->id]);
    }

    public function test_a_popup_needs_at_least_one_target_page(): void
    {
        $this->from('/admin/popups/create')
            ->post('/admin/popups', ['translations' => [
                'tr' => ['title' => 'Hedefsiz', 'size' => 'md'],
            ]])
            ->assertSessionHasErrors('translations.tr.pages');

        $this->assertSame(0, Popup::count());
    }

    /**
     * Uploads made by the suite must land in the throwaway directory, never in
     * the real public/uploads folder that ships with the project.
     */
    public function test_test_uploads_stay_out_of_the_real_upload_folder(): void
    {
        $this->assertStringContainsString(
            'framework/testing',
            (string) config('uploads.path'),
            'Test yüklemeleri gerçek public/uploads dizinine yazıyor',
        );

        $category = GalleryCategory::factory()->create();

        $this->post('/admin/gallery-items', ['translations' => [
            'tr' => [
                'title'               => 'İzolasyon',
                'type'                => GalleryType::Photo->value,
                'gallery_category_id' => $category->id,
                'image'               => $this->image(),
                'is_active'           => 1,
            ],
        ]])->assertSessionHasNoErrors();

        $item = GalleryItem::where('title', 'İzolasyon')->firstOrFail();

        $this->assertFileExists(config('uploads.path') . '/' . $item->image);
        $this->assertFileDoesNotExist(public_path('uploads/' . $item->image));
    }

    // ── Editor limits ──

    /**
     * Editors may create content but deletion stays with the admin.
     */
    public function test_an_editor_can_create_but_not_delete(): void
    {
        $editorRole = Role::firstOrCreate(['slug' => 'editor'], ['name' => 'Editör']);
        $editor = User::factory()->create();
        $editor->roles()->attach($editorRole);

        $this->actingAs($editor)
            ->post('/admin/faqs', ['translations' => [
                'tr' => ['question' => 'Editör sorusu', 'answer' => 'Editör cevabı', 'is_active' => 1],
            ]])
            ->assertSessionHasNoErrors();

        $faq = Faq::where('question', 'Editör sorusu')->firstOrFail();

        $this->actingAs($editor)
            ->delete("/admin/faqs/{$faq->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('faqs', ['id' => $faq->id]);
    }
}
