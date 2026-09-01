<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Page;
use App\Services\Seo\SeoContentAuditor;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * SEO denetim listesinin dışa aktarma tanımı.
 *
 * Bu listenin arkasında tablo yok: satırlar denetim sonucundan doğuyor ve
 * skor veritabanında durmuyor. Dosya, düzeltme işini panel dışında paylaşmanın
 * yolu — "şu on sayfanın meta açıklaması eksik" listesi bir editöre e-postayla
 * gidebiliyor.
 */
final class SeoAuditExport extends ListExport
{
    public function __construct(
        private readonly SeoContentAuditor $auditor,
    ) {}

    public function title(): string
    {
        return 'SEO Denetimi';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', Page::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->auditor->filterKeys();
    }

    public function count(array $filters): int
    {
        return $this->auditor->paginate(PHP_INT_MAX, $filters)->total();
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        // Denetim zaten bellekte tamamlanıyor (skor SQL ile sıralanamıyor);
        // parçalama yalnız yazıcının beklediği biçimi karşılıyor.
        $rows = $this->auditor->paginate(PHP_INT_MAX, $filters)->items();

        foreach (array_chunk($rows, $size) as $chunk) {
            $handler(new Collection($chunk));
        }
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Puan', static fn (array $row): int => (int) $row['score'])
                ->asNumber()
                ->width(8),
            ExportColumn::make('Başlık', static fn (array $row): string => (string) $row['title'])->width(36),
            ExportColumn::make('Adres', static fn (array $row): string => '/' . $row['slug'])->width(28),
            ExportColumn::make(
                'Tür',
                static fn (array $row): string => $row['type'] === 'page' ? 'Sayfa' : 'Blog Yazısı',
            )->width(14),
            ExportColumn::make('Dil', static fn (array $row): string => strtoupper((string) $row['locale']))->width(8),
            ExportColumn::make('Hata', static fn (array $row): int => (int) $row['counts']['error'])
                ->asNumber()
                ->width(8),
            ExportColumn::make('Uyarı', static fn (array $row): int => (int) $row['counts']['warning'])
                ->asNumber()
                ->width(8),
            ExportColumn::make('Öneri', static fn (array $row): int => (int) $row['counts']['info'])
                ->asNumber()
                ->width(8),
            // Bulgular tek hücrede: dosyayı açan kişi hangi sayfada ne olduğunu
            // ayrı bir listeye bakmadan görmeli.
            ExportColumn::make('Bulgular', static fn (array $row): string => implode(
                ' · ',
                array_map(
                    static fn (array $issue): string => (string) $issue['message'],
                    $row['issues'],
                ),
            ))->width(60),
        ];
    }
}
