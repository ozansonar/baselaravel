<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blog yazısının altındaki yorum formu.
 *
 * Form sayfa yenilenmeden gönderiliyor ve kuralları alanların üstünde duruyor;
 * ikisi de kolayca sessizce bozulabilecek şeyler, o yüzden burada duruyorlar.
 * reCAPTCHA da panelden açılıp kapanıyor: kapalıyken form robot kutusu istemeden
 * çalışmalı, açıkken kutusuz yorum sunucuda durmalı.
 */
final class BlogCommentFormTest extends TestCase
{
    use RefreshDatabase;

    private BlogPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        $category = BlogCategory::create([
            'locale' => 'tr',
            'name'   => 'Duyurular',
            'slug'   => 'duyurular',
        ]);

        $this->post = BlogPost::create([
            'locale'           => 'tr',
            'blog_category_id' => $category->id,
            'title'            => 'Yorum denemesi',
            'slug'             => 'yorum-denemesi',
            'body'             => 'Gövde metni.',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
        ]);
    }

    private function url(): string
    {
        return route('blog.show', [$this->post->category->slug, $this->post->slug]);
    }

    // ── Formun kendisi ──

    public function test_the_comment_form_sits_between_the_share_buttons_and_the_related_posts(): void
    {
        // İlgili içerikler bölümünün gerçekten basılması için aynı kategoride
        // ikinci bir yazı gerekiyor; yoksa sıralama sınanmadan geçerdi.
        BlogPost::create([
            'locale'           => 'tr',
            'blog_category_id' => $this->post->blog_category_id,
            'title'            => 'İkinci yazı',
            'slug'             => 'ikinci-yazi',
            'body'             => 'Gövde.',
            'status'           => 'published',
            'published_at'     => now()->subDays(2),
        ]);

        $html = (string) $this->get($this->url())->assertOk()->getContent();

        $paylasim = strpos($html, 'social-share');
        $yorum    = strpos($html, 'id="comments"');
        $ilgili   = strpos($html, __('site.blog.related'));

        $this->assertIsInt($paylasim, 'Paylaşım düğmeleri sayfada yok');
        $this->assertIsInt($yorum, 'Yorum bölümü sayfada yok');
        $this->assertIsInt($ilgili, 'İlgili içerikler bölümü basılmamış');

        $this->assertGreaterThan($paylasim, $yorum, 'Yorumlar paylaşım düğmelerinin üstünde kalmış');
        $this->assertLessThan($ilgili, $yorum, 'Yorumlar ilgili içeriklerin altına düşmüş');
    }

    public function test_every_comment_field_carries_its_validation_rule(): void
    {
        $html = (string) $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString('data-validate', $html, 'Form doğrulama motoruna bağlanmamış');

        foreach ([
            'validate[required,minSize[2],maxSize[100]]',           // name  → min:2  max:100
            'validate[required,custom[email],maxSize[255]]',        // email → email  max:255
            'validate[required,minSize[3],maxSize[2000]]',          // body  → min:3  max:2000
        ] as $rule) {
            $this->assertStringContainsString($rule, $html, "Kural eksik: {$rule}");
        }
    }

    /**
     * Tarayıcının kendi doğrulaması projede kapalı: mesajı biçimlendirilemiyor
     * ve sunucudaki kuralla uyuşmadığı yerde kullanıcıyı yanlış yönlendiriyor.
     */
    public function test_the_form_does_not_fall_back_to_browser_validation(): void
    {
        $markup = (string) file_get_contents(resource_path('views/partials/blog-comments.blade.php'));

        $this->assertStringNotContainsString(' required>', $markup);
        $this->assertStringNotContainsString('type="email"', $markup);
    }

    // ── reCAPTCHA ──

    public function test_no_robot_check_is_shown_while_it_is_switched_off(): void
    {
        $html = (string) $this->get($this->url())->assertOk()->getContent();

        $this->assertStringNotContainsString('g-recaptcha', $html);

        $this->postJson(route('blog-comments.store'), [
            'blog_post_id' => $this->post->id,
            'name'         => 'Deneme Kullanıcı',
            'email'        => 'deneme@ornekfirma.com',
            'body'         => 'Robot kutusu kapalıyken yorum gidebilmeli.',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, BlogComment::count());
    }

    public function test_the_widget_uses_the_site_key_from_the_panel(): void
    {
        $this->enableRecaptcha();

        $html = (string) $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString('class="g-recaptcha', $html);
        $this->assertStringContainsString('data-sitekey="panel-site-key"', $html);
    }

    public function test_a_comment_without_the_robot_check_is_refused(): void
    {
        $this->enableRecaptcha();

        $this->postJson(route('blog-comments.store'), [
            'blog_post_id' => $this->post->id,
            'name'         => 'Deneme Kullanıcı',
            'email'        => 'deneme@ornekfirma.com',
            'body'         => 'Robot kutusu işaretlenmeden gönderiliyor.',
        ])->assertStatus(422)->assertJsonValidationErrors('g-recaptcha-response');

        $this->assertSame(0, BlogComment::count());
    }

    /** Panelden açılmış gibi: anahtarlar ayarlar tablosundan okunuyor. */
    private function enableRecaptcha(): void
    {
        Setting::setValue('recaptcha_enabled', '1', 'recaptcha');
        Setting::setValue('recaptcha_site_key', 'panel-site-key', 'recaptcha');
        Setting::setValue('recaptcha_secret_key', 'panel-secret-key', 'recaptcha', 'password');
        Setting::clearSettingsCache();
    }
}
