<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Language;
use App\Services\LanguageService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Dil listesinin dışa aktarma tanımı. */
final class LanguageExport extends ListExport
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    public function title(): string
    {
        return 'Diller';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Language::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->languages->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->languages->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        // Arayüz çevirisi ve içerik adedi sütun değil: biri diskten, diğeri
        // dokuz tablodan geliyor. Satır başına yeniden hesaplanmasın diye bir
        // kez okunup kapanışlara veriliyor.
        $translated = $this->languages->translatedLocales();
        $contentCounts = $this->languages->contentCounts();

        return [
            ExportColumn::make('Dil', static fn (Language $language): string => (string) ($language->native_name ?: $language->name))->width(20),
            ExportColumn::make('Ad', static fn (Language $language): string => (string) $language->name)->width(18),
            ExportColumn::make('Kod', static fn (Language $language): string => (string) $language->code)->width(8),
            ExportColumn::make('Durum', static fn (Language $language): string => $language->is_active ? 'Yayında' : 'Pasif')->width(10),
            ExportColumn::make('Varsayılan', static fn (Language $language): string => $language->is_default ? 'Evet' : 'Hayır')->width(10),
            ExportColumn::make(
                'Arayüz Çevirisi',
                static fn (Language $language): string => in_array($language->code, $translated, true) ? 'var' : 'yok',
            )->width(14),
            ExportColumn::make(
                'İçerik',
                static fn (Language $language): int => $contentCounts[$language->code] ?? 0,
            )->asNumber()->width(10),
            ExportColumn::make('Sıra', static fn (Language $language): int => (int) $language->sort_order)
                ->asNumber()
                ->width(8),
        ];
    }
}
