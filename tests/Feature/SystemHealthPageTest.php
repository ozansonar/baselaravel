<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sistem sağlık ekranı.
 *
 * Ekranın işi bir listeyi göstermek değil, "şu an neyle ilgilenmeliyim"
 * sorusunu cevaplamak: sorunlu kontroller başta durmalı ve her biri ne
 * yapılacağını söylemeli.
 */
class SystemHealthPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Sistem',
            'last_name'  => 'Yöneticisi',
            'email'      => 'health-admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    private function health(): array
    {
        return app(HealthCheckService::class)->runAll();
    }

    public function test_the_page_opens_for_an_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.system-health.index'))
            ->assertOk()
            ->assertSee('Sistem Sağlık');
    }

    /**
     * Alt başlık "10 kritik sistem kontrolü — ... IG token, AI, son paylaşım"
     * yazıyordu; bu projede öyle kontroller yok ve sayı da tutmuyordu. Metin
     * artık çalışan kontrollerden üretiliyor.
     */
    public function test_the_subtitle_describes_the_checks_that_actually_run(): void
    {
        $health = $this->health();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.system-health.index'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString($health['summary']['total'] . ' kontrol çalıştırıldı', $html);
        $this->assertStringNotContainsString('IG token', $html);
        $this->assertStringNotContainsString('son paylaşım', $html);

        foreach ($health['checks'] as $check) {
            $this->assertStringContainsString($check['label'], $html);
        }
    }

    /**
     * Ekranı açan kişi önce neyin bozuk olduğunu görmeli.
     */
    public function test_the_troubled_checks_come_first(): void
    {
        $weights = ['critical' => 0, 'warning' => 1, 'ok' => 2];
        $seen = array_map(
            static fn (array $check): int => $weights[$check['status']],
            $this->health()['checks'],
        );

        $sorted = $seen;
        sort($sorted);

        $this->assertSame($sorted, $seen, 'Sorunlu kontroller listenin başında değil');
    }

    /**
     * Her kontrol ne işe yaradığını söylemeli; ipucu ise yalnızca sorun varken
     * anlamlı, sağlıklı kartta ekranı doldurur.
     */
    public function test_every_check_explains_itself(): void
    {
        foreach ($this->health()['checks'] as $check) {
            $this->assertArrayHasKey('icon', $check);
            $this->assertNotSame('', $check['what'], "{$check['key']} kontrolünün açıklaması yok");

            if ($check['status'] === HealthCheckService::STATUS_OK) {
                $this->assertNull($check['hint'], "{$check['key']} sağlıklıyken ipucu göstermemeli");
            } else {
                $this->assertNotEmpty($check['hint'], "{$check['key']} sorunluyken ne yapılacağını söylemeli");
            }
        }
    }

    /**
     * Yedek alınmamışsa kart hem uyarı vermeli hem de yedekler sayfasına
     * götürmeli.
     */
    public function test_a_failing_check_points_at_the_page_that_fixes_it(): void
    {
        Setting::query()->where('key', 'last_backup_at')->delete();

        $health = $this->health();
        $backup = collect($health['checks'])->firstWhere('key', 'last_backup');

        $this->assertSame(HealthCheckService::STATUS_WARNING, $backup['status']);
        $this->assertNotEmpty($backup['hint']);
        $this->assertStringContainsString('/admin/yedekler', (string) $backup['url']);

        $this->actingAs($this->admin())
            ->get(route('admin.system-health.index'))
            ->assertOk()
            ->assertSee('İlgili sayfaya git');
    }

    /**
     * JSON ucu izleme araçları için; ekranla aynı veriyi vermeli.
     */
    public function test_the_json_endpoint_serves_the_same_payload(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.system-health.json'))
            ->assertOk();

        $response->assertJsonStructure([
            'summary' => ['ok', 'warning', 'critical', 'total', 'overall'],
            'checked_at',
            'checks' => [['key', 'label', 'status', 'message', 'detail', 'icon', 'what', 'hint', 'url']],
        ]);
    }

    public function test_a_role_without_the_permission_cannot_see_the_page(): void
    {
        $this->seedAuthorization();

        $moderator = User::create([
            'first_name' => 'Moderatör',
            'last_name'  => 'Kullanıcı',
            'email'      => 'health-moderator@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $moderator->roles()->attach(Role::where('slug', 'moderator')->firstOrFail());

        $this->actingAs($moderator)
            ->get(route('admin.system-health.index'))
            ->assertForbidden();
    }
}
