<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Instagram media tipine göre aspect ratio aralık kontrolü.
 *
 * Spec:
 *   - Feed Post: 4:5 (0.8 dikey) ile 1.91:1 (1.91 yatay) arası — KAPALI ARALIK
 *   - Story:     9:16 (0.5625) civarı — esnek ama 0.5-0.7 arası kabul edilir
 *
 * Laravel'in built-in `dimensions:ratio` rule'u TEK oran ister, aralık
 * desteklemez. Bu yüzden custom rule.
 *
 * Min/max width kontrolü Laravel `dimensions:min_width=...,max_width=...`
 * ile zaten yapılır — burada SADECE oran kontrolü.
 */
final class InstagramAspectRatio implements ValidationRule
{
    public function __construct(
        private readonly float $minRatio,
        private readonly float $maxRatio,
        private readonly string $contextLabel,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return; // Diğer rule'lar (image, file) hata verir
        }

        $info = @getimagesize($value->getRealPath());
        if (! is_array($info) || ! isset($info[0], $info[1])) {
            $fail('Görsel boyutları okunamadı (bozuk dosya?).');

            return;
        }

        $width  = (int) $info[0];
        $height = (int) $info[1];

        if ($height <= 0) {
            $fail('Görsel yüksekliği geçersiz.');

            return;
        }

        $ratio = $width / $height;

        if ($ratio < $this->minRatio || $ratio > $this->maxRatio) {
            $fail(sprintf(
                '%s için aspect ratio uygun değil (mevcut: %.2f). %.2f ile %.2f arasında olmalı.',
                $this->contextLabel,
                $ratio,
                $this->minRatio,
                $this->maxRatio,
            ));
        }
    }
}
