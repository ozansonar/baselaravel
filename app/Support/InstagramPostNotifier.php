<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\InstagramPostStatus;
use App\Mail\InstagramPostFailedNotification;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Instagram post hata bildirim helper'ı (multi-channel).
 *
 * Hem cron komutundan (PublishScheduledInstagramPosts) hem manuel "Şimdi Paylaş"
 * akışından (InstagramPostController::publishAndRedirect) çağrılır — tek noktadan
 * idempotent kontrol.
 *
 * Kanallar:
 *  - Mail (admin_notification_email): kalıcı hatada (3/3 retry) tek seferlik
 *  - Telegram: kalıcı hatada her zaman; her hatada (every_failure modu) opsiyonel
 *
 * Telegram seviyesi (Setting `telegram_notify_level`):
 *  - 'permanent_only' (default) → sadece 3. retry'de
 *  - 'every_failure'            → her başarısız denemede ayrı mesaj
 */
final class InstagramPostNotifier
{
    /**
     * @return array{
     *     mail: string,
     *     telegram_permanent: string,
     *     telegram_attempt: string
     * }
     *   - mail: 'sent' | 'skipped' | 'no_recipient' | 'queue_error'
     *   - telegram_permanent: 'sent' | 'skipped' | 'disabled' | 'error'
     *   - telegram_attempt: 'sent' | 'skipped' | 'disabled' | 'error'
     */
    public static function notifyIfPermanentlyFailed(InstagramPost $post): array
    {
        $result = [
            'mail'               => 'skipped',
            'telegram_permanent' => 'skipped',
            'telegram_attempt'   => 'skipped',
        ];

        if ($post->status !== InstagramPostStatus::Failed) {
            return $result;
        }

        $isPermanent     = $post->retry_count >= InstagramPost::MAX_RETRY_COUNT;
        $alreadyNotified = $post->notified_failed_at !== null;

        // 1) Kalıcı hata bildirimi (idempotent — tek sefer)
        if ($isPermanent && ! $alreadyNotified) {
            $mailStatus       = self::sendPermanentMail($post);
            $telegramStatus   = self::sendPermanentTelegram($post);

            $result['mail']               = $mailStatus;
            $result['telegram_permanent'] = $telegramStatus;

            // Idempotency: en az bir kanal başarılıysa VEYA
            // her iki kanal da yapılandırılmamışsa flag'i işaretle
            // (queue_error/error durumunda işaretleme — tekrar denenir)
            $sent = $mailStatus === 'sent' || $telegramStatus === 'sent';
            $unconfigured = $mailStatus === 'no_recipient' && $telegramStatus === 'disabled';

            if ($sent || $unconfigured) {
                $post->update(['notified_failed_at' => now()]);
            }
        }

        // 2) Her başarısızlık için Telegram (every_failure modu)
        // Kalıcı durumda zaten yukarıda gönderildi → çift mesajı engelle.
        if (! $isPermanent) {
            $level = (string) Setting::getValue('telegram_notify_level', 'permanent_only');
            if ($level === 'every_failure') {
                $result['telegram_attempt'] = self::sendAttemptTelegram($post);
            }
        }

        return $result;
    }

    /**
     * @return string 'sent' | 'no_recipient' | 'queue_error'
     */
    private static function sendPermanentMail(InstagramPost $post): string
    {
        $adminEmail   = trim((string) Setting::getValue('admin_notification_email', ''));
        $contactEmail = trim((string) Setting::getValue('contact_email', ''));
        $recipient    = $adminEmail !== '' ? $adminEmail : $contactEmail;

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return 'no_recipient';
        }

        try {
            Mail::to($recipient)->queue(new InstagramPostFailedNotification($post));

            return 'sent';
        } catch (\Throwable $e) {
            Log::warning('Instagram failed-post mail could not be queued', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);

            return 'queue_error';
        }
    }

    /**
     * @return string 'sent' | 'disabled' | 'error'
     */
    private static function sendPermanentTelegram(InstagramPost $post): string
    {
        if (! TelegramNotifier::isEnabled()) {
            return 'disabled';
        }

        $text = self::formatPermanentMessage($post);
        $res  = TelegramNotifier::send($text);

        return $res['ok'] ? 'sent' : 'error';
    }

    /**
     * @return string 'sent' | 'disabled' | 'error'
     */
    private static function sendAttemptTelegram(InstagramPost $post): string
    {
        if (! TelegramNotifier::isEnabled()) {
            return 'disabled';
        }

        $text = self::formatAttemptMessage($post);
        $res  = TelegramNotifier::send($text);

        return $res['ok'] ? 'sent' : 'error';
    }

    private static function formatPermanentMessage(InstagramPost $post): string
    {
        $title = e(self::truncate($post->caption ?? ('Post #' . $post->id), 80));
        $error = e(self::truncate($post->error_message ?? '—', 300));
        $when  = optional($post->scheduled_at)->format('d.m.Y H:i') ?? '—';

        return "<b>🚨 Instagram gönderisi kalıcı olarak başarısız</b>\n"
            . "Post: <b>#{$post->id}</b>\n"
            . "Caption: <i>{$title}</i>\n"
            . "Planlanan: {$when}\n"
            . "Deneme: <b>{$post->retry_count}/" . InstagramPost::MAX_RETRY_COUNT . "</b>\n"
            . "Hata: <code>{$error}</code>\n\n"
            . self::adminUrl($post);
    }

    private static function formatAttemptMessage(InstagramPost $post): string
    {
        $title = e(self::truncate($post->caption ?? ('Post #' . $post->id), 80));
        $error = e(self::truncate($post->error_message ?? '—', 200));

        return "<b>⚠️ Instagram paylaşımı başarısız</b>\n"
            . "Post: <b>#{$post->id}</b> — Deneme {$post->retry_count}/" . InstagramPost::MAX_RETRY_COUNT . "\n"
            . "Caption: <i>{$title}</i>\n"
            . "Hata: <code>{$error}</code>\n\n"
            . self::adminUrl($post);
    }

    private static function adminUrl(InstagramPost $post): string
    {
        try {
            $url = route('admin.instagram-posts.edit', $post);

            return '<a href="' . e($url) . '">Panelde aç →</a>';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function truncate(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1) . '…' : $text;
    }
}
