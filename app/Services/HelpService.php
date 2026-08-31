<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Route;

/**
 * Panel içi yardım.
 *
 * Panelde otuzdan fazla ekran var ve devralan kişinin bunların ne işe
 * yaradığını koddan çıkarması bekleniyordu. Bu kit başkalarına teslim
 * edilmek için var; teslim edilen şeyin kendi kılavuzu da olmalı.
 *
 * İçerik `config/help.php`'de: metni değiştirmek için dağıtım yapmak
 * gerekmiyor ve bu kit'ten türeyen her proje kendi modüllerini kendi
 * diliyle anlatabiliyor.
 */
final class HelpService
{
    /**
     * Kılavuzlar — yalnız bu kurulumda gerçekten var olan ekranlar.
     *
     * Rotası olmayan modül listelenmiyor: bir projede kaldırılan modülün
     * kılavuzu ekranda durup tıklanınca hata vermemeli.
     *
     * @return list<array<string, mixed>>
     */
    public function guides(?string $search = null): array
    {
        $guides = array_values(array_filter(
            (array) config('help.guides', []),
            static fn (array $guide): bool => Route::has((string) $guide['route']),
        ));

        return $this->filter($guides, $search, ['title', 'description']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function faqs(?string $search = null, ?string $category = null): array
    {
        $faqs = (array) config('help.faqs', []);

        if ($category !== null && $category !== '' && $category !== 'all') {
            $faqs = array_filter($faqs, static fn (array $faq): bool => $faq['category'] === $category);
        }

        return $this->filter(array_values($faqs), $search, ['question', 'answer']);
    }

    /**
     * @return array<string, string>
     */
    public function faqCategories(): array
    {
        return (array) config('help.faq_categories', []);
    }

    /**
     * Ekranın üstündeki sayılar.
     *
     * @return array<string, int|string>
     */
    public function stats(): array
    {
        return [
            'guides'  => count($this->guides()),
            'faqs'    => count($this->faqs()),
            'modules' => count(array_filter(
                (array) config('help.guides', []),
                static fn (array $guide): bool => Route::has((string) $guide['route']),
            )),
        ];
    }

    /**
     * Sürüm ve ortam bilgisi.
     *
     * Destek isteyen kişinin ilk sorulacağı şeyler. Ekranda durması, "hangi
     * sürümü kullanıyorsunuz" sorusunu bir e-posta turu olmaktan çıkarıyor.
     *
     * @return list<array{label: string, value: string, icon: string}>
     */
    public function environment(): array
    {
        return [
            [
                'label' => 'Uygulama Sürümü',
                'value' => (string) config('app.version', 'Laravel ' . app()->version()),
                'icon'  => 'bi-box-seam',
            ],
            [
                'label' => 'PHP Sürümü',
                'value' => PHP_VERSION,
                'icon'  => 'bi-filetype-php',
            ],
            [
                'label' => 'Ortam',
                'value' => (string) config('app.env'),
                'icon'  => 'bi-hdd-network',
            ],
            [
                'label' => 'Zaman Dilimi',
                'value' => (string) config('app.timezone'),
                'icon'  => 'bi-clock',
            ],
        ];
    }

    /**
     * Arama: başlık ve açıklamada, büyük/küçük harf ve Türkçe harf ayrımı
     * gözetmeden.
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $fields
     * @return list<array<string, mixed>>
     */
    private function filter(array $items, ?string $search, array $fields): array
    {
        if ($search === null || trim($search) === '') {
            return $items;
        }

        // "İ" küçültüldüğünde birleşik noktalı bir harf çıkıyor ve klavyeden
        // yazılan "i" ile eşleşmiyor; harfler önce eşleniyor.
        $normalize = static fn (string $value): string => mb_strtolower(
            str_replace(['İ', 'I', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'], ['i', 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç'], $value),
        );

        $needle = $normalize(trim($search));

        return array_values(array_filter($items, static function (array $item) use ($fields, $needle, $normalize): bool {
            foreach ($fields as $field) {
                if (str_contains($normalize((string) ($item[$field] ?? '')), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
