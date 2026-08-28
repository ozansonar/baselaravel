<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Translation;
use App\Services\LanguageService;
use App\Services\TranslationService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Arayüz metinleri (dil yazıları) listesinin dışa aktarma tanımı.
 *
 * Bu listenin arkasında tek bir tablo yok: metinler dil dosyalarıyla veritabanı
 * üzerine yazımlarının birleşiminden çıkıyor. Bu yüzden satırlar sorgudan değil
 * servisin bölümlerinden akıyor; anahtar sayısı yüzlerle ölçüldüğü için bunun
 * bellek maliyeti yok.
 */
final class TranslationExport extends ListExport
{
    /** Ekranla aynı grup: yönetilen arayüz metinleri. */
    private const GROUP = 'site';

    public function __construct(
        private readonly TranslationService $translations,
        private readonly LanguageService $languages,
    ) {}

    public function title(): string
    {
        return 'Dil Yazıları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Translation::class);
    }

    /**
     * Ekranda hangi dil açıksa o dil dosyaya iner.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['locale'];
    }

    public function count(array $filters): int
    {
        return count($this->rows($filters));
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        foreach (array_chunk($this->rows($filters), $size) as $chunk) {
            $handler(new Collection($chunk));
        }
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Bölüm', static fn (array $row): string => (string) $row['section'])->width(22),
            ExportColumn::make('Anahtar', static fn (array $row): string => (string) $row['key'])->width(26),
            ExportColumn::make('Etiket', static fn (array $row): string => (string) $row['label'])->width(22),
            ExportColumn::make('Metin', static fn (array $row): string => (string) $row['value'])->width(40),
            // Kaynak metin: çeviriyi gözden geçiren kişi aslını yanında görmeli.
            ExportColumn::make('Kaynak Metin', static fn (array $row): string => (string) $row['reference'])->width(40),
            ExportColumn::make('Durum', static fn (array $row): string => match (true) {
                (bool) $row['missing']    => 'çevrilmemiş',
                (bool) $row['overridden'] => 'değiştirildi',
                default                   => 'dosyadan',
            })->width(14),
        ];
    }

    /**
     * Bölümlere ayrılmış metinleri düz satırlara açar.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function rows(array $filters): array
    {
        $locale = $this->resolveLocale($filters);

        $sections = $this->translations->groupIntoSections(
            $this->translations->keysFrom(self::GROUP),
            $this->translations->effectiveLines($locale, self::GROUP),
            $this->translations->fileLines($locale, self::GROUP),
            $this->translations->overridesFor($locale, self::GROUP),
        );

        $rows = [];

        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                $rows[] = $row + ['section' => $section['label']];
            }
        }

        return $rows;
    }

    /**
     * Adresteki dil kodu yalnızca yayında olan diller arasından kabul edilir.
     *
     * @param array<string, mixed> $filters
     */
    private function resolveLocale(array $filters): string
    {
        $requested = (string) ($filters['locale'] ?? '');

        return $this->languages->active()->contains('code', $requested)
            ? $requested
            : $this->languages->defaultCode();
    }
}
