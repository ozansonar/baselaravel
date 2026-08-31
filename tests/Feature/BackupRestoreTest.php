<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupRestoreService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * Yedeğin geri yüklenmesi.
 *
 * Yedek alınıyordu ama geri dönüş yolu yoktu: dosya indirilebiliyor, ama
 * uygulanabilmesi için sunucuda elle SQL çalıştırmak gerekiyordu. Hiç
 * denenmemiş bir yedek, olmayan bir yedektir.
 *
 * Veritabanı geri yüklemesi MySQL'e özgü (döküm `SHOW TABLES` ve ters tırnak
 * kullanıyor), suite ise SQLite üzerinde koşuyor. Buradaki testler bu yüzden
 * doğrulama, güvenlik ve dosya tarafını kapsıyor; veritabanı turu gerçek bir
 * MySQL veritabanında elle doğrulandı (bkz. docs/PROJE-DURUMU.md 5u).
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();

        $this->backupDir = \App\Services\BackupService::basePath();

        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0o775, true);
        }
    }

    /**
     * Yedek dizini sınama için ayrı bir yere çevrildi (bkz. phpunit.xml);
     * yine de her testten sonra boşaltılıyor, çünkü `create()` eski
     * arşivleri döndürüyor ve testler birbirinin sayımını kaydırmamalı.
     */
    protected function tearDown(): void
    {
        foreach (glob($this->backupDir . '/*.zip') ?: [] as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $user;
    }

    private function service(): BackupRestoreService
    {
        return app(BackupRestoreService::class);
    }

    /**
     * @param array<string, string> $entries
     */
    private function makeArchive(string $name, array $entries): string
    {
        $path = $this->backupDir . '/' . $name;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entryName => $content) {
            $zip->addFromString($entryName, $content);
        }

        $zip->close();

        return $path;
    }

    private function validArchive(string $name = 'sinama-gecerli.zip', array $extra = []): string
    {
        return $this->makeArchive($name, array_merge([
            'backup-meta.json'   => json_encode(['version' => 1, 'created_at' => now()->toIso8601String()]),
            'uploads/blog/a.txt' => 'birinci dosya',
            'uploads/b.txt'      => 'ikinci dosya',
        ], $extra));
    }

    // ── Doğrulama ────────────────────────────────────────────────

    public function test_a_valid_archive_is_described(): void
    {
        $this->validArchive();

        $result = $this->service()->inspect('sinama-gecerli.zip');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['has_database']);
        $this->assertTrue($result['has_uploads']);
        $this->assertSame(2, $result['upload_count']);
        $this->assertSame(1, $result['meta']['version'] ?? null);
    }

    public function test_a_missing_file_is_refused(): void
    {
        $this->assertFalse($this->service()->inspect('sinama-yok.zip')['ok']);
    }

    public function test_a_file_that_is_not_an_archive_is_refused(): void
    {
        file_put_contents($this->backupDir . '/sinama-bozuk.zip', 'bu bir zip değil');

        $result = $this->service()->inspect('sinama-bozuk.zip');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('açılamadı', $result['message']);
    }

    /**
     * Yedek imzası olmayan bir zip geri yüklenemez: içeriği ne olursa olsun
     * onu uygulamak rastgele SQL çalıştırmak demek olurdu.
     */
    public function test_an_archive_without_the_backup_signature_is_refused(): void
    {
        $this->makeArchive('sinama-imzasiz.zip', ['rastgele.txt' => 'içerik']);

        $result = $this->service()->inspect('sinama-imzasiz.zip');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('yedek dosyası değil', $result['message']);
    }

    /**
     * Zip Slip: `uploads/../../../.env` adlı bir girdi açılırken hedef dizinin
     * dışına yazar. Yedek dosyası panelden yüklenebildiği için arşiv her zaman
     * güvenilir değil.
     */
    public function test_an_archive_that_escapes_its_directory_is_refused(): void
    {
        foreach ([
            'uploads/../../../.env',
            '/etc/passwd',
            'uploads/..\\..\\gizli.txt',
        ] as $index => $entry) {
            $name = "sinama-kacan-{$index}.zip";

            $this->makeArchive($name, [
                'backup-meta.json' => json_encode(['version' => 1]),
                $entry             => 'kötü içerik',
            ]);

            $result = $this->service()->inspect($name);

            $this->assertFalse($result['ok'], "kabul edilmemeliydi: {$entry}");
            $this->assertStringContainsString('dizin dışına', $result['message']);
        }
    }

    public function test_a_filename_cannot_walk_out_of_the_backup_directory(): void
    {
        foreach (['../.env', 'alt/dizin.zip', '..\\..\\gizli.zip'] as $filename) {
            $this->assertFalse(
                $this->service()->inspect($filename)['ok'],
                "kabul edilmemeliydi: {$filename}",
            );
        }
    }

    // ── Geri yükleme ─────────────────────────────────────────────

    public function test_restoring_writes_the_uploaded_files_back(): void
    {
        $this->validArchive();

        $result = $this->service()->restore('sinama-gecerli.zip');

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(2, $result['files']);

        $base = rtrim(UploadService::basePath(), '/');

        $this->assertSame('birinci dosya', file_get_contents($base . '/blog/a.txt'));
        $this->assertSame('ikinci dosya', file_get_contents($base . '/b.txt'));
    }

    /**
     * Geri yükleme yanlış dosyayla da başlatılabilir; o an geriye dönülecek
     * bir yer olmalı.
     */
    public function test_a_safety_backup_is_taken_before_anything_is_touched(): void
    {
        $this->validArchive();

        $result = $this->service()->restore('sinama-gecerli.zip');

        $this->assertNotNull($result['safety_backup']);
        $this->assertFileExists($this->backupDir . '/' . $result['safety_backup']);

        // Güvenlik yedeği geri yüklenen arşivin kendisi olamaz.
        $this->assertNotSame('sinama-gecerli.zip', $result['safety_backup']);
    }

    /**
     * Yedekten sonra eklenen dosyalar silinmiyor: kurtarma işleminin yan
     * etkisi olarak veri silmek, kurtarmanın kendisinden büyük risk.
     */
    public function test_files_added_after_the_backup_are_left_alone(): void
    {
        $this->validArchive();

        $base = rtrim(UploadService::basePath(), '/');

        if (! is_dir($base)) {
            mkdir($base, 0o775, true);
        }

        file_put_contents($base . '/sonradan-eklenen.txt', 'kalmalı');

        $this->service()->restore('sinama-gecerli.zip');

        $this->assertFileExists($base . '/sonradan-eklenen.txt');
    }

    public function test_the_site_is_not_left_in_maintenance_mode(): void
    {
        $this->validArchive();

        $this->service()->restore('sinama-gecerli.zip');

        Setting::clearSettingsCache();

        $this->assertSame('0', Setting::getValue('maintenance_mode'));
    }

    public function test_an_empty_archive_is_refused_before_the_safety_backup(): void
    {
        $this->makeArchive('sinama-bos.zip', [
            'backup-meta.json' => json_encode(['version' => 1]),
        ]);

        $before = count(glob($this->backupDir . '/backup-*.zip') ?: []);

        $result = $this->service()->restore('sinama-bos.zip');

        $this->assertFalse($result['success']);
        $this->assertNull($result['safety_backup']);
        $this->assertSame($before, count(glob($this->backupDir . '/backup-*.zip') ?: []));
    }

    public function test_the_restore_lands_in_the_audit_trail(): void
    {
        $this->validArchive();

        $this->service()->restore('sinama-gecerli.zip');

        $this->assertContains('Yedek geri yüklendi', AuditLog::pluck('label')->all());
    }

    // ── Yedeğin gerçekten dolu olması ────────────────────────────

    /**
     * Asıl kusur buydu: veritabanı dökümü alınamadığında sessizce devam
     * ediliyor, arşiv yalnız dosyaları taşıyor ve sonuç yine "başarılı"
     * diyordu. Yönetici gövdesiz bir yedeğe güveniyor, bunu ancak geri
     * yüklemeye çalıştığı gün öğreniyordu.
     */
    public function test_a_backup_without_its_database_is_not_reported_as_successful(): void
    {
        // Sürücü mysql: döküm alınabilmesi *beklenen* bir kurulum. Bağlantı
        // bilgileri geçersiz olduğu için döküm alınamayacak.
        $original = config('database.default');

        config([
            'database.default' => 'sinama_mysql',
            'database.connections.sinama_mysql' => [
                'driver'   => 'mysql',
                'host'     => '127.0.0.1',
                'port'     => '1',
                'database' => 'olmayan_veritabani',
                'username' => 'yok',
                'password' => 'yok',
            ],
        ]);

        try {
            $result = app(\App\Services\BackupService::class)->create();
        } finally {
            // Varsayılan bağlantı geri verilmeli: RefreshDatabase testin
            // sonunda işlemi *o an geçerli* bağlantı üzerinden geri alıyor.
            config(['database.default' => $original]);
        }

        $this->assertFalse($result['success'], 'Gövdesiz yedek başarılı sayıldı');
        $this->assertStringContainsString('Veritabanı dökümü alınamadı', $result['message']);
        $this->assertNull($result['file']);
    }

    /**
     * Dökümü desteklemeyen bir sürücüde (geliştirmedeki SQLite) yedek almak
     * hata değil — ama sonucun bunu açıkça söylemesi gerekiyor, yoksa yine
     * eksik olduğu fark edilmez.
     */
    public function test_a_driver_without_dump_support_says_so_instead_of_failing(): void
    {
        // Suite hem SQLite hem MySQL üzerinde koşuyor; bu dal yalnızca dökümü
        // desteklemeyen sürücüyü anlatıyor.
        if (in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Bu sürücüde döküm destekleniyor.');
        }

        $result = app(\App\Services\BackupService::class)->create();

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['db_size']);
        $this->assertStringContainsString('veritabanı dökümü desteklenmiyor', $result['message']);
    }

    // ── Yetki ────────────────────────────────────────────────────

    public function test_only_a_backup_manager_can_restore(): void
    {
        $this->validArchive();

        $editor = User::factory()->create();
        $editor->roles()->attach(Role::where('slug', 'editor')->firstOrFail());

        $this->actingAs($editor)
            ->post('/admin/yedekler/geri-yukle/sinama-gecerli.zip')
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post('/admin/yedekler/geri-yukle/sinama-gecerli.zip')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_the_inspect_endpoint_answers_before_restoring(): void
    {
        $this->validArchive();

        $this->actingAs($this->admin())
            ->getJson('/admin/yedekler/incele/sinama-gecerli.zip')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('upload_count', 2);
    }

    // ── Dışarıdan dosya yükleme ──────────────────────────────────

    public function test_an_uploaded_archive_joins_the_list(): void
    {
        $path = $this->makeArchive('sinama-disaridan.zip', [
            'backup-meta.json' => json_encode(['version' => 1]),
            'uploads/x.txt'    => 'içerik',
        ]);

        $response = $this->actingAs($this->admin())->post('/admin/yedekler/yukle', [
            'backup' => new UploadedFile($path, 'disaridan.zip', 'application/zip', null, true),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertNotEmpty(glob($this->backupDir . '/backup-yuklenen-*.zip') ?: []);

        foreach (glob($this->backupDir . '/backup-yuklenen-*.zip') ?: [] as $created) {
            @unlink($created);
        }
    }

    /**
     * Uzantı ve MIME dosyanın içeriği hakkında hiçbir şey söylemez; asıl
     * kontrol arşivin açılıp yedek imzasının aranması.
     */
    public function test_a_zip_that_is_not_a_backup_is_refused_on_upload(): void
    {
        $path = $this->makeArchive('sinama-sahte.zip', ['rastgele.txt' => 'içerik']);

        $this->actingAs($this->admin())
            ->post('/admin/yedekler/yukle', [
                'backup' => new UploadedFile($path, 'sahte.zip', 'application/zip', null, true),
            ])
            ->assertSessionHas('error');

        $this->assertEmpty(glob($this->backupDir . '/backup-yuklenen-*.zip') ?: []);
    }

    public function test_an_editor_cannot_upload_a_backup(): void
    {
        $path = $this->validArchive('sinama-yetki.zip');

        $editor = User::factory()->create();
        $editor->roles()->attach(Role::where('slug', 'editor')->firstOrFail());

        $this->actingAs($editor)
            ->post('/admin/yedekler/yukle', [
                'backup' => new UploadedFile($path, 'yedek.zip', 'application/zip', null, true),
            ])
            ->assertForbidden();
    }
}
