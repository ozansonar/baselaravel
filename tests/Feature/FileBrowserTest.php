<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionKey;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Editörün dosya seçicisi.
 *
 * Seçici public/uploads dizinini doğrudan okuyor: dosya yöneticisi tabloyu
 * listeliyor ama editörden yüklenen görseller o tabloya yazılmıyor, yalnızca
 * diskte duruyor. Yol istemciden geldiği için buradaki asıl mesele dizin
 * dışına çıkılamaması.
 */
class FileBrowserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, PermissionKey> $permissions
     */
    private function userWith(array $permissions): User
    {
        $this->seedAuthorization();

        $user = User::create([
            'first_name' => 'Dosya',
            'last_name'  => 'Kullanıcısı',
            'email'      => 'file-' . uniqid() . '@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $role = Role::create(['name' => 'Dosya Rolü', 'slug' => 'file-' . uniqid()]);
        $role->permissions()->syncWithoutDetaching(
            Permission::whereIn('key', array_map(static fn (PermissionKey $p): string => $p->value, $permissions))
                ->pluck('id')->all(),
        );

        $user->roles()->attach($role);

        return $user;
    }

    private function writeFile(string $relative, string $contents = 'x'): string
    {
        $full = UploadService::basePath($relative);
        $directory = dirname($full);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($full, $contents);

        return $relative;
    }

    public function test_the_root_lists_folders(): void
    {
        $this->writeFile('content/bir.webp');

        $veri = $this->actingAs($this->userWith([PermissionKey::FilesView]))
            ->getJson(route('admin.file-browser.index'))
            ->assertOk()
            ->json();

        $this->assertContains('content', array_column($veri['folders'], 'name'));
    }

    public function test_a_folder_lists_its_files(): void
    {
        $this->writeFile('content/rapor.webp');

        $veri = $this->actingAs($this->userWith([PermissionKey::FilesView]))
            ->getJson(route('admin.file-browser.index', ['folder' => 'content']))
            ->assertOk()
            ->json();

        $this->assertSame(['rapor.webp'], array_column($veri['files'], 'name'));
        $this->assertSame('/uploads/content/rapor.webp', $veri['files'][0]['url']);
    }

    /**
     * Duyarlı varyantlar ayrı dosya olarak duruyor; ızgarada aynı görselin dört
     * kopyası görünseydi liste okunmaz hâle gelirdi.
     */
    public function test_responsive_variants_are_hidden(): void
    {
        $this->writeFile('content/gorsel.webp');
        $this->writeFile('content/gorsel-thumb.webp');
        $this->writeFile('content/gorsel-sm.webp');
        $this->writeFile('content/gorsel-md.webp');
        $this->writeFile('content/gorsel-lg.webp');

        $veri = $this->actingAs($this->userWith([PermissionKey::FilesView]))
            ->getJson(route('admin.file-browser.index', ['folder' => 'content']))
            ->assertOk()
            ->json();

        $this->assertSame(['gorsel.webp'], array_column($veri['files'], 'name'));
    }

    /**
     * Sonek benzerliği yetmez: aslı olmayan bir dosya varyant sayılmamalı,
     * yoksa "toplanti-sm.jpg" diye yüklenmiş gerçek bir dosya kaybolur.
     */
    public function test_a_file_that_only_looks_like_a_variant_is_kept(): void
    {
        $this->writeFile('content/toplanti-sm.jpg');

        $veri = $this->actingAs($this->userWith([PermissionKey::FilesView]))
            ->getJson(route('admin.file-browser.index', ['folder' => 'content']))
            ->assertOk()
            ->json();

        $this->assertSame(['toplanti-sm.jpg'], array_column($veri['files'], 'name'));
    }

    public function test_only_images_are_returned_when_asked(): void
    {
        $this->writeFile('content/gorsel.webp');
        $this->writeFile('content/belge.pdf');

        $veri = $this->actingAs($this->userWith([PermissionKey::FilesView]))
            ->getJson(route('admin.file-browser.index', ['folder' => 'content', 'type' => 'image']))
            ->assertOk()
            ->json();

        $this->assertSame(['gorsel.webp'], array_column($veri['files'], 'name'));
    }

    /**
     * Klasör yolu istemciden geliyor; uploads dizininin dışına çıkan bir yol
     * sunucudaki her şeyi listeleyebilirdi.
     */
    public function test_a_folder_outside_uploads_is_refused(): void
    {
        $this->actingAs($this->userWith([PermissionKey::FilesView]))
            ->getJson(route('admin.file-browser.index', ['folder' => '../../']))
            ->assertNotFound();
    }

    public function test_a_file_outside_uploads_cannot_be_deleted(): void
    {
        $this->actingAs($this->userWith([PermissionKey::FilesView, PermissionKey::FilesDelete]))
            ->deleteJson(route('admin.file-browser.destroy'), ['path' => '../../.env'])
            ->assertNotFound();

        $this->assertFileExists(base_path('.env'));
    }

    public function test_an_upload_lands_in_the_chosen_folder(): void
    {
        $veri = $this->actingAs($this->userWith([PermissionKey::FilesView, PermissionKey::FilesManage]))
            ->postJson(route('admin.file-browser.store'), [
                'file'   => UploadedFile::fake()->image('kapak.jpg', 800, 600),
                'folder' => 'content',
            ])
            ->assertOk()
            ->json();

        $this->assertStringStartsWith('content/', $veri['path']);
        $this->assertFileExists(UploadService::basePath($veri['path']));
    }

    /**
     * Yükleme hedefi de istemciden geliyor; uploads dizininin dışına dosya
     * yazdırmanın yolu olmamalı.
     */
    public function test_uploading_outside_uploads_is_refused(): void
    {
        $this->actingAs($this->userWith([PermissionKey::FilesView, PermissionKey::FilesManage]))
            ->postJson(route('admin.file-browser.store'), [
                'file'   => UploadedFile::fake()->image('kotu.jpg'),
                'folder' => '../../public',
            ])
            ->assertStatus(422);
    }

    public function test_a_file_can_be_deleted(): void
    {
        $path = $this->writeFile('content/silinecek.webp');

        $this->actingAs($this->userWith([PermissionKey::FilesView, PermissionKey::FilesDelete]))
            ->deleteJson(route('admin.file-browser.destroy'), ['path' => $path])
            ->assertOk();

        $this->assertFileDoesNotExist(UploadService::basePath($path));
    }

    public function test_listing_needs_the_view_permission(): void
    {
        $this->actingAs($this->userWith([]))
            ->getJson(route('admin.file-browser.index'))
            ->assertForbidden();
    }

    public function test_deleting_needs_the_delete_permission(): void
    {
        $path = $this->writeFile('content/korunan.webp');

        $this->actingAs($this->userWith([PermissionKey::FilesView]))
            ->deleteJson(route('admin.file-browser.destroy'), ['path' => $path])
            ->assertForbidden();

        $this->assertFileExists(UploadService::basePath($path));
    }
}
