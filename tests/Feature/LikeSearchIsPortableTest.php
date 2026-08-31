<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\LikeSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Serbest metin aramasının iki veritabanında da aynı davranması.
 *
 * Bu kural bir üretim hatasından doğdu. İki servis LIKE koşulunu
 * `ESCAPE '\'` ile yazıyordu; MySQL ters bölüyü dizge içinde de kaçış saydığı
 * için o SQL **sözdizimi hatası** veriyor ve arama yapan ekran 500 dönüyordu.
 * Suite SQLite üzerinde koştuğu için hiç görünmüyordu — CI MySQL'e karşı
 * koşturulduğu ilk gün ortaya çıktı.
 *
 * İki servis de tersini yapıyordu: kaçırılmış terimi `ESCAPE` bildirmeden
 * kullanıyorlardı, o da SQLite'ta kaçışı hiç uygulamıyordu.
 *
 * Buradaki testler hem kuralı hem de kuralın uygulandığını bekçilik ediyor.
 */
class LikeSearchIsPortableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Hiçbir yer LIKE kalıbını elle kurmamalı.
     *
     * Ters bölü yasağı tek başına yetmiyordu: kaçışı hiç yapmayan çağrılar da
     * vardı ve onlar yasağa takılmıyordu. Sonuç, süzgeç kutusuna "%" yazan
     * ziyaretçinin süzgeç yaptığını sanarak bütün listeyi görmesiydi — on üç
     * dosyada birden.
     *
     * Kural artık daha basit: LIKE kalıbı yalnız LikeSearch'ten çıkar.
     */
    public function test_no_service_builds_a_like_pattern_by_hand(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            if (str_ends_with($file, 'Support/LikeSearch.php')) {
                continue;
            }

            foreach (file($file) ?: [] as $number => $line) {
                // ->where('x', 'like', ...) ya da 'LIKE' — büyük/küçük fark etmez.
                if (preg_match("/->(or)?[wW]here\s*\(\s*'[^']+'\s*,\s*'like'/i", $line) !== 1) {
                    continue;
                }

                $offenders[] = str_replace(base_path() . '/', '', $file) . ':' . ($number + 1);
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Elle kurulmuş LIKE — App\\Support\\LikeSearch kullanın:\n  " . implode("\n  ", $offenders),
        );
    }

    /**
     * Ters bölü kaçışı MySQL'de kırılıyor; hiçbir yerde geri gelmemeli.
     */
    public function test_no_service_escapes_a_like_with_a_backslash(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            // Yardımcının kendisi hariç: yanlış biçimi neden kullanmadığımızı
            // açıklayan tek yer orası ve açıklama örneği içeriyor.
            if (str_ends_with($file, 'Support/LikeSearch.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            // Kaynak metne bakılıyor, çalışma zamanı değerine değil: ters bölü
            // dosyada çift yazılır (`ESCAPE '\\'`). Kalıp, kaçış karakteri
            // ters bölüyle başlayan her biçimi yakalıyor.
            if (preg_match('/ESCAPE\s*\'\\\\/', $contents) === 1) {
                $offenders[] = str_replace(base_path() . '/', '', $file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "ESCAPE '\\' MySQL'de sözdizimi hatası verir; LikeSearch::clause() kullanın",
        );
    }

    /**
     * Kaçırılmış bir terim ESCAPE bildirilmeden kullanılırsa SQLite kaçışı
     * hiç uygulamaz ve joker karakter süzgeci sessizce çalışmaz.
     */
    public function test_every_like_search_goes_through_the_shared_helper(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(app_path('Services')) as $file) {
            $contents = (string) file_get_contents($file);

            if (! str_contains($contents, 'LikeSearch::term(')) {
                continue;
            }

            // Yardımcıyı kullanan bir servis, koşulu da ondan almalı.
            if (preg_match("/'like'\s*,\s*\\\$term/", $contents) === 1) {
                $offenders[] = str_replace(base_path() . '/', '', $file);
            }
        }

        $this->assertSame([], $offenders, 'ESCAPE bildirilmeden LIKE kullanılıyor');
    }

    /**
     * Asıl davranış: kullanıcının yazdığı joker karakter harf sayılmalı.
     *
     * Sorgu gerçek veritabanına gidiyor, yani bu test hangi sürücüde
     * koşuluyorsa onu sınıyor — CI ikisini de koşuyor.
     */
    public function test_a_wildcard_matches_only_itself_on_this_driver(): void
    {
        DB::table('settings')->insert([
            ['key' => 'sinama_duz', 'value' => 'normal deger', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sinama_yuzde', 'value' => 'yuzde % isareti', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sinama_alt', 'value' => 'alt_cizgi', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Sorgu yalnızca bu testin satırlarına bakıyor: ayarlar tablosu
        // tohumlanmış kayıtlar da taşıyor ve onlar sonucu kaydırırdı.
        $matching = static fn (string $search): array => DB::table('settings')
            ->whereIn('key', ['sinama_duz', 'sinama_yuzde', 'sinama_alt'])
            ->whereRaw(LikeSearch::clause('value'), [LikeSearch::term($search)])
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $this->assertSame(['sinama_yuzde'], $matching('%'), 'yüzde işareti joker gibi davrandı');
        $this->assertSame(['sinama_alt'], $matching('_'), 'alt çizgi joker gibi davrandı');
        $this->assertSame(['sinama_duz'], $matching('normal'));
    }

    /**
     * Kaçış karakterinin kendisi de kaçırılmalı, yoksa ünlem arayan biri
     * sonraki karakteri yutar.
     */
    public function test_the_escape_character_itself_is_escaped(): void
    {
        DB::table('settings')->insert([
            ['key' => 'sinama_unlem', 'value' => 'dikkat! onemli', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sinama_sade', 'value' => 'dikkat onemli', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $found = DB::table('settings')
            ->whereIn('key', ['sinama_unlem', 'sinama_sade'])
            ->whereRaw(LikeSearch::clause('value'), [LikeSearch::term('dikkat!')])
            ->pluck('key')
            ->all();

        $this->assertSame(['sinama_unlem'], $found);
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
