<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * The backup screen is where an administrator decides which archive to keep or
 * download, so the list has to say what each file holds and how long it has
 * left before rotation removes it.
 */
class BackupListTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        // Only the files this test made are removed; a real backup sitting in
        // the same folder must survive.
        foreach ($this->created as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function makeBackup(string $name, int $daysAgo, int $dbSize = 1024, int $filesSize = 2048, int $totalFiles = 12): string
    {
        // Dizin yapılandırmadan geliyor; sınamada geçici bir yere
        // çevriliyor (bkz. phpunit.xml), yoksa testler geliştiricinin gerçek
        // yedeklerinin arasına yazar ve rotate() onları silebilirdi.
        $dir = \App\Services\BackupService::basePath();
        @mkdir($dir, 0775, true);

        $path = $dir . '/' . $name;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup-meta.json', (string) json_encode([
            'version'     => 1,
            'db_size'     => $dbSize,
            'files_size'  => $filesSize,
            'total_files' => $totalFiles,
        ]));
        $zip->close();

        touch($path, now()->subDays($daysAgo)->timestamp);

        $this->created[] = $path;

        return $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entry(string $name, array $filters = []): ?array
    {
        foreach (app(BackupService::class)->list($filters) as $backup) {
            if ($backup['name'] === $name) {
                return $backup;
            }
        }

        return null;
    }

    public function test_a_backup_reports_what_it_holds(): void
    {
        $name = 'backup-2020-01-02-030405.zip';
        $this->makeBackup($name, 1, dbSize: 4096, filesSize: 8192, totalFiles: 34);

        $entry = $this->entry($name);

        $this->assertNotNull($entry);
        $this->assertSame(4096, $entry['contents']['db_size']);
        $this->assertSame(8192, $entry['contents']['files_size']);
        $this->assertSame(34, $entry['contents']['total_files']);
    }

    /**
     * Rotation deletes anything older than the retention window, so the list
     * says how long each file has left — that is the cue to download it.
     */
    public function test_the_list_counts_down_to_rotation(): void
    {
        $fresh = 'backup-2020-01-03-030405.zip';
        $old = 'backup-2020-01-04-030405.zip';

        $this->makeBackup($fresh, 1);
        $this->makeBackup($old, 13);

        $retention = app(BackupService::class)->retentionDays();

        $this->assertSame($retention - 1, $this->entry($fresh)['expires_in_days']);
        $this->assertSame($retention - 13, $this->entry($old)['expires_in_days']);
    }

    public function test_the_search_narrows_the_list_by_file_name(): void
    {
        $wanted = 'backup-2020-02-09-030405.zip';
        $other = 'backup-2020-03-09-030405.zip';

        $this->makeBackup($wanted, 1);
        $this->makeBackup($other, 2);

        $this->assertNotNull($this->entry($wanted, ['q' => '2020-02']));
        $this->assertNull($this->entry($other, ['q' => '2020-02']));
    }

    public function test_the_list_can_be_ordered_by_size_and_date(): void
    {
        $small = 'backup-2020-04-01-030405.zip';
        $large = 'backup-2020-04-02-030405.zip';

        $this->makeBackup($small, 1);
        // A second entry inside the archive is enough to make it the larger file.
        $path = $this->makeBackup($large, 5);
        $zip = new ZipArchive();
        $zip->open($path);
        $zip->addFromString('padding.bin', str_repeat('x', 4096));
        $zip->close();
        touch($path, now()->subDays(5)->timestamp);

        $mine = static fn (array $backups): array => array_values(array_filter(
            array_column($backups, 'name'),
            static fn (string $name): bool => str_starts_with($name, 'backup-2020-04-'),
        ));

        $service = app(BackupService::class);

        $this->assertSame([$small, $large], $mine($service->list(['sort' => 'newest'])));
        $this->assertSame([$large, $small], $mine($service->list(['sort' => 'oldest'])));
        $this->assertSame([$large, $small], $mine($service->list(['sort' => 'largest'])));
        $this->assertSame([$small, $large], $mine($service->list(['sort' => 'smallest'])));
    }

    public function test_the_summary_adds_up_what_is_stored(): void
    {
        $before = app(BackupService::class)->stats();

        $this->makeBackup('backup-2020-05-01-030405.zip', 1);
        $this->makeBackup('backup-2020-05-02-030405.zip', 2);

        $after = app(BackupService::class)->stats();

        $this->assertSame($before['count'] + 2, $after['count']);
        $this->assertGreaterThan($before['total_size'], $after['total_size']);
        $this->assertNotNull($after['latest']);
    }
}
