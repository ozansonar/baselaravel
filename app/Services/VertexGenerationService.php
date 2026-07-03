<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VertexGeneration;
use Illuminate\Support\Facades\DB;

final class VertexGenerationService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    public function destroy(VertexGeneration $generation): void
    {
        if ($generation->output_image_path) {
            $this->uploadService->deleteImage($generation->output_image_path);
        }
        $generation->delete();
    }

    /**
     * @param  array<int>  $ids
     */
    public function bulkDestroy(array $ids): int
    {
        $generations = VertexGeneration::whereIn('id', $ids)->get();

        return DB::transaction(function () use ($generations): int {
            $count = 0;
            foreach ($generations as $generation) {
                if ($generation->output_image_path) {
                    $this->uploadService->deleteImage($generation->output_image_path);
                }
                $generation->delete();
                $count++;
            }

            return $count;
        });
    }
}
