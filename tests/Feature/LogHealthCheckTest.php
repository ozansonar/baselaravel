<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Log dizini kontrolü.
 *
 * Dönmeyen bir log dosyası sessizce büyüyor ve dolduğu gün yalnız log yazımını
 * değil yüklemeyi, yedeklemeyi ve oturum yazımını da durduruyor. Disk kontrolü
 * bunu ancak disk tamamen dolduğunda görüyor; bu kontrol sebebe bakıyor.
 */
class LogHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sınama kendi log dizinini kullanıyor. Kontrol dizini `storage/logs`
     * varsaymıyor, kanalın kendi `path` değerinden çözüyor — bu yüzden testi
     * geliştiricinin gerçek `laravel.log` boyutuna bağlamak gerekmiyor.
     */
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/logs');

        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0o775, true);
        }

        $this->rotationOn();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.log') ?: [] as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function rotationOn(): void
    {
        config([
            'logging.default'                 => 'stack',
            'logging.channels.stack.channels' => ['daily'],
            'logging.channels.daily.path'     => $this->dir . '/laravel.log',
        ]);
    }

    private function rotationOff(): void
    {
        config([
            'logging.default'                 => 'stack',
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.path'    => $this->dir . '/laravel.log',
        ]);
    }

    /**
     * Boyutu olan ama diskte yer kaplamayan bir dosya: ftruncate veriyi
     * yazmadan dosyayı uzatıyor, filesize() istenen boyutu bildiriyor. Aksi
     * hâlde eşikleri sınamak için gigabaytlarca gerçek veri yazmak gerekirdi.
     */
    private function writeLogOfSize(int $bytes, string $name = 'health-check-probe.log'): void
    {
        $path = $this->dir . '/' . $name;

        $handle = fopen($path, 'w');
        ftruncate($handle, $bytes);
        fclose($handle);

        $this->assertSame($bytes, filesize($path), 'Sınama dosyası beklenen boyutta değil');
    }

    /** @return array<string, mixed> */
    private function logCheck(): array
    {
        $checks = app(HealthCheckService::class)->runAll()['checks'];

        $found = null;

        foreach ($checks as $check) {
            if ($check['key'] === 'logs') {
                $found = $check;
            }
        }

        $this->assertNotNull($found, 'Sağlık ekranında log kontrolü yok');

        return $found;
    }

    public function test_the_check_appears_on_the_health_screen(): void
    {
        $check = $this->logCheck();

        $this->assertSame('Log Dizini', $check['label']);
        $this->assertNotSame('', $check['what']);
    }

    public function test_a_small_log_directory_with_rotation_on_is_healthy(): void
    {
        $this->rotationOn();
        $this->writeLogOfSize(4096);

        $check = $this->logCheck();

        $this->assertSame(HealthCheckService::STATUS_OK, $check['status']);
        $this->assertStringContainsString('günlük dönüş açık', (string) $check['detail']);
    }

    /**
     * Asıl yakalanmak istenen kusur: LOG_STACK=single ile dosya hiç dönmüyor.
     */
    public function test_rotation_being_off_is_reported(): void
    {
        $this->rotationOff();
        $this->writeLogOfSize(4096);

        $this->assertStringContainsString('dönüş KAPALI', (string) $this->logCheck()['detail']);
    }

    /**
     * Dönüş kapalıyken sıfırdan uyarmak gürültü olurdu; eşik uyarının fark
     * edilmesi için bol zaman bırakıyor ama dosya büyümeden önce çalıyor.
     */
    public function test_an_unrotated_log_warns_once_it_has_grown(): void
    {
        $this->rotationOff();
        $this->writeLogOfSize(HealthCheckService::LOG_UNROTATED_WARNING_BYTES + 1024);

        $check = $this->logCheck();

        $this->assertSame(HealthCheckService::STATUS_WARNING, $check['status']);
        $this->assertStringContainsString('dönüş', $check['message']);
    }

    public function test_a_small_unrotated_log_does_not_nag(): void
    {
        $this->rotationOff();
        $this->writeLogOfSize(1024);

        $this->assertSame(HealthCheckService::STATUS_OK, $this->logCheck()['status']);
    }

    /**
     * Dönüş açık olsa bile boyut kendi başına bir sinyal: 14 günde bu kadar
     * log biriktiyse ortada gürültü yapan bir hata vardır.
     */
    public function test_a_large_directory_warns_even_with_rotation_on(): void
    {
        $this->rotationOn();
        $this->writeLogOfSize(HealthCheckService::LOG_WARNING_BYTES + 1024);

        $this->assertSame(HealthCheckService::STATUS_WARNING, $this->logCheck()['status']);
    }

    public function test_a_directory_past_a_gigabyte_is_critical(): void
    {
        $this->rotationOn();
        $this->writeLogOfSize(HealthCheckService::LOG_CRITICAL_BYTES + 1024);

        $check = $this->logCheck();

        $this->assertSame(HealthCheckService::STATUS_CRITICAL, $check['status']);
        $this->assertStringContainsString('GB', $check['message']);
    }

    /**
     * Sorun varken ekranda ne yapılacağı yazmalı — ipucu yalnızca sağlıklı
     * kontrolde gizleniyor.
     */
    public function test_the_hint_tells_the_reader_what_to_change(): void
    {
        $this->rotationOff();
        $this->writeLogOfSize(HealthCheckService::LOG_UNROTATED_WARNING_BYTES + 1024);

        $this->assertStringContainsString('LOG_STACK=daily', (string) $this->logCheck()['hint']);
    }

    /**
     * Kontrol dizini sabit değil: log başka bir yere yönlendirilmişse oraya
     * bakıyor. Testin geliştiricinin gerçek laravel.log dosyasından
     * etkilenmemesi de bu sayede.
     */
    public function test_the_check_follows_the_configured_log_path(): void
    {
        $this->rotationOn();
        $this->writeLogOfSize(HealthCheckService::LOG_CRITICAL_BYTES + 1024);

        $this->assertSame(HealthCheckService::STATUS_CRITICAL, $this->logCheck()['status']);

        // Aynı anda gerçek storage/logs dizini küçük; kontrol oraya bakıyor
        // olsaydı bu ikinci çağrı da kritik dönerdi.
        config(['logging.channels.daily.path' => storage_path('logs/laravel.log')]);

        $this->assertNotSame(HealthCheckService::STATUS_CRITICAL, $this->logCheck()['status']);
    }

    public function test_the_thresholds_are_ordered(): void
    {
        $this->assertLessThan(HealthCheckService::LOG_WARNING_BYTES, HealthCheckService::LOG_UNROTATED_WARNING_BYTES);
        $this->assertLessThan(HealthCheckService::LOG_CRITICAL_BYTES, HealthCheckService::LOG_WARNING_BYTES);
    }
}
