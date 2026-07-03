<?php

declare(strict_types=1);

namespace App\Services\Tiktok;

/**
 * TikTok Content Posting API hatalarını taşır.
 * docs/tiktok.md Bölüm 4.2.
 */
final class TiktokApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?array $rawResponse = null,
    ) {
        parent::__construct($message);
    }
}
