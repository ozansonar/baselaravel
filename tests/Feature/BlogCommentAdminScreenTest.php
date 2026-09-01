<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Role;
use App\Models\User;
use App\Services\BlogCommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Yorum yönetim ekranları: liste süzgeçleri, istatistik kartları ve detay.
 *
 * Kartlar beş dakikalık bir önbellekten okuyor ama durum değiştiren yollar
 * onu düşürmüyordu: yorum onaylanıyor, kart hâlâ eski bekleyen sayısını
 * yazıyordu. Sayılar burada gerçek veriyle karşılaştırılıyor.
 */
final class BlogCommentAdminScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BlogPost $post;
    private BlogPost $otherPost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
        $this->actingAs($this->admin);

        $category = BlogCategory::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'name' => 'Duyurular', 'slug' => 'duyurular',
        ]);

        $this->post = $this->makePost($category->id, 'Birinci yazı', 'birinci-yazi');
        $this->otherPost = $this->makePost($category->id, 'İkinci yazı', 'ikinci-yazi');
    }

    private function makePost(int $categoryId, string $title, string $slug): BlogPost
    {
        return BlogPost::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'blog_category_id' => $categoryId,
            'title' => $title, 'slug' => $slug, 'body' => 'Gövde.',
            'status' => \App\Enums\ContentStatus::Published, 'published_at' => now()->subDay(),
        ]);
    }

    private function comment(array $attributes = []): BlogComment
    {
        return BlogComment::create(array_merge([
            'blog_post_id' => $this->post->id,
            'name'         => 'Deneme Kişi',
            'email'        => 'deneme@ornekfirma.com',
            'body'         => 'Bir yorum.',
            'status'       => CommentStatus::Pending,
        ], $attributes));
    }

    // ── İstatistik kartları ──

    public function test_the_cards_follow_the_database_after_an_approval(): void
    {
        $this->comment();
        $this->comment();
        $bekleyen = $this->comment();

        // Kartları bir kez okutup önbelleği doldur.
        $this->get(route('admin.blog-comments.index'))->assertOk();
        $this->assertSame(3, app(BlogCommentService::class)->getAdminStats()['pending']);

        $this->patch(route('admin.blog-comments.approve', $bekleyen))->assertRedirect();

        $stats = app(BlogCommentService::class)->getAdminStats();

        $this->assertSame(2, $stats['pending'], 'Onaydan sonra bekleyen sayısı düşmedi');
        $this->assertSame(1, $stats['approved']);
        $this->assertSame(3, $stats['total']);
    }

    public function test_the_cards_follow_the_database_after_a_new_comment(): void
    {
        $this->get(route('admin.blog-comments.index'))->assertOk();

        $this->postJson(route('blog-comments.store'), [
            'blog_post_id' => $this->post->id,
            'name'         => 'Yeni Kişi',
            'email'        => 'yeni@ornekfirma.com',
            'body'         => 'Yeni gelen yorum.',
        ])->assertOk();

        $this->assertSame(1, app(BlogCommentService::class)->getAdminStats()['pending']);
    }

    public function test_the_cards_follow_the_database_after_a_deletion(): void
    {
        $comment = $this->comment();
        $this->get(route('admin.blog-comments.index'))->assertOk();

        $this->delete(route('admin.blog-comments.destroy', $comment))->assertRedirect();

        $stats = app(BlogCommentService::class)->getAdminStats();

        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['total']);
    }

    // ── Süzgeçler ──

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $this->comment(['body' => 'Bekleyen yorum']);
        $this->comment(['body' => 'Onaylı yorum', 'status' => CommentStatus::Approved]);

        $html = (string) $this->get(route('admin.blog-comments.index', ['status' => 'approved']))->assertOk()->getContent();

        $this->assertStringContainsString('Onaylı yorum', $html);
        $this->assertStringNotContainsString('Bekleyen yorum', $html);
    }

    public function test_the_list_can_be_filtered_by_post(): void
    {
        $this->comment(['body' => 'Birinci yazının yorumu']);
        $this->comment(['body' => 'İkinci yazının yorumu', 'blog_post_id' => $this->otherPost->id]);

        $html = (string) $this->get(route('admin.blog-comments.index', ['post_id' => $this->otherPost->id]))->assertOk()->getContent();

        $this->assertStringContainsString('İkinci yazının yorumu', $html);
        $this->assertStringNotContainsString('Birinci yazının yorumu', $html);
    }

    public function test_the_list_can_be_filtered_by_a_date_range(): void
    {
        $eski = $this->comment(['body' => 'Geçen ayın yorumu']);
        $eski->forceFill(['created_at' => now()->subMonth()])->save();

        $this->comment(['body' => 'Bugünün yorumu']);

        $html = (string) $this->get(route('admin.blog-comments.index', [
            'date_from' => now()->toDateString(),
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Bugünün yorumu', $html);
        $this->assertStringNotContainsString('Geçen ayın yorumu', $html);

        // Üst sınır da gün sonunu kapsamalı: bugün yazılan yorum "bugüne
        // kadar" süzgecinin dışında kalmamalı.
        $html = (string) $this->get(route('admin.blog-comments.index', [
            'date_to' => now()->toDateString(),
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Bugünün yorumu', $html);
    }

    /** Bozuk bir tarih listeyi düşürmemeli; adres çubuğuna elle yazılabiliyor. */
    public function test_a_broken_date_does_not_break_the_list(): void
    {
        $this->comment();

        $this->get(route('admin.blog-comments.index', ['date_from' => 'olmayan-tarih']))->assertOk();
    }

    public function test_the_filters_are_offered_on_the_screen(): void
    {
        $this->comment();

        $html = (string) $this->get(route('admin.blog-comments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="post_id"', $html);
        $this->assertStringContainsString('name="date_from"', $html);
        $this->assertStringContainsString('name="date_to"', $html);
        // Süzgeç listesinde yalnız yorumu olan yazı var.
        $this->assertStringContainsString('Birinci yazı', $html);
        $this->assertStringNotContainsString('İkinci yazı', $html);
    }

    // ── Detay ekranı ──

    public function test_the_detail_screen_shows_the_comment_and_its_context(): void
    {
        $parent = $this->comment(['name' => 'Ana Yorumcu', 'body' => 'Ana yorum metni']);
        $comment = $this->comment(['name' => 'Yanıtlayan', 'body' => 'Yanıt metni', 'parent_id' => $parent->id]);

        $html = (string) $this->get(route('admin.blog-comments.show', $comment))->assertOk()->getContent();

        $this->assertStringContainsString('Yanıt metni', $html);
        $this->assertStringContainsString('Yanıtlayan', $html);
        // Yanıtlanan yorum bağlam olarak sayfada.
        $this->assertStringContainsString('Ana yorum metni', $html);
        $this->assertStringContainsString($this->post->title, $html);
    }

    /**
     * Onay ve reddetme tıklama anında iş bitiriyor; ikisi de onay penceresi
     * arkasında olmalı. Düğmeler doğrudan submit ederse tek yanlış tıklama
     * yorumu yayına alır.
     */
    public function test_the_detail_actions_ask_for_confirmation_first(): void
    {
        $comment = $this->comment();

        $html = (string) $this->get(route('admin.blog-comments.show', $comment))->assertOk()->getContent();

        // Onay kancası: davranış artık nitelikte değil, merkezi bağlayıcıda
        // (inline-actions.js). Sınanan şey değişmedi — düğme onay istiyor mu.
        $this->assertStringContainsString('data-action="yorum-eylem"', $html);
        $this->assertStringContainsString('data-eylem="approve"', $html);
        $this->assertStringContainsString('data-eylem="reject"', $html);
        $this->assertStringContainsString('data-action="sil"', $html);

        // İşlem formlarının düğmeleri doğrudan gönderim yapmıyor. Denetim
        // formun kendi gövdesine bakıyor: düzendeki çıkış formu da submit
        // düğmesi taşıyor, sayfanın tamamına bakmak yanıltırdı.
        foreach (['approveForm', 'rejectForm', 'deleteForm'] as $formId) {
            $bas = strpos($html, 'id="' . $formId . '-' . $comment->id . '"');
            $this->assertIsInt($bas, "{$formId} formu sayfada yok");

            $govde = substr($html, $bas, strpos($html, '</form>', $bas) - $bas);

            $this->assertStringNotContainsString('type="submit"', $govde, "{$formId} onaysız gönderiyor");
            $this->assertStringContainsString('type="button"', $govde);
        }
    }

    public function test_the_list_actions_ask_for_confirmation_first(): void
    {
        $this->comment();

        $html = (string) $this->get(route('admin.blog-comments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-action="yorum-eylem"', $html);
        $this->assertStringContainsString('data-eylem="approve"', $html);
        $this->assertStringContainsString('data-eylem="reject"', $html);
    }
}
