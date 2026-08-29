<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ön yüzdeki her yazı panelden değiştirilebilmeli.
 *
 * "Dil Yazıları" ekranı lang/{dil}/site.php'deki anahtarları listeliyor ve
 * yöneticinin değişikliğini veritabanına yazıyor. Bu düzenin tek açığı, bir
 * metnin Blade'e doğrudan yazılması: ekranda görünür, panelde görünmez,
 * değiştirmek için koda dokunmak gerekir. Sessizce olur, çünkü sayfa gayet
 * düzgün açılır.
 *
 * Bu yüzden denetim tek tek metin saymak yerine görünümleri tarıyor: sonradan
 * eklenen bir yazı da aynı kuralı karşılamak zorunda.
 *
 * Aynı sözleşmenin öteki yönü de burada: panelde görünen her satırın sitede bir
 * karşılığı olmalı. Kullanılmayan anahtar hata vermiyor ama sessizce yanlış
 * yönlendiriyor — yönetici metni değiştiriyor, kaydediyor, sitede hiçbir şey
 * olmuyor.
 */
final class FrontTextsAreEditableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ziyaretçinin okuduğu, dolayısıyla çevrilmesi gereken nitelikler.
     *
     * @var list<string>
     */
    private const VISIBLE_ATTRIBUTES = ['placeholder', 'aria-label', 'title', 'alt'];

    /**
     * Metin sayılmayan değerler: teknik terimler ve ekranda söz olarak
     * okunmayan simgeler.
     *
     * @var list<string>
     */
    private const NOT_PROSE = ['breadcrumb', 'button', 'true', 'false', 'null'];

    /** Panelin düzenlettiği tek çeviri grubu. */
    private const GROUP = 'site';

    public function test_no_front_view_writes_a_visitor_facing_string_by_hand(): void
    {
        $bulgular = [];

        foreach ($this->frontViews() as $file) {
            $masked = $this->maskCode((string) file_get_contents($file));
            $relative = str_replace(base_path() . '/', '', $file);

            foreach ($this->hardcodedStrings($masked) as [$line, $where, $text]) {
                $bulgular[] = "{$relative}:{$line}  ({$where})  {$text}";
            }
        }

        $this->assertSame(
            [],
            $bulgular,
            "Panelden değiştirilemeyen yazı — lang/*/site.php'ye anahtar açıp __() ile çağırın:\n"
                . implode("\n", $bulgular),
        );
    }

    /**
     * Görünümlerin çağırdığı her site.* anahtarı gerçekten tanımlı olmalı.
     *
     * Tanımsız anahtar hata vermiyor: Laravel anahtarın kendisini basıyor, yani
     * ekranda "site.blog.eyebrow" yazıyor ve panelde düzenlenecek bir satır
     * çıkmıyor.
     */
    public function test_every_key_the_views_call_actually_exists(): void
    {
        $tanimli = app(TranslationService::class)->fileLines('tr', 'site');
        $eksik = [];

        foreach ($this->frontViews() as $file) {
            $contents = (string) file_get_contents($file);
            $relative = str_replace(base_path() . '/', '', $file);

            preg_match_all("/__\(\s*'site\.([a-z0-9_.]+)'/i", $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $match) {
                if (! array_key_exists($match[0], $tanimli)) {
                    $line = substr_count(substr($contents, 0, (int) $match[1]), "\n") + 1;
                    $eksik[] = "{$relative}:{$line}  site.{$match[0]}";
                }
            }
        }

        $this->assertSame([], $eksik, "Tanımsız çeviri anahtarı:\n" . implode("\n", $eksik));
    }

    /**
     * Panel ekranı anahtarları dosyadan okuyor; kodda açılan bir anahtar
     * kendiliğinden düzenlenebilir bir satır olarak görünmeli.
     */
    public function test_the_panel_lists_the_keys_the_views_use(): void
    {
        $anahtarlar = app(TranslationService::class)->keysFrom('site');

        foreach (['nav.home', 'blog.eyebrow', 'gallery.all', 'misc.main_nav', 'login.email_ph'] as $key) {
            $this->assertArrayHasKey($key, $anahtarlar, "Panel {$key} anahtarını listelemiyor");
        }

        $this->assertContains('site', TranslationService::EDITABLE_GROUPS);
    }

    /**
     * @return list<string>
     */
    private function frontViews(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            // Yönetim paneli kapsam dışı: oradaki yazılar yöneticinin kendi
            // arayüzü, ziyaretçi görmüyor ve tek dilde tutuluyor. admin-theme
            // hazır HTML tasarım referansı, Blade olarak servis edilmiyor.
            // E-posta gövdeleri ve dışa aktarma şablonlarının kendi modülleri var.
            $disarida = ['/admin/', '/admin-theme/', '/components/', '/emails/', '/exports/', '/vendor/'];

            if (! $file->isFile()
                || ! str_ends_with($file->getFilename(), '.blade.php')
                || str_contains($path, 'layouts/admin')
                || array_any($disarida, static fn (string $parca): bool => str_contains($path, $parca))) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * Kodu boşlukla değiştirir, düz metni bırakır.
     *
     * Satır numaraları korunsun diye silinen her parça aynı sayıda satır sonu
     * bırakıyor; yoksa bulgu dosyanın yanlış satırını gösterirdi.
     */
    private function maskCode(string $source): string
    {
        $blank = static fn (array $m): string => (string) preg_replace('/[^\n]/', '', $m[0]);

        foreach ([
            '/\{\{--.*?--\}\}/s',      // Blade yorumu
            '/@php.*?@endphp/s',
            '/<script\b.*?<\/script>/si',
            '/<style\b.*?<\/style>/si',
            '/\{\{.*?\}\}/s',          // yankılanan ifade
            '/\{!!.*?!!\}/s',
        ] as $pattern) {
            $source = (string) preg_replace_callback($pattern, $blank, $source);
        }

        $source = $this->maskDirectives($source);

        return (string) preg_replace_callback('/@\w+/', $blank, $source);
    }

    /**
     * @directive(...) çağrılarını, parantezleri sayarak siler.
     *
     * Düzenli ifadeyle yapılamıyor: @section('og_image', $post->image ? url(...) : '')
     * gibi iç içe parantezli çağrılar var ve kalıp yanlış yerde kapanınca geriye
     * kalan " : '')" parçası metin sanılıyordu.
     */
    private function maskDirectives(string $source): string
    {
        $result = '';
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            if (! preg_match('/\G@\w+\s*\(/', $source, $m, 0, $i)) {
                $result .= $source[$i];
                $i++;

                continue;
            }

            $start = $i;
            $i += strlen($m[0]);
            $depth = 1;

            while ($i < $length && $depth > 0) {
                $depth += match ($source[$i]) {
                    '(' => 1,
                    ')' => -1,
                    default => 0,
                };
                $i++;
            }

            $result .= (string) preg_replace('/[^\n]/', '', substr($source, $start, $i - $start));
        }

        return $result;
    }

    /**
     * @return list<array{int, string, string}>
     */
    private function hardcodedStrings(string $masked): array
    {
        $bulgular = [];

        $topla = function (string $pattern, string $where) use ($masked, &$bulgular): void {
            preg_match_all($pattern, $masked, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $match) {
                if (! $this->isProse($match[0])) {
                    continue;
                }

                $bulgular[] = [
                    substr_count(substr($masked, 0, (int) $match[1]), "\n") + 1,
                    $where,
                    trim($match[0]),
                ];
            }
        };

        $topla('/>([^<>]+)</', 'metin');

        foreach (self::VISIBLE_ATTRIBUTES as $attribute) {
            $topla('/' . preg_quote($attribute, '/') . '="([^"]*)"/', $attribute);
        }

        return $bulgular;
    }

    /** Ziyaretçinin söz olarak okuduğu bir şey mi? */
    private function isProse(string $text): bool
    {
        // Varlık imleri simge, söz değil: "&copy; {{ date('Y') }} {{ $ad }}."
        // kalıbından geriye kalan "&copy; ." harf taşıdığı için yazı sanılıyordu.
        $text = trim((string) preg_replace('/&[a-z]+;|&#\d+;/i', '', $text));

        if (mb_strlen($text) < 3 || in_array(mb_strtolower($text), self::NOT_PROSE, true)) {
            return false;
        }

        // İçinde harf olmayan (rakam, simge, madde imi) değerler yazı değil.
        return preg_match('/\p{L}/u', $text) === 1;
    }

    /**
     * Panelde görünen her anahtarın kodda bir çağrısı olmalı.
     *
     * Ölü anahtar hata vermiyor: ekranda satır olarak duruyor, yönetici metni
     * değiştirip kaydediyor ve sitede hiçbir şey değişmiyor. Çoğu, canlı bir
     * anahtarın biraz farklı yazılmış ikizi olarak birikiyordu
     * (comment_form ↔ comment_title, no_comments ↔ comment_empty).
     *
     * Testler taramanın dışında. Anahtarı ayakta tutan tek şey bir test
     * fikstürüyse o anahtar uygulamada ölüdür; bir kez öyle oldu.
     */
    public function test_every_key_the_panel_offers_is_actually_used(): void
    {
        $used = $this->calledKeys();

        // Tarayıcı bozulursa küme boşalır ve denetim hiçbir şey bulmadan
        // yeşil geçerdi. Ölçtüğünden emin olunmadan sonucuna güvenilmez.
        $this->assertGreaterThan(
            200,
            count($used),
            'Çeviri çağrıları okunamıyor; denetim ölçmüyor',
        );

        $olu = array_values(array_diff(
            array_keys(app(TranslationService::class)->fileLines('tr', self::GROUP)),
            $used,
        ));

        sort($olu);

        $this->assertSame(
            [],
            $olu,
            "Panelde görünen ama hiçbir yerde çağrılmayan anahtar:\n  " . implode("\n  ", $olu),
        );
    }

    /**
     * Çağrılan her anahtar tanımlı da olmalı.
     *
     * Tanımsız anahtar hata vermiyor, Laravel anahtarın kendisini basıyor:
     * ekranda "site.blog.eyebrow" yazıyor. Yukarıdaki denetim yalnız ön yüze
     * bakıyor, bu bütün projeye.
     */
    public function test_no_call_points_at_a_key_that_does_not_exist(): void
    {
        $tanimli = app(TranslationService::class)->fileLines('tr', self::GROUP);

        $eksik = array_values(array_diff($this->calledKeys(), array_keys($tanimli)));

        sort($eksik);

        $this->assertSame([], $eksik, "Tanımsız anahtar çağrısı:\n  " . implode("\n  ", $eksik));
    }

    /**
     * Kodun çağırdığı site.* anahtarları.
     *
     * Yalnız çeviri çağrısının içindekiler sayılıyor. Düz "site." aramak
     * yetmiyordu: metinde geçen "site.com" ve "site.php" de anahtar sanılıyordu.
     *
     * @return list<string>
     */
    private function calledKeys(): array
    {
        $pattern = '/(?:__|@lang|trans|trans_choice|Lang::get)\(\s*[\'"]'
            . preg_quote(self::GROUP, '/') . '\.([a-z0-9_.]+)[\'"]/i';

        $keys = [];

        foreach ($this->projectSources() as $file) {
            preg_match_all($pattern, (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    /**
     * Anahtarın çağrılabileceği her yer.
     *
     * Yönetim görünümleri de dahil: ölü anahtar denetimi ön yüzle sınırlı
     * olsaydı, yalnız panelde kullanılan bir anahtar ölü sanılırdı.
     *
     * @return list<string>
     */
    private function projectSources(): array
    {
        $files = [];

        foreach (['app', 'resources', 'routes', 'database', 'config', 'public'] as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $name = $file->getFilename();

                // admin-theme hazır HTML tasarım referansı; vendor kütüphaneleri
                // bizim anahtarlarımızı tanımıyor.
                if ((! str_ends_with($name, '.php') && ! str_ends_with($name, '.js'))
                    || str_contains($file->getPathname(), '/admin-theme/')
                    || str_contains($file->getPathname(), '/vendor/')) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Ziyaretçinin gördüğü formların uyarıları da panelden yönetilmeli.
     *
     * FormRequest::messages() koda gömülü metin taşıdığında iki şey birden
     * oluyor: İngilizce ziyaretçi Türkçe uyarı görüyor ve yönetici metni
     * değiştiremiyor. Görünüm taraması bunu göremiyor, çünkü metin Blade'de
     * değil PHP'de duruyor.
     *
     * Hangi isteklerin ziyaretçiye açık olduğu elle listelenmiyor: yönetim
     * dışındaki bir kontrolcünün kullandığı her istek ziyaretçiye açıktır.
     * Yeni bir ön yüz formu kendiliğinden kapsama giriyor.
     */
    public function test_no_visitor_facing_form_writes_its_warnings_by_hand(): void
    {
        $siniflar = $this->visitorFacingRequests();

        $this->assertGreaterThan(
            4,
            count($siniflar),
            'Ön yüz istek sınıfları bulunamıyor; denetim ölçmüyor',
        );

        $bulgular = [];

        // Hız sınırı uyarıları da aynı formlarda görünüyor ama FormRequest'te
        // değil, sınırlayıcının yanıt kapanışında duruyorlar.
        $kaynaklar = array_merge(
            array_map(static fn (string $f): array => [$f, 'messages'], $siniflar),
            [[app_path('Providers/AppServiceProvider.php'), 'configureRateLimiting']],
        );

        foreach ($kaynaklar as [$file, $method]) {
            $source = (string) file_get_contents($file);
            $body = $this->methodBody($source, $method);

            if ($body === '') {
                continue;
            }

            // Çeviri çağrıları maskeleniyor; geriye kalan her metin elle
            // yazılmış demektir. Anahtarlar da metin olduğu için yalnız
            // "=>" sağındaki değerlere bakılıyor.
            $masked = $this->maskTranslationCalls($body);
            $relative = str_replace(base_path() . '/', '', $file);
            $offset = strpos($source, $body);

            preg_match_all(
                '/=>\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/',
                $masked,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[1] as [$literal, $at]) {
                $line = substr_count(substr($source, 0, (int) $offset + (int) $at), "\n") + 1;
                $bulgular[] = "{$relative}:{$line}  {$literal}";
            }
        }

        sort($bulgular);

        $this->assertSame(
            [],
            $bulgular,
            "Panelden değiştirilemeyen form uyarısı — lang/*/site.php'ye anahtar açıp __() ile çağırın:\n  "
                . implode("\n  ", $bulgular),
        );
    }

    /**
     * Ziyaretçiye açık FormRequest dosyaları.
     *
     * Yönetim paneli kapsam dışı: tek dilde ve yalnız yöneticinin gördüğü
     * arayüz. Ayrım kontrolcünün yerinden geliyor — Admin dizini dışındaki bir
     * kontrolcünün kullandığı istek, ziyaretçinin doldurduğu bir formdur.
     *
     * @return list<string>
     */
    private function visitorFacingRequests(): array
    {
        $files = [];

        foreach ((array) glob(app_path('Http/Controllers/*.php')) as $controller) {
            preg_match_all(
                '/use\s+App\\\\Http\\\\Requests\\\\([A-Za-z0-9_\\\\]+);/',
                (string) file_get_contents((string) $controller),
                $matches,
            );

            foreach ($matches[1] as $class) {
                $path = app_path('Http/Requests/' . str_replace('\\', '/', $class) . '.php');

                if (is_file($path)) {
                    $files[$path] = true;
                }
            }
        }

        $files = array_keys($files);
        sort($files);

        return $files;
    }

    /**
     * __('...') çağrılarını, parantezleri sayarak boşlukla değiştirir.
     *
     * Düzenli ifadeyle yapılamıyor: çağrının içinde yer değiştirme dizisi
     * (__('site.x', ['count' => 3])) olabiliyor ve kalıp yanlış yerde
     * kapanınca geride kalan parça elle yazılmış metin sanılıyor.
     */
    private function maskTranslationCalls(string $source): string
    {
        $result = '';
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            if (preg_match('/\G(?:__|trans|trans_choice)\s*\(/', $source, $m, 0, $i) !== 1) {
                $result .= $source[$i];
                $i++;

                continue;
            }

            $start = $i;
            $i += strlen($m[0]);
            $depth = 1;

            while ($i < $length && $depth > 0) {
                $depth += match ($source[$i]) {
                    '(' => 1,
                    ')' => -1,
                    default => 0,
                };
                $i++;
            }

            $result .= (string) preg_replace('/[^\n]/', ' ', substr($source, $start, $i - $start));
        }

        return $result;
    }

    /**
     * Bir metodun gövdesi, süslü parantezler sayılarak.
     *
     * @return string metot yoksa boş
     */
    private function methodBody(string $source, string $method): string
    {
        // Görünürlük serbest: aranan metot private de olabiliyor (hız
        // sınırlayıcısının kurulumu böyle) ve parametre alabilir.
        $pattern = '/(?:public|protected|private)\s+function\s+'
            . preg_quote($method, '/') . '\s*\([^)]*\)[^{;]*\{/';

        if (preg_match($pattern, $source, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        $open = (int) $m[0][1] + strlen($m[0][0]) - 1;
        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            $depth += match ($source[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return substr($source, $open + 1, $i - $open - 1);
            }
        }

        return '';
    }
}
