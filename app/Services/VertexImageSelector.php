<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VertexGeneration;
use App\Models\VertexPrompt;
use Illuminate\Support\Collection;

final class VertexImageSelector
{
    /**
     * @return VertexGeneration|null
     */
    public function pickByTag(string $tag, string $aspectRatio): ?VertexGeneration
    {
        $promptIds = VertexPrompt::query()
            ->active()
            ->whereJsonContains('tags', $tag)
            ->pluck('id');

        if ($promptIds->isEmpty()) {
            return null;
        }

        return VertexGeneration::query()
            ->completed()
            ->whereIn('prompt_id', $promptIds)
            ->where('aspect_ratio', $aspectRatio)
            ->whereNotNull('output_image_path')
            ->orderBy('usage_count')
            ->inRandomOrder()
            ->first();
    }

    /**
     * @return VertexGeneration|null
     */
    public function pickByPromptName(string $promptName, string $aspectRatio): ?VertexGeneration
    {
        $prompt = VertexPrompt::query()
            ->where('name', 'LIKE', '%' . $promptName . '%')
            ->first();

        if ($prompt === null) {
            return null;
        }

        return VertexGeneration::query()
            ->completed()
            ->where('prompt_id', $prompt->id)
            ->where('aspect_ratio', $aspectRatio)
            ->whereNotNull('output_image_path')
            ->orderBy('usage_count')
            ->inRandomOrder()
            ->first();
    }

    /**
     * @return VertexGeneration|null
     */
    public function pickByKeyword(string $keyword, string $aspectRatio): ?VertexGeneration
    {
        return VertexGeneration::query()
            ->completed()
            ->where('aspect_ratio', $aspectRatio)
            ->whereNotNull('output_image_path')
            ->where('prompt_used', 'LIKE', '%' . $keyword . '%')
            ->orderBy('usage_count')
            ->inRandomOrder()
            ->first();
    }

    /**
     * @return VertexGeneration|null
     */
    public function pickById(int $generationId): ?VertexGeneration
    {
        return VertexGeneration::query()
            ->completed()
            ->whereNotNull('output_image_path')
            ->find($generationId);
    }

    /**
     * @return VertexGeneration|null
     */
    public function pickBest(string $value, string $aspectRatio): ?VertexGeneration
    {
        if (is_numeric($value)) {
            return $this->pickById((int) $value);
        }

        $result = $this->pickByTag($value, $aspectRatio);
        if ($result !== null) {
            return $result;
        }

        $result = $this->pickByPromptName($value, $aspectRatio);
        if ($result !== null) {
            return $result;
        }

        return $this->pickByKeyword($value, $aspectRatio);
    }

    /**
     * @return Collection<int, VertexGeneration>
     */
    public function pickMultiple(string $value, string $aspectRatio, int $count): Collection
    {
        if (is_numeric($value)) {
            $gen = $this->pickById((int) $value);
            return $gen !== null ? collect([$gen]) : collect();
        }

        $promptIds = VertexPrompt::query()
            ->active()
            ->whereJsonContains('tags', $value)
            ->pluck('id');

        $query = VertexGeneration::query()
            ->completed()
            ->where('aspect_ratio', $aspectRatio)
            ->whereNotNull('output_image_path');

        if ($promptIds->isNotEmpty()) {
            $query->whereIn('prompt_id', $promptIds);
        } else {
            $query->where('prompt_used', 'LIKE', '%' . $value . '%');
        }

        return $query->orderBy('usage_count')->inRandomOrder()->limit($count)->get();
    }

    /**
     * @param  list<int>|null  $promptIds
     * @param  list<int>       $excludeIds
     * @return Collection<int, VertexGeneration>
     */
    public function pickLeastUsed(
        string|array $aspectRatio,
        int $count,
        ?string $tag = null,
        array $excludeIds = [],
        ?array $promptIds = null,
    ): Collection {
        $query = VertexGeneration::query()
            ->completed()
            ->whereNotNull('output_image_path');

        if (is_array($aspectRatio)) {
            $query->whereIn('aspect_ratio', $aspectRatio);
        } else {
            $query->where('aspect_ratio', $aspectRatio);
        }

        if ($promptIds !== null && $promptIds !== []) {
            $query->whereIn('prompt_id', $promptIds);
        } elseif ($tag !== null && $tag !== '') {
            $resolved = VertexPrompt::query()
                ->active()
                ->whereJsonContains('tags', $tag)
                ->pluck('id');

            if ($resolved->isEmpty()) {
                return collect();
            }

            $query->whereIn('prompt_id', $resolved);
        }

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->with('prompt:id,name')->orderBy('usage_count')->inRandomOrder()->limit($count)->get();
    }

    /**
     * @return Collection<int, VertexGeneration>
     */
    public function gallery(string $aspectRatio, int $limit = 50, ?int $promptId = null): Collection
    {
        $query = VertexGeneration::query()
            ->completed()
            ->where('aspect_ratio', $aspectRatio)
            ->whereNotNull('output_image_path')
            ->with('prompt')
            ->latest();

        if ($promptId !== null) {
            $query->where('prompt_id', $promptId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * @param  list<int>|null  $promptIds
     */
    public function availableCount(string|array $aspectRatio, ?string $tag = null, ?array $promptIds = null): int
    {
        $query = VertexGeneration::query()
            ->completed()
            ->whereNotNull('output_image_path');

        if (is_array($aspectRatio)) {
            $query->whereIn('aspect_ratio', $aspectRatio);
        } else {
            $query->where('aspect_ratio', $aspectRatio);
        }

        if ($promptIds !== null && $promptIds !== []) {
            $query->whereIn('prompt_id', $promptIds);
        } elseif ($tag !== null && $tag !== '') {
            $resolved = VertexPrompt::query()
                ->active()
                ->whereJsonContains('tags', $tag)
                ->pluck('id');

            if ($resolved->isEmpty()) {
                return 0;
            }

            $query->whereIn('prompt_id', $resolved);
        }

        return $query->count();
    }
}
