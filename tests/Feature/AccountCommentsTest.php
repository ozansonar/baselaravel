<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TokenAbility;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Yorumlarım" — hem ekranda hem API'de.
 *
 * Yorum gönderilebiliyordu ama kişi kendi yorumlarını göremiyordu: onay
 * bekleyen bir yorumun akıbetini öğrenmenin tek yolu yazıyı tekrar tekrar
 * açmaktı.
 */
class AccountCommentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    private function user(string $email = 'yorumcu@example.test'): User
    {
        $user = User::create([
            'first_name' => 'Yorum', 'last_name' => 'Sahibi',
            'email' => $email, 'password' => 'sifre-123456', 'is_active' => true,
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    private function comment(User $user, string $body, string $status = 'approved'): BlogComment
    {
        $category = BlogCategory::firstOrCreate(
            ['locale' => 'tr', 'slug' => 'genel'],
            ['name' => 'Genel', 'is_active' => true],
        );

        $post = BlogPost::firstOrCreate(
            ['locale' => 'tr', 'slug' => 'yazi'],
            [
                'blog_category_id' => $category->id, 'title' => 'Yazı', 'body' => 'Gövde',
                'status' => 'published', 'published_at' => now(),
            ],
        );

        return BlogComment::create([
            'blog_post_id' => $post->id,
            'name'         => $user->full_name,
            'email'        => $user->email,
            'body'         => $body,
            'status'       => $status,
        ]);
    }

    // ── Ekran ──

    public function test_the_screen_lists_own_comments_including_the_pending_ones(): void
    {
        $user = $this->user();
        $this->comment($user, 'Yayınlanmış yorumum');
        $this->comment($user, 'Onay bekleyen yorumum', 'pending');

        $this->actingAs($user)->get('/tr/hesabim/yorumlarim')
            ->assertOk()
            ->assertSee('Yayınlanmış yorumum')
            ->assertSee('Onay bekleyen yorumum')
            ->assertSee(__('site.comments.pending'));
    }

    public function test_another_persons_comment_is_not_listed(): void
    {
        $user = $this->user();
        $other = $this->user('baskasi@example.test');

        $this->comment($other, 'Başkasının yorumu');

        $this->actingAs($user)->get('/tr/hesabim/yorumlarim')
            ->assertOk()
            ->assertDontSee('Başkasının yorumu');
    }

    public function test_a_comment_can_be_deleted_by_its_owner(): void
    {
        $user = $this->user();
        $comment = $this->comment($user, 'Silinecek yorum');

        $this->actingAs($user)
            ->delete('/tr/hesabim/yorumlarim/' . $comment->id)
            ->assertRedirect(route('account.comments'));

        $this->assertSoftDeleted('blog_comments', ['id' => $comment->id]);
    }

    /**
     * Kimlik numarasını deneyen biri başkasının yorumunu silememeli.
     */
    public function test_someone_elses_comment_cannot_be_deleted(): void
    {
        $user = $this->user();
        $other = $this->user('kurban@example.test');
        $comment = $this->comment($other, 'Korunacak yorum');

        $this->actingAs($user)->delete('/tr/hesabim/yorumlarim/' . $comment->id);

        $this->assertDatabaseHas('blog_comments', ['id' => $comment->id, 'deleted_at' => null]);
    }

    public function test_the_screen_is_closed_to_guests(): void
    {
        $this->get('/tr/hesabim/yorumlarim')->assertRedirect();
    }

    // ── API ──

    private function apiToken(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('test', array_map(
            fn (TokenAbility $ability): string => $ability->value,
            TokenAbility::cases(),
        ))->plainTextToken;
    }

    public function test_the_api_returns_own_comments_with_their_status(): void
    {
        $user = $this->user();
        $this->comment($user, 'API yorumum', 'pending');

        $this->withToken($this->apiToken($user))
            ->getJson('/api/v1/account/comments')
            ->assertOk()
            ->assertJsonPath('data.0.body', 'API yorumum')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_the_api_can_delete_an_own_comment(): void
    {
        $user = $this->user();
        $comment = $this->comment($user, 'Silinecek');

        $this->withToken($this->apiToken($user))
            ->deleteJson('/api/v1/account/comments/' . $comment->id)
            ->assertOk();

        $this->assertSoftDeleted('blog_comments', ['id' => $comment->id]);
    }

    public function test_the_api_refuses_to_delete_someone_elses_comment(): void
    {
        $user = $this->user();
        $other = $this->user('api-kurban@example.test');
        $comment = $this->comment($other, 'Korunacak');

        $this->withToken($this->apiToken($user))
            ->deleteJson('/api/v1/account/comments/' . $comment->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('blog_comments', ['id' => $comment->id, 'deleted_at' => null]);
    }

    /**
     * Onay bekleyen yorumların varlığı ziyaretçiye söylenmemeli: sitede
     * görünmeyen içeriği duyurmak olurdu.
     */
    public function test_the_public_comment_endpoint_does_not_leak_status(): void
    {
        $user = $this->user();
        $this->comment($user, 'Onaylı yorum');

        $post = BlogPost::firstOrFail();

        $body = (string) $this->getJson('/api/v1/blog/posts/' . $post->slug . '/comments')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('"status"', $body);
    }
}
