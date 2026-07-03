<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\UpdateMailLogOnFailed;
use App\Listeners\UpdateMailLogOnSent;
use App\Models\BlogComment;
use App\Models\Category;
use App\Models\CityLandingPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\User;
use App\Observers\AiImagePromptObserver;
use App\Observers\BlogCategoryObserver;
use App\Observers\BlogCommentObserver;
use App\Observers\BlogPostObserver;
use App\Observers\CategoryObserver;
use App\Observers\CityLandingPageObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuObserver;
use App\Observers\OrderObserver;
use App\Observers\PageObserver;
use App\Observers\ProductObserver;
use App\Observers\RedirectObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
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
        //
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

        User::observe(UserObserver::class);
        Category::observe(CategoryObserver::class);
        Product::observe(ProductObserver::class);
        Order::observe(OrderObserver::class);
        BlogComment::observe(BlogCommentObserver::class);
        Menu::observe(MenuObserver::class);
        MenuItem::observe(MenuItemObserver::class);
        Redirect::observe(RedirectObserver::class);
        Page::observe(PageObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        BlogCategory::observe(BlogCategoryObserver::class);
        CityLandingPage::observe(CityLandingPageObserver::class);
        \App\Models\AiImagePrompt::observe(AiImagePromptObserver::class);

        // Audit Trail — kritik modellerde otomatik aktivite logu
        \App\Models\InstagramPost::observe(\App\Observers\AuditObserver::class);
        \App\Models\Setting::observe(\App\Observers\AuditObserver::class);
        \App\Models\MediaLibraryImage::observe(\App\Observers\AuditObserver::class);
        \App\Models\BulkImport::observe(\App\Observers\AuditObserver::class);
        \App\Models\SpecialDay::observe(\App\Observers\AuditObserver::class);
        \App\Models\AiImagePrompt::observe(\App\Observers\AuditObserver::class);
        Order::observe(\App\Observers\AuditObserver::class);

        // AI log fail bildirimi — herhangi bir AI servisi (blog, topic, ürün,
        // sayfa, şehir) fail olduğunda Telegram + in-app bell otomatik.
        \App\Models\AiLog::observe(\App\Observers\AiLogObserver::class);

        // Mail log status tracking via events
        Event::listen(MessageSent::class, [UpdateMailLogOnSent::class, 'handle']);
        Event::listen(JobFailed::class, [UpdateMailLogOnFailed::class, 'handleJobFailed']);

        // Share dynamic header menu with navbar partial
        View::composer('partials.navbar', function (\Illuminate\View\View $view): void {
            $view->with('headerMenu', app(\App\Services\MenuService::class)->getByLocation('header'));
        });

        // Share active city landing pages with footer (Modül 8 — internal
        // linking güçlendirme: footer'daki "Hizmet Bölgelerimiz" linkleri
        // her sayfa görüntülenmesinde city sayfalarına link juice akıtır).
        View::composer('partials.footer', function (\Illuminate\View\View $view): void {
            $view->with(
                'footerCityPages',
                app(\App\Services\CityLandingPageService::class)->activePages(),
            );
        });

        // Share active popups with front layout
        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $routeName = request()->route()?->getName() ?? '';

            $pageMap = [
                'home'           => 'home',
                'products.all'   => 'products',
                'products.index' => 'products',
                'products.show'  => 'product_detail',
                'contact'        => 'contact',
                'blog.index'     => 'blog',
                'blog.category'  => 'blog',
                'blog.show'      => 'blog',
                'gallery'        => 'gallery',
                'faq'            => 'faq',
                'cart.index'     => 'cart',
            ];

            $currentPage = $pageMap[$routeName] ?? 'other';
            $popups = app(\App\Services\PopupService::class)->getForPage($currentPage);

            $view->with(compact('popups', 'currentPage'));
        });

        // Share admin badge counts once across sidebar & topbar
        View::composer(['partials.admin.sidebar', 'partials.admin.topbar'], function (\Illuminate\View\View $view): void {
            static $unreadMessageCount = null;
            static $pendingReviewCount = null;

            $unreadMessageCount ??= \App\Models\ContactMessage::where('is_read', false)->count();
            $pendingReviewCount ??= \App\Models\ProductReview::where('status', 'pending')->count();

            $view->with(compact('unreadMessageCount', 'pendingReviewCount'));
        });
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
