<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * public/uploads altına betik taşıyan bir dosya inemez.
 *
 * Oradaki dosyalar sitenin kendi kaynağından, Laravel'e hiç uğramadan servis
 * ediliyor: bir `.html` ya da `.svg` açıldığında içindeki JavaScript o anki
 * oturumun bağlamında çalışıyor. Panele erişebilen herhangi biri —bir editör—
 * böyle bir dosya bırakıp bağlantısını bir yöneticiye açtırırsa paneli
 * devralıyordu.
 *
 * Yükleme kutularının çoğu zaten beyaz liste taşıyordu; iki uç taşımıyordu
 * (dosya seçici ve kampanya eki) ve ikisi de aynı yazma noktasına gidiyordu.
 * Asıl denetim artık o noktada — UploadService::uploadFile — çünkü istemcinin
 * verdiği uzantının diskteki ada geçtiği tek yer orası.
 */
final class UploadsRejectScriptableFilesTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $this->seedAuthorization();

        $editor = User::create([
            'first_name' => 'Sınırlı',
            'last_name'  => 'Editör',
            'email'      => 'editor@example.com',
            'password'   => 'password',
            'is_active'  => true,
        ]);

        $editor->roles()->attach(Role::where('slug', 'editor')->firstOrFail());

        return $editor;
    }

    /**
     * @return list<array{string, string}>
     */
    public static function scriptableFiles(): array
    {
        return [
            'html'  => ['sayfa.html', 'text/html'],
            'svg'   => ['gorsel.svg', 'image/svg+xml'],
            'php'   => ['kabuk.php', 'application/x-php'],
            'phtml' => ['kabuk.phtml', 'application/x-php'],
            'htaccess yerine geçen' => ['ayar.htm', 'text/html'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scriptableFiles')]
    public function test_the_file_picker_refuses_scriptable_files(string $name, string $mime): void
    {
        $this->actingAs($this->editor())
            ->postJson(route('admin.file-browser.store'), [
                'file' => UploadedFile::fake()->createWithContent($name, '<script>alert(1)</script>'),
            ])
            ->assertStatus(422);

        $this->assertFileDoesNotExist(UploadService::basePath('content/' . $name));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scriptableFiles')]
    public function test_the_upload_service_refuses_them_even_without_validation(string $name, string $mime): void
    {
        // Doğrulamayı hiç kurmamış bir çağrı — ileride eklenen bir uç bu
        // hâle düşerse yazma noktası yine reddetmeli.
        $this->expectException(\RuntimeException::class);

        app(UploadService::class)->uploadFile(
            UploadedFile::fake()->createWithContent($name, '<script>alert(1)</script>'),
            'content',
            'deneme',
        );
    }

    /**
     * Büyük harfli uzantı da aynı dosya.
     */
    public function test_the_extension_check_is_case_insensitive(): void
    {
        $this->expectException(\RuntimeException::class);

        app(UploadService::class)->uploadFile(
            UploadedFile::fake()->createWithContent('KABUK.PHP', '<?php echo 1;'),
            'content',
            'deneme',
        );
    }

    /**
     * Kapı meşru dosyaya kapanmamalı.
     */
    public function test_a_document_still_uploads(): void
    {
        $path = app(UploadService::class)->uploadFile(
            UploadedFile::fake()->createWithContent('rapor.pdf', '%PDF-1.4'),
            'content',
            'rapor',
        );

        $this->assertStringEndsWith('.pdf', $path);
        $this->assertFileExists(UploadService::basePath($path));

        @unlink(UploadService::basePath($path));
    }
}
