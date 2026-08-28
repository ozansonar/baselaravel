<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Mail\BlogCommentAdminNotification;
use App\Mail\BlogCommentApprovedMail;
use App\Mail\BlogCommentReceivedMail;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\BlogCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Yorum bildirimleri.
 *
 * Üç mail var: yöneticiye "yeni yorum var", yazan kişiye "aldık,
 * değerlendiriyoruz" ve onaydan sonra "yayınlandı". Üçü de MailService
 * üzerinden gidiyor — mail loglarına düşmelerinin sebebi bu — ve metinleri
 * "Mail Temaları" ekranından düzenlenebilsin diye şablon anahtarı taşıyorlar.
 */
final class BlogCommentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private BlogPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        $category = BlogCategory::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'name' => 'Duyurular', 'slug' => 'duyurular',
        ]);

        $this->post = BlogPost::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'blog_category_id' => $category->id,
            'title' => 'Yorum denemesi', 'slug' => 'yorum-denemesi', 'body' => 'Gövde.',
            'status' => \App\Enums\ContentStatus::Published, 'published_at' => now()->subDay(),
        ]);
    }

    private function comment(CommentStatus $status = CommentStatus::Pending): BlogComment
    {
        return BlogComment::create([
            'blog_post_id' => $this->post->id,
            'name'         => 'Mehmet Deneme',
            'email'        => 'mehmet@ornekfirma.com',
            'body'         => 'Yazı için teşekkürler.',
            'status'       => $status,
        ]);
    }

    // ── Yeni yorum ──

    public function test_a_new_comment_notifies_both_the_admin_and_its_author(): void
    {
        Mail::fake();

        $this->postJson(route('blog-comments.store'), [
            'blog_post_id' => $this->post->id,
            'name'         => 'Mehmet Deneme',
            'email'        => 'mehmet@ornekfirma.com',
            'body'         => 'Yazı için teşekkürler.',
        ])->assertOk();

        $adminEmail = (string) config('mail.admin_address', config('mail.from.address'));

        Mail::assertQueued(
            BlogCommentAdminNotification::class,
            fn (BlogCommentAdminNotification $mail): bool => $mail->hasTo($adminEmail),
        );

        Mail::assertQueued(
            BlogCommentReceivedMail::class,
            fn (BlogCommentReceivedMail $mail): bool => $mail->hasTo('mehmet@ornekfirma.com'),
        );
    }

    /**
     * Mail gönderimi yorumun kaydedilmesini bozmamalı: SMTP kapalıyken de
     * ziyaretçinin yorumu kaydedilmiş sayılır.
     */
    public function test_a_failing_mailer_does_not_lose_the_comment(): void
    {
        // Tanımsız bir sürücü: Mail katmanı çözümlemede patlıyor. Cephe
        // taklidi kullanılmıyor — Mockery üzerinden sonraki testlere sızıp
        // onların gönderimini de kırıyordu; MailService de final olduğu için
        // taklit edilemiyor.
        config(['mail.default' => 'olmayan-surucu']);

        $this->postJson(route('blog-comments.store'), [
            'blog_post_id' => $this->post->id,
            'name'         => 'Mehmet Deneme',
            'email'        => 'mehmet@ornekfirma.com',
            'body'         => 'Yazı için teşekkürler.',
        ])->assertOk();

        $this->assertSame(1, BlogComment::count());
    }

    // ── Onay ──

    public function test_approving_a_comment_tells_its_author(): void
    {
        Mail::fake();

        $comment = $this->comment();

        app(BlogCommentService::class)->approve($comment);

        Mail::assertQueued(
            BlogCommentApprovedMail::class,
            fn (BlogCommentApprovedMail $mail): bool => $mail->hasTo('mehmet@ornekfirma.com'),
        );
    }

    public function test_approving_an_already_approved_comment_sends_nothing(): void
    {
        $comment = $this->comment(CommentStatus::Approved);

        Mail::fake();

        app(BlogCommentService::class)->approve($comment);

        Mail::assertNotQueued(BlogCommentApprovedMail::class);
    }

    public function test_bulk_approval_only_mails_the_comments_that_changed(): void
    {
        $bekleyen = $this->comment();
        $onayli   = $this->comment(CommentStatus::Approved);

        Mail::fake();

        app(BlogCommentService::class)->approveMany([$bekleyen->id, $onayli->id]);

        // İkisi seçildi ama yalnız biri değişti; mail de yalnız ona gitmeli.
        Mail::assertQueuedCount(1);
    }

    public function test_rejecting_a_comment_sends_no_mail(): void
    {
        Mail::fake();

        app(BlogCommentService::class)->reject($this->comment());

        Mail::assertNothingQueued();
    }

    // ── Şablonlar ──

    /**
     * @return array<string, array{string}>
     */
    public static function templateKeys(): array
    {
        $keys = ['blog_comment_admin', 'blog_comment_received', 'blog_comment_approved'];

        return array_combine($keys, array_map(static fn (string $k): array => [$k], $keys));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('templateKeys')]
    public function test_every_notification_is_editable_from_the_mail_templates_screen(string $key): void
    {
        $template = MailTemplate::where('key', $key)->first();

        $this->assertNotNull($template, "{$key} şablonu Mail Temaları ekranında yok");
        $this->assertTrue($template->is_active);
        $this->assertNotEmpty($template->subject);
        $this->assertNotEmpty($template->variableKeys(), 'Şablonun değişken listesi boş');
    }

    /** Panelde yazılan metin gerçekten kullanılmalı; sınıfın içindeki gömülü değil. */
    public function test_the_subject_comes_from_the_template_the_panel_edits(): void
    {
        MailTemplate::where('key', 'blog_comment_approved')
            ->update(['subject' => 'Panelden değişen konu — {site_name}']);

        config(['mail.default' => 'array']);

        app(BlogCommentService::class)->approve($this->comment());

        $log = MailLog::where('mailable_class', BlogCommentApprovedMail::class)->latest()->firstOrFail();

        $this->assertStringContainsString('Panelden değişen konu', (string) $log->subject);
    }

    // ── Loglama ──

    public function test_every_notification_lands_in_the_mail_log(): void
    {
        config(['mail.default' => 'array']);

        $this->postJson(route('blog-comments.store'), [
            'blog_post_id' => $this->post->id,
            'name'         => 'Mehmet Deneme',
            'email'        => 'mehmet@ornekfirma.com',
            'body'         => 'Yazı için teşekkürler.',
        ])->assertOk();

        $comment = BlogComment::firstOrFail();
        app(BlogCommentService::class)->approve($comment);

        foreach ([
            BlogCommentAdminNotification::class,
            BlogCommentReceivedMail::class,
            BlogCommentApprovedMail::class,
        ] as $sinif) {
            $this->assertDatabaseHas('mail_logs', ['mailable_class' => $sinif]);
        }

        // Kullanıcıya giden iki mailin alıcısı yorumu yazan kişi olmalı.
        $this->assertSame(
            2,
            MailLog::where('to', 'mehmet@ornekfirma.com')->count(),
        );
    }

    /**
     * Kayıt gönderimden önce açılıyor ve o anda konu henüz yok; loglara
     * sınıf adı düşüyordu. Gerçek konu gönderim anında yazılıyor.
     */
    public function test_the_log_keeps_the_real_subject_not_the_class_name(): void
    {
        config(['mail.default' => 'array']);

        app(BlogCommentService::class)->approve($this->comment());

        $log = MailLog::where('mailable_class', BlogCommentApprovedMail::class)->latest()->firstOrFail();

        $this->assertNotSame('BlogCommentApprovedMail', $log->subject);
        $this->assertStringContainsString('Yorum', (string) $log->subject);
    }

    // ── Ön yüzde görünürlük ──

    public function test_an_approved_comment_shows_up_on_the_public_page(): void
    {
        $comment = $this->comment();

        $url = route('blog.show', [$this->post->category->slug, $this->post->slug]);

        $this->assertStringNotContainsString('Yazı için teşekkürler.', (string) $this->get($url)->getContent());

        app(BlogCommentService::class)->approve($comment);

        $this->assertStringContainsString('Yazı için teşekkürler.', (string) $this->get($url)->getContent());
    }

    /** Panelden onaylandığında da aynı yol işlemeli. */
    public function test_approving_from_the_panel_publishes_the_comment(): void
    {
        $this->seedAuthorization();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        $comment = $this->comment();

        $this->actingAs($admin)
            ->patch(route('admin.blog-comments.approve', $comment))
            ->assertRedirect();

        $this->assertSame(CommentStatus::Approved, $comment->fresh()->status);

        $url = route('blog.show', [$this->post->category->slug, $this->post->slug]);
        $this->assertStringContainsString('Yazı için teşekkürler.', (string) $this->get($url)->getContent());
    }
}
