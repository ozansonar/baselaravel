<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ön yüzün bütün sayfaları açılıyor mu?
 *
 * Panelin duman testi vardı, ön yüzün yoktu: ziyaretçinin gördüğü sayfaların
 * açıldığını doğrulayan tek şey, o sayfaya ait modül testinin varlığıydı.
 * Modülü olmayan sayfa (çevrimdışı sayfası, hesap ekranları, besleme) sessizce
 * bozulabiliyordu.
 *
 * Rota listesi elle yazılmıyor: dil önekli her GET rotası, parametre
 * istemiyorsa taranıyor.
 */
class FrontSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dosya indiren ya da yönlendirme yapan uçlar: gövde döndürmedikleri için
     * "sayfa açılıyor mu" sınavının konusu değiller, kendi testleri var.
     */
    private const SKIPPED = [
        'account.data.download',
        'locale.switch',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    /**
     * Herkese açık sayfalar oturum açmamış ziyaretçiye 200 dönüyor.
     */
    public function test_every_public_page_renders_for_a_guest(): void
    {
        // Kök adres dil önekine yönlendiriyor; sayfa orada başlıyor.
        $this->get('/')->assertRedirect();

        $pages = ['/tr', '/tr/blog', '/tr/galeri', '/tr/iletisim', '/tr/arama',
            '/tr/sikca-sorulan-sorular', '/tr/feed', '/tr/giris', '/tr/kayit',
            '/tr/sifremi-unuttum', '/offline', '/robots.txt', '/sitemap.xml',
            '/site.webmanifest', '/sw.js'];

        foreach ($pages as $page) {
            $response = $this->get($page);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$page} adresi {$response->getStatusCode()} döndü.",
            );
        }
    }

    /**
     * Hesap alanının bütün ekranları giriş yapmış kullanıcıya açılıyor.
     *
     * Liste rota tablosundan geliyor: hesap alanına yeni bir ekran eklendiğinde
     * kapsama kendiliğinden giriyor.
     */
    public function test_every_account_screen_renders_for_a_member(): void
    {
        $screens = $this->accountScreens();

        $this->assertGreaterThan(5, count($screens), 'Hesap ekranları taranamadı.');

        $member = $this->member();

        foreach ($screens as $uri) {
            $url = '/' . str_replace('{locale}', 'tr', $uri);
            $response = $this->actingAs($member)->get($url);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$url} adresi {$response->getStatusCode()} döndü.",
            );
        }
    }

    /**
     * Aynı ekranlar oturum açmamış ziyaretçiye kapalı.
     */
    public function test_no_account_screen_is_open_to_a_guest(): void
    {
        foreach ($this->accountScreens() as $uri) {
            $url = '/' . str_replace('{locale}', 'tr', $uri);
            $response = $this->get($url);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403],
                "{$url} adresi oturum açmamış ziyaretçiye {$response->getStatusCode()} döndü.",
            );
        }
    }

    /**
     * Hesap alanının dil öneki dışında parametre istemeyen GET rotaları.
     *
     * @return list<string>
     */
    private function accountScreens(): array
    {
        $screens = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! Str::startsWith($name, 'account.') || in_array($name, self::SKIPPED, true)) {
                continue;
            }

            // Dil öneki dışında parametre isteyen rotalar kayıt gerektiriyor.
            if (substr_count($uri, '{') > 1 || ! str_contains($uri, '{locale}')) {
                continue;
            }

            $screens[] = $uri;
        }

        sort($screens);

        return array_values(array_unique($screens));
    }

    private function member(): User
    {
        $user = User::create([
            'first_name' => 'Uye',
            'last_name'  => 'Kisi',
            'email'      => 'uye@example.test',
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'user')->firstOrFail()->id);

        return $user->fresh();
    }
}
