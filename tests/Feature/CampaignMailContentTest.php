<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\RecipientImportService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

/**
 * What actually lands in the inbox.
 *
 * Images are embedded rather than linked: most mail clients block remote
 * images by default, and a linked one breaks completely once the mail is
 * forwarded or read offline.
 */
class CampaignMailContentTest extends TestCase
{
    use RefreshDatabase;

    private function image(string $name = 'gorsel.webp'): string
    {
        $directory = UploadService::basePath('content');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $image = imagecreatetruecolor(60, 40);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 160, 140));
        imagewebp($image, $directory . '/' . $name);
        imagedestroy($image);

        return 'content/' . $name;
    }

    private function mailFor(string $body): CampaignMail
    {
        $campaign = Campaign::factory()->create(['body' => $body]);

        $recipient = new CampaignRecipient([
            'campaign_id'       => $campaign->id,
            'email'             => 'alici@ornek.com',
            'name'              => 'Alıcı',
            'unsubscribe_token' => str_repeat('b', 64),
        ]);

        return new CampaignMail($campaign->load('attachments'), $recipient);
    }

    public function test_a_site_image_is_embedded_as_an_inline_attachment(): void
    {
        $path = $this->image();

        $html = $this->mailFor('<p>Metin</p><img src="/uploads/' . $path . '" alt="a">')->render();

        $this->assertMatchesRegularExpression('/<img[^>]+src="cid:img-[0-9a-f]{16}"/', $html);
        $this->assertStringNotContainsString('/uploads/' . $path, $html, 'Görsel hâlâ bağlantı olarak duruyor');
    }

    public function test_the_same_image_used_twice_is_embedded_once(): void
    {
        $path = $this->image();
        $tag = '<img src="/uploads/' . $path . '">';

        $html = $this->mailFor($tag . '<p>arada</p>' . $tag)->render();

        preg_match_all('/src="(cid:img-[0-9a-f]{16})"/', $html, $matches);

        $this->assertCount(2, $matches[1]);
        $this->assertCount(1, array_unique($matches[1]), 'Aynı görsel iki ayrı ek olarak gömüldü');
    }

    /**
     * Fetching a third-party URL from inside the send loop is not something a
     * bulk mailer should do, so remote images are left as they are.
     */
    public function test_an_external_image_is_left_alone(): void
    {
        $html = $this->mailFor('<img src="https://cdn.baskasite.com/logo.png">')->render();

        $this->assertStringContainsString('https://cdn.baskasite.com/logo.png', $html);
        $this->assertStringNotContainsString('cid:img-', $html);
    }

    public function test_an_absolute_url_to_our_own_upload_is_still_embedded(): void
    {
        $path = $this->image();
        $absolute = rtrim((string) config('app.url'), '/') . '/uploads/' . $path;

        $html = $this->mailFor('<img src="' . $absolute . '">')->render();

        $this->assertStringContainsString('cid:img-', $html);
    }

    public function test_a_cache_busting_query_does_not_break_the_lookup(): void
    {
        $path = $this->image();

        $html = $this->mailFor('<img src="/uploads/' . $path . '?v=12345">')->render();

        $this->assertStringContainsString('cid:img-', $html);
    }

    /**
     * The editor stores whatever it is given, so the path is resolved against
     * the uploads directory and anything escaping it is refused.
     */
    public function test_a_path_outside_the_uploads_directory_is_not_embedded(): void
    {
        $html = $this->mailFor('<img src="/uploads/../../../etc/passwd">')->render();

        $this->assertStringNotContainsString('cid:', $html);
    }

    public function test_a_missing_file_is_not_embedded(): void
    {
        $html = $this->mailFor('<img src="/uploads/content/boyle-bir-dosya-yok.webp">')->render();

        $this->assertStringNotContainsString('cid:img-', $html);
    }

    public function test_a_data_uri_is_left_alone(): void
    {
        $data = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

        $html = $this->mailFor('<img src="' . $data . '">')->render();

        $this->assertStringContainsString($data, $html);
    }

    // ── Excel / CSV okuma ──

    public function test_the_shipped_template_round_trips(): void
    {
        $importer = app(RecipientImportService::class);
        $path = tempnam(sys_get_temp_dir(), 'sablon') . '.xlsx';

        $importer->writeTemplate($path);
        $result = $importer->parse(new UploadedFile($path, 'sablon.xlsx', null, null, true));

        $this->assertSame(3, $result['total']);
        $this->assertSame(['name' => 'Ahmet Yılmaz', 'email' => 'ahmet@ornek.com'], $result['rows'][0]);

        unlink($path);
    }

    /**
     * Excel on a Turkish locale writes CSV with semicolons and a BOM.
     */
    public function test_a_semicolon_csv_with_a_bom_is_read(): void
    {
        $result = $this->parseCsv("\xEF\xBB\xBFAd Soyad;E-posta\nZeynep Ak;zeynep@ornek.com\n");

        $this->assertSame([['name' => 'Zeynep Ak', 'email' => 'zeynep@ornek.com']], $result['rows']);
    }

    public function test_a_comma_csv_is_read(): void
    {
        $result = $this->parseCsv("name,email\nJohn Doe,john@ornek.com\n");

        $this->assertSame([['name' => 'John Doe', 'email' => 'john@ornek.com']], $result['rows']);
    }

    public function test_columns_in_the_other_order_are_still_matched(): void
    {
        $result = $this->parseCsv("E-posta;Ad Soyad\nters@ornek.com;Ters Sıra\n");

        $this->assertSame([['name' => 'Ters Sıra', 'email' => 'ters@ornek.com']], $result['rows']);
    }

    public function test_a_file_with_no_header_falls_back_to_the_address_column(): void
    {
        $result = $this->parseCsv("basliksiz@ornek.com\nikinci@ornek.com\n");

        $this->assertCount(2, $result['rows'], 'Başlıksız dosyada ilk satır atlanmış');
        $this->assertSame('basliksiz@ornek.com', $result['rows'][0]['email']);
    }

    public function test_invalid_addresses_are_counted_and_skipped(): void
    {
        $result = $this->parseCsv("Ad;E-posta\nA;iyi@ornek.com\nB;bu-mail-degil\nC;\n");

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['invalid'], 'Boş satır geçersiz sayılmamalı, bozuk olan sayılmalı');
    }

    public function test_a_duplicate_address_in_the_file_is_kept_once(): void
    {
        $result = $this->parseCsv("Ad;E-posta\nA;ayni@ornek.com\nB;AYNI@ornek.com\n");

        $this->assertSame(1, $result['total']);
    }

    public function test_a_file_without_any_address_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parseCsv("Ad;Telefon\nA;05551112233\n");
    }

    public function test_an_unsupported_extension_is_refused(): void
    {
        $this->expectExceptionMessage('Desteklenmeyen dosya biçimi');

        $path = tempnam(sys_get_temp_dir(), 'x') . '.docx';
        file_put_contents($path, 'x');

        app(RecipientImportService::class)->parse(new UploadedFile($path, 'liste.docx', null, null, true));
    }

    /**
     * @return array{rows: array<int, array{name: ?string, email: string}>, total: int, invalid: int}
     */
    private function parseCsv(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'liste') . '.csv';
        file_put_contents($path, $contents);

        try {
            return app(RecipientImportService::class)->parse(new UploadedFile($path, 'liste.csv', null, null, true));
        } finally {
            @unlink($path);
        }
    }
}
