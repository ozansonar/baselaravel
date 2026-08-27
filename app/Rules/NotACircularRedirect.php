<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Redirect;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Yönlendirmenin kendine ya da bir halkaya dönmesini engeller.
 *
 * Tek tek bakıldığında iki kayıt da doğrudur: /a → /b ve /b → /a. Birlikte ise
 * tarayıcıyı sonsuz döngüye sokar ve sayfa "çok fazla yönlendirme" hatasıyla
 * hiç açılmaz. Kaydeden kişi bunu ancak siteyi gezerken fark eder — o yüzden
 * kayıt anında söyleniyor.
 */
final class NotACircularRedirect implements ValidationRule
{
    /** Zincir bu adımdan uzunsa halka aramayı bırakıyoruz. */
    private const MAX_DEPTH = 10;

    public function __construct(
        private readonly string $oldUrl,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || $this->oldUrl === '') {
            return;
        }

        $target = $this->normalize($value);
        $source = $this->normalize($this->oldUrl);

        if ($target === $source) {
            $fail('Yeni URL eski URL ile aynı olamaz: sayfa kendine yönlenir ve hiç açılmaz.');

            return;
        }

        // Hedefin ucunu takip ediyoruz: /a → /b → /c → /a ise halka var.
        $gezilen = [$source];
        $sonraki = $target;

        for ($adim = 0; $adim < self::MAX_DEPTH; $adim++) {
            $kayit = Redirect::query()
                ->where('old_url', $sonraki)
                ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
                ->first();

            if ($kayit === null || $kayit->new_url === null) {
                return;
            }

            $sonraki = $this->normalize($kayit->new_url);

            if (in_array($sonraki, $gezilen, true)) {
                $fail(sprintf(
                    'Bu yönlendirme bir döngü oluşturuyor: %s zinciri başa dönüyor.',
                    implode(' → ', array_slice([...$gezilen, $sonraki], 0, 4)),
                ));

                return;
            }

            $gezilen[] = $sonraki;
        }
    }

    /**
     * Karşılaştırma için yolu sadeleştirir: sondaki bölü ve büyük/küçük harf
     * farkı iki adresi farklı göstermemeli.
     */
    private function normalize(string $value): string
    {
        $value = trim($value);

        return $value === '/' ? '/' : rtrim(mb_strtolower($value), '/');
    }
}
