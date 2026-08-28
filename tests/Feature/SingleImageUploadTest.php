<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Panelde tekli yüklenen görsellerin ortak kuralı: yalnız görsel, en fazla 1 MB.
 *
 * Sınır modül modül farklıydı — kimi alan 4 MB, kimi 2 MB, kimi 1 MB kabul
 * ediyordu; aynı işi yapan iki form kullanıcıya iki farklı sınır söylüyordu.
 * Kural tek yerde toplandı (components/image-field.blade.php ve FormRequest'ler)
 * ve buradan denetleniyor: yeni bir görsel alanı eklendiğinde sınırı kendiliğinden
 * karşılamak zorunda.
 *
 * Toplu galeri yüklemesi kapsam dışı: orada tek tek değil, bir seferde onlarca
 * dosya gidiyor ve kendi sınırını StoreGalleryBulkImageRequest tanımlıyor.
 */
final class SingleImageUploadTest extends TestCase
{
    use RefreshDatabase;

    /** Sunucu sınırı, kilobayt — 1 MB. */
    private const MAX_KB = 1024;

    /** İstemci sınırı, megabayt. İkisi birebir aynı sınırı anlatmalı. */
    private const MAX_MB = '1';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
        $this->seedAuthorization();

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Yönetici']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role);

        $this->actingAs($this->admin);
    }

    // ── Sunucu ──

    public function test_every_single_image_rule_stops_at_one_megabyte(): void
    {
        $sorunlu = [];

        foreach ($this->requestFiles() as $file) {
            $contents = (string) file_get_contents($file);

            preg_match_all("/'image',\s*'mimes:[^']*',\s*'max:(\d+)'/", $contents, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                if ((int) $match[1] === self::MAX_KB) {
                    continue;
                }

                $sorunlu[] = sprintf(
                    '%s → max:%s (beklenen max:%d)',
                    str_replace(base_path() . '/', '', $file),
                    $match[1],
                    self::MAX_KB,
                );
            }
        }

        $this->assertSame([], $sorunlu, "Sınırı kaçmış görsel kuralı:\n" . implode("\n", $sorunlu));
    }

    public function test_an_image_over_the_limit_is_refused(): void
    {
        // 1 MB'ı aşan bir görsel: sunucu kabul etmemeli.
        $response = $this->from('/admin/sliders/create')->post('/admin/sliders', [
            'translations' => [
                'tr' => [
                    'title' => 'Çok büyük görsel',
                    'image' => UploadedFile::fake()->create('buyuk.jpg', 1500, 'image/jpeg'),
                ],
            ],
        ]);

        $response->assertSessionHasErrors('translations.tr.image');
        $this->assertSame(0, Slider::count());
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $response = $this->from('/admin/sliders/create')->post('/admin/sliders', [
            'translations' => [
                'tr' => [
                    'title' => 'Görsel değil',
                    'image' => UploadedFile::fake()->create('belge.pdf', 100, 'application/pdf'),
                ],
            ],
        ]);

        $response->assertSessionHasErrors('translations.tr.image');
        $this->assertSame(0, Slider::count());
    }

    // ── İstemci ──

    public function test_every_image_field_declares_the_same_limit_to_the_browser(): void
    {
        $sorunlu = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = (string) file_get_contents($file);

            preg_match_all('/<input[^>]*type="file"[^>]*>/is', $contents, $inputs);

            foreach ($inputs[0] as $input) {
                // Yalnız görsel alanları: kuralını imageFile ile veren girdiler.
                if (!str_contains($input, 'FormValidation.rules.imageFile')) {
                    continue;
                }

                if (!preg_match('/data-max-size="([^"]*)"/', $input, $match) || $match[1] !== self::MAX_MB) {
                    $sorunlu[] = str_replace(base_path() . '/', '', $file)
                        . ' → data-max-size="' . ($match[1] ?? 'yok') . '" (beklenen "' . self::MAX_MB . '")';
                }
            }
        }

        $this->assertSame([], $sorunlu, "İstemci sınırı sunucununkiyle uyuşmuyor:\n" . implode("\n", $sorunlu));
    }

    // ── Form yüklü görseli gösteriyor mu ──

    public function test_the_edit_form_shows_the_image_that_is_already_saved(): void
    {
        $this->post('/admin/sliders', ['translations' => [
            'tr' => [
                'title'      => 'Kampanya',
                'image'      => UploadedFile::fake()->image('slider.jpg', 600, 400),
                'is_active'  => 1,
                'sort_order' => 0,
            ],
        ]])->assertSessionHasNoErrors();

        $slider = Slider::where('title', 'Kampanya')->firstOrFail();

        $html = $this->get("/admin/sliders/{$slider->id}/edit")->getContent();

        // Kayıtlı görsel önizleme kutusunda basılıyor: küçük resmin adresi ve
        // dosya adı sayfada. Çevirisi olmayan dilin kutusu boş kalıyor, o
        // yüzden "hiç d-none olmasın" denmiyor — beklenen durum o.
        $this->assertStringContainsString('data-cover-box', $html);
        $this->assertStringContainsString(basename((string) $slider->image), $html);
        $this->assertStringContainsString(upload_url($slider->image, 'thumb'), $html);
    }

    /**
     * @return list<string>
     */
    private function requestFiles(): array
    {
        return $this->filesUnder(app_path('Http/Requests'), '.php');
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        return $this->filesUnder(resource_path('views'), '.blade.php');
    }

    /**
     * @return list<string>
     */
    private function filesUnder(string $directory, string $suffix): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
