<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\SeoLevel;

/**
 * Tek bir SEO bulgusu.
 *
 * Bulgu üç soruyu birden yanıtlamak zorunda: **ne** yanlış, **nerede** yanlış
 * ve **ne yapmalı**. Yalnız ilkini söyleyen bir uyarı ("meta açıklama kısa")
 * yazarı ekranda arattırıyor; alanı da söyleyen bir uyarı doğrudan oraya
 * götürüyor.
 */
final readonly class SeoIssue
{
    /**
     * @param string      $code    Kural kodu — "meta.desc.length" gibi. Çeviri
     *                             anahtarı ve testlerin tutamağı bu.
     * @param SeoLevel    $level   Ne kadar ciddi
     * @param string      $message Yazara görünen cümle
     * @param string|null $field   Formda hangi alan — panel oraya bağlanıyor
     * @param string|null $hint    Ne yapılacağı; mesaj sorunu söylüyor, bu çözümü
     */
    public function __construct(
        public string $code,
        public SeoLevel $level,
        public string $message,
        public ?string $field = null,
        public ?string $hint = null,
    ) {}

    public static function error(string $code, string $message, ?string $field = null, ?string $hint = null): self
    {
        return new self($code, SeoLevel::Error, $message, $field, $hint);
    }

    public static function warning(string $code, string $message, ?string $field = null, ?string $hint = null): self
    {
        return new self($code, SeoLevel::Warning, $message, $field, $hint);
    }

    public static function info(string $code, string $message, ?string $field = null, ?string $hint = null): self
    {
        return new self($code, SeoLevel::Info, $message, $field, $hint);
    }

    /**
     * @return array{code: string, level: string, message: string, field: string|null, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'code'    => $this->code,
            'level'   => $this->level->value,
            'message' => $this->message,
            'field'   => $this->field,
            'hint'    => $this->hint,
        ];
    }
}
