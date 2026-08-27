<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\UploadedFile;
use App\Services\FileManagerService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/** Dosya yöneticisi listesinin dışa aktarma tanımı. */
final class UploadedFileExport extends ListExport
{
    public function __construct(
        private readonly FileManagerService $files,
    ) {}

    public function title(): string
    {
        return 'Dosyalar';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', UploadedFile::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->files->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->files->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Dosya Adı', static fn (UploadedFile $file): string => (string) $file->original_name)->width(34),
            ExportColumn::make('Kategori', static fn (UploadedFile $file): string => $file->categoryLabel())->width(12),
            ExportColumn::make('Boyut', static fn (UploadedFile $file): string => $file->humanSize())->width(12),
            // Adres ekranda kopyala düğmesinin arkasında duruyor; dosyada da
            // olmalı, listenin panel dışındaki asıl işi bu.
            ExportColumn::make('Adres', static fn (UploadedFile $file): string => $file->fullUrl())->width(38),
            ExportColumn::make('Yükleyen', static fn (UploadedFile $file): string => (string) ($file->uploader?->full_name ?? ''))->width(18),
            ExportColumn::make('Tarih', static fn (UploadedFile $file): ?\DateTimeInterface => $file->created_at)
                ->asDateTime()
                ->width(14),
        ];
    }
}
