<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kuralsız alan yasağının bütün proje üzerindeki bekçisi.
 *
 * Kural şuydu: kullanıcının veri girdiği her alan ya `data-validation-engine`
 * taşır ya da bilerek boş bırakıldığını söyleyen `data-fv-ignore` taşır.
 * Bekçisi vardı ama yalnız elle yazılmış on ön yüz görünümüne bakıyordu —
 * panelin doksana yakın formu hiç taranmıyordu. Yeni bir ekran eklendiğinde
 * kapsama girmesi de o listeye elle satır yazmaya bağlıydı.
 *
 * Burada liste yok: görünüm ağacının tamamı taranıyor.
 */
class FormFieldsCarryRulesTest extends TestCase
{
    /**
     * Taramanın dışında kalan dizinler ve gerekçeleri.
     */
    private const EXCLUDED = [
        // Uyarlanmamış hazır tasarımlar; Blade'e dönüşmeden önce kural aranmaz.
        'admin-theme',
        // E-posta gövdeleri: form değil, alıcının doldurduğu bir alan yok.
        'emails',
        // Çerçevenin kendi görünümleri (sayfalama gibi).
        'vendor',
    ];

    /**
     * Kural aranmayan alan adları: çerçevenin kendi taşıyıcıları.
     */
    private const FRAMEWORK_FIELDS = ['_token', '_method'];

    public function test_no_field_in_any_view_is_left_without_a_rule(): void
    {
        $views = $this->views();

        // Tarama gerçekten dosya buluyor mu? Yol bozulursa test sessizce
        // hiçbir şeye bakmadan yeşil biterdi.
        $this->assertGreaterThan(100, count($views), 'Görünüm ağacı taranamadı.');

        $ruleless = [];

        foreach ($views as $view) {
            $source = $this->maskBladeExpressions((string) file_get_contents($view));
            $relative = str_replace(resource_path('views/'), '', $view);

            foreach ($this->fieldTags($source) as [$line, $tag]) {
                if (preg_match('/name="([^"]*)"/', $tag, $match) !== 1) {
                    continue;
                }

                $name = $match[1];

                // Gizli alanlar kullanıcı girdisi değil. Adı tamamen Blade
                // ifadesinden gelen alanlar da (bileşenler) maskeleme sonrası
                // boş kalıyor: kuralı bileşeni çağıran ekran veriyor.
                if (trim($name) === '' || str_contains($tag, 'type="hidden"')) {
                    continue;
                }

                if (in_array($name, self::FRAMEWORK_FIELDS, true)) {
                    continue;
                }

                if (str_contains($tag, 'data-validation-engine') || str_contains($tag, 'data-fv-ignore')) {
                    continue;
                }

                $ruleless[] = "{$relative}:{$line}  {$name}";
            }
        }

        sort($ruleless);

        $this->assertSame(
            [],
            $ruleless,
            "Kuralsız alan — data-validation-engine ya da data-fv-ignore ekleyin:\n  "
                . implode("\n  ", $ruleless),
        );
    }

    /**
     * Etiket ayrıştırması nitelik değerlerinin içine bakmıyor.
     *
     * Bu, taramanın kendi sınavı: bir `placeholder` içindeki ">" işareti
     * etiketi erken kapatırsa alanın nitelikleri görünmez olur ve kural taşıyan
     * bir alan taşımıyor sanılır — ya da tersi, taşımayan bir alan hiç
     * görülmez. Ayarlar ekranındaki harita ve head kodu alanları tam olarak
     * böyle yazılmış durumda.
     */
    public function test_the_scan_reads_attributes_that_contain_angle_brackets(): void
    {
        $source = '<textarea name="ornek" placeholder="<iframe src=\'...\'></iframe>" '
            . 'data-validation-engine="validate[maxSize[10]]"></textarea>';

        $tags = $this->fieldTags($source);

        $this->assertCount(1, $tags);
        $this->assertStringContainsString('data-validation-engine', $tags[0][1]);
    }

    /**
     * Nitelik yerine geçen Blade direktifleri de taramayı kesmiyor.
     *
     * `@checked($tercih[$tur->value])` yazımındaki ok işareti etiketi erken
     * kapatıyordu; ondan sonra gelen `data-fv-ignore` görünmediği için kuralı
     * olan bir alan kuralsız sanılıyordu.
     */
    public function test_the_scan_survives_directives_that_contain_arrows(): void
    {
        $source = $this->maskBladeExpressions(
            '<input type="checkbox" name="tercihler[]" @checked($tercih[$tur->value]) data-fv-ignore>',
        );

        $tags = $this->fieldTags($source);

        $this->assertCount(1, $tags);
        $this->assertStringContainsString('data-fv-ignore', $tags[0][1]);
    }

    // ── Yardımcılar ──

    /** @return list<string> */
    private function views(): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(resource_path('views/'), '', $file->getPathname());

            foreach (self::EXCLUDED as $directory) {
                if (str_starts_with($relative, $directory . '/')) {
                    continue 2;
                }
            }

            $found[] = $file->getPathname();
        }

        sort($found);

        return $found;
    }

    /**
     * Blade ifadeleri boşlukla değiştirilir.
     *
     * Maskelenmezse bir ok işaretinin ">"si etiketi erken kapatıyor ve
     * nitelikler görünmüyor. Uzunluk korunuyor ki satır numarası kaymasın.
     */
    private function maskBladeExpressions(string $source): string
    {
        $blank = static fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]);

        $patterns = [
            '/\{\{--.*?--\}\}/s',
            '/\{\{.*?\}\}/s',
            '/\{!!.*?!!\}/s',
            // Nitelik yerine geçen direktifler: @checked($tercih[$tur->value])
            // gibi bir yazımda ok işaretinin ">"si etiketi erken kapatıyor ve
            // ondan sonraki nitelikler — kuralın kendisi dahil — görünmez
            // oluyordu.
            '/@\w+\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/s',
        ];

        foreach ($patterns as $pattern) {
            $source = (string) preg_replace_callback($pattern, $blank, $source);
        }

        return $source;
    }

    /**
     * Girdi etiketleri ve satır numaraları.
     *
     * Nitelik değerleri tırnak içinde okunuyor: tırnağın içindeki ">" etiketi
     * kapatmıyor.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function fieldTags(string $source): array
    {
        preg_match_all(
            '/<(?:input|select|textarea)\b(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/s',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $tags = [];

        foreach ($matches[0] as [$tag, $offset]) {
            $tags[] = [substr_count(substr($source, 0, $offset), "\n") + 1, $tag];
        }

        return $tags;
    }
}
