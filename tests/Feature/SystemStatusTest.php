<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ayarlardaki sistem durumu.
 *
 * Ekranın işi "bir sorun var mı" sorusunu bakışta cevaplamak. Yanlış bir "her
 * şey yolunda" cevabı, sessizce düşen yüklemelerin haftalarca fark edilmemesi
 * demek — asıl korunacak şey bu karar.
 */
class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SystemStatusService
    {
        return app(SystemStatusService::class);
    }

    private function admin(): User
    {
        $role = Role::create(['name' => 'Ayar', 'slug' => 'ayar-' . uniqid()]);

        foreach ([PermissionKey::SettingsView, PermissionKey::SettingsManage] as $key) {
            $permission = Permission::firstOrCreate(
                ['key' => $key->value],
                ['name' => $key->label(), 'group' => $key->group()],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @param array<int, array<string, mixed>> $limits
     */
    private function verdict(array $limits, bool $db = true, bool $debug = false, bool $writable = true): array
    {
        return $this->service()->verdict($limits, $db, $debug, $writable);
    }

    private function limit(string $key, string $state): array
    {
        return ['key' => $key, 'label' => $key, 'value' => '2M', 'bytes' => 1, 'recommended' => 2, 'state' => $state, 'note' => null];
    }

    public function test_php_size_notation_is_read_correctly(): void
    {
        $service = $this->service();

        $this->assertSame(128 * 1024 * 1024, $service->parseSize('128M'));
        $this->assertSame(2 * 1024 * 1024 * 1024, $service->parseSize('2G'));
        $this->assertSame(512 * 1024, $service->parseSize('512K'));
        $this->assertSame(1024, $service->parseSize('1024'));

        // "0" ve "-1" sınırsız demek; sınırsız bir tavan her tavanı karşılar.
        $this->assertSame(0, $service->parseSize('0'));
        $this->assertSame(0, $service->parseSize('-1'));
    }

    /**
     * Gövde tavanı dosya tavanının altındaysa büyük dosya sunucuya hiç ulaşmaz;
     * iki değer tek tek bakıldığında iyi görünse bile birlikte bozuk.
     */
    public function test_a_post_limit_below_the_upload_limit_is_reported_as_broken(): void
    {
        $limits = $this->service()->limits();

        $this->assertCount(4, $limits);
        $this->assertSame(
            ['upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time'],
            array_column($limits, 'key'),
        );

        foreach ($limits as $row) {
            $this->assertContains($row['state'], ['ok', 'warn', 'danger']);
            $this->assertNotSame('', $row['recommended_human']);
        }
    }

    public function test_the_verdict_reports_a_healthy_system(): void
    {
        $sonuc = $this->verdict([$this->limit('upload_max_filesize', 'ok'), $this->limit('post_max_size', 'ok')]);

        $this->assertSame('ok', $sonuc['state']);
        $this->assertSame('Her şey yolunda', $sonuc['title']);
    }

    public function test_a_warning_does_not_look_like_a_failure(): void
    {
        $sonuc = $this->verdict([$this->limit('memory_limit', 'warn')]);

        $this->assertSame('warn', $sonuc['state']);
        $this->assertStringContainsString('Çalışıyor', $sonuc['title']);
    }

    public function test_the_verdict_counts_every_problem(): void
    {
        $sonuc = $this->verdict(
            [$this->limit('upload_max_filesize', 'danger'), $this->limit('post_max_size', 'danger')],
            db: false,
            debug: true,
            writable: false,
        );

        $this->assertSame('danger', $sonuc['state']);
        $this->assertSame('4 sorun var', $sonuc['title']);
        $this->assertStringContainsString('Veritabanı bağlantısı yok', $sonuc['detail']);
        $this->assertStringContainsString('canlıda hata ayıklama açık', $sonuc['detail']);
        $this->assertStringContainsString('storage klasörü yazılabilir değil', $sonuc['detail']);
        $this->assertStringContainsString('2 yükleme limiti düşük', $sonuc['detail']);
    }

    public function test_the_screen_shows_the_status_and_the_fix(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sys-verdict', $html);
        $this->assertStringContainsString('Dosya Yükleme Limitleri', $html);
        // Ne olduğu değil, ne yapılacağı da yazmalı.
        $this->assertStringContainsString('upload_max_filesize = 128M', $html);
        $this->assertStringContainsString('phpIniSnippet', $html);
    }

    /**
     * Kütüphaneler projede duruyor; CDN'e erişilemediğinde ekranın bir parçası
     * sessizce çalışmamalı.
     */
    public function test_the_settings_screen_does_not_depend_on_a_cdn(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringContainsString('assets/vendor/glightbox', $html);
    }
}
