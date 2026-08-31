<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Yedek listesinden toplu silme.
 *
 * Dosya adları doğrudan istekten geldiği için buradaki asıl mesele silmenin
 * çalışması değil, sadece yedek klasöründeki dosyaların silinebilmesi.
 */
class BackupBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        $this->seedAuthorization();

        $admin = User::create([
            'first_name' => 'Yedek',
            'last_name'  => 'Yöneticisi',
            'email'      => 'backup-admin@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $admin->roles()->attach(Role::where('slug', 'admin')->firstOrFail());

        return $admin;
    }

    private function makeBackup(string $name): string
    {
        // Dizin yapılandırmadan geliyor; sınamada geçici bir yere
        // çevriliyor (bkz. phpunit.xml), yoksa testler geliştiricinin gerçek
        // yedeklerinin arasına yazar ve rotate() onları silebilirdi.
        $dir = \App\Services\BackupService::basePath();
        @mkdir($dir, 0775, true);

        $path = $dir . '/' . $name;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backup-meta.json', '{"version":1}');
        $zip->close();

        $this->created[] = $path;

        return $path;
    }

    public function test_selected_backups_are_deleted_and_the_rest_stay(): void
    {
        $first = $this->makeBackup('backup-2021-01-01-010101.zip');
        $second = $this->makeBackup('backup-2021-01-02-010101.zip');
        $kept = $this->makeBackup('backup-2021-01-03-010101.zip');

        $this->actingAs($this->admin())
            ->delete(route('admin.backups.bulk-destroy'), [
                'files' => [basename($first), basename($second)],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', '2 yedek silindi.');

        $this->assertFileDoesNotExist($first);
        $this->assertFileDoesNotExist($second);
        $this->assertFileExists($kept);
    }

    /**
     * Silme sonrası kullanıcı baktığı listeye dönmeli, filtresi sıfırlanmamalı.
     */
    public function test_the_current_filter_survives_the_delete(): void
    {
        $file = $this->makeBackup('backup-2021-02-01-010101.zip');

        $this->actingAs($this->admin())
            ->delete(route('admin.backups.bulk-destroy'), [
                'files' => [basename($file)],
                'q'     => '2021-02',
                'sort'  => 'largest',
            ])
            ->assertRedirect(route('admin.backups.index', ['q' => '2021-02', 'sort' => 'largest']));
    }

    public function test_nothing_is_deleted_when_no_file_is_selected(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.backups.index'))
            ->delete(route('admin.backups.bulk-destroy'), ['files' => []])
            ->assertSessionHasErrors('files');
    }

    /**
     * Klasör dışına çıkmaya çalışan ad doğrulamada elenir; servise ulaşmaz.
     */
    public function test_a_name_that_climbs_out_of_the_folder_is_rejected(): void
    {
        $outside = \App\Services\BackupService::basePath() . '/../outside-target.txt';
        file_put_contents($outside, 'silinmemeli');
        $this->created[] = $outside;

        $this->actingAs($this->admin())
            ->from(route('admin.backups.index'))
            ->delete(route('admin.backups.bulk-destroy'), [
                'files' => ['../outside-target.txt'],
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertFileExists($outside);
    }

    public function test_a_missing_file_is_reported_rather_than_counted(): void
    {
        $file = $this->makeBackup('backup-2021-03-01-010101.zip');

        $this->actingAs($this->admin())
            ->delete(route('admin.backups.bulk-destroy'), [
                'files' => [basename($file), 'backup-2021-03-09-999999.zip'],
            ])
            ->assertSessionHas('warning', '1 yedek silindi, 1 tanesi silinemedi.');
    }

    public function test_a_user_without_the_permission_cannot_delete(): void
    {
        $this->seedAuthorization();

        $file = $this->makeBackup('backup-2021-04-01-010101.zip');

        $editor = User::create([
            'first_name' => 'Editör',
            'last_name'  => 'Kullanıcı',
            'email'      => 'backup-editor@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);
        $editor->roles()->attach(Role::where('slug', 'editor')->firstOrFail());

        $this->actingAs($editor)
            ->delete(route('admin.backups.bulk-destroy'), ['files' => [basename($file)]])
            ->assertForbidden();

        $this->assertFileExists($file);
    }
}
