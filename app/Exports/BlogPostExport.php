<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\ContentStatus;
use App\Models\BlogPost;
use App\Services\BlogService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** İçerik (blog yazısı) listesinin dışa aktarma tanımı. */
final class BlogPostExport extends ListExport
{
    public function __construct(
        private readonly BlogService $posts,
    ) {}

    public function title(): string
    {
        return 'İçerikler';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', BlogPost::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->posts->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->posts->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Başlık', static fn (BlogPost $post): string => (string) $post->title)->width(32),
            ExportColumn::make('Kategori', static fn (BlogPost $post): string => (string) ($post->category?->name ?? ''))->width(16),
            ExportColumn::make('Yazar', static fn (BlogPost $post): string => (string) ($post->author?->full_name ?? ''))->width(16),
            // Ekrandaki rozetle aynı ayrım: "zamanlanmış" ayrı bir durum değil,
            // tarihi henüz gelmemiş yayın kaydı.
            ExportColumn::make('Durum', static fn (BlogPost $post): string => match (true) {
                $post->trashed() => 'Silinmiş',
                $post->status === ContentStatus::Published && $post->published_at?->lte(now()) => 'Yayında',
                $post->status === ContentStatus::Published => 'Zamanlanmış',
                $post->status === ContentStatus::Archived  => 'Arşivlendi',
                default => 'Taslak',
            })->width(12),
            ExportColumn::make('Görüntülenme', static fn (BlogPost $post): int => (int) $post->views)
                ->asNumber()
                ->width(12),
            ExportColumn::make('Tarih', static fn (BlogPost $post): ?\DateTimeInterface => $post->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
