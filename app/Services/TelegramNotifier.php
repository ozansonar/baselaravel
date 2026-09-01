<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bot API üzerinden bildirim gönderir.
 *
 * Yapılandırma (Settings):
 *  - telegram_enabled       : '1' = aktif
 *  - telegram_bot_token     : @BotFather'dan alınan bot token
 *  - telegram_chat_id       : Mesajın gönderileceği chat ID (kullanıcı veya grup)
 *
 * Mesaj formatı: HTML parse mode (sadece <b>, <i>, <code>, <a> tag'leri).
 */
final class TelegramNotifier
{
    private const API_BASE = 'https://api.telegram.org/bot';

    private const HTTP_TIMEOUT = 10;

    public static function isEnabled(): bool
    {
        if (Setting::getValue('telegram_enabled', '0') !== '1') {
            return false;
        }

        return self::token() !== '' && self::chatId() !== '';
    }

    /**
     * Telegram'a düz mesaj gönderir.
     *
     * @return array{ok: bool, message: string}
     */
    public static function send(string $text, ?string $chatId = null): array
    {
        $token = self::token();
        $target = $chatId ?? self::chatId();

        if ($token === '' || $target === '') {
            return ['ok' => false, 'message' => 'Telegram bot token veya chat ID ayarlanmamış.'];
        }

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->asJson()
                ->post(self::API_BASE . $token . '/sendMessage', [
                    'chat_id'                  => $target,
                    'text'                     => self::trimToTelegramLimit($text),
                    'parse_mode'               => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            $payload = $response->json();

            if ($response->successful() && ($payload['ok'] ?? false) === true) {
                return ['ok' => true, 'message' => 'Telegram mesajı gönderildi.'];
            }

            $description = is_array($payload) && isset($payload['description'])
                ? (string) $payload['description']
                : ('HTTP ' . $response->status());

            Log::warning('Telegram sendMessage failed', [
                'status'      => $response->status(),
                'description' => $description,
            ]);

            return ['ok' => false, 'message' => $description];
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage threw', [
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Servis hatası bildirimi — herhangi bir cron/job/servis fail olduğunda
     * admin'e Telegram'da anında bildirim atar.
     *
     * Throttling: aynı (title|cacheKey) için 60sn'de bir kez gönderilir
     * (spam koruması — aynı hata tekrar tekrarlanırsa flood gelmez).
     *
     * @param  string                $title       Kısa başlık (örn. "Bulk import satır hatası")
     * @param  array<string, mixed>  $context     Key-value detaylar (post_id, error vs.)
     * @param  string|null           $url         Detay paneline link (ops.)
     * @param  string                $emoji       Başlığın önünde 🚨 / ⚠️ vs.
     * @param  string|null           $cacheKey    Throttle anahtarı (default: title hash)
     */
    public static function notifyAdminError(
        string $title,
        array $context = [],
        ?string $url = null,
        string $emoji = '🚨',
        ?string $cacheKey = null,
    ): void {
        if (! self::isEnabled()) {
            return;
        }

        $throttleKey = 'tg_admin_err:' . md5(($cacheKey ?? $title));
        if (Cache::has($throttleKey)) {
            return; // 60 saniye içinde aynı hata zaten bildirildi
        }
        Cache::put($throttleKey, 1, now()->addSeconds(60));

        $lines = ["<b>{$emoji} " . e($title) . '</b>'];

        foreach ($context as $key => $value) {
            $valueStr = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            $valueStr = self::truncate((string) $valueStr, 300);
            $keyLabel = e(ucfirst(str_replace('_', ' ', (string) $key)));
            // Hata mesajı vb. uzun string'ler için <code>, kısa metadata için düz
            $lines[] = mb_strlen($valueStr) > 60
                ? "<b>{$keyLabel}:</b> <code>" . e($valueStr) . '</code>'
                : "<b>{$keyLabel}:</b> " . e($valueStr);
        }

        if ($url !== null && $url !== '') {
            $lines[] = "\n<a href=\"" . e($url) . '">Detayı panelde aç →</a>';
        }

        $text = implode("\n", $lines);

        $res = self::send($text);
        if (! $res['ok']) {
            Log::warning('Telegram admin error bildirimi gönderilemedi', [
                'title'  => $title,
                'reason' => $res['message'] ?? 'unknown',
            ]);
        }
    }

    private static function truncate(string $s, int $limit): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        return mb_strlen($s) > $limit ? mb_substr($s, 0, $limit - 1) . '…' : $s;
    }

    /**
     * Test mesajı — Settings sayfasındaki "Test Mesajı Gönder" butonu için.
     *
     * @return array{ok: bool, message: string}
     */
    public static function testConnection(): array
    {
        $text = "<b>✅ Test mesajı</b>\n"
            . 'Admin paneli — Telegram bildirimleri çalışıyor.' . "\n"
            . '<i>' . now()->format('d.m.Y H:i') . '</i>';

        return self::send($text);
    }

    /**
     * Telegram mesaj uzunluk limiti 4096 karakter — fazlasını güvenle kırp.
     */
    private static function trimToTelegramLimit(string $text): string
    {
        $max = 4000; // güvenlik payı

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 20) . "\n…(kesildi)";
    }

    private static function token(): string
    {
        return trim((string) Setting::getValue('telegram_bot_token', ''));
    }

    private static function chatId(): string
    {
        return trim((string) Setting::getValue('telegram_chat_id', ''));
    }
}
