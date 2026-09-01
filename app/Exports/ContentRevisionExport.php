<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\BlogPost;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Bir içeriğin bir dilindeki sürüm geçmişinin dışa aktarma tanımı.
 *
 * Hangi içerik olduğu adres satırındaki `type` ve `id` değerlerinden geliyor;
 * ekranda da liste her içeriğin kendi sayfasında duruyor. Tür serbest değil:
 * ekrandaki haritanın aynısı burada da sabit.
 */
final class ContentRevisionExport extends ListExport
{
    /** @var array<string, class-string<Model>> */
    private const TYPES = [
        'sayfa' => Page::class,
        'blog'  => BlogPost::class,
    ];

    public function title(): string
    {
        return 'Sürüm Geçmişi';
    }

    public function authorize(): void
    {
        // Geçmişi görmek içeriği düzenleyebilmeye bağlı: ekranın kendisi de
        // aynı yetkiyi istiyor.
        Gate::authorize('update', $this->target());
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return ['type', 'id'];
    }

    public function query(array $filters): Builder
    {
        return ContentRevision::query()
            ->with('author')
            ->forTarget($this->target());
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Tarih', static fn (ContentRevision $r): ?\DateTimeInterface => $r->created_at)
                ->asDateTime()
                ->width(16),
            ExportColumn::make('Kaydeden', static fn (ContentRevision $r): string => $r->author?->full_name ?? 'Sistem')->width(20),
            ExportColumn::make('Başlık', static fn (ContentRevision $r): string => (string) $r->value('title'))->width(30),
            ExportColumn::make('Adres', static fn (ContentRevision $r): string => (string) $r->value('slug'))->width(24),
            ExportColumn::make('Durum', static fn (ContentRevision $r): string => (string) $r->value('status'))->width(12),
            // İçerik etiketlerinden arındırılıp kırpılıyor: dosyanın işi neyin
            // değiştiğini göstermek, sayfayı yeniden üretmek değil.
            ExportColumn::make('Özet', static fn (ContentRevision $r): string => Str::limit(
                trim(strip_tags((string) ($r->value('excerpt') ?? ''))),
                180,
            ))->width(40),
            ExportColumn::make('İçerik', static fn (ContentRevision $r): string => Str::limit(
                trim(strip_tags((string) ($r->value('content') ?? $r->value('body') ?? ''))),
                500,
            ))->width(60),
        ];
    }

    /**
     * Adres satırındaki içerik.
     *
     * İçerik belirtilmeden bu liste anlamlı değil: hangi sayfanın geçmişi
     * olduğu bilinmeden dosya da okunamaz.
     */
    private function target(): Model
    {
        $type = (string) request()->query('type');
        $id = (int) request()->query('id');

        $class = self::TYPES[$type] ?? null;

        $target = ($class !== null && $id > 0) ? $class::query()->find($id) : null;

        if ($target === null) {
            throw new NotFoundHttpException('Dışa aktarılacak içerik bulunamadı.');
        }

        return $target;
    }
}
