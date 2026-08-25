<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks down which admin areas each panel role may reach.
 *
 * AdminMiddleware lets admin, editor and moderator into the panel; the
 * per-area decision belongs to the policies and gates. Without these
 * assertions a missing authorize() call is invisible.
 */
class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    /**
     * route => [admin, editor, moderator] expected status codes.
     *
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    private function matrix(): array
    {
        return [
            // Everyone who can reach the panel
            '/admin'                    => [200, 200, 200],
            '/admin/bildirimler'        => [200, 200, 200],
            '/admin/contact-messages'   => [200, 200, 200],
            // Moderation is what the moderator role exists for
            '/admin/blog-comments'      => [200, 200, 200],

            // Content — admin and editor
            '/admin/pages'              => [200, 200, 403],
            '/admin/blog-posts'         => [200, 200, 403],
            '/admin/gallery-items'      => [200, 200, 403],
            '/admin/faqs'               => [200, 200, 403],
            '/admin/sliders'            => [200, 200, 403],
            '/admin/popups'             => [200, 200, 403],
            '/admin/menus'              => [200, 200, 403],
            '/admin/files'              => [200, 200, 403],
            '/admin/analytics'          => [200, 200, 403],

            // Admin only
            '/admin/settings'           => [200, 403, 403],
            '/admin/users'              => [200, 403, 403],
            '/admin/roller'             => [200, 403, 403],
            '/admin/redirects'          => [200, 403, 403],
            '/admin/mail-templates'     => [200, 403, 403],
            '/admin/mail-logs'          => [200, 403, 403],
            '/admin/aktivite-loglari'   => [200, 403, 403],
            '/admin/yedekler'           => [200, 403, 403],
            '/admin/sistem-saglik'      => [200, 403, 403],
        ];
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug)],
        );

        $user = User::create([
            'first_name' => ucfirst($slug),
            'last_name'  => 'Tester',
            'email'      => $slug . '@example.test',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $user->roles()->attach($role);

        return $user;
    }

    public function test_each_role_reaches_only_its_own_admin_areas(): void
    {
        $users = [
            'admin'     => $this->userWithRole('admin'),
            'editor'    => $this->userWithRole('editor'),
            'moderator' => $this->userWithRole('moderator'),
        ];

        $roles = array_keys($users);

        foreach ($this->matrix() as $route => $expected) {
            foreach ($roles as $index => $role) {
                $status = $this->actingAs($users[$role])->get($route)->getStatusCode();

                $this->assertSame(
                    $expected[$index],
                    $status,
                    "{$role} → GET {$route}: beklenen {$expected[$index]}, gelen {$status}",
                );
            }
        }
    }

    public function test_editor_cannot_download_a_backup(): void
    {
        $editor = $this->userWithRole('editor');

        $this->actingAs($editor)
            ->get('/admin/yedekler/indir/backup-test.zip')
            ->assertForbidden();
    }

    public function test_editor_cannot_read_a_mail_log_body(): void
    {
        $editor = $this->userWithRole('editor');

        $log = \App\Models\MailLog::create([
            'to'      => 'someone@example.test',
            'subject' => 'Şifre Sıfırlama',
            'body'    => '<p>https://example.test/sifre-sifirla/secret-token</p>',
            'status'  => 'sent',
        ]);

        $this->actingAs($editor)
            ->get("/admin/mail-logs/{$log->id}/body")
            ->assertForbidden();
    }

    /**
     * The sidebar must not advertise areas the role would be refused from,
     * otherwise every such link is a dead end.
     */
    public function test_sidebar_hides_links_the_role_cannot_open(): void
    {
        $hiddenFor = [
            'editor' => [
                '/admin/settings', '/admin/users', '/admin/roller', '/admin/redirects',
                '/admin/mail-templates', '/admin/mail-logs',
                '/admin/aktivite-loglari', '/admin/yedekler', '/admin/sistem-saglik',
            ],
            'moderator' => [
                '/admin/settings', '/admin/users', '/admin/roller', '/admin/redirects',
                '/admin/mail-templates', '/admin/mail-logs',
                '/admin/aktivite-loglari', '/admin/yedekler', '/admin/sistem-saglik',
                '/admin/pages', '/admin/blog-posts', '/admin/files',
                '/admin/menus', '/admin/analytics',
            ],
        ];

        foreach ($hiddenFor as $role => $routes) {
            $html = $this->actingAs($this->userWithRole($role))->get('/admin')->getContent();

            foreach ($routes as $route) {
                $this->assertStringNotContainsString(
                    'href="' . config('app.url') . $route . '"',
                    $html,
                    "{$role} sidebar'ında görünmemeli: {$route}",
                );
            }
        }
    }

    public function test_admin_sidebar_still_shows_everything(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))->get('/admin')->getContent();

        foreach (array_keys($this->matrix()) as $route) {
            $this->assertStringContainsString(
                'href="' . config('app.url') . $route . '"',
                $html,
                "admin sidebar'ında olmalı: {$route}",
            );
        }
    }

    public function test_a_user_without_a_panel_role_is_rejected(): void
    {
        $plain = $this->userWithRole('user');

        $this->actingAs($plain)->get('/admin')->assertForbidden();
    }

    /**
     * The moderator role is described as "Mesaj ve yorum yönetimi", so it must
     * actually be able to do both.
     */
    public function test_moderator_can_moderate_comments_but_not_delete_them(): void
    {
        $moderator = $this->userWithRole('moderator');

        $category = \App\Models\BlogCategory::create([
            'name'      => 'Duyurular',
            'slug'      => 'duyurular',
            'is_active' => true,
        ]);

        $post = \App\Models\BlogPost::create([
            'blog_category_id' => $category->id,
            'user_id'          => $moderator->id,
            'title'            => 'Örnek Yazı',
            'slug'             => 'ornek-yazi',
            'body'             => 'İçerik',
            'is_published'     => true,
            'published_at'     => now(),
        ]);

        $comment = \App\Models\BlogComment::create([
            'blog_post_id' => $post->id,
            'name'         => 'Ziyaretçi',
            'email'        => 'ziyaretci@example.test',
            'body'         => 'Güzel yazı.',
            'status'       => 'pending',
        ]);

        $this->actingAs($moderator)
            ->patch("/admin/blog-comments/{$comment->id}/approve")
            ->assertRedirect();

        $this->assertSame(\App\Enums\CommentStatus::Approved, $comment->fresh()->status);

        // Deleting stays with the admin.
        $this->actingAs($moderator)
            ->delete("/admin/blog-comments/{$comment->id}")
            ->assertForbidden();
    }
}
