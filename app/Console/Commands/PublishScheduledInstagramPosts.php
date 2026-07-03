<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InstagramPostStatus;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Services\FacebookPageService;
use App\Services\InstagramService;
use App\Services\TiktokService;
use App\Support\InstagramPostNotifier;
use Illuminate\Console\Command;

final class PublishScheduledInstagramPosts extends Command
{
    protected $signature = 'instagram:publish-scheduled
                            {--limit=5 : Maksimum işlenecek gönderi sayısı}
                            {--force : Otomatik paylaşım kapalı olsa bile çalıştır}';

    protected $description = 'Zamanı gelmiş Instagram + Facebook gönderilerini Graph API üzerinden yayınlar';

    public function handle(InstagramService $service, FacebookPageService $facebookService, TiktokService $tiktokService): int
    {
        $enabled = Setting::getValue('instagram_enabled', '0') === '1';

        if (! $enabled && ! $this->option('force')) {
            $this->warn('Instagram otomatik paylaşım ayarlardan kapatılmış. Çalıştırılmadı.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $due = InstagramPost::query()
            ->due()
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        if ($due->isEmpty()) {
            $this->info('Yayınlanacak planlanmış gönderi yok.');

            return self::SUCCESS;
        }

        $this->info("{$due->count()} gönderi işlenecek.");

        $success = 0;
        $failed = 0;

        foreach ($due as $post) {
            $attempt = $post->retry_count + 1;
            $attemptInfo = $attempt > 1 ? " [deneme {$attempt}/" . InstagramPost::MAX_RETRY_COUNT . ']' : '';
            $this->line(" → #{$post->id} yayınlanıyor (Instagram" . ($post->publish_to_facebook ? ' + Facebook' : '') . "){$attemptInfo}...");

            $post->update(['status' => InstagramPostStatus::Publishing]);

            // Instagram önce
            $igResult = $service->publish($post);

            if ($igResult['success']) {
                $success++;
                $this->info("   ✓ Instagram başarılı.");
            } else {
                $failed++;
                $this->error("   ✗ Instagram: {$igResult['message']}");
            }

            // Facebook bağımsız çalışır — Instagram başarısız olsa bile dener
            if ($post->publish_to_facebook) {
                $fbResult = $facebookService->publish($post->refresh());

                if ($fbResult['success']) {
                    $this->info("   ✓ Facebook başarılı.");
                } else {
                    $this->error("   ✗ Facebook: {$fbResult['message']}");
                }
            }

            // TikTok cross-post — sadece IG yayını başarılı olduysa dene
            // (TT publish IG container'a değil, ayrı public URL'e bağlı; IG yoksa anlamsız değil
            //  ama IG retry akışında çift tetiklenmemek için gate koyuyoruz).
            // Detay: docs/tiktok.md Bölüm 5.
            if ($igResult['success'] && $post->refresh()->publish_to_tiktok) {
                $this->crossPostTiktok($tiktokService, $post->refresh());
            }

            // Safety net: service beklenmedik bir şekilde Publishing'de bıraktıysa
            // Failed'e çevir — aksi halde post sonsuza kayboluyor (scopeDue almıyor).
            $post->refresh();
            if ($post->status === InstagramPostStatus::Publishing) {
                $post->update([
                    'status'        => InstagramPostStatus::Failed,
                    'error_message' => $post->error_message
                        ?? 'Servis Publishing durumundan çıkmadı (bilinmeyen hata).',
                    'retry_count'   => $post->retry_count + 1,
                ]);
                $this->warn("   ! #{$post->id} Publishing'de takılı kalmıştı, Failed'e çevrildi.");
            }

            // Kalıcı hataya geçtiyse admin'e tek seferlik bildirim (mail + telegram).
            // Telegram seviyesi 'every_failure' ise her başarısızlıkta da Telegram'a düşer.
            $notify = InstagramPostNotifier::notifyIfPermanentlyFailed($post->refresh());

            match ($notify['mail']) {
                'sent'         => $this->info("   ✉ #{$post->id} kalıcı hata e-postası kuyruğa eklendi."),
                'no_recipient' => $this->warn("   ! #{$post->id} e-posta atlandı: admin alıcısı ayarlanmamış."),
                'queue_error'  => $this->error("   ! #{$post->id} e-posta kuyruğa eklenemedi (log'a yazıldı)."),
                default        => null,
            };

            match ($notify['telegram_permanent']) {
                'sent'  => $this->info("   📨 #{$post->id} kalıcı hata Telegram'a gönderildi."),
                'error' => $this->error("   ! #{$post->id} Telegram (kalıcı) gönderilemedi (log'a yazıldı)."),
                default => null, // 'disabled' / 'skipped' → sessiz
            };

            match ($notify['telegram_attempt']) {
                'sent'  => $this->info("   📨 #{$post->id} deneme bildirimi Telegram'a gönderildi."),
                'error' => $this->error("   ! #{$post->id} Telegram (deneme) gönderilemedi (log'a yazıldı)."),
                default => null,
            };
        }

        // TT-only retry — IG zaten yayında ama TT bekliyor olan post'lar
        // (docs/tiktok.md Bölüm 5.4 Yöntem A)
        $ttRetryEnabled = Setting::getValue('tiktok_enabled', '0') === '1';
        if ($ttRetryEnabled) {
            $ttRetryPosts = InstagramPost::tiktokRetryDue()->limit(5)->get();
            if ($ttRetryPosts->isNotEmpty()) {
                $this->line('');
                $this->info("TT-only retry: {$ttRetryPosts->count()} bekleyen post.");
                foreach ($ttRetryPosts as $rPost) {
                    $this->line(" → #{$rPost->id} TikTok retry...");
                    $this->crossPostTiktok($tiktokService, $rPost);
                }
            }
        }

        $this->info("Bitti. Instagram başarılı: {$success}, başarısız: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Tek bir post için TikTok cross-post — sonucu DB'ye yazar + console output.
     * Hata olursa IG yayını ASLA etkilenmez.
     *
     * docs/tiktok.md Bölüm 5.3.
     */
    private function crossPostTiktok(TiktokService $tiktokService, InstagramPost $post): void
    {
        try {
            $result = $tiktokService->publish($post);

            if ($result['success']) {
                $post->update([
                    'tt_post_id'       => $result['tt_post_id'] ?? null,
                    'tt_permalink'     => $result['tt_permalink'] ?? null,
                    'tt_published_at'  => isset($result['tt_post_id']) || isset($result['tt_inbox_id']) ? now() : null,
                    'tt_inbox_id'      => $result['tt_inbox_id'] ?? null,
                    'tt_error_message' => null,
                ]);
                if (isset($result['tt_inbox_id'])) {
                    $this->info("   📥 TikTok Inbox'a düştü (mobilde yayınla).");
                } else {
                    $this->info("   🎵 TikTok başarılı.");
                }
            } else {
                $post->update([
                    'tt_error_message' => $result['message'],
                    'tt_retry_count'   => $post->tt_retry_count + 1,
                ]);
                $this->error("   ✗ TikTok: {$result['message']}");

                // Kalıcı hata → kritik bildirim
                if ($post->fresh()->tt_retry_count >= InstagramPost::MAX_RETRY_COUNT) {
                    $logUrl = rescue(static fn (): ?string => route('admin.instagram-posts.edit', $post->id), null, false);
                    \App\Services\NotificationCenter::sendCritical(
                        title: 'TikTok cross-post kalıcı hata',
                        message: "Post #{$post->id} 3 deneme sonrası TikTok'a paylaşılamadı: " . $result['message'],
                        actionUrl: $logUrl,
                    );
                }
            }
        } catch (\Throwable $e) {
            $post->update([
                'tt_error_message' => 'Exception: ' . $e->getMessage(),
                'tt_retry_count'   => $post->tt_retry_count + 1,
            ]);
            $this->error("   ! TikTok exception: {$e->getMessage()}");
        }
    }
}
