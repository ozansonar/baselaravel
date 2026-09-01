<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campaign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\User;
use App\Support\Export\ExportFormat;
use App\Support\Export\ExportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Liste dışa aktarma.
 *
 * Panelin bütün listeleri tek uçtan dışa aktarılıyor; o uç 32 tanımı üç ayrı
 * yazıcıya bağlıyor. Bir tanımdaki hata (yanlış sütun kapanışı, olmayan ilişki,
 * sorgusuz liste) ancak dosya üretilirken patlıyor — yani ekranda hiçbir şey
 * görünmüyor, kullanıcı indirmeye bastığında 500 alıyor.
 *
 * Bu yüzden sınav tek tek listeler üzerinden değil, kayıt defterinin tamamı
 * üzerinden koşuyor: config'e yeni bir liste eklendiği anda o liste de bu
 * testlerin kapsamına giriyor, ayrıca test yazmak gerekmiyor.
 */
class ListExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
        $this->seed(\Database\Seeders\LanguageSeeder::class);
    }

    // ── Kayıt defterinin tamamı ──

    /**
     * Her liste, her biçimde gerçekten dosya üretiyor.
     *
     * Boş veritabanında koşuyor: amaç satırları değil, tanımın kendisini
     * sınamak — sütun kapanışları çağrılabiliyor mu, sorgu kurulabiliyor mu,
     * yazıcı dosyayı bütünleyebiliyor mu.
     */
    #[DataProvider('exportKeys')]
    public function test_every_registered_list_downloads_in_every_format(string $key): void
    {
        $admin = $this->admin();
        $query = $this->requiredQueryFor($key);

        foreach (ExportFormat::cases() as $format) {
            $response = $this->actingAs($admin)->get("/admin/disa-aktar/{$key}/{$format->value}{$query}");

            $response->assertOk();

            $this->assertNotSame(
                '',
                $this->contentOf($response),
                "{$key} listesi {$format->value} biçiminde boş dosya üretti.",
            );
        }
    }

    /**
     * Üretilen dosya gerçekten iddia ettiği biçimde mi?
     *
     * "200 döndü" yetmiyor: yanlış yazıcıya bağlanan bir biçim de 200 döner.
     * Dosyanın ilk baytları biçimi ele veriyor.
     */
    public function test_each_format_produces_a_file_of_that_type(): void
    {
        $admin = $this->admin();

        $xlsx = $this->contentOf($this->actingAs($admin)->get('/admin/disa-aktar/users/excel'));
        // XLSX bir ZIP arşividir.
        $this->assertStringStartsWith('PK', $xlsx);

        $pdf = $this->contentOf($this->actingAs($admin)->get('/admin/disa-aktar/users/pdf'));
        $this->assertStringStartsWith('%PDF', $pdf);

        $csv = $this->contentOf($this->actingAs($admin)->get('/admin/disa-aktar/users/csv'));
        // UTF-8 BOM: onsuz Excel Türkçe harfleri bozuyor.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_the_file_name_carries_the_list_and_the_moment(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/disa-aktar/users/csv');

        $response->assertOk();
        $response->assertDownload();

        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('kullanici-listesi', $disposition);
        $this->assertStringContainsString(now()->format('Y-m-d'), $disposition);
        $this->assertStringEndsWith('.csv', $disposition);
    }

    // ── CSV'nin kendine ait kuralları ──

    public function test_the_csv_carries_the_headers_and_the_rows(): void
    {
        $this->makeUser('Ayse', 'Yilmaz', 'ayse@example.test');

        $csv = $this->contentOf($this->actingAs($this->admin())->get('/admin/disa-aktar/users/csv'));
        $lines = $this->csvLines($csv);

        // Boşluk taşıyan başlıklar tırnak içinde: fputcsv'nin kaçış kuralı.
        $this->assertSame('"Ad Soyad";E-posta;Rol;Durum;"Kayıt Tarihi"', $lines[0]);
        $this->assertTrue(
            array_any($lines, static fn (string $line): bool => str_contains($line, '"Ayse Yilmaz";ayse@example.test')),
            'Kullanıcı satırı CSV dosyasına düşmedi.',
        );
    }

    /**
     * Ayraç yapılandırmadan geliyor; Türkçe Excel'e göre varsayılan noktalı
     * virgül ama başka yerel ayara kurulan proje bunu çevirebilmeli.
     */
    public function test_the_csv_delimiter_comes_from_the_configuration(): void
    {
        config(['export.csv_delimiter' => ',']);

        $csv = $this->contentOf($this->actingAs($this->admin())->get('/admin/disa-aktar/users/csv'));

        $this->assertSame('"Ad Soyad",E-posta,Rol,Durum,"Kayıt Tarihi"', $this->csvLines($csv)[0]);
    }

    public function test_the_csv_bom_can_be_switched_off(): void
    {
        config(['export.csv_bom' => false]);

        $csv = $this->contentOf($this->actingAs($this->admin())->get('/admin/disa-aktar/users/csv'));

        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $csv);
    }

    /**
     * Formül enjeksiyonu: kullanıcının adına yazdığı "=..." metni, dosya
     * Excel'de açıldığında formüle dönüşmemeli.
     */
    public function test_a_formula_in_the_data_is_neutralised_in_the_csv(): void
    {
        $this->makeUser('=HYPERLINK("http://kotu.test")', 'Deneme', 'formul@example.test');

        $csv = $this->contentOf($this->actingAs($this->admin())->get('/admin/disa-aktar/users/csv'));

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
    }

    // ── Yetki ──

    /**
     * Dışa aktarma, veriyi sistemin dışına çıkaran okuma işlemi: ekranı
     * göremeyen dosyayı da indirememeli.
     */
    public function test_a_user_without_the_permission_cannot_export(): void
    {
        $viewer = $this->makeUser('Yetkisiz', 'Kisi', 'yetkisiz@example.test');
        $viewer->roles()->attach(Role::where('slug', 'user')->firstOrFail()->id);

        foreach (['users', 'audit-logs', 'failed-jobs', 'custom-routes', 'content-list'] as $key) {
            $this->actingAs($viewer->fresh())
                ->get("/admin/disa-aktar/{$key}/csv")
                ->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/admin/disa-aktar/users/excel')->assertRedirect();
    }

    // ── Tanımsız istekler ──

    public function test_an_unknown_list_key_is_not_found(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/disa-aktar/olmayan-liste/excel')
            ->assertNotFound();
    }

    public function test_an_unknown_format_is_not_found(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/disa-aktar/users/word')
            ->assertNotFound();
    }

    // ── Süzgeçler ──

    /**
     * Ekrandaki süzgeç dosyaya aynen yansımalı; yansımazsa yönetici ekranda
     * beş satır görüp beş yüz satırlık dosya indiriyor.
     */
    public function test_the_screen_filters_reach_the_file(): void
    {
        $this->makeUser('Filtre', 'Birinci', 'birinci@example.test');
        $this->makeUser('Filtre', 'Ikinci', 'ikinci@example.test');

        $csv = $this->contentOf(
            $this->actingAs($this->admin())->get('/admin/disa-aktar/users/csv?search=ikinci'),
        );

        $this->assertStringContainsString('ikinci@example.test', $csv);
        $this->assertStringNotContainsString('birinci@example.test', $csv);
    }

    /**
     * Sayfa numarası süzgeç değil: dosyaya listenin tamamı iner.
     */
    public function test_the_page_number_is_not_treated_as_a_filter(): void
    {
        foreach (range(1, 5) as $index) {
            $this->makeUser('Sayfa', "Kisi{$index}", "sayfa{$index}@example.test");
        }

        $csv = $this->contentOf(
            $this->actingAs($this->admin())->get('/admin/disa-aktar/users/csv?page=99'),
        );

        $this->assertStringContainsString('sayfa1@example.test', $csv);
        $this->assertStringContainsString('sayfa5@example.test', $csv);
    }

    // ── Satır tavanı ──

    /**
     * PDF sayfaları belge kapanana kadar bellekte tutuyor; tavan aşıldığında
     * dosya sessizce kırpılmıyor, kullanıcı uyarılıyor.
     */
    public function test_the_pdf_warns_instead_of_producing_a_truncated_file(): void
    {
        config(['export.pdf_row_limit' => 1]);

        $this->makeUser('Tavan', 'Birinci', 'tavan1@example.test');
        $this->makeUser('Tavan', 'Ikinci', 'tavan2@example.test');

        $response = $this->actingAs($this->admin())
            ->from('/admin/users')
            ->get('/admin/disa-aktar/users/pdf');

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('warning');
    }

    /**
     * Tavan yalnız PDF'e ait: Excel ve CSV akış hâlinde yazıldığı için satır
     * sayısı belleği etkilemiyor, aynı sınır onları durdurmamalı.
     */
    public function test_the_row_limit_does_not_stop_the_streaming_formats(): void
    {
        config(['export.pdf_row_limit' => 1]);

        $this->makeUser('Tavan', 'Birinci', 'tavan1@example.test');
        $this->makeUser('Tavan', 'Ikinci', 'tavan2@example.test');

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/disa-aktar/users/excel')->assertOk();

        $csv = $this->contentOf($this->actingAs($admin)->get('/admin/disa-aktar/users/csv'));

        $this->assertStringContainsString('tavan1@example.test', $csv);
        $this->assertStringContainsString('tavan2@example.test', $csv);
    }

    // ── Sorgu bütçesi ──

    /**
     * Dışa aktarma satır başına sorgu atmıyor.
     *
     * Bu, listelerin en sessiz tehlikesi: ekranda yirmi beş satır varken
     * ilişkiye dokunan bir sütun fark edilmiyor, aynı sütun on bin satırlık
     * dosyada on bin sorgu açıyor ve istek zaman aşımına düşüyor. Sınav sayıya
     * değil davranışa bakıyor: kayıt sayısı artınca sorgu sayısı artmamalı.
     */
    #[DataProvider('exportKeys')]
    public function test_no_export_queries_once_per_row(string $key): void
    {
        // Bağlam isteyen listeler (bir kampanyanın alıcıları gibi) dışarıda:
        // sorguları tek bir üst kayda bağlı, kayıt sayısıyla büyümüyorlar.
        if ($key === 'campaign-recipients') {
            $this->markTestSkipped('Kampanya alıcıları tek kampanyaya bağlı.');
        }

        $export = app(ExportRegistry::class)->get($key);

        try {
            $model = $export->query([])->getModel()::class;
        } catch (\LogicException) {
            // Arkasında tablo olmayan listeler (yedekler, sistem durumu,
            // başarısız işler) kendi kaynaklarından okuyor.
            $this->markTestSkipped("{$key} sorgu üzerinden gezilmiyor.");
        }

        if (! method_exists($model, 'factory')) {
            $this->markTestSkipped("{$key} için fabrika yok.");
        }

        $admin = $this->admin();
        $query = $this->requiredQueryFor($key);

        // Ölçüm önbellek durumuna duyarlı: ısınmış bir anahtar bir sorguyu
        // gizler, düşmüş bir anahtar fazladan bir sorgu doğurur. Tam paket
        // içinde koşarken önceki testlerin bıraktığı önbellek bu ölçümü
        // kaydırabiliyordu — sayım kendi bilinen durumundan başlasın.
        Cache::flush();

        $model::factory()->create();

        // Isıtma: ilk istek ayarları ve dilleri önbelleğe alıyor, o sorgular
        // listeye değil kuruluma ait. Ölçüm ısınmış durumdan başlamalı.
        $this->actingAs($admin)->get("/admin/disa-aktar/{$key}/csv{$query}")->assertOk();

        $first = $this->countQueries(fn () => $this->actingAs($admin)
            ->get("/admin/disa-aktar/{$key}/csv{$query}")->assertOk());

        $model::factory()->count(4)->create();

        // İkinci ısıtma: kayıt eklemek ilgili önbellekleri düşürüyor (örneğin
        // yönlendirme haritası), o bir kerelik tazeleme sorgusu satır sayısına
        // bağlı değil ve ölçüme karışmamalı.
        $this->actingAs($admin)->get("/admin/disa-aktar/{$key}/csv{$query}")->assertOk();

        $second = $this->countQueries(fn () => $this->actingAs($admin)
            ->get("/admin/disa-aktar/{$key}/csv{$query}")->assertOk());

        $this->assertSame(
            $first,
            $second,
            "{$key} listesi dört kayıt daha eklenince {$second} sorgu attı (önce {$first}); "
                . 'ilişkileri eager loading ile çekin.',
        );
    }

    // ── Denetim izi ──

    public function test_every_download_leaves_an_audit_record(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/disa-aktar/users/csv?search=kimse')->assertOk();

        $log = AuditLog::latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('CSV olarak dışa aktarıldı', (string) $log->label);
        $this->assertSame('csv', $log->new_values['format'] ?? null);
        $this->assertSame(['search' => 'kimse'], $log->new_values['filters'] ?? null);
    }

    // ── Ekranlarla bağ ──

    /**
     * Tanım kayıtlı olsa da ekranda düğmesi yoksa kimse ulaşamaz. Bu turda
     * eklenen üç ekranın menüsü yerinde mi?
     */
    public function test_the_new_screens_carry_the_export_menu(): void
    {
        $admin = $this->admin();

        $screens = [
            '/admin/icerikler'      => 'content-list',
            '/admin/custom-routes'  => 'custom-routes',
            '/admin/kuyruk'         => 'failed-jobs',
        ];

        foreach ($screens as $url => $key) {
            $response = $this->actingAs($admin)->get($url);

            $response->assertOk();

            foreach (['excel', 'csv', 'pdf'] as $format) {
                $response->assertSee(
                    route('admin.export', ['key' => $key, 'format' => $format], absolute: false),
                    escape: false,
                );
            }
        }
    }

    /**
     * Bir liste ekranı dışa aktarma menüsü taşıyorsa, gösterdiği anahtar
     * kayıt defterinde bulunmalı — yanlış yazılmış bir anahtar ekranda
     * görünmüyor, tıklandığında 404 veriyor.
     */
    public function test_no_screen_points_at_an_unregistered_list(): void
    {
        $registered = app(ExportRegistry::class)->keys();
        $used = [];

        foreach (glob(resource_path('views/admin/**/*.blade.php')) ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match_all('/<x-export-menu[^>]*\bexport="([^"]+)"/', $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $key) {
                $used[$key] = basename(dirname($file)) . '/' . basename($file);
            }
        }

        $this->assertNotEmpty($used, 'Hiçbir ekranda dışa aktarma menüsü bulunamadı — tarama yanlış yerde arıyor.');

        foreach ($used as $key => $file) {
            $this->assertContains($key, $registered, "{$file} kayıtlı olmayan '{$key}' listesini gösteriyor.");
        }
    }

    // ── Yardımcılar ──

    /**
     * Kayıtlı liste anahtarları.
     *
     * Veri sağlayıcı uygulama ayağa kalkmadan çalışıyor; yapılandırma dosyası
     * `storage_path()` çağırdığı için require edilemiyor. Anahtarlar bu yüzden
     * metinden okunuyor — ayrıştırma bozulursa test kendi kendini durduruyor.
     *
     * @return list<array{string}>
     */
    public static function exportKeys(): array
    {
        $config = (string) file_get_contents(__DIR__ . '/../../config/export.php');

        preg_match('/\'lists\'\s*=>\s*\[(.*?)\n    \],/s', $config, $block);

        preg_match_all('/\'([a-z0-9-]+)\'\s*=>\s*App\\\\Exports\\\\/', $block[1] ?? '', $matches);

        if ($matches[1] === []) {
            throw new \RuntimeException('config/export.php içindeki liste anahtarları okunamadı.');
        }

        return array_map(static fn (string $key): array => [$key], $matches[1]);
    }

    /**
     * Bazı listeler tek başına anlamlı değil: kampanya alıcıları hangi
     * kampanyanın alıcıları olduğu söylenmeden bulunamıyor ve doğru olarak 404
     * veriyor. Bu listeler için gereken en küçük bağlam burada kuruluyor.
     */
    private function requiredQueryFor(string $key): string
    {
        if ($key !== 'campaign-recipients') {
            return '';
        }

        return '?campaign=' . Campaign::factory()->create()->getKey();
    }

    private function admin(): User
    {
        $user = $this->makeUser('Disa', 'Aktarici', 'disa-aktarici@example.test');
        $user->roles()->attach(Role::where('slug', 'admin')->firstOrFail()->id);

        return $user->fresh();
    }

    private function makeUser(string $first, string $last, string $email): User
    {
        $user = User::create([
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $email,
            'password'   => 'sifre-123456',
            'is_active'  => true,
        ]);
        $user->markEmailAsVerified();

        return $user;
    }

    /**
     * Bir işin attığı sorgu sayısı.
     *
     * @param callable(): mixed $work
     */
    private function countQueries(callable $work): int
    {
        $count = 0;

        DB::listen(static function () use (&$count): void {
            ++$count;
        });

        $work();

        return $count;
    }

    /**
     * İndirilen dosyanın gövdesi.
     *
     * Yanıt bir dosya indirmesi: gövde bellekte değil diskte duruyor ve
     * gönderim bitince siliniyor. Test gönderimi tetiklemediği için dosya
     * hâlâ yerinde, doğrudan oradan okunuyor.
     */
    private function contentOf(TestResponse $response): string
    {
        $native = $response->baseResponse;

        if ($native instanceof BinaryFileResponse) {
            return (string) file_get_contents($native->getFile()->getPathname());
        }

        return (string) $response->getContent();
    }

    /** @return list<string> */
    private function csvLines(string $csv): array
    {
        $text = str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;

        return array_values(array_filter(explode("\r\n", $text), static fn (string $line): bool => $line !== ''));
    }
}
