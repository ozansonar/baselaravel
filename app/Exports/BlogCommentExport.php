<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\CommentStatus;
use App\Models\BlogComment;
use App\Services\BlogCommentService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Yorum listesinin dışa aktarma tanımı. */
final class BlogCommentExport extends ListExport
{
    public function __construct(
        private readonly BlogCommentService $comments,
    ) {}

    public function title(): string
    {
        return 'Yorumlar';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', BlogComment::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->comments->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->comments->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Kişi', static fn (BlogComment $comment): string => (string) $comment->name)->width(18),
            ExportColumn::make('E-posta', static fn (BlogComment $comment): string => (string) $comment->email)->width(24),
            ExportColumn::make('Yazı', static fn (BlogComment $comment): string => (string) ($comment->post?->title ?? ''))->width(26),
            ExportColumn::make('Yorum', static fn (BlogComment $comment): string => (string) $comment->body)->width(40),
            ExportColumn::make('Durum', static fn (BlogComment $comment): string => match (true) {
                $comment->trashed() => 'Silinmiş',
                $comment->status === CommentStatus::Approved => 'Onaylı',
                $comment->status === CommentStatus::Pending  => 'Beklemede',
                $comment->status === CommentStatus::Rejected => 'Reddedildi',
                default => '',
            })->width(12),
            ExportColumn::make('Tarih', static fn (BlogComment $comment): ?\DateTimeInterface => $comment->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
