<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Services\BackupOffsiteService;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Yedeğin ikinci kopyası.
 *
 * Yedekleme buraya kadar tek bir varsayıma dayanıyordu: disk sağlam. Oysa
 * yedeğin var olma sebebi tam da o varsayımın çökmesi — diski kaybeden tek
 * kopyayı da kaybediyordu.
 */
class BackupOffsiteTest extends TestCase
{
    use RefreshDatabase;

    private string $offsitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offsitePath = storage_path('framework/testing/offsite');

        File::deleteDirectory($this->offsitePath);
        File::ensureDirectoryExists($this->offsitePath, 0755);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->offsitePath);

        parent::tearDown();
    }

    private function service(): BackupOffsiteService
    {
        return app(BackupOffsiteService::class);
    }

    private function fakeBackup(string $name = 'backup-2026-01-01-000000.zip'): string
    {
        $path = BackupService::basePath() . '/' . $name;

        File::ensureDirectoryExists(BackupService::basePath(), 0755);
        File::put($path, str_repeat('yedek', 200));

        return $path;
    }

    /**
     * Kapalıyken "başarısız" demek, hiç istenmemiş bir şeyi hata saymak
     * olurdu.
     */
    public function test_it_stays_out_of_the_way_when_disabled(): void
    {
        config(['backups.offsite.driver' => 'none']);

        $result = $this->service()->copy($this->fakeBackup());

        $this->assertSame('disabled', $result['status']);
        $this->assertFalse($this->service()->isEnabled());
    }

    public function test_it_copies_the_archive_to_the_second_path(): void
    {
        config([
            'backups.offsite.driver' => 'local',
            'backups.offsite.path'   => $this->offsitePath,
        ]);

        $path = $this->fakeBackup();

        $result = $this->service()->copy($path);

        $this->assertSame('ok', $result['status']);
        $this->assertFileExists($this->offsitePath . '/' . basename($path));
        $this->assertSame(File::size($path), File::size($this->offsitePath . '/' . basename($path)));
    }

    public function test_a_missing_archive_is_reported(): void
    {
        config([
            'backups.offsite.driver' => 'local',
            'backups.offsite.path'   => $this->offsitePath,
        ]);

        $result = $this->service()->copy(BackupService::basePath() . '/olmayan.zip');

        $this->assertSame('failed', $result['status']);
    }

    /**
     * Hedef tanımsızken sessizce başarılı dönmek, kopyanın alındığı yanılgısı
     * yaratırdı.
     */
    public function test_a_missing_target_path_fails_loudly(): void
    {
        config([
            'backups.offsite.driver' => 'local',
            'backups.offsite.path'   => '',
        ]);

        $result = $this->service()->copy($this->fakeBackup());

        $this->assertSame('failed', $result['status']);
    }

    /**
     * Dış kopya kimsenin her gün baktığı bir şey değil: aylarca alınmadığı
     * fark edilmezse yedekleme yine tek kopyaya düşer.
     */
    public function test_a_failure_reaches_the_panel(): void
    {
        config([
            'backups.offsite.driver' => 'local',
            'backups.offsite.path'   => '',
        ]);

        $this->service()->copy($this->fakeBackup());

        $this->assertTrue(
            AdminNotification::where('title', 'Yedeğin dış kopyası alınamadı')->exists(),
            'Başarısızlık panele bildirilmedi',
        );
    }

    /**
     * İkinci hedef zamanla dolarsa dolduğu gün yeni kopya alınamaz — hem de
     * kimse fark etmeden.
     */
    public function test_old_copies_are_pruned_from_the_second_path(): void
    {
        config([
            'backups.offsite.driver'         => 'local',
            'backups.offsite.path'           => $this->offsitePath,
            'backups.offsite.retention_days' => 30,
        ]);

        $eski = $this->offsitePath . '/backup-2020-01-01-000000.zip';
        File::put($eski, 'eski');
        touch($eski, now()->subDays(60)->getTimestamp());

        $this->service()->copy($this->fakeBackup());

        $this->assertFileDoesNotExist($eski);
    }

    /**
     * Yerel kopya alındıysa iş görülmüştür; dış hedefin ulaşılamaz olması onu
     * geri almaz. Yönetici durumu mesajda ve denetim izinde görüyor.
     */
    public function test_a_failed_offsite_copy_does_not_fail_the_backup(): void
    {
        config([
            'backups.offsite.driver' => 'local',
            'backups.offsite.path'   => '',
        ]);

        $result = app(BackupService::class)->create();

        $this->assertTrue($result['success']);
        $this->assertSame('failed', $result['offsite']);
        $this->assertStringContainsString('Dış kopya alınamadı', $result['message']);
    }

    public function test_a_successful_backup_reports_the_second_copy(): void
    {
        config([
            'backups.offsite.driver' => 'local',
            'backups.offsite.path'   => $this->offsitePath,
        ]);

        $result = app(BackupService::class)->create();

        $this->assertTrue($result['success']);
        $this->assertSame('ok', $result['offsite']);
        $this->assertCount(1, File::glob($this->offsitePath . '/backup-*.zip'));
    }

    public function test_the_backup_still_works_with_the_copy_switched_off(): void
    {
        config(['backups.offsite.driver' => 'none']);

        $result = app(BackupService::class)->create();

        $this->assertTrue($result['success']);
        $this->assertSame('disabled', $result['offsite']);
    }
}
