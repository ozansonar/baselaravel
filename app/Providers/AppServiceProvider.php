<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\PermissionKey;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Campaign;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Redirect;
use App\Models\User;
use App\Observers\BlogCategoryObserver;
use App\Observers\CampaignObserver;
use App\Observers\BlogCommentObserver;
use App\Observers\BlogPostObserver;
use App\Observers\MenuItemObserver;
use App\Observers\MenuObserver;
use App\Observers\PageObserver;
use App\Observers\RedirectObserver;
use App\Observers\UserObserver;
use App\Services\MenuItemService;
use App\Services\MenuService;
use App\Services\LanguageService;
use App\Services\LocalizedUrlService;
use App\Services\TranslationService;
use App\Translation\DatabaseOverrideLoader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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

        // Shared instance on purpose: the loader holds one of these and keeps a
        // per-request memo of the overrides. A second instance would clear its
        // own memo on save while the loader kept serving the stale one.
        $this->app->singleton(TranslationService::class);

        // Dil listesi ve adres çevirici istek içinde onlarca kez soruluyor
        // (locale kapsamı, hreflang, dil değiştirici, kanonik, alt bilgi
        // bağlantıları). İkisi de cevabı örnekte saklıyor; singleton
        // olmasalardı her app() çağrısı yeni bir örnek doğurur ve saklama
        // hiç işe yaramazdı.
        $this->app->singleton(\App\Services\TranslationGroupResolver::class);
        // Sitenin yedek alt metni istek başına bir kez seçilsin.
        $this->app->singleton(\App\Services\ImageAltResolver::class);
        // Dil dosyasının varlığı istek başına bir kez bakılsın.
        $this->app->singleton(\App\Services\ValidationEngineLocale::class);
        // Adres haritası istek başına bir kez okunsun: her çağrıda yeni bir
        // örnek doğarsa önbellek sürücüsüne tekrar tekrar gidilir.
        $this->app->singleton(\App\Services\CustomRouteService::class);
        $this->app->singleton(LanguageService::class);
        $this->app->singleton(LocalizedUrlService::class);

        // Interface texts stay in lang/ files; the panel's edits are laid over
        // them at load time. Decorating the loader keeps every __() call and
        // every Blade @lang untouched.
        $this->app->extend('translation.loader', fn ($loader, $app) => new DatabaseOverrideLoader(
            $loader,
            $app->make(TranslationService::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyConfiguredTimezone();

        Schema::defaultStringLength(191);

        // Front routes carry their language in the URL. Mails, the scheduler
        // and console commands build those URLs with no request to read the
        // language from, so the site default stands in and route('home') keeps
        // working everywhere; SetLocale overrides it per request.
        URL::defaults(['locale' => $this->defaultLocaleCode()]);

        // Carbon locale Türkçe — translatedFormat / isoFormat / diffForHumans
        // 'Nis', 'Nisan', 'Pzt' gibi Türkçe çıktı versin. config('app.locale')
        // Laravel kendi locale'i (validation/translation) için; Carbon ayrı.
        \Carbon\Carbon::setLocale(config('app.locale', 'tr'));

        $this->configureRateLimiting();
        $this->configureAuthorization();

        Campaign::observe(CampaignObserver::class);

        User::observe(UserObserver::class);
        BlogComment::observe(BlogCommentObserver::class);
        Menu::observe(MenuObserver::class);
        MenuItem::observe(MenuItemObserver::class);
        Redirect::observe(RedirectObserver::class);
        Page::observe(PageObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        BlogCategory::observe(BlogCategoryObserver::class);

        // Gösterge panosunun sayıları: besleyen dört model değiştiğinde
        // önbellek düşüyor. Tek tek servislere bırakılsaydı yeni bir yol
        // eklendiğinde yine unutulurdu.
        foreach ([User::class, BlogPost::class, Page::class, \App\Models\ContactMessage::class] as $model) {
            $model::observe(\App\Observers\DashboardStatsObserver::class);
        }

        // Denetim izi — kim ne zaman ne değiştirdi.
        //
        // Liste dizi üzerinden geçiyor: yeni bir kritik model eklendiğinde tek
        // satır yetiyor ve gözden kaçmıyor.
        //
        // Kapsam bilinçli olarak dar: içerik modelleri (sayfa, blog, galeri)
        // her kaydetmede satır üretir ve denetim izini kendi gürültüsünde
        // boğar — 90 günlük saklama süresiyle asıl aranan kayıt bulunamaz
        // hâle gelir. Buradakiler erişimi, yetkiyi, gönderilen mailleri ve
        // ziyaretçinin nereye gideceğini belirleyenler. İçeriğin geçmişi
        // denetim izinin değil sürümlemenin işi.
        foreach ([
            \App\Models\Setting::class,
            \App\Models\User::class,
            \App\Models\Role::class,
            \App\Models\Redirect::class,
            \App\Models\CustomRoute::class,
            \App\Models\MailTemplate::class,
            \App\Models\Language::class,
        ] as $audited) {
            $audited::observe(\App\Observers\AuditObserver::class);
        }

        // Giriş, çıkış ve başarısız deneme hiçbir satırı değiştirmiyor, yani
        // gözlemci onları göremez. Denetimin ilk sorduğu şeyler de bunlar.
        Event::subscribe(\App\Listeners\AuditAuthenticationEvents::class);

        // Mail olaylarının dinleyicileri app/Listeners dizininden kendiliğinden
        // bağlanıyor (LogOutgoingMail, UpdateMailLogOnFailed). Elle bir kez daha
        // bağlanırlarsa her mail iki kez kaydedilir.

        // Share dynamic header menu with navbar partial
        View::composer('partials.navbar', function (\Illuminate\View\View $view): void {
            $view->with('headerMenu', app(\App\Services\MenuService::class)->getByLocation('header'));
        });

        // Alt bilginin bağlantı sütunları da menü modülünden geliyor.
        View::composer('partials.footer', function (\Illuminate\View\View $view): void {
            $view->with('footerMenu', app(\App\Services\MenuService::class)->getByLocation('footer'));
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

    /**
     * İsteğin dili — uygulamanınki değil.
     *
     * Sınırlayıcının yanıtı SetLocale'den önce üretiliyor (ThrottleRequests
     * çerçevenin öncelik listesinde, SetLocale değil), yani o anda
     * app()->getLocale() hâlâ varsayılan dili söylüyor: İngilizce ziyaretçi
     * Türkçe uyarı alıyordu.
     */
    private function localeOf(Request $request): string
    {
        return app(\App\Services\LocaleResolver::class)->forRequest($request);
    }

    /**
     * Uyarı metinleri panelden yönetiliyor.
     *
     * __() burada değil, yanıt kapanışının içinde: kapanış istek anında
     * çalışıyor, yani metin ziyaretçinin dilinde çözülüyor. Sınırlayıcı ise
     * bir kez, açılışta kuruluyor.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $key = $request->input('email', '') . '|' . $request->ip();

            return Limit::perMinute(5)->by($key)->response(function () use ($request) {
                return back()->withErrors([
                    'email' => __('site.forms.throttle_login', [], $this->localeOf($request)),
                ]);
            });
        });

        RateLimiter::for('contact', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->ip())->response(function () use ($request) {
                return back()->withErrors([
                    'message' => __('site.forms.throttle_contact', [], $this->localeOf($request)),
                ]);
            });
        });

        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(3)->by($request->ip())->response(function () use ($request) {
                return back()->withErrors([
                    'email' => __('site.forms.throttle_register', [], $this->localeOf($request)),
                ]);
            });
        });
    }

    /**
     * Apply the timezone chosen in the panel.
     *
     * Deliberately here rather than in a middleware: the scheduler runs in the
     * console, where middleware never fires. With it in a middleware the site
     * and the cron wrote timestamps in two different timezones — into the same
     * columns.
     */
    /**
     * The site's default language, safe to ask for before the database exists.
     *
     * boot() also runs during a fresh install and during migrations, where the
     * languages table may not be there yet.
     */
    private function defaultLocaleCode(): string
    {
        try {
            return app(\App\Services\LanguageService::class)->defaultCode();
        } catch (\Throwable) {
            return (string) config('app.locale', 'tr');
        }
    }

    private function applyConfiguredTimezone(): void
    {
        try {
            $timezone = Setting::getValue('app_timezone');
        } catch (\Throwable) {
            // Before the settings table exists (a fresh clone, mid-migration)
            // the config default is the right answer.
            return;
        }

        if (! is_string($timezone) || $timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }
}
