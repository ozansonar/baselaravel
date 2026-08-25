<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\UploadService;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

/**
 * Uploads write real files, so this is the one place the base kit cannot be
 * checked by reading the code alone.
 *
 * Every project cloned from here inherits this service; a regression means
 * missing artwork or orphaned files on disk, neither of which surfaces as an
 * exception.
 *
 * Writes are redirected by config('uploads.path') — see phpunit.xml — and the
 * directory is cleared in TestCase::tearDown().
 */
class ImageUploadTest extends TestCase
{
    private UploadService $uploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploads = app(UploadService::class);
    }

    private function image(int $width = 1000, int $height = 800, string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    private function path(string $relative): string
    {
        return UploadService::basePath($relative);
    }

    public function test_an_upload_is_converted_to_webp(): void
    {
        $stored = $this->uploads->uploadImage($this->image(), 'testing', 'Organik Köy Sütü');

        $this->assertStringEndsWith('.webp', $stored);
        $this->assertFileExists($this->path($stored));
        $this->assertSame('image/webp', (string) mime_content_type($this->path($stored)));
    }

    /**
     * The slug comes from the human-readable name, the suffix keeps two uploads
     * of the same name from overwriting each other.
     */
    public function test_the_filename_is_slugged_and_made_unique(): void
    {
        $first = $this->uploads->uploadImage($this->image(), 'testing', 'Organik Köy Sütü');
        $second = $this->uploads->uploadImage($this->image(), 'testing', 'Organik Köy Sütü');

        $this->assertStringStartsWith('testing/organik-koy-sutu-', $first);
        $this->assertNotSame($first, $second, 'Aynı isimli ikinci yükleme birincinin üzerine yazdı');
        $this->assertFileExists($this->path($first));
        $this->assertFileExists($this->path($second));
    }

    public function test_responsive_variants_are_created(): void
    {
        $stored = $this->uploads->uploadImage($this->image(1000, 800), 'testing', 'Kapak');

        foreach (['thumb', 'sm', 'md'] as $size) {
            $this->assertFileExists($this->variant($stored, $size), "{$size} varyantı üretilmedi");
        }
    }

    public function test_a_variant_larger_than_the_source_is_not_created(): void
    {
        // 400px wide: lg (1200) and md (600) would be upscaling.
        $stored = $this->uploads->uploadImage($this->image(400, 300), 'testing', 'Küçük');

        $this->assertFileExists($this->variant($stored, 'thumb'));
        $this->assertFileExists($this->variant($stored, 'sm'));
        $this->assertFileDoesNotExist($this->variant($stored, 'md'), 'Kaynaktan büyük varyant üretildi');
        $this->assertFileDoesNotExist($this->variant($stored, 'lg'));
    }

    public function test_the_thumbnail_is_cropped_square(): void
    {
        $stored = $this->uploads->uploadImage($this->image(1000, 400), 'testing', 'Geniş');

        $size = getimagesize($this->variant($stored, 'thumb'));

        $this->assertIsArray($size);
        $this->assertSame(150, $size[0]);
        $this->assertSame(150, $size[1], 'Thumb kare kırpılmadı');
    }

    public function test_a_wide_original_is_capped_at_the_maximum_width(): void
    {
        $stored = $this->uploads->uploadImage($this->image(3000, 2000), 'testing', 'Devasa');

        $size = getimagesize($this->path($stored));

        $this->assertIsArray($size);
        $this->assertSame(1920, $size[0], 'Orijinal 1920px sınırına indirilmedi');
    }

    public function test_only_the_requested_variants_are_created(): void
    {
        $stored = $this->uploads->uploadImage($this->image(1000, 800), 'testing', 'Seçmeli', ['sm']);

        $this->assertFileExists($this->variant($stored, 'sm'));
        $this->assertFileDoesNotExist($this->variant($stored, 'md'));
        $this->assertFileDoesNotExist($this->variant($stored, 'thumb'));
    }

    public function test_the_original_format_can_be_preserved(): void
    {
        $stored = $this->uploads->uploadImage(
            $this->image(800, 600, 'belge.png'),
            'testing',
            'Şeffaf Logo',
            null,
            null,
            null,
            preserveFormat: true,
        );

        $this->assertStringEndsWith('.png', $stored);
        $this->assertFileExists($this->path($stored));
    }

    // ── Silme ve değiştirme ──

    /**
     * The variants are the part that gets forgotten; an orphaned -thumb file
     * stays on disk forever because nothing references it any more.
     */
    public function test_deleting_an_image_removes_its_variants_too(): void
    {
        $stored = $this->uploads->uploadImage($this->image(1000, 800), 'testing', 'Silinecek');

        $this->assertFileExists($this->variant($stored, 'sm'));

        $this->uploads->deleteImage($stored);

        $this->assertFileDoesNotExist($this->path($stored));
        foreach (['thumb', 'sm', 'md'] as $size) {
            $this->assertFileDoesNotExist($this->variant($stored, $size), "{$size} varyantı diskte kaldı");
        }
    }

    public function test_replacing_an_image_deletes_the_old_one(): void
    {
        $old = $this->uploads->uploadImage($this->image(1000, 800), 'testing', 'Eski');

        $new = $this->uploads->replaceImage($this->image(1000, 800), 'testing', 'Yeni', $old);

        $this->assertNotSame($old, $new);
        $this->assertFileExists($this->path($new));
        $this->assertFileDoesNotExist($this->path($old), 'Eski görsel diskte kaldı');
        $this->assertFileDoesNotExist($this->variant($old, 'sm'), 'Eski görselin varyantı diskte kaldı');
    }

    public function test_replacing_without_an_old_path_just_uploads(): void
    {
        $stored = $this->uploads->replaceImage($this->image(), 'testing', 'İlk', null);

        $this->assertFileExists($this->path($stored));
    }

    public function test_deleting_an_empty_path_is_a_no_op(): void
    {
        $this->uploads->deleteImage('');

        $this->assertTrue(true, 'Boş yol istisna fırlatmamalı');
    }

    // ── Doğrulama ──

    public function test_an_unsupported_image_type_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->uploads->uploadImage(
            UploadedFile::fake()->create('zararli.svg', 8, 'image/svg+xml'),
            'testing',
            'SVG',
        );
    }

    public function test_a_non_image_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->uploads->uploadImage(
            UploadedFile::fake()->create('rapor.pdf', 8, 'application/pdf'),
            'testing',
            'PDF',
        );
    }

    /**
     * Rejecting the file must not leave a half-written one behind.
     */
    public function test_a_rejected_upload_writes_nothing(): void
    {
        try {
            $this->uploads->uploadImage(
                UploadedFile::fake()->create('rapor.pdf', 8, 'application/pdf'),
                'reddedilen',
                'PDF',
            );
        } catch (RuntimeException) {
            // beklenen
        }

        $this->assertDirectoryDoesNotExist(UploadService::basePath('reddedilen'));
    }

    // ── Görsel olmayan dosyalar ──

    public function test_a_document_is_stored_without_conversion(): void
    {
        $stored = $this->uploads->uploadFile(
            UploadedFile::fake()->create('Fiyat Listesi.pdf', 8, 'application/pdf'),
            'documents',
            'Fiyat Listesi',
        );

        $this->assertStringStartsWith('documents/fiyat-listesi-', $stored);
        $this->assertStringEndsWith('.pdf', $stored);
        $this->assertFileExists($this->path($stored));
    }

    public function test_replacing_a_document_deletes_the_old_one(): void
    {
        $old = $this->uploads->uploadFile(
            UploadedFile::fake()->create('eski.pdf', 8, 'application/pdf'),
            'documents',
            'Eski',
        );

        $new = $this->uploads->replaceFile(
            UploadedFile::fake()->create('yeni.pdf', 8, 'application/pdf'),
            'documents',
            'Yeni',
            $old,
        );

        $this->assertFileExists($this->path($new));
        $this->assertFileDoesNotExist($this->path($old));
    }

    // ── URL çözümleme ──

    /**
     * The reads used to hardcode public/uploads while the writes honoured
     * config('uploads.path'). Anywhere the two disagreed, the variant lookup
     * missed and every image quietly served its full-size original.
     */
    public function test_the_url_helper_points_at_an_existing_variant(): void
    {
        $stored = $this->uploads->uploadImage($this->image(1000, 800), 'testing', 'Kapak');

        $this->assertSame("/uploads/{$stored}", UploadService::url($stored));
        $this->assertStringEndsWith('-sm.webp', UploadService::url($stored, 'sm'));
    }

    public function test_the_url_helper_falls_back_when_the_variant_is_missing(): void
    {
        // 400px wide, so no md variant is produced.
        $stored = $this->uploads->uploadImage($this->image(400, 300), 'testing', 'Küçük');

        $this->assertSame("/uploads/{$stored}", UploadService::url($stored, 'md'));
    }

    public function test_the_url_helper_returns_a_placeholder_for_no_image(): void
    {
        $this->assertStringContainsString('placehold.co', UploadService::url(null));
        $this->assertStringContainsString('placehold.co', UploadService::url(''));
    }

    public function test_srcset_lists_only_variants_that_exist(): void
    {
        $stored = $this->uploads->uploadImage($this->image(1000, 800), 'testing', 'Kapak');

        $srcset = UploadService::srcset($stored);

        $this->assertStringContainsString('-sm.webp 300w', $srcset);
        $this->assertStringContainsString('-md.webp 600w', $srcset);
        $this->assertStringNotContainsString('-lg.webp', $srcset, 'Üretilmemiş lg varyantı srcset e girdi');
    }

    public function test_srcset_is_empty_without_an_image(): void
    {
        $this->assertSame('', UploadService::srcset(null));
    }

    /**
     * Blade reaches the service through this helper, so it has to agree.
     */
    public function test_the_blade_helper_matches_the_service(): void
    {
        $stored = $this->uploads->uploadImage($this->image(1000, 800), 'testing', 'Kapak');

        $this->assertSame(UploadService::url($stored, 'sm'), upload_url($stored, 'sm'));
    }

    private function variant(string $stored, string $size): string
    {
        $info = pathinfo($stored);

        return $this->path("{$info['dirname']}/{$info['filename']}-{$size}.{$info['extension']}");
    }
}
