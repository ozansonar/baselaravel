<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kimliği doğrulanabilen sosyal sağlayıcılar.
 *
 * İkisi de OpenID Connect konuşuyor: uygulama sağlayıcıdan bir kimlik jetonu
 * (imzalı JWT) alıyor, sunucu onu sağlayıcının açık anahtarıyla doğruluyor.
 * Tarayıcı yönlendirmesi yok — mobil uygulamanın akışı bu.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';

    /**
     * Jetonun içindeki `iss` bu olmalı.
     *
     * Google iki biçimi de kullanıyor (tarihsel sebeple), ikisi de geçerli.
     *
     * @return list<string>
     */
    public function issuers(): array
    {
        return match ($this) {
            self::Google => ['https://accounts.google.com', 'accounts.google.com'],
            self::Apple  => ['https://appleid.apple.com'],
        };
    }

    /**
     * Sağlayıcının açık anahtarlarının adresi (JWKS).
     */
    public function jwksUrl(): string
    {
        return match ($this) {
            self::Google => 'https://www.googleapis.com/oauth2/v3/certs',
            self::Apple  => 'https://appleid.apple.com/auth/keys',
        };
    }

    /**
     * Jetonun kime düzenlendiği — bizim istemci kimliklerimiz.
     *
     * Liste, çünkü aynı ürünün iOS, Android ve web istemcileri ayrı kimlik
     * taşıyor ve üçü de aynı hesaba giriyor. Boşsa doğrulama hiç yapılmıyor:
     * yapılandırılmamış bir sağlayıcı kapalı sayılıyor.
     *
     * @return list<string>
     */
    public function audiences(): array
    {
        /** @var string $raw */
        $raw = (string) config('services.' . $this->value . '.client_ids', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function isConfigured(): bool
    {
        return $this->audiences() !== [];
    }

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Apple  => 'Apple',
        };
    }
}
