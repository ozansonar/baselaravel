<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cascade belongs to the observers, not to ON DELETE CASCADE (laravel skill).
 *
 * A foreign key cascade only fires on a hard delete, so it can neither follow
 * a soft delete nor be undone by a restore. These tests pin the behaviour the
 * observers are supposed to provide instead.
 */
class ObserverCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    private function makeCategory(string $slug = 'duyurular'): BlogCategory
    {
        return BlogCategory::create([
            'name'      => ucfirst($slug),
            'slug'      => $slug,
            'is_active' => true,
        ]);
    }

    private function makePost(BlogCategory $category, ?User $author = null, string $slug = 'ornek-yazi'): BlogPost
    {
        return BlogPost::create([
            'blog_category_id' => $category->id,
            'user_id'          => $author?->id,
            'title'            => 'Örnek Yazı',
            'slug'             => $slug,
            'body'             => 'İçerik',
            'is_published'     => true,
            'published_at'     => now(),
        ]);
    }

    private function makeComment(BlogPost $post): BlogComment
    {
        return BlogComment::create([
            'blog_post_id' => $post->id,
            'name'         => 'Ziyaretçi',
            'email'        => 'ziyaretci@example.test',
            'body'         => 'Güzel yazı.',
            'status'       => 'approved',
        ]);
    }

    private function makeUser(string $email = 'cascade@example.test'): User
    {
        return User::create([
            'first_name' => 'Cascade',
            'last_name'  => 'Tester',
            'email'      => $email,
            'password'   => 'password',
            'is_active'  => true,
        ]);
    }

    public function test_soft_deleting_a_category_hides_its_posts_and_their_comments(): void
    {
        $category = $this->makeCategory();
        $post = $this->makePost($category);
        $this->makeComment($post);

        $category->delete();

        $this->assertSame(0, BlogPost::count(), 'Yazılar kategoriyle birlikte gizlenmedi');
        $this->assertSame(0, BlogComment::count(), 'Yorumlar yazıyla birlikte gizlenmedi');

        // Nothing was actually removed — a foreign key cascade would have.
        $this->assertSame(1, DB::table('blog_posts')->count());
        $this->assertSame(1, DB::table('blog_comments')->count());
    }

    public function test_restoring_a_category_brings_back_posts_and_comments(): void
    {
        $category = $this->makeCategory();
        $post = $this->makePost($category);
        $this->makeComment($post);

        $category->delete();
        $category->restore();

        $this->assertSame(1, BlogPost::count(), 'Yazılar geri gelmedi');
        $this->assertSame(1, BlogComment::count(), 'Yorumlar geri gelmedi');
    }

    public function test_soft_deleting_a_menu_hides_its_items_and_restoring_brings_them_back(): void
    {
        // A migration already seeds the header menu, so every assertion here is
        // scoped to the menu this test owns.
        $menu = Menu::create(['name' => 'Footer', 'location' => 'footer', 'is_active' => true]);

        MenuItem::create([
            'menu_id'    => $menu->id,
            'label'      => 'Anasayfa',
            'url'        => '/',
            'sort_order' => 0,
            'is_active'  => true,
        ]);

        $menu->delete();

        $this->assertSame(0, MenuItem::where('menu_id', $menu->id)->count());
        $this->assertSame(1, DB::table('menu_items')->where('menu_id', $menu->id)->count());

        $menu->restore();

        $this->assertSame(1, MenuItem::where('menu_id', $menu->id)->count());
    }

    public function test_force_deleting_a_category_removes_the_whole_tree(): void
    {
        $category = $this->makeCategory();
        $post = $this->makePost($category);
        $this->makeComment($post);

        $category->forceDelete();

        $this->assertSame(0, DB::table('blog_posts')->count());
        $this->assertSame(0, DB::table('blog_comments')->count());
        $this->assertSame(0, DB::table('blog_categories')->count());
    }

    /**
     * The old foreign key cascade deleted an author's posts along with them.
     * Content has to outlive the account.
     */
    public function test_force_deleting_an_author_keeps_their_posts(): void
    {
        $author = $this->makeUser();
        $category = $this->makeCategory();
        $post = $this->makePost($category, $author);

        $author->forceDelete();

        $this->assertSame(1, BlogPost::count(), 'Yazar silinince yazısı da gitti');
        $this->assertNull($post->fresh()->user_id, 'user_id null a düşmedi');
    }

    public function test_force_deleting_a_user_clears_roles_and_notifications(): void
    {
        $role = Role::firstOrCreate(['slug' => 'editor'], ['name' => 'Editör']);
        $user = $this->makeUser('roller@example.test');
        $user->roles()->attach($role);

        AdminNotification::create([
            'user_id' => $user->id,
            'type'    => 'test',
            'level'   => 'info',
            'title'   => 'Kişisel bildirim',
        ]);

        $user->forceDelete();

        $this->assertSame(0, DB::table('role_user')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('admin_notifications')->count());
    }

    public function test_soft_deleting_a_user_keeps_roles_so_a_restore_is_complete(): void
    {
        $role = Role::firstOrCreate(['slug' => 'editor'], ['name' => 'Editör']);
        $user = $this->makeUser('geri@example.test');
        $user->roles()->attach($role);

        $user->delete();
        $this->assertSame(1, DB::table('role_user')->where('user_id', $user->id)->count());

        $user->restore();
        $this->assertTrue($user->fresh()->hasRole('editor'));
    }

    public function test_no_foreign_key_still_cascades(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Bu kontrol SQLite şema okuması için yazıldı.');
        }

        foreach (['blog_posts', 'blog_comments', 'menu_items', 'admin_notifications'] as $table) {
            foreach (DB::select("PRAGMA foreign_key_list({$table})") as $fk) {
                $this->assertNotSame(
                    'CASCADE',
                    strtoupper((string) $fk->on_delete),
                    "{$table}.{$fk->from} hâlâ ON DELETE CASCADE kullanıyor",
                );
            }
        }
    }
}
