<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class ImportOldContent extends Command
{
    protected $signature = 'import:old-content
        {url : Export endpoint URL (ör: https://orhanbabaninciftligi.com/data-export.php)}
        {--key=orhan-baba-export-2026 : Güvenlik anahtarı}
        {--category=urunlerimiz : Hedef blog kategori slug}
        {--dry-run : Gerçekten kaydetmeden sadece önizleme yap}
        {--limit=500 : Sayfa başına kayıt}';

    protected $description = 'Eski siteden içerikleri çekip blog yazısı olarak aktarır ve 301 redirect kurar';

    public function handle(): int
    {
        $baseUrl  = $this->argument('url');
        $key      = $this->option('key');
        $catSlug  = $this->option('category');
        $dryRun   = $this->option('dry-run');
        $limit    = (int) $this->option('limit');

        // Hedef blog kategorisini bul
        $category = BlogCategory::where('slug', $catSlug)->first();
        if (!$category) {
            $this->error("'{$catSlug}' slug'lı blog kategorisi bulunamadı.");
            $this->info('Mevcut kategoriler:');
            BlogCategory::all()->each(fn ($c) => $this->line("  - {$c->slug} ({$c->name})"));
            return self::FAILURE;
        }

        $this->info("Hedef kategori: {$category->name} (ID: {$category->id})");

        // Önce stats çek
        $this->info('Eski siteden istatistikler alınıyor...');
        $statsResponse = Http::timeout(30)->get($baseUrl, [
            'key'  => $key,
            'mode' => 'stats',
        ]);

        if (!$statsResponse->successful()) {
            $this->error('Stats endpoint erişilemedi: ' . $statsResponse->status());
            return self::FAILURE;
        }

        $stats = $statsResponse->json();
        $totalContent = $stats['total_content'] ?? 0;
        $this->info("Toplam {$totalContent} içerik bulundu.");

        if ($totalContent === 0) {
            $this->warn('Aktarılacak içerik yok.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN modu: Veritabanına kayıt yapılmayacak.');
        }

        // Sayfalar halinde içerikleri çek ve aktar
        $totalPages = (int) ceil($totalContent / $limit);
        $imported = 0;
        $skipped = 0;
        $redirectsCreated = 0;

        $bar = $this->output->createProgressBar($totalContent);
        $bar->start();

        for ($page = 1; $page <= $totalPages; $page++) {
            $response = Http::timeout(60)->get($baseUrl, [
                'key'   => $key,
                'mode'  => 'export',
                'page'  => $page,
                'limit' => $limit,
            ]);

            if (!$response->successful()) {
                $this->error("\nSayfa {$page} alınamadı: " . $response->status());
                continue;
            }

            $data = $response->json('data', []);

            foreach ($data as $item) {
                $bar->advance();

                // Zaten slug ile mevcut mu kontrol et
                $slug = Str::slug($item['title']);
                if (empty($slug)) {
                    $skipped++;
                    continue;
                }

                // Boş içerik kontrolü
                if (empty(trim(strip_tags($item['text'] ?? '')))) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $imported++;
                    continue;
                }

                DB::transaction(function () use ($item, $category, &$imported, &$redirectsCreated): void {
                    // Blog yazısı oluştur
                    $post = BlogPost::create([
                        'blog_category_id' => $category->id,
                        'user_id'          => 1,
                        'title'            => $item['title'],
                        'excerpt'          => $this->cleanExcerpt($item['abstract'] ?? $item['description'] ?? ''),
                        'body'             => $this->cleanBody($item['text']),
                        'image'            => $this->mapImage($item['img'] ?? null),
                        'meta_title'       => Str::limit($item['title'], 70, ''),
                        'meta_description' => Str::limit($item['description'] ?? $item['abstract'] ?? '', 160, ''),
                        'is_published'     => (bool) ($item['status'] ?? true),
                        'published_at'     => $item['created_at'] ?? now(),
                        'views'            => (int) ($item['show_count'] ?? 0),
                    ]);

                    $imported++;

                    // 301 redirect oluştur
                    $oldUrl = $item['old_url'] ?? null;
                    if ($oldUrl) {
                        Redirect::updateOrCreate(
                            ['old_url' => $oldUrl],
                            [
                                'new_url'     => '/blog/' . $post->slug,
                                'status_code' => 301,
                            ],
                        );
                        $redirectsCreated++;
                    }
                });
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Redirect cache temizle
        if (!$dryRun) {
            Cache::forget('redirects.map');
            Cache::forget('blog.admin_stats');
            Cache::forget('blog_categories.active');
        }

        $this->info("Aktarılan: {$imported}");
        $this->info("Atlanan (boş/geçersiz): {$skipped}");
        $this->info("301 Redirect: {$redirectsCreated}");

        if ($dryRun) {
            $this->warn('DRY-RUN moduydu, veritabanına hiçbir şey yazılmadı.');
        } else {
            $this->info('İçerik aktarımı tamamlandı!');
        }

        return self::SUCCESS;
    }

    /**
     * HTML içeriği temizle, temel formatlamayı koru.
     */
    private function cleanBody(string $html): string
    {
        // Görsellerdeki eski URL'leri düzelt
        $html = preg_replace(
            '#src=["\'](?:https?://(?:www\.)?orhanbabaninciftligi\.com)?/uploads/#i',
            'src="/uploads/content/',
            $html,
        ) ?? $html;

        return $html;
    }

    /**
     * Özet metni temizle.
     */
    private function cleanExcerpt(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        return Str::limit(strip_tags($text), 300, '...');
    }

    /**
     * Eski görsel yolunu yeni yapıya map'le.
     * Eski site: uploads/content/{dosya_adi}
     * Yeni site: content/{dosya_adi}
     */
    private function mapImage(?string $img): ?string
    {
        if (empty($img)) {
            return null;
        }

        return 'content/' . basename($img);
    }
}
