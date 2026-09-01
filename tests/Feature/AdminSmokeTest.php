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
 * Panelin bütün ekranları açılıyor mu?
 *
 * Rota listesi elle yazılmıyor: rota tablosundan çıkarılıyor. Elle yazılan
 * listenin sorunu, yeni bir ekran eklendiğinde kapsama girmemesiydi — panel
 * otuzdan fazla ekrana çıkmışken duman testi yirmi altısına bakıyordu ve
 * aradaki fark (kuyruk, raporlar, içerikler, diller, kampanyalar, yardım…)
 * hiç açılmadan kalıyordu.
 */
class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kapsam dışı bırakılan rotalar ve gerekçeleri.
     *
     * Buraya bir rota eklemek bilinçli bir karardır: ekran gezilmiyorsa
     * sebebi yazılı olmalı.
     */
    private const SKIPPED = [
        // Oturum kapatma bir GET değil, ama listede görünürse gezilmemeli.
        'admin.logout',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    /**
     * Parametresiz her admin GET rotası yönetici için açılıyor.
     */
    public function test_every_admin_screen_renders(): void
    {
        $admin = $this->admin();
        $screens = $this->adminScreens();

        // Tarama gerçekten çalışıyor mu? Rota tablosu okunamazsa test sessizce
        // hiçbir şey gezmeden yeşil biterdi.
        $this->assertGreaterThan(40, count($screens), 'Admin ekranları taranamadı.');

        foreach ($screens as $uri) {
            $response = $this->actingAs($admin)->get('/' . $uri);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "/{$uri} adresi {$response->getStatusCode()} döndü.",
            );
        }
    }

    /**
     * Aynı ekranlar yetkisiz kullanıcıya kapalı.
     *
     * Panelin kapısı ortak bir ara katmanla korunuyor; bu testin işi o kapının
     * bütün ekranların önünde durduğunu doğrulamak — tek bir rotanın grubun
     * dışında tanımlanması yetiyor ve o ekran herkese açık kalıyor.
     */
    public function test_no_admin_screen_is_open_to_the_public(): void
    {
        foreach ($this->adminScreens() as $uri) {
            $response = $this->get('/' . $uri);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403],
                "/{$uri} adresi oturum açmamış ziyaretçiye {$response->getStatusCode()} döndü.",
            );
        }
    }

    /**
     * Parametresiz admin GET rotalarının adres kalıpları.
     *
     * Dosya indiren dışa aktarma ucu dışarıda: onun kendi test sınıfı var
     * (`ListExportTest`) ve buradaki her biçim için ayrı ayrı dosya üretmek
     * duman testini gereksiz ağırlaştırırdı.
     *
     * @return list<string>
     */
    private function adminScreens(): array
    {
        $screens = [];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! Str::startsWith($name, 'admin.') || in_array($name, self::SKIPPED, true)) {
                continue;
            }

            // Parametre isteyen rotalar kayıt gerektiriyor; onları kendi
            // modüllerinin testleri geziyor.
            if (str_contains($uri, '{')) {
                continue;
            }

            $screens[] = $uri;
        }

        sort($screens);

        return array_values(array_unique($screens));
    }

    private function admin(): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name'  => 'Admin',
            'email'      => 'smoke@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }
}
