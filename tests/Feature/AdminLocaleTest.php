<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yönetim paneli her zaman Türkçe.
 *
 * Panelin metinleri koda Türkçe yazılmış ama dil, ziyaretçinin ön yüzde
 * seçtiğini izliyordu: başlıklar Türkçe, tarihler İngilizceydi ("1 day ago"),
 * doğrulama mesajları da öyle. Buradaki testler panelin dilinin ön yüz
 * tercihinden bağımsız olduğunu ve ön yüzün çok dilli kalmaya devam ettiğini
 * doğruluyor.
 */
class AdminLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ön yüzün İngilizceyi gerçekten desteklemesi gerekiyor: desteklenmeyen bir
     * kod SetLocale tarafından yok sayılıp varsayılana düşerdi ve test panelin
     * dilini sabitleyen katmanı değil, eksik dil kaydını sınamış olurdu.
     */
    private function enableEnglish(): void
    {
        Language::query()->updateOrCreate(
            ['code' => 'en'],
            ['name' => 'İngilizce', 'native_name' => 'English', 'flag' => '🇬🇧', 'is_active' => true, 'sort_order' => 2],
        );
    }

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Dil',
            'last_name'  => 'Yöneticisi',
            'email'      => 'locale-admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    /**
     * Oturumda İngilizce seçili olsa bile panel Türkçe açılmalı.
     */
    public function test_the_panel_stays_turkish_when_the_visitor_picked_another_language(): void
    {
        $this->enableEnglish();

        $this->withSession([SetLocale::SESSION_KEY => 'en'])
            ->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertSame('tr', app()->getLocale());
        $this->assertSame('tr', Carbon::getLocale());
    }

    /**
     * Tarayıcı İngilizce isteyip oturumda tercih yokken de aynı sonuç.
     */
    public function test_the_accept_language_header_does_not_change_the_panel(): void
    {
        $this->enableEnglish();

        $this->actingAs($this->admin())
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertSame('tr', app()->getLocale());
        $this->assertSame('tr', Carbon::getLocale());
    }

    /**
     * Asıl belirti: göreli tarihler. Panelde Türkçe yazmalı.
     */
    public function test_relative_dates_are_rendered_in_turkish(): void
    {
        $this->enableEnglish();

        $this->withSession([SetLocale::SESSION_KEY => 'en'])
            ->actingAs($this->admin())
            ->get(route('admin.mail-templates.index'))
            ->assertOk()
            ->assertSee('önce')
            ->assertDontSee('ago');
    }

    /**
     * Doğrulama mesajları da panelin dilinden geliyor; İngilizce dönüyorlardı.
     */
    public function test_validation_messages_come_back_in_turkish(): void
    {
        $this->enableEnglish();

        $errors = $this->withSession([SetLocale::SESSION_KEY => 'en'])
            ->actingAs($this->admin())
            ->post(route('admin.campaigns.store'), [])
            ->assertSessionHasErrors('name')
            ->getSession()
            ->get('errors');

        $this->assertStringContainsString('zorunlu', $errors->first('name'));
    }

    /**
     * Panel sabitlenirken ön yüz çok dilli kalmalı: dil URL'de yazıyorsa o
     * geçerli olmayı sürdürüyor.
     */
    public function test_the_front_site_still_follows_the_url_language(): void
    {
        $this->enableEnglish();

        $this->get('/en')->assertOk();

        $this->assertSame('en', app()->getLocale());
    }
}
