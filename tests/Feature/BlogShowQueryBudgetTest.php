<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\ContentStatus;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blog detay sayfasının sorgu bütçesi.
 *
 * Sayfa 76 sorgu atıyordu, 52'si aynı sorgunun tekrarıydı. Üç kaynak vardı:
 * dil listesi her çağrıda önbellek sürücüsüne (veritabanı) gidiyordu, adres
 * çevirici aynı yazıyı her bağlantı için yeniden çözüyordu ve yetki denetimi
 * iki ayrı görünümde tekrar sorgulanıyordu.
 *
 * Bütçe, düzeltmelerin zamanla geri gelmemesi için burada duruyor: sayı
 * sabit bir hedef değil, tavana yaklaşınca fark edilsin diye var.
 */
final class BlogShowQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sayfanın atabileceği en çok sorgu.
     *
     * Optimizasyondan sonra ölçülen sayının biraz üstünde: küçük
     * değişiklikler testi kırmasın, ama eski hâline (76) dönüş fark edilsin.
     *
     * 30'dan 34'e çıkarıldı: alt bilginin bağlantıları menü modülüne taşındı
     * ve soğuk önbellekte menüyü okumak üç sorgu ekliyor (menü + kök öğeler +
     * çocukları). Ölçülen sayı 31. Sıcak önbellekte maliyet sıfır — menü bir
     * saat önbellekte duruyor, tarayıcıda /tr/hakkimizda hâlâ 13 sorgu ve
     * içlerinde menü sorgusu yok.
     */
    private const BUDGET = 34;

    /** Aynı sorgunun kaç kez tekrarlanabileceği. */
    private const DUPLICATE_BUDGET = 6;

    private BlogPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\LanguageSeeder::class);
        app(\App\Services\LanguageService::class)->clearCache();

        $author = User::factory()->create();

        $category = BlogCategory::create([
            'locale' => 'tr', 'lang_group_id' => (string) Str::uuid(),
            'name' => 'Teknoloji', 'slug' => 'teknoloji',
        ]);

        $groupId = (string) Str::uuid();

        $this->post = $this->makePost($category->id, $groupId, 'tr', 'Yazı', 'yazi', $author->id);

        // İngilizce çevirisi de olsun: adres çevirici asıl yükü çeviri
        // ararken çıkarıyor, tek dilli kurulumda sorun görünmezdi.
        $enCategory = BlogCategory::create([
            'locale' => 'en', 'lang_group_id' => $category->lang_group_id,
            'name' => 'Technology', 'slug' => 'technology',
        ]);

        $this->makePost($enCategory->id, $groupId, 'en', 'Post', 'post', $author->id);

        // Yorumlar: liste ve yanıtları da sayfaya giriyor.
        foreach (range(1, 5) as $i) {
            $comment = BlogComment::create([
                'blog_post_id' => $this->post->id,
                'name' => "Yorumcu {$i}", 'email' => "kisi{$i}@ornek.com",
                'body' => 'Yorum metni.', 'status' => CommentStatus::Approved,
            ]);

            BlogComment::create([
                'blog_post_id' => $this->post->id, 'parent_id' => $comment->id,
                'name' => "Yanıt {$i}", 'email' => "yanit{$i}@ornek.com",
                'body' => 'Yanıt metni.', 'status' => CommentStatus::Approved,
            ]);
        }
    }

    private function makePost(int $categoryId, string $groupId, string $locale, string $title, string $slug, int $authorId): BlogPost
    {
        return BlogPost::create([
            'locale' => $locale, 'lang_group_id' => $groupId,
            'blog_category_id' => $categoryId, 'user_id' => $authorId,
            'title' => $title, 'slug' => $slug, 'body' => 'Gövde metni.',
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(),
        ]);
    }

    /**
     * @return array{count: int, duplicates: array<string, int>, cacheKeys: array<string, int>}
     */
    private function measure(string $url): array
    {
        $queries = [];
        $cacheKeys = [];

        DB::listen(function ($query) use (&$queries, &$cacheKeys): void {
            $queries[] = $query->sql;

            // Önbellek sürücüsü veritabanıysa okunan anahtar ilk bağlamada.
            if (str_contains($query->sql, '"cache"') || str_contains($query->sql, '`cache`')) {
                $key = (string) ($query->bindings[0] ?? '?');

                if (! is_numeric($key)) {
                    $cacheKeys[] = $key;
                }
            }
        });

        $this->get($url)->assertOk();

        $counts = array_count_values($queries);
        arsort($counts);

        $keyCounts = array_count_values($cacheKeys);
        arsort($keyCounts);

        return [
            'count'      => count($queries),
            'duplicates' => array_filter($counts, static fn (int $n): bool => $n > 1),
            'cacheKeys'  => $keyCounts,
        ];
    }

    private function url(): string
    {
        return route('blog.show', ['teknoloji', 'yazi']);
    }

    public function test_the_page_stays_within_its_query_budget(): void
    {
        $result = $this->measure($this->url());

        $this->assertLessThanOrEqual(
            self::BUDGET,
            $result['count'],
            sprintf(
                "Sayfa %d sorgu attı (bütçe %d). En çok tekrarlananlar:\n%s",
                $result['count'],
                self::BUDGET,
                $this->report($result['duplicates']),
            ),
        );
    }

    public function test_no_single_query_is_repeated_over_and_over(): void
    {
        $result = $this->measure($this->url());

        $enCok = $result['duplicates'] ? max($result['duplicates']) : 0;

        $this->assertLessThanOrEqual(
            self::DUPLICATE_BUDGET,
            $enCok,
            "Aynı sorgu {$enCok} kez çalıştı:\n" . $this->report($result['duplicates']),
        );
    }

    /**
     * Yorum sayısı sorgu sayısını değiştirmemeli: değiştiriyorsa yorum
     * başına sorgu atılıyor demektir (N+1).
     */
    public function test_more_comments_do_not_mean_more_queries(): void
    {
        // İlk istek önbellekleri dolduruyor; ölçüm ısınmış hâl üzerinden
        // yapılmalı, yoksa iki istek karşılaştırılabilir olmuyor.
        $this->get($this->url())->assertOk();

        $az = $this->measure($this->url())['count'];

        foreach (range(6, 20) as $i) {
            BlogComment::create([
                'blog_post_id' => $this->post->id,
                'name' => "Yorumcu {$i}", 'email' => "kisi{$i}@ornek.com",
                'body' => 'Yorum metni.', 'status' => CommentStatus::Approved,
            ]);
        }

        $this->get($this->url())->assertOk();

        $cok = $this->measure($this->url())['count'];

        $this->assertSame($az, $cok, "Yorum sayısı artınca sorgu {$az} → {$cok} oldu (N+1)");
    }

    /**
     * Önbellek sürücüsü veritabanıysa her Cache::remember çağrısı bir SELECT
     * demek. Dil listesi istek boyunca onlarca kez soruluyordu ve tek bir
     * sayfada yirmiden fazla "select * from cache" doğuruyordu.
     *
     * Doğru davranış: her anahtar istek başına bir kez okunur. Toplam sayı
     * değil bu ölçülüyor — yeni bir önbellekli servis eklenmesi testi
     * kırmamalı, aynı anahtarın döngüye girmesi kırmalı.
     */
    public function test_no_cache_key_is_read_over_and_over(): void
    {
        config(['cache.default' => 'database']);
        app(\App\Services\LanguageService::class)->clearCache();

        $result = $this->measure($this->url());

        $enCok = $result['cacheKeys'] ? max($result['cacheKeys']) : 0;

        $satirlar = [];

        foreach (array_slice($result['cacheKeys'], 0, 5, true) as $key => $n) {
            $satirlar[] = "  {$n}×  {$key}";
        }

        $this->assertLessThanOrEqual(
            2,
            $enCok,
            "Aynı önbellek anahtarı {$enCok} kez okundu:\n" . implode("\n", $satirlar),
        );
    }

    /** Sayfanın işi bozulmamalı: sayaç, yorumlar ve çeviri bağlantısı. */
    public function test_the_page_still_does_its_job(): void
    {
        $before = $this->post->views;

        $html = (string) $this->get($this->url())->assertOk()->getContent();

        $this->assertSame($before + 1, $this->post->fresh()->views, 'Okunma sayacı artmadı');
        $this->assertStringContainsString('Yorumcu 1', $html, 'Yorumlar basılmadı');
        $this->assertStringContainsString('Yanıt 1', $html, 'Yanıtlar basılmadı');
        $this->assertStringContainsString('Teknoloji', $html, 'Kategori basılmadı');
        // Çevirisi olan yazı için öteki dilin adresi kuruluyor.
        $this->assertStringContainsString('/en/blog/technology/post', $html, 'Çeviri bağlantısı yok');
    }

    /**
     * @param array<string, int> $duplicates
     */
    private function report(array $duplicates): string
    {
        $lines = [];

        foreach (array_slice($duplicates, 0, 6, true) as $sql => $n) {
            $lines[] = "  {$n}×  " . Str::limit($sql, 110);
        }

        return implode("\n", $lines);
    }
}
