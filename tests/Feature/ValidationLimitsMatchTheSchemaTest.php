<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Doğrulama sınırları sütunun taşıyabildiğinden geniş olamaz.
 *
 * AppServiceProvider `Schema::defaultStringLength(191)` çağırıyor, yani uzunluk
 * verilmeden açılan her varchar sütun 191 karakter. Kurallarda ise 255, 500 gibi
 * sayılar yazıyordu — kopyala-yapıştırla çoğalmış, sütunla ilgisi olmayan
 * rakamlar. Sonuç sessiz değil ama geç: 200 karakterlik bir değer doğrulamadan
 * geçiyor, ardından veritabanı "Data too long" ile düşüyor ve kullanıcı
 * doğrulama hatası değil 500 görüyor.
 *
 * Denetim iki yönlü. Sunucu kuralı sütuna sığmalı, form kuralı da sunucununkiyle
 * birebir aynı olmalı: istemci gevşek olursa hata sunucuya kadar gidiyor, katı
 * olursa kullanıcı kabul edilecek bir değeri yazamıyor.
 *
 * Sütun genişlikleri göçlerden okunuyor, veritabanından değil: SQLite varchar
 * uzunluğunu saklamıyor (şemada yalnız "varchar" yazıyor) ve testler SQLite'ta
 * koşuyor. Ayrıştırıcı bilerek temkinli — tanımadığı sütunu atlıyor. Gerçek
 * MariaDB şemasıyla karşılaştırıldığında 185 varchar/char sütunun 152'sini
 * doğru biliyor, 33'ünü hiç bilmiyor, hiçbirini yanlış bilmiyor.
 */
final class ValidationLimitsMatchTheSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** AppServiceProvider::boot() → Schema::defaultStringLength() */
    private const DEFAULT_STRING_LENGTH = 191;

    /**
     * Çok dilli istekler kurallarını aktif diller üzerinde döngüyle kuruyor:
     * dil yoksa translations.* kuralları hiç doğmuyor ve denetim en kalabalık
     * grubu —çevrilebilir içerik formlarını— sessizce atlıyor.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();
    }

    /**
     * Adından tablosu çıkmayan istek sınıfları.
     *
     * @var array<string, string|null>
     */
    private const TABLES = [
        'ProfileUpdateRequest'         => 'users',
        'UpdateProfileRequest'         => 'users',
        'RegisterRequest'              => 'users',
        'LoginRequest'                 => 'users',
        'ForgotPasswordRequest'        => 'users',
        'StoreUserRequest'             => 'users',
        'UpdateUserRequest'            => 'users',
        'MailTemplateUpdateRequest'    => 'mail_templates',
        'ImportSubscribersRequest'     => 'subscribers',
        'UpdateSubscriberRequest'      => 'subscribers',
        'StoreCampaignRequest'         => 'campaigns',
        'UpdateCampaignRequest'        => 'campaigns',
        'StoreContactMessageRequest'   => 'contact_messages',
        'ContactMessageReplyRequest'   => 'contact_messages',
        'StoreGalleryBulkImageRequest' => 'gallery_items',
        'StoreContentFileRequest'      => 'content_files',
        'UpdateFileRequest'            => 'uploaded_files',
        'UploadFileRequest'            => 'uploaded_files',
        'StoreMenuItemRequest'         => 'menu_items',
        'UpdateMenuItemRequest'        => 'menu_items',
        'UpdateMenuRequest'            => 'menus',
        'StoreLanguageRequest'         => 'languages',
        'UpdateLanguageRequest'        => 'languages',
        'StoreRoleRequest'             => 'roles',
        'UpdateRoleRequest'            => 'roles',
        'StoreRedirectRequest'         => 'redirects',
        'UpdateRedirectRequest'        => 'redirects',
        'StoreBlogCommentRequest'      => 'blog_comments',
        // Sütuna yazılmayan istekler: dosya sistemi, toplu işlem kimlikleri,
        // izin matrisi. Bunlarda karşılaştırılacak bir sütun yok.
        'SyncPermissionMatrixRequest'  => null,
        'BulkDeleteBackupsRequest'     => null,
        'BulkNotificationRequest'      => null,
        'TrackPageViewRequest'         => null,
    ];

    /**
     * Form alanı adı sütun adından farklı olanlar.
     *
     * @var array<string, string>
     */
    private const COLUMN_ALIASES = [
        'contact_messages.reply_body' => 'reply_text',
    ];

    /**
     * Görünüm dizini → o formun yazdığı tablo.
     *
     * @var array<string, string>
     */
    private const VIEW_TABLES = [
        'admin/blog-categories'    => 'blog_categories',
        'admin/blog-posts'         => 'blog_posts',
        'admin/campaigns'          => 'campaigns',
        'admin/faqs'               => 'faqs',
        'admin/files'              => 'uploaded_files',
        'admin/gallery-categories' => 'gallery_categories',
        'admin/gallery-items'      => 'gallery_items',
        'admin/languages'          => 'languages',
        'admin/mail-templates'     => 'mail_templates',
        'admin/menus'              => 'menu_items',
        'admin/pages'              => 'pages',
        'admin/popups'             => 'popups',
        'admin/profile'            => 'users',
        'admin/redirects'          => 'redirects',
        'admin/roles'              => 'roles',
        'admin/sliders'            => 'sliders',
        'admin/subscribers'        => 'subscribers',
        'admin/users'              => 'users',
        'account'                  => 'users',
        'auth'                     => 'users',
        'contact'                  => 'contact_messages',
        'partials/blog-comments'   => 'blog_comments',
        'partials/newsletter-form' => 'subscribers',
    ];

    // ── Denetimler ──

    public function test_no_rule_lets_through_more_than_the_column_holds(): void
    {
        $widths = $this->columnWidths();
        $tasan = [];

        foreach ($this->serverLimits() as $limit) {
            $key = $limit['table'] . '.' . $limit['column'];
            $width = $widths[$key] ?? false;

            // Bilinmeyen sütun ve metin türleri kapsam dışı: ayrıştırıcı
            // tanımadığını uydurmuyor, sınır atıyor.
            if ($width === false || $width === null) {
                continue;
            }

            if ($limit['max'] > $width) {
                $tasan[] = sprintf(
                    '%s → %s alanı max:%d, ama %s sütunu %d karakter',
                    $limit['class'],
                    $limit['field'],
                    $limit['max'],
                    $key,
                    $width,
                );
            }
        }

        sort($tasan);

        $this->assertSame(
            [],
            $tasan,
            "Doğrulamadan geçip veritabanında düşecek değerler var:\n" . implode("\n", $tasan),
        );
    }

    public function test_the_forms_carry_the_same_limits_as_the_server(): void
    {
        $server = [];

        foreach ($this->serverLimits() as $limit) {
            $server[$limit['table'] . '.' . $limit['column']][] = $limit['max'];
        }

        $uyusmaz = [];

        foreach ($this->bladeLimits() as $field) {
            $key = $field['table'] . '.' . $field['column'];

            if (! isset($server[$key])) {
                continue;
            }

            $beklenen = array_values(array_unique($server[$key]));

            // Aynı sütuna yazan birden çok istek sınıfı olabilir ve farklı
            // sayılar söyleyebilirler (çevrilebilir form ile eski tekil form
            // gibi). Formun hangisine bağlı olduğunu buradan bilemiyoruz;
            // aranan, sunucuda karşılığı olan bir sayı taşıması. Hiçbirine
            // uymuyorsa sınır serbest yazılmış demektir.
            if (in_array($field['max'], $beklenen, true)) {
                continue;
            }

            sort($beklenen);

            $uyusmaz[] = sprintf(
                '%s:%d → %s alanı maxSize[%d], sunucu %s diyor',
                $field['view'],
                $field['line'],
                $field['column'],
                $field['max'],
                'max:' . implode(' / max:', $beklenen),
            );
        }

        sort($uyusmaz);

        $this->assertSame(
            [],
            $uyusmaz,
            "Formdaki sınır sunucudakinden farklı:\n" . implode("\n", $uyusmaz),
        );
    }

    /**
     * Denetimin gerçekten bir şey ölçtüğü.
     *
     * Üstteki iki denetim, çözemediği alanı atlıyor. Ayrıştırıcılardan biri
     * bozulsa ikisi de boş listeyle yeşil geçerdi — sessizce hiçbir şey
     * ölçmemek, ölçüp bulamamaktan farklı.
     */
    public function test_the_check_actually_covers_the_project(): void
    {
        $widths = $this->columnWidths();
        $server = $this->serverLimits();
        $blades = $this->bladeLimits();

        $karsilastirilan = array_filter(
            $server,
            fn (array $l): bool => ($widths[$l['table'] . '.' . $l['column']] ?? null) !== null,
        );

        $this->assertGreaterThan(150, count($widths), 'Göçlerden sütun genişliği okunamıyor');
        $this->assertGreaterThan(150, count($karsilastirilan), 'Kurallar sütunlarla eşleşmiyor');
        $this->assertGreaterThan(70, count($blades), 'Formlardan sınır okunamıyor');

        // Çeviri blokları, kurallarını aktif diller üzerinde döngüyle kuruyor.
        // Diller tohumlanmazsa bu kuralların hiçbiri doğmuyor ve denetim en
        // kalabalık grubu sessizce atlıyordu; bir kez öyle oldu.
        $ceviri = array_filter($server, static fn (array $l): bool => str_starts_with($l['field'], 'translations.'));

        $this->assertGreaterThan(40, count($ceviri), 'Çevrilebilir form kuralları hiç okunmadı');

        // Bilinen bir örnek: son turda hizalanan kullanıcı e-postası.
        $this->assertSame(191, $widths['users.email'] ?? null);

        $email = array_values(array_filter(
            $server,
            static fn (array $l): bool => $l['table'] === 'users' && $l['column'] === 'email',
        ));

        $this->assertNotEmpty($email, 'users.email kuralı hiç okunmadı');

        foreach ($email as $limit) {
            $this->assertSame(191, $limit['max']);
        }
    }

    // ── Sütun genişlikleri (göçlerden) ──

    /**
     * Göçlerin bildirdiği varchar/char genişlikleri.
     *
     * Veritabanı yerine göç dosyaları okunuyor: SQLite varchar uzunluğunu
     * saklamıyor ve testler orada koşuyor. Göçler sırayla işleniyor, sonraki
     * bildirim öncekini eziyor — tablonun son hâli neyse o.
     *
     * Metin türleri null ile işaretleniyor (sınırsız sayılıyor), tanınmayan
     * sütun hiç girmiyor. Ayrıştırıcı emin olmadığında susuyor.
     *
     * @return array<string, int|null>
     */
    private function columnWidths(): array
    {
        $texty = ['text', 'longText', 'mediumText', 'tinyText', 'json', 'jsonb'];
        $widths = [];

        foreach ($this->migrationFiles() as $file) {
            $up = $this->methodBody((string) file_get_contents($file), 'up');

            foreach ($this->schemaBlocks($up) as [$table, $body]) {
                preg_match_all("/\\\$table->(?:string|char)\(\s*'([a-z0-9_]+)'\s*(?:,\s*(\d+)\s*)?\)/", $body, $m, PREG_SET_ORDER);

                foreach ($m as $match) {
                    $widths["{$table}.{$match[1]}"] = isset($match[2]) && $match[2] !== ''
                        ? (int) $match[2]
                        : self::DEFAULT_STRING_LENGTH;
                }

                preg_match_all('/\$table->(' . implode('|', $texty) . ")\(\s*'([a-z0-9_]+)'/", $body, $m, PREG_SET_ORDER);

                foreach ($m as $match) {
                    $widths["{$table}.{$match[2]}"] = null;
                }

                preg_match_all("/\\\$table->dropColumn\(\s*(?:'([a-z0-9_]+)'|\[([^\]]*)\])/", $body, $m, PREG_SET_ORDER);

                foreach ($m as $match) {
                    $columns = $match[1] !== '' ? [$match[1]] : [];

                    if (($match[2] ?? '') !== '') {
                        preg_match_all("/'([a-z0-9_]+)'/", $match[2], $inner);
                        $columns = $inner[1];
                    }

                    foreach ($columns as $column) {
                        unset($widths["{$table}.{$column}"]);
                    }
                }

                preg_match_all("/\\\$table->renameColumn\(\s*'([a-z0-9_]+)'\s*,\s*'([a-z0-9_]+)'/", $body, $m, PREG_SET_ORDER);

                foreach ($m as $match) {
                    if (array_key_exists("{$table}.{$match[1]}", $widths)) {
                        $widths["{$table}.{$match[2]}"] = $widths["{$table}.{$match[1]}"];
                        unset($widths["{$table}.{$match[1]}"]);
                    }
                }
            }
        }

        return $widths;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $files = glob(database_path('migrations/*.php')) ?: [];

        // Göç sırası dosya adı sırası; sonraki bildirim öncekini eziyor.
        sort($files);

        return $files;
    }

    /**
     * Schema::create / Schema::table blokları: [tablo, gövde].
     *
     * @return list<array{0: string, 1: string}>
     */
    private function schemaBlocks(string $source): array
    {
        $blocks = [];
        $offset = 0;

        while (preg_match("/Schema::(?:create|table)\(\s*'([a-z0-9_]+)'\s*,/", $source, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $brace = strpos($source, '{', (int) $m[0][1] + strlen($m[0][0]));

            if ($brace === false) {
                break;
            }

            [$body, $end] = $this->balanced($source, $brace);
            $blocks[] = [$m[1][0], $body];
            $offset = $end;
        }

        return $blocks;
    }

    private function methodBody(string $source, string $method): string
    {
        if (! preg_match('/public function ' . preg_quote($method, '/') . '\(\)\s*:\s*void\s*\{/', $source, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        return $this->balanced($source, (int) $m[0][1] + strlen($m[0][0]) - 1)[0];
    }

    /**
     * Açılış süslü parantezinden eşleşen kapanışa kadar.
     *
     * @return array{0: string, 1: int}
     */
    private function balanced(string $source, int $open): array
    {
        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            $depth += match ($source[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return [substr($source, $open + 1, $i - $open - 1), $i];
            }
        }

        return [substr($source, $open + 1), $length];
    }

    // ── Sunucu kuralları (istek sınıflarından) ──

    /**
     * FormRequest'lerin gerçekten ürettiği metin uzunluğu sınırları.
     *
     * Kaynak metni taranmıyor, sınıf örneklenip rules() çağrılıyor: çeviri
     * blokları kurallarını döngüyle ve değişken anahtarla kuruyor, sabitten
     * gelen sayılar var — metin taraması bunların hiçbirini göremezdi.
     *
     * max: her kuralda aynı şeyi söylemiyor. Dosyada kilobayt, dizide eleman
     * sayısı, sayıda üst sınır demek; sütun genişliğiyle yalnız metinde
     * karşılaştırılabilir.
     *
     * @return list<array{class: string, field: string, table: string, column: string, max: int}>
     */
    private function serverLimits(): array
    {
        $limits = [];

        foreach ($this->formRequests() as $class) {
            $table = $this->tableFor(class_basename($class));

            if ($table === null) {
                continue;
            }

            $request = new $class();

            foreach ($request->rules() as $field => $rules) {
                if (! is_string($field)) {
                    continue;
                }

                $rules = is_array($rules) ? $rules : explode('|', (string) $rules);
                $strings = array_values(array_filter($rules, is_string(...)));
                $flat = implode('|', $strings);

                if (preg_match('/\b(array|file|image|integer|numeric)\b|\bmimes(types)?:/', $flat) === 1) {
                    continue;
                }

                foreach ($strings as $rule) {
                    if (preg_match('/^max:(\d+)$/', $rule, $m) !== 1) {
                        continue;
                    }

                    $parts = explode('.', $field);
                    $column = (string) end($parts);
                    $column = self::COLUMN_ALIASES["{$table}.{$column}"] ?? $column;

                    $limits[] = [
                        'class'  => class_basename($class),
                        'field'  => $field,
                        'table'  => $table,
                        'column' => $column,
                        'max'    => (int) $m[1],
                    ];
                }
            }
        }

        return $limits;
    }

    /**
     * @return list<class-string<FormRequest>>
     */
    private function formRequests(): array
    {
        $base = app_path('Http/Requests');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );

        $classes = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $relative = str_replace([$base . '/', '.php'], '', $file->getPathname());
            $class = 'App\\Http\\Requests\\' . str_replace('/', '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->hasMethod('rules')
                || ! $reflection->isSubclassOf(FormRequest::class)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    /**
     * İstek sınıfının yazdığı tablo.
     *
     * Çoğu addan çözülüyor: StoreTranslatedBlogPostRequest → blog_posts.
     * Çözülemeyen ve sütuna hiç yazmayanlar TABLES'ta.
     */
    private function tableFor(string $class): ?string
    {
        if (array_key_exists($class, self::TABLES)) {
            return self::TABLES[$class];
        }

        $name = preg_replace('/^(Store|Update|Bulk)/', '', $class) ?? $class;
        $name = str_replace('Translated', '', $name);
        $name = preg_replace('/Request$/', '', $name) ?? $name;
        $snake = strtolower((string) preg_replace('/(?<!^)([A-Z])/', '_$1', $name));

        foreach ([$snake . 's', $snake . 'es', str_ends_with($snake, 'y') ? substr($snake, 0, -1) . 'ies' : null] as $candidate) {
            if ($candidate !== null && \Illuminate\Support\Facades\Schema::hasTable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    // ── Form sınırları (Blade'den) ──

    /**
     * Formlardaki maxSize[] değerleri.
     *
     * Sınır, onu taşıyan etiketten okunuyor; etiketin name'i yoksa alan
     * atlanıyor. Geriye doğru "en yakın name" aramak daha çok alan yakalardı
     * ama yanlış alana bağlanma pahasına — yanlış eşleşme, kapsam
     * eksikliğinden kötü.
     *
     * @return list<array{view: string, line: int, table: string, column: string, max: int}>
     */
    private function bladeLimits(): array
    {
        $fields = [];

        foreach ($this->bladeFiles() as $file) {
            $view = str_replace(resource_path('views') . '/', '', $file);
            $table = $this->tableForView($view);

            if ($table === null) {
                continue;
            }

            $source = (string) file_get_contents($file);

            preg_match_all('/<(?:input|textarea|select)\b[^>]*>/s', $this->maskBlade($source), $tags, PREG_OFFSET_CAPTURE);

            foreach ($tags[0] as [$tag, $offset]) {
                if (preg_match('/maxSize\[(\d+)\]/', $tag, $size) !== 1
                    || preg_match('/name="([^"]+)"/', $tag, $name) !== 1) {
                    continue;
                }

                // translations[tr][title] ya da settings[site_name] gibi
                // adlarda sütun son köşeli parantezin içinde.
                $column = preg_match('/\[([a-z0-9_]+)\]\s*$/', $name[1], $inner) === 1
                    ? $inner[1]
                    : $name[1];

                $fields[] = [
                    'view'   => $view,
                    'line'   => substr_count(substr($source, 0, $offset), "\n") + 1,
                    'table'  => $table,
                    'column' => $column,
                    'max'    => (int) $size[1],
                ];
            }
        }

        return $fields;
    }

    /**
     * Blade ifadelerini boşlukla değiştirir, işaretlemeyi bırakır.
     *
     * Etiketi ">" ile aramak yetmiyor: name="translations[{{ $language->code }}][title]"
     * gibi bir nitelikte ok işaretinin ">"si etiketi erken kapatıyor ve alan
     * hiç görünmüyordu — üstelik en kalabalık grup olan çok dilli formlar.
     * Uzunluk ve satır sonları korunuyor ki bulgu doğru satırı göstersin.
     */
    private function maskBlade(string $source): string
    {
        $blank = static fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]);

        foreach (['/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s'] as $pattern) {
            $source = (string) preg_replace_callback($pattern, $blank, $source);
        }

        // Blade yönergesi içinde kalan ok işaretleri: @if($item->is_active)
        return str_replace('->', '  ', $source);
    }

    private function tableForView(string $view): ?string
    {
        $matched = null;

        foreach (self::VIEW_TABLES as $prefix => $table) {
            if (str_starts_with($view, $prefix)
                && ($matched === null || strlen($prefix) > strlen($matched[0]))) {
                $matched = [$prefix, $table];
            }
        }

        return $matched[1] ?? null;
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            // admin-theme hazır HTML tasarım referansı, Blade olarak servis
            // edilmiyor.
            if ($file->isFile()
                && str_ends_with($file->getFilename(), '.blade.php')
                && ! str_contains($file->getPathname(), '/admin-theme/')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
