<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialProvider;

/**
 * Sağlayıcının doğrulanmış kimlik jetonundan çıkan bilgiler.
 *
 * Bu nesne yalnız {@see SocialIdentityVerifier} tarafından üretiliyor ve
 * üretilmiş olması "imza, iss, aud ve exp doğrulandı" demek. Bir yerden
 * doğrudan kurulup hesap açmaya yollanırsa doğrulamanın tamamı atlanmış olur.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        /** Sağlayıcının kullanıcı kimliği (`sub`) — hesabın gerçek anahtarı. */
        public string $subject,
        public ?string $email,
        /** Sağlayıcı bu adresi doğruladı mı? Hesap eşleştirmesi buna bakıyor. */
        public bool $emailVerified,
        public ?string $name,
    ) {}

    /**
     * Adı ada/soyada bölmeye çalışır.
     *
     * Google tek bir `name` gönderiyor, Apple ilk yetkilendirmede ayrı alanlar
     * veriyor ve sonraki girişlerde hiç göndermiyor. Bölme kabaca: tek
     * kelimelik ad soyadsız kalıyor, "hiç ad yok"tan iyi.
     *
     * @return array{0: string, 1: string}
     */
    public function splitName(): array
    {
        $name = trim((string) $this->name);

        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/u', $name) ?: [];

        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $last = (string) array_pop($parts);

        return [implode(' ', $parts), $last];
    }
}
