<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Onboarding wizard durum yöneticisi.
 *
 * İlk kurulumda admin'in sırayla tamamlaması gereken adımları belirler:
 *   1. Site bilgileri (başlık, açıklama, iletişim)
 *   2. Instagram bağlantısı (access token + ig_user_id)
 *   3. Telegram bildirim (bot token + chat id)
 *   4. AI sağlayıcı (en az bir API key — Gemini, OpenAI, Anthropic)
 *   5. Tamamlandı
 *
 * `onboarding_completed=1` setting'i ile bir kez yapılır işareti.
 */
final class OnboardingService
{
    public const TOTAL_STEPS = 4;
    private const COMPLETED_KEY = 'onboarding_completed';

    /**
     * Onboarding tamamlandı mı?
     */
    public function isComplete(): bool
    {
        return Setting::getValue(self::COMPLETED_KEY, '0') === '1';
    }

    /**
     * Tamamlandı işaretle (kullanıcı manuel veya otomatik tamamlayınca).
     */
    public function markComplete(): void
    {
        Setting::setValue(self::COMPLETED_KEY, '1', 'general', 'text');
    }

    /**
     * Onboarding'i sıfırla (test/debug için).
     */
    public function reset(): void
    {
        Setting::setValue(self::COMPLETED_KEY, '0', 'general', 'text');
    }

    /**
     * Tüm adımların durumu.
     *
     * @return list<array{step: int, key: string, title: string, description: string, done: bool, route: ?string}>
     */
    public function steps(): array
    {
        return [
            [
                'step'        => 1,
                'key'         => 'site_info',
                'title'       => 'Site Bilgileri',
                'description' => 'Site başlığı, açıklaması ve iletişim bilgilerini tanımlayın.',
                'done'        => $this->checkSiteInfo(),
                'route'       => 'admin.settings.index',
            ],
            [
                'step'        => 2,
                'key'         => 'instagram',
                'title'       => 'Instagram Bağlantısı',
                'description' => 'Otomatik paylaşım için Instagram Graph API token ve hesap ID girin.',
                'done'        => $this->checkInstagram(),
                'route'       => 'admin.settings.index',
            ],
            [
                'step'        => 3,
                'key'         => 'telegram',
                'title'       => 'Telegram Bildirimleri',
                'description' => 'Hata ve sistem bildirimleri için Telegram bot kurun.',
                'done'        => $this->checkTelegram(),
                'route'       => 'admin.settings.index',
            ],
            [
                'step'        => 4,
                'key'         => 'ai',
                'title'       => 'AI Sağlayıcı',
                'description' => 'Caption ve içerik üretimi için en az bir AI sağlayıcı API key\'i girin.',
                'done'        => $this->checkAi(),
                'route'       => 'admin.settings.index',
            ],
        ];
    }

    /**
     * Tamamlanmış adım sayısı.
     */
    public function completedCount(): int
    {
        return count(array_filter($this->steps(), fn ($s) => $s['done']));
    }

    /**
     * Tamamlama yüzdesi.
     */
    public function progressPercent(): int
    {
        return (int) round(($this->completedCount() / self::TOTAL_STEPS) * 100);
    }

    /**
     * Bir sonraki yapılması gereken adım (yoksa null).
     *
     * @return ?array<string, mixed>
     */
    public function nextStep(): ?array
    {
        foreach ($this->steps() as $s) {
            if (! $s['done']) return $s;
        }
        return null;
    }

    /**
     * Wizard banner gösterilsin mi? (tamamlanmadı + tüm adımlar otomatik tamamlanmadı)
     */
    public function shouldShowBanner(): bool
    {
        return ! $this->isComplete() && $this->completedCount() < self::TOTAL_STEPS;
    }

    // ── Adım kontrolleri ─────────────────────────────────────

    private function checkSiteInfo(): bool
    {
        $title = trim((string) Setting::getValue('site_title', ''));
        $desc  = trim((string) Setting::getValue('site_description', ''));
        return $title !== '' && $desc !== '';
    }

    private function checkInstagram(): bool
    {
        $token  = trim((string) Setting::getValue('instagram_access_token', ''));
        $userId = trim((string) Setting::getValue('instagram_user_id', ''));
        return $token !== '' && $userId !== '';
    }

    private function checkTelegram(): bool
    {
        $bot  = trim((string) Setting::getValue('telegram_bot_token', ''));
        $chat = trim((string) Setting::getValue('telegram_chat_id', ''));
        return $bot !== '' && $chat !== '';
    }

    private function checkAi(): bool
    {
        $gemini    = trim((string) Setting::getValue('gemini_api_key', ''));
        $openai    = trim((string) Setting::getValue('openai_api_key', ''));
        $anthropic = trim((string) Setting::getValue('anthropic_api_key', ''));
        return $gemini !== '' || $openai !== '' || $anthropic !== '';
    }
}
