<?php

declare(strict_types=1);

namespace App\Services\Seo\Checks;

use App\Services\Seo\SeoCheck;
use App\Support\Seo\SeoIssue;
use App\Support\Seo\SeoSubject;
use Illuminate\Support\Str;

/**
 * Adres parçası.
 *
 * Adres, içeriğin en kalıcı parçası: bir kez yayınlandıktan sonra değiştirmek
 * yönlendirme borcu doğuruyor ve dışarıdan verilmiş bağlantıları kırıyor. Bu
 * yüzden denetim yayından **önce** anlamlı — sonrasında uyarmak, düzeltmesi
 * pahalı bir şeyi geç söylemek olur.
 */
final class SlugCheck implements SeoCheck
{
    /** @return list<SeoIssue> */
    public function run(SeoSubject $subject): array
    {
        $slug = trim($subject->slug);

        if ($slug === '') {
            // Boş slug'ı model kaydederken başlıktan üretiyor; burada uyarmak
            // yazara üretilecek adresi görme fırsatı veriyor.
            return [SeoIssue::info(
                'slug.format',
                __('seo.checks.slug.empty'),
                'slug',
                __('seo.checks.slug.empty_hint'),
            )];
        }

        $issues = [];
        $max = (int) config('seo.slug.max', 75);

        if (Str::length($slug) > $max) {
            $issues[] = SeoIssue::warning(
                'slug.format',
                __('seo.checks.slug.too_long', ['length' => Str::length($slug), 'max' => $max]),
                'slug',
                __('seo.checks.slug.too_long_hint'),
            );
        }

        // Büyük harf, boşluk, alt çizgi ve Türkçe harfler adres olarak
        // taşınabilir değil: kopyalanıp yapıştırıldığında yüzde işaretlerine
        // dönüşüyor ve okunmaz hâle geliyor.
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $issues[] = SeoIssue::warning(
                'slug.format',
                __('seo.checks.slug.invalid'),
                'slug',
                __('seo.checks.slug.invalid_hint'),
            );
        }

        if ($subject->title !== '' && $this->driftedFromTitle($slug, $subject->title)) {
            $issues[] = SeoIssue::info(
                'slug.mismatch',
                __('seo.checks.slug.mismatch'),
                'slug',
                __('seo.checks.slug.mismatch_hint'),
            );
        }

        return $issues;
    }

    /**
     * Adres başlıkla hiç örtüşmüyor mu?
     *
     * Tam eşleşme aranmıyor — adresin kısaltılmış olması iyi bir şey. Aranan
     * şey ortak kelime yokluğu: başlık değişmiş ama adres eskisiyle kalmış
     * olabilir ve o durumda yazarın haberi olmalı.
     */
    private function driftedFromTitle(string $slug, string $title): bool
    {
        $slugWords = array_filter(explode('-', $slug), static fn (string $w): bool => Str::length($w) > 2);
        $titleWords = array_filter(
            explode('-', Str::slug($title)),
            static fn (string $w): bool => Str::length($w) > 2,
        );

        if ($slugWords === [] || $titleWords === []) {
            return false;
        }

        return array_intersect($slugWords, $titleWords) === [];
    }
}
