<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NotificationPreference;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\BlogCommentService;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Bildirim tercihleri.
 *
 * Asıl sınav ekranın kutuları değil, gönderim yolunun o kutuya bakması:
 * "kapattım" diyen kişiye posta gitmeye devam ediyorsa tercih ekranı bir
 * yalandır.
 */
class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function user(string $email = 'tercih@example.test'): User
    {
        $user = User::create([
            'first_name' => 'Deneme',
            'last_name'  => 'Kullanici',
            'email'      => $email,
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);

        $user->markEmailAsVerified();

        return $user;
    }

    private function service(): NotificationPreferenceService
    {
        return app(NotificationPreferenceService::class);
    }

    public function test_a_type_is_on_until_the_person_says_otherwise(): void
    {
        $user = $this->user();

        $this->assertTrue($this->service()->allows($user, NotificationPreference::CommentUpdates));
        $this->assertDatabaseCount('user_notification_preferences', 0);
    }

    public function test_turning_a_type_off_is_remembered(): void
    {
        $user = $this->user();

        $this->service()->set($user, NotificationPreference::CommentUpdates, false);

        $this->assertFalse($this->service()->allows($user, NotificationPreference::CommentUpdates));
    }

    public function test_the_switch_can_be_flipped_back(): void
    {
        $user = $this->user();

        $this->service()->set($user, NotificationPreference::CommentUpdates, false);
        $this->service()->set($user, NotificationPreference::CommentUpdates, true);

        $this->assertTrue($this->service()->allows($user, NotificationPreference::CommentUpdates));
        $this->assertDatabaseCount('user_notification_preferences', 1);
    }

    // ── Gönderim yolu ──

    private function approveACommentFor(User $user): void
    {
        $category = BlogCategory::create(['locale' => 'tr', 'name' => 'Genel', 'slug' => 'genel', 'is_active' => true]);
        $post = BlogPost::create([
            'locale' => 'tr', 'blog_category_id' => $category->id, 'title' => 'Yazı',
            'slug' => 'yazi', 'body' => 'Gövde', 'status' => 'published', 'published_at' => now(),
        ]);
        $comment = BlogComment::create([
            'blog_post_id' => $post->id, 'name' => $user->full_name,
            'email' => $user->email, 'body' => 'Yorumum', 'status' => 'pending',
        ]);

        app(BlogCommentService::class)->approve($comment);
    }

    public function test_the_mail_goes_out_when_the_type_is_on(): void
    {
        Mail::fake();

        $user = $this->user();
        $this->approveACommentFor($user);

        Mail::assertQueued(\App\Mail\BlogCommentApprovedMail::class);
    }

    /**
     * Ekranın karşılığı burada: kapalıysa posta hiç kuyruğa girmiyor.
     */
    public function test_the_mail_is_not_sent_when_the_type_is_off(): void
    {
        Mail::fake();

        $user = $this->user();
        $this->service()->set($user, NotificationPreference::CommentUpdates, false);

        $this->approveACommentFor($user);

        Mail::assertNothingQueued();
    }

    /**
     * Girişsiz bırakılan yorumun sahibinin tercihi yok; ona her zaman gidiyor.
     */
    public function test_a_guest_comment_still_gets_its_notification(): void
    {
        Mail::fake();

        $category = BlogCategory::create(['locale' => 'tr', 'name' => 'Genel', 'slug' => 'genel', 'is_active' => true]);
        $post = BlogPost::create([
            'locale' => 'tr', 'blog_category_id' => $category->id, 'title' => 'Yazı',
            'slug' => 'yazi', 'body' => 'Gövde', 'status' => 'published', 'published_at' => now(),
        ]);
        $comment = BlogComment::create([
            'blog_post_id' => $post->id, 'name' => 'Ziyaretçi',
            'email' => 'ziyaretci@example.test', 'body' => 'Yorum', 'status' => 'pending',
        ]);

        app(BlogCommentService::class)->approve($comment);

        Mail::assertQueued(\App\Mail\BlogCommentApprovedMail::class);
    }

    // ── Bülten: kaynağı abone tablosu ──

    public function test_the_newsletter_switch_reads_the_subscriber_table(): void
    {
        $user = $this->user();

        $this->assertFalse($this->service()->newsletterEnabled($user));

        Subscriber::create(['email' => $user->email, 'status' => 'subscribed', 'source' => 'form']);

        $this->assertTrue($this->service()->newsletterEnabled($user));
    }

    public function test_turning_the_newsletter_off_unsubscribes_the_person(): void
    {
        $user = $this->user();
        Subscriber::create(['email' => $user->email, 'status' => 'subscribed', 'source' => 'form']);

        $this->service()->setNewsletter($user, false);

        $this->assertFalse($this->service()->newsletterEnabled($user));
    }

    public function test_turning_the_newsletter_on_subscribes_the_person(): void
    {
        $user = $this->user();

        $this->service()->setNewsletter($user, true);

        $this->assertTrue($this->service()->newsletterEnabled($user));
    }

    // ── Ekran ──

    public function test_the_screen_lists_every_type(): void
    {
        $user = $this->user();

        $html = (string) $this->actingAs($user)->get('/tr/hesabim/bildirimler')->assertOk()->getContent();

        foreach (NotificationPreference::cases() as $type) {
            $this->assertStringContainsString($type->value, $html);
        }
    }

    public function test_the_form_saves_the_choices(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/tr/hesabim/bildirimler', [
            'preferences' => [NotificationPreference::CommentUpdates->value => '0'],
            'newsletter'  => '1',
        ])->assertRedirect(route('account.notifications'));

        $this->assertFalse($this->service()->allows($user, NotificationPreference::CommentUpdates));
        $this->assertTrue($this->service()->newsletterEnabled($user));
    }

    public function test_the_screen_is_closed_to_guests(): void
    {
        $this->get('/tr/hesabim/bildirimler')->assertRedirect();
    }
}
