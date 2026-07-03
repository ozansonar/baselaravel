<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VertexPrompt;
use App\Models\VertexPromptVersion;
use Illuminate\Support\Facades\DB;

final class VertexPromptService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    public function createVersion(VertexPrompt $prompt, ?int $userId): VertexPromptVersion
    {
        $nextVersion = (int) $prompt->versions()->max('version_number') + 1;

        return VertexPromptVersion::create([
            'vertex_prompt_id' => $prompt->id,
            'version_number'   => $nextVersion,
            'data'             => $prompt->snapshotData(),
            'created_by'       => $userId,
            'created_at'       => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(VertexPrompt $prompt, array $data, ?int $userId): void
    {
        DB::transaction(function () use ($prompt, $data, $userId): void {
            $this->createVersion($prompt, $userId);
            $prompt->update($data);
        });
    }

    public function restoreVersion(VertexPrompt $prompt, VertexPromptVersion $version, ?int $userId): void
    {
        DB::transaction(function () use ($prompt, $version, $userId): void {
            $this->createVersion($prompt, $userId);

            $restoreData = $version->data;
            unset($restoreData['sort_order'], $restoreData['is_active'], $restoreData['is_pinned']);

            $prompt->update($restoreData);
        });
    }

    /**
     * @param  array<string, mixed>                                    $data
     * @param  array<int, \Illuminate\Http\UploadedFile>|null          $uploadedFiles
     */
    public function store(array $data, ?array $uploadedFiles = null): VertexPrompt
    {
        $data['sort_order'] = (int) (VertexPrompt::max('sort_order') ?? 0) + 10;

        $images = $this->processUploadedFiles($uploadedFiles, $data['name']);
        $data['reference_images'] = $images !== [] ? $images : null;

        unset($data['reference_images_remove']);

        return VertexPrompt::create($data);
    }

    /**
     * @param  array<string, mixed>                                    $data
     * @param  array<int>                                              $removeIndices
     * @param  array<int, \Illuminate\Http\UploadedFile>|null          $uploadedFiles
     */
    public function updateWithImages(VertexPrompt $prompt, array $data, array $removeIndices, ?int $userId, ?array $uploadedFiles = null): void
    {
        DB::transaction(function () use ($prompt, $data, $removeIndices, $userId, $uploadedFiles): void {
            $this->createVersion($prompt, $userId);

            $existing = is_array($prompt->reference_images) ? $prompt->reference_images : [];
            if ($removeIndices !== []) {
                foreach ($removeIndices as $idx) {
                    if (isset($existing[$idx]['path'])) {
                        $this->uploadService->deleteFile($existing[$idx]['path']);
                        unset($existing[$idx]);
                    }
                }
                $existing = array_values($existing);
            }

            $newImages = $this->processUploadedFiles($uploadedFiles, $data['name'] ?? $prompt->name);
            $existing = array_merge($existing, $newImages);

            $data['reference_images'] = $existing !== [] ? $existing : null;

            $prompt->update($data);
        });
    }

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile>|null  $files
     * @return array<int, array{path: string, mime: string}>
     */
    private function processUploadedFiles(?array $files, string $name): array
    {
        $images = [];
        if ($files === null) {
            return $images;
        }

        foreach ($files as $file) {
            if ($file === null || ! $file->isValid()) {
                continue;
            }
            $mime = $file->getMimeType();
            $path = $this->uploadService->uploadFile($file, 'vertex-prompts', $name);
            $images[] = ['path' => $path, 'mime' => $mime ?: 'image/png'];
        }

        return $images;
    }

    public function togglePin(VertexPrompt $prompt): bool
    {
        $prompt->update(['is_pinned' => ! $prompt->is_pinned]);

        return $prompt->is_pinned;
    }

    public function destroy(VertexPrompt $prompt): void
    {
        if (is_array($prompt->reference_images)) {
            foreach ($prompt->reference_images as $img) {
                if (! empty($img['path'])) {
                    $this->uploadService->deleteFile($img['path']);
                }
            }
        }
        $prompt->delete();
    }
}
