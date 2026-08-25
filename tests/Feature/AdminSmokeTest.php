<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    /**
     * Every admin GET route should render for an admin user.
     */
    public function test_admin_pages_render(): void
    {
        $role = Role::where('slug', 'admin')->firstOrFail();

        $admin = User::create([
            'first_name' => 'Test',
            'last_name'  => 'Admin',
            'email'      => 'smoke@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach($role);

        $routes = [
            '/admin',
            '/admin/pages',
            '/admin/pages/create',
            '/admin/sliders',
            '/admin/popups',
            '/admin/gallery-categories',
            '/admin/gallery-items',
            '/admin/faqs',
            '/admin/blog-categories',
            '/admin/blog-posts',
            '/admin/blog-comments',
            '/admin/contact-messages',
            '/admin/users',
            '/admin/roller',
            '/admin/redirects',
            '/admin/menus',
            '/admin/settings',
            '/admin/mail-templates',
            '/admin/mail-logs',
            '/admin/files',
            '/admin/profile',
            '/admin/analytics',
            '/admin/aktivite-loglari',
            '/admin/bildirimler',
            '/admin/yedekler',
            '/admin/sistem-saglik',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get($route);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Route {$route} returned {$response->getStatusCode()}",
            );
        }
    }
}
