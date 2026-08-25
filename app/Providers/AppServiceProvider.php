<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\PermissionKey;
use App\Listeners\UpdateMailLogOnFailed;
use App\Listeners\UpdateMailLogOnSent;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use App\Observers\BlogCategoryObserver;
use App\Observers\BlogCommentObserver;
use App\Observers\BlogPostObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuObserver;
use App\Observers\PageObserver;
use App\Observers\RedirectObserver;
use App\Observers\UserObserver;
use App\Services\MenuItemService;
use App\Services\MenuService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The navigation renders twice per page (desktop and the mobile
        // drawer) and asks these for every item, so they keep a per-request
        // memo — which only helps if the instance is shared.
        $this->app->singleton(MenuService::class);
        $this->app->singleton(MenuItemService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Carbon locale Türkçe — translatedFormat / isoFormat / diffForHumans
        // 'Nis', 'Nisan', 'Pzt' gibi Türkçe çıktı versin. config('app.locale')
        // Laravel kendi locale'i (validation/translation) için; Carbon ayrı.
        \Carbon\Carbon::setLocale(config('app.locale', 'tr'));

        $this->configureRateLimiting();
        $this->configureAuthorization();

        User::observe(UserObserver::class);
        BlogComment::observe(BlogCommentObserver::class);
        Menu::observe(MenuObserver::class);
        MenuItem::observe(MenuItemObserver::class);
        Redirect::observe(RedirectObserver::class);
        Page::observe(PageObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        BlogCategory::observe(BlogCategoryObserver::class);

        // Audit Trail — automatic activity log on critical models
        \App\Models\Setting::observe(\App\Observers\AuditObserver::class);

        // Mail log status tracking via events
        Event::listen(MessageSent::class, [UpdateMailLogOnSent::class, 'handle']);
        Event::listen(JobFailed::class, [UpdateMailLogOnFailed::class, 'handleJobFailed']);

        // Share dynamic header menu with navbar partial
        View::composer('partials.navbar', function (\Illuminate\View\View $view): void {
            $view->with('headerMenu', app(\App\Services\MenuService::class)->getByLocation('header'));
        });

        // Share active popups for the current page with the front layout
        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $pageMap = [
                'home'          => 'home',
                'blog.index'    => 'blog',
                'blog.category' => 'blog',
                'blog.show'     => 'blog',
                'gallery'       => 'gallery',
                'contact'       => 'contact',
                'faq'           => 'faq',
            ];

            $currentPage = $pageMap[request()->route()?->getName() ?? ''] ?? 'other';
            $view->with('popups', app(\App\Services\PopupService::class)->getForPage($currentPage));
        });

        // Share admin badge counts once across sidebar & topbar
        View::composer(['partials.admin.sidebar', 'partials.admin.topbar'], function (\Illuminate\View\View $view): void {
            static $unreadMessageCount = null;

            $unreadMessageCount ??= \App\Models\ContactMessage::where('is_read', false)->count();

            $view->with(compact('unreadMessageCount'));
        });
    }

    /**
     * Gates for admin areas that have no backing Eloquent model, so they
     * cannot be covered by a Policy.
     */
    private function configureAuthorization(): void
    {
        // These areas have no Eloquent model to hang a Policy on, so they are
        // Gates. The decision still comes from the database permissions.
        Gate::define('manage-backups', fn (User $user): bool => $user->hasPermission(PermissionKey::BackupsManage));
        Gate::define('view-backups', fn (User $user): bool => $user->hasPermission(PermissionKey::BackupsView));
        Gate::define('delete-backups', fn (User $user): bool => $user->hasPermission(PermissionKey::BackupsDelete));
        Gate::define('view-system-health', fn (User $user): bool => $user->hasPermission(PermissionKey::SystemHealthView));
        Gate::define('view-analytics', fn (User $user): bool => $user->hasPermission(PermissionKey::AnalyticsView));
        Gate::define('upload-editor-media', fn (User $user): bool => $user->hasPermission(PermissionKey::EditorUpload));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $key = $request->input('email', '') . '|' . $request->ip();

            return Limit::perMinute(5)->by($key)->response(function () {
                return back()->withErrors([
                    'email' => 'Çok fazla giriş denemesi yaptınız. Lütfen 1 dakika bekleyin.',
                ]);
            });
        });

        RateLimiter::for('contact', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->ip())->response(function () {
                return back()->withErrors([
                    'message' => 'Çok fazla mesaj gönderdiniz. Lütfen birkaç dakika bekleyin.',
                ]);
            });
        });

        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->ip())->response(function () {
                return back()->withErrors([
                    'email' => 'Çok fazla kayıt denemesi yaptınız. Lütfen birkaç dakika bekleyin.',
                ]);
            });
        });
    }
}
