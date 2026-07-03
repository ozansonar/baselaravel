<?php

declare(strict_types=1);

namespace App\Enums;

enum InstagramMediaType: string
{
    case Image = 'image';
    case Reels = 'reels';
    case Story = 'story';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Feed Post',
            self::Reels => 'Reels',
            self::Story => 'Story',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'bi-image',
            self::Reels => 'bi-camera-reels',
            self::Story => 'bi-circle-fill',
        };
    }

    /**
     * Meta Graph API'de kullanılan media_type değeri.
     * Image type'ında parametre gönderilmez, default IMAGE/CAROUSEL kullanılır.
     */
    public function metaApiType(): ?string
    {
        return match ($this) {
            self::Image => null,
            self::Reels => 'REELS',
            self::Story => 'STORIES',
        };
    }

    /**
     * Bu medya tipi video gerektirir mi?
     * Reels: zorunlu video. Story: video VEYA görsel (esnek).
     * Image: sadece görsel.
     */
    public function requiresVideo(): bool
    {
        return $this === self::Reels;
    }

    /**
     * Bu medya tipi carousel destekler mi?
     */
    public function supportsCarousel(): bool
    {
        return $this === self::Image;
    }

    /**
     * Facebook cross-post desteği var mı?
     * - Image: Page /photos endpoint'i
     * - Reels: Page /video_reels endpoint'i (3 fazlı upload)
     * - Story: Facebook Page Story için public Graph API yok
     */
    public function supportsFacebookCrossPost(): bool
    {
        return $this !== self::Story;
    }
}
