<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BlogPost;
use App\Services\ContentListService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use stdClass;

/**
 * Genel içerik listesinin dışa aktarma tanımı.
 *
 * Arkasında tek bir tablo yok: blog, sayfa, galeri ve SSS dört ayrı tablodan
 * birleştiriliyor (UNION). Bu yüzden Eloquent sorgusu yerine servisin ortak
 * biçime indirgediği satırlar akıtılıyor — ekranın okuduğu sorgunun aynısı.
 */
final class ContentListExport extends ListExport
{
    public function __construct(
        private readonly ContentListService $contents,
    ) {}

    public function title(): string
    {
        return 'İçerik Listesi';
    }

    public function authorize(): void
    {
        // Ekranla aynı kapı: liste yalnız başlık ve tarih taşıyor, kaydın
        // kendisine gitmek isteyen zaten o ekranın yetkisinden geçiyor.
        Gate::authorize('viewAny', BlogPost::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->contents->filterKeys();
    }

    public function count(array $filters): int
    {
        return $this->contents->count($filters);
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        $this->contents->rows($filters)->chunk($size, static function ($rows) use ($handler): void {
            $handler(new Collection($rows->all()));
        });
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make(
                'Tür',
                static fn (stdClass $row): string => ContentListService::TYPES[$row->type] ?? (string) $row->type,
            )->width(14),
            ExportColumn::make('Başlık', static fn (stdClass $row): string => (string) $row->title)->width(38),
            ExportColumn::make('Dil', static fn (stdClass $row): string => strtoupper((string) $row->locale))->width(8),
            ExportColumn::make(
                'Durum',
                static fn (stdClass $row): string => $row->status === 'published' ? 'Yayında' : 'Taslak',
            )->width(10),
            ExportColumn::make(
                'Oluşturulma',
                static fn (stdClass $row): ?\DateTimeInterface => self::toDate($row->created_at),
            )->asDateTime()->width(16),
            ExportColumn::make(
                'Güncelleme',
                static fn (stdClass $row): ?\DateTimeInterface => self::toDate($row->updated_at),
            )->asDateTime()->width(16),
        ];
    }

    /**
     * Birleşim sorgusu ham satır döndürüyor: tarihler Eloquent'ten geçmediği
     * için metin hâlinde geliyor ve elle çevrilmeleri gerekiyor. Okunamayan
     * değer tarih sütununa metin olarak sızmasın diye null'a düşüyor.
     */
    private static function toDate(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
