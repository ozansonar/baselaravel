<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InstagramMediaType;
use App\Enums\InstagramPostStatus;
use App\Models\BlogPost;
use App\Models\InstagramPost;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cron'dan otomatik üretilen blog yazılarını Instagram + Facebook'ta paylaşır.
 *
 * Akış:
 *   1. Setting kontrolü (blog_auto_share_instagram = '1' mi)
 *   2. BlogPost'un kapak görseli geçerli mi (placeholder değil mi)
 *   3. Görseli Instagram klasörüne kopyala (1:1 oranında auto-fit)
 *   4. Caption + hashtag oluştur:
 *        - AI denenir (InstagramCaptionAiService::generateFromTopic)
 *        - Hashtagler InstagramHashtagBuilder ile 4-katmanlı + filtrelenir
 *        - AI fail olursa template fallback (eski davranış)
 *   5. InstagramPost oluştur:
 *        status=Scheduled, scheduled_at=now+5dk
 *        publish_to_facebook=Setting'e göre
 *        blog_post_id=$post->id (idempotency + izleme için)
 *
 * Cron `instagram:publish-scheduled` (her 5 dk) bu postu paylaşacak.
 *
 * Hata durumunda log yazıp null döner — blog yayını hiçbir zaman bloke olmaz.
 */
final class BlogToInstagramService
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly InstagramImageProcessor $imageProcessor,
        private readonly InstagramCaptionAiService $captionAi,
        private readonly InstagramHashtagBuilder $hashtagBuilder,
    ) {}

    /**
     * Blog yazısını Instagram'da paylaşmak için scheduled post oluştur.
     *
     * @return InstagramPost|null  Oluşturuldu/skipped null
     */
    public function shareBlog(BlogPost $post): ?InstagramPost
    {
        // Idempotency: aynı blog için zaten IG post oluşturulmuş mu?
        $existing = InstagramPost::where('blog_post_id', $post->id)->first();
        if ($existing !== null) {
            Log::info('Blog auto-share: zaten paylaşılmış, atlanıyor', [
                'blog_post_id'      => $post->id,
                'instagram_post_id' => $existing->id,
            ]);

            return null;
        }

        // Master switch
        if (Setting::getValue('blog_auto_share_instagram', '1') !== '1') {
            return null;
        }

        // Blog yayında olmalı
        if (! $post->is_published) {
            return null;
        }

        // Kapak görseli geçerli mi (placeholder veya yoksa skip)
        $sourcePath = $this->resolveSourceImage($post);
        if ($sourcePath === null) {
            Log::info('Blog auto-share: kapak görseli yok/placeholder, atlanıyor', [
                'blog_post_id' => $post->id,
                'image'        => $post->image,
            ]);

            return null;
        }

        // Görseli IG-uygun forma getir (1:1 center crop) + IG klasörüne kaydet
        try {
            $igImagePath = $this->prepareInstagramImage($sourcePath, $post);
        } catch (\Throwable $e) {
            Log::warning('Blog auto-share: görsel hazırlanamadı', [
                'blog_post_id' => $post->id,
                'source'       => $sourcePath,
                'error'        => $e->getMessage(),
            ]);

            TelegramNotifier::notifyAdminError(
                'Blog→Instagram: görsel hazırlanamadı',
                [
                    'blog_id' => '#' . $post->id,
                    'baslik'  => mb_strimwidth((string) $post->title, 0, 80, '…', 'UTF-8'),
                    'kaynak'  => basename($sourcePath),
                    'hata'    => $e->getMessage(),
                ],
                rescue(fn () => route('admin.blog-posts.edit', $post->id), null, false),
                emoji: '⚠️',
                cacheKey: "blog_share_img_fail:{$post->id}",
            );

            return null;
        }

        // AI ile caption + hashtag (cache: tek AI çağrısı, ikisi paylaşır)
        $aiResult = $this->fetchAiContent($post);

        $caption  = $this->buildCaption($post, $aiResult);
        $hashtags = $this->buildHashtags($post, $aiResult);

        $shareToFacebook = $this->shouldShareToFacebook();
        $shareToTiktok   = $this->shouldShareToTiktok();

        try {
            $igPost = InstagramPost::create([
                'media_type'          => InstagramMediaType::Image,
                'image_path'          => $igImagePath,
                'caption'             => $caption,
                'hashtags'            => $hashtags,
                'scheduled_at'        => now()->addMinutes(5), // Cron 30 dk'da bir → max 35 dk gecikme
                'status'              => InstagramPostStatus::Scheduled,
                'created_by'          => $post->user_id,
                'publish_to_facebook' => $shareToFacebook,
                'publish_to_tiktok'   => $shareToTiktok,
                'blog_post_id'        => $post->id,
            ]);

            Log::info('Blog auto-share: IG post oluşturuldu', [
                'blog_post_id'      => $post->id,
                'instagram_post_id' => $igPost->id,
                'scheduled_at'      => $igPost->scheduled_at?->toDateTimeString(),
                'facebook'          => $shareToFacebook,
                'tiktok'            => $shareToTiktok,
            ]);

            return $igPost;
        } catch (\Throwable $e) {
            Log::error('Blog auto-share: InstagramPost oluşturulamadı', [
                'blog_post_id' => $post->id,
                'error'        => $e->getMessage(),
            ]);

            TelegramNotifier::notifyAdminError(
                'Blog→Instagram: post oluşturulamadı',
                [
                    'blog_id' => '#' . $post->id,
                    'baslik'  => mb_strimwidth((string) $post->title, 0, 80, '…', 'UTF-8'),
                    'hata'    => $e->getMessage(),
                ],
                rescue(fn () => route('admin.blog-posts.edit', $post->id), null, false),
                cacheKey: "blog_share_post_fail:{$post->id}",
            );

            return null;
        }
    }

    /**
     * Blog kapak görselinin gerçek dosya yolunu çöz. Placeholder ise null.
     */
    private function resolveSourceImage(BlogPost $post): ?string
    {
        $image = (string) ($post->image ?? '');
        if ($image === '') {
            return null;
        }

        // Placeholder kontrolü — varsayılan görsel (content/post-image.jpg)
        if (str_starts_with($image, 'content/')) {
            return null;
        }

        // public/uploads/{path} → fiziksel yol
        $fullPath = public_path('uploads/' . ltrim($image, '/'));

        return is_file($fullPath) ? $fullPath : null;
    }

    /**
     * Blog görselini Instagram için 1:1 oranında işle ve IG klasörüne kaydet.
     *
     * @return string  Relative path: "instagram/blog-{slug}-{hash}.webp"
     *
     * @throws \RuntimeException
     */
    private function prepareInstagramImage(string $sourcePath, BlogPost $post): string
    {
        // Source path'ten UploadedFile sarmalı oluştur (test=true → move'lamaz)
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
        $filename  = 'blog-' . Str::slug((string) $post->slug ?: (string) $post->title) . '-' . substr(md5((string) $post->id), 0, 8) . '.' . $extension;

        // Geçici kopya (autoFix UploadedFile alıyor, original'i bozma)
        $tmpPath = tempnam(sys_get_temp_dir(), 'blog-ig-');
        if ($tmpPath === false) {
            throw new \RuntimeException('Geçici dosya oluşturulamadı.');
        }

        if (! @copy($sourcePath, $tmpPath)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Görsel kopyalanamadı: ' . $sourcePath);
        }

        try {
            $sourceFile = new UploadedFile(
                $tmpPath,
                $filename,
                mime_content_type($tmpPath) ?: 'image/jpeg',
                null,
                true, // test mode = move yapma
            );

            // 1:1 (Feed) center crop — Instagram için en güvenli oran
            $fittedFile = $this->imageProcessor->autoFix($sourceFile, 'image');

            // IG klasörüne kaydet (responsive varyantlar dahil)
            return $this->uploadService->uploadImage(
                $fittedFile,
                'instagram',
                'blog-' . Str::slug((string) $post->title),
            );
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Tek bir AI çağrısı — caption + hashtags birlikte. Sonucu hem buildCaption
     * hem buildHashtags kullanır (ekonomi: çift çağrı yok).
     *
     * @return array{success: bool, caption?: string, hashtags?: list<string>}
     */
    private function fetchAiContent(BlogPost $post): array
    {
        // AI'a verilecek konu — başlık + body ilk 800 char (HTML temizlenmiş)
        $body = trim(strip_tags((string) ($post->body ?? '')));
        $bodyExcerpt = mb_substr($body, 0, 800, 'UTF-8');
        $topic = trim((string) $post->title) . ($bodyExcerpt !== '' ? "\n\n" . $bodyExcerpt : '');

        try {
            $result = $this->captionAi->generateFromTopic($topic);
            if ($result['success'] ?? false) {
                return [
                    'success'  => true,
                    'caption'  => (string) ($result['caption'] ?? ''),
                    'hashtags' => (array) ($result['hashtags'] ?? []),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Blog auto-share: AI caption üretimi başarısız, template fallback', [
                'blog_post_id' => $post->id,
                'error'        => $e->getMessage(),
            ]);
        }

        return ['success' => false];
    }

    /**
     * Caption — AI başarılıysa AI'dan, yoksa template fallback.
     *
     * AI caption zaten 600-1200 char + emoji + CTA içeriyor (servis prompt'u).
     * Sonuna marka link satırı eklenir.
     *
     * @param  array{success: bool, caption?: string, hashtags?: list<string>}  $aiResult
     */
    private function buildCaption(BlogPost $post, array $aiResult): string
    {
        $title = trim((string) $post->title);

        // ── AI yolu ──
        if (($aiResult['success'] ?? false) && ! empty($aiResult['caption'])) {
            $aiCaption = trim((string) $aiResult['caption']);

            // Toplam IG limit = 2200 char. Caption + "\n\n" + hashtag birleşimi
            // buildFullCaption() içinde tekrar kontrol ediliyor (akıllı kırpma).
            // Burada caption'ı CAPTION_BUDGET'a sığdırıyoruz ki hashtag'lere yer
            // kalsın (yaklaşık 1700 char caption + 500 char hashtag + ayraç).
            $lines = [$aiCaption];

            // AI caption'da link/yönlendirme yoksa marka kapanışı ekle
            if (! preg_match('/orhanbabaninciftligi\.com|bio.?daki|bio\'daki/i', $aiCaption)) {
                $lines[] = '';
                $lines[] = '🌿 orhanbabaninciftligi.com';
            }

            $combined = implode("\n", $lines);

            // Caption için 1700 char hedef → hashtag'lere ~500 char kalır.
            // Aşılırsa kelime sınırına saygılı kırp.
            $captionBudget = 1700;
            if (mb_strlen($combined, 'UTF-8') > $captionBudget) {
                $cut = mb_substr($combined, 0, $captionBudget - 1, 'UTF-8');
                $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');
                if ($lastSpace !== false && $lastSpace > $captionBudget * 0.8) {
                    $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
                }
                $combined = rtrim($cut, " ,.;:-") . '…';
            }

            return $combined;
        }

        // ── Fallback: template (eski davranış, daha zayıf ama her zaman çalışır) ──
        $excerpt = trim((string) $post->excerpt);
        if (mb_strlen($excerpt, 'UTF-8') > 280) {
            $excerpt = mb_substr($excerpt, 0, 277, 'UTF-8') . '…';
        }

        $lines = [];
        $lines[] = '✨ ' . $title;
        $lines[] = '';
        if ($excerpt !== '') {
            $lines[] = $excerpt;
            $lines[] = '';
        }
        $lines[] = '📖 Yazının tamamı için bio\'daki bağlantıdan blog sayfamızı ziyaret edin!';
        $lines[] = '';
        $lines[] = '🌿 orhanbabaninciftligi.com';

        return implode("\n", $lines);
    }

    /**
     * Hashtag listesi — InstagramHashtagBuilder ile 4 katman + filtre.
     *
     * Builder kendi içinde AI'ı tekrar çağırıyor; biz zaten bu çağrıyı
     * fetchAiContent'te yapmış olabiliriz. Verimlilik için: AI tag'leri
     * elimizde varsa onları doğrudan filter'a soktuğumuz `buildFromCandidates`
     * akışı yok — Builder her zaman taze çağrı yapıyor. Ama generateFromTopic
     * içinde rate limit yoksa 2x çağrı kabul edilebilir (cron 4×/gün).
     *
     * Pragmatik optimizasyon: Builder'a "ön-getirilmiş AI hashtag" verme
     * imkanı ekledik (passAiHashtags). AI bir kez çağrılır, ikisi paylaşır.
     *
     * @param  array{success: bool, caption?: string, hashtags?: list<string>}  $aiResult
     * @return list<string>
     */
    private function buildHashtags(BlogPost $post, array $aiResult): array
    {
        return $this->hashtagBuilder->buildForBlog(
            $post,
            preFetchedAiHashtags: ($aiResult['success'] ?? false)
                ? (array) ($aiResult['hashtags'] ?? [])
                : null,
        );
    }

    /**
     * Facebook cross-post yapılsın mı?
     * - Master setting kapalıysa → false
     * - FB Page token kurulu değilse → false
     */
    private function shouldShareToFacebook(): bool
    {
        if (Setting::getValue('blog_auto_share_facebook', '1') !== '1') {
            return false;
        }

        return ! empty(Setting::getValue('instagram_facebook_page_id'))
            && ! empty(Setting::getValue('instagram_facebook_page_token'));
    }

    /**
     * Otomatik blog yazılarında TikTok cross-post yapılsın mı?
     *
     * 3 koşul birden:
     *  - tiktok_enabled = '1'
     *  - tiktok_auto_share_blog = '1'  (otomatik blog için ayrı opt-in)
     *  - tiktok_access_token dolu (OAuth bağlı)
     *
     * docs/tiktok.md Bölüm 7.3.
     */
    private function shouldShareToTiktok(): bool
    {
        if (Setting::getValue('tiktok_enabled', '0') !== '1') {
            return false;
        }
        if (Setting::getValue('tiktok_auto_share_blog', '0') !== '1') {
            return false;
        }
        return trim((string) Setting::getValue('tiktok_access_token', '')) !== '';
    }
}
