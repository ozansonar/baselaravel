<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\MailTemplate;
use App\Services\MailTemplateService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Mail şablonları listesinin dışa aktarma tanımı.
 *
 * Bu liste tümüyle sorguya çevrilemiyor: değişken ve köken süzgeçleri JSON
 * sütununa ve varsayılan içerikle karşılaştırmaya bakıyor. Bu yüzden satır
 * kaynağı sorgu değil, servisin süzülmüş koleksiyonu. Şablon sayısı onlarla
 * ölçüldüğü için bunun bellek maliyeti yok.
 */
final class MailTemplateExport extends ListExport
{
    public function __construct(
        private readonly MailTemplateService $templates,
    ) {}

    public function title(): string
    {
        return 'Mail Şablonları';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', MailTemplate::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->templates->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->templates->query($filters);
    }

    public function count(array $filters): int
    {
        return $this->templates->filter($filters)->count();
    }

    public function eachChunk(array $filters, int $size, callable $handler): void
    {
        foreach ($this->templates->filter($filters)->chunk($size) as $chunk) {
            $handler($chunk);
        }
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Şablon', static fn (MailTemplate $template): string => (string) $template->name)->width(24),
            ExportColumn::make('Anahtar', static fn (MailTemplate $template): string => (string) $template->key)->width(22),
            ExportColumn::make('Konu', static fn (MailTemplate $template): string => (string) $template->subject)->width(30),
            ExportColumn::make('Açıklama', static fn (MailTemplate $template): string => (string) $template->description)->width(30),
            ExportColumn::make('Durum', static fn (MailTemplate $template): string => $template->is_active ? 'Aktif' : 'Pasif')->width(10),
            ExportColumn::make('Güncelleme', static fn (MailTemplate $template): ?\DateTimeInterface => $template->updated_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
