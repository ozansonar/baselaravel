<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiGeneratedImage;
use App\Services\Ai\AiImageGenerator;
use App\Models\Product;

/**
 * Instagram-flavored wrapper around the unified `AiImageGenerator`.
 *
 * The Instagram surface only owns the prompt/aspect-ratio helpers — the actual
 * image generation (HTTP, fallback chain, logging, cost) lives in the base.
 *
 * Default aspect ratios per media type:
 *   - Feed (image)  → 1:1   (Instagram feed range 4:5..1.91:1)
 *   - Reels / Story → 9:16
 */
final class InstagramImageAiService
{
    /** @var array<string, string> */
    private const array DEFAULT_ASPECT_RATIO = [
        'image' => '1:1',
        'reels' => '9:16',
        'story' => '9:16',
    ];

    public function __construct(
        private readonly AiImageGenerator $generator,
    ) {}

    public function defaultAspectRatio(string $mediaType): string
    {
        return self::DEFAULT_ASPECT_RATIO[$mediaType] ?? '1:1';
    }

    /**
     * Generate an Instagram-bound image.
     *
     * @return array{success: bool, message: string, image_path?: string, url?: string, model_used?: string, cost_usd?: ?float, width?: ?int, height?: ?int}
     */
    public function generate(string $prompt, string $aspectRatio, string $folder = 'instagram', ?string $slugSuffix = null, string $source = AiGeneratedImage::SOURCE_INSTAGRAM_CREATE): array
    {
        $result = $this->generator->generate(
            prompt: $prompt,
            aspectRatio: $aspectRatio,
            folder: $folder,
            slugSuffix: $slugSuffix,
            source: $source,
        );

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => $result['message'],
            ];
        }

        return [
            'success'    => true,
            'message'    => $result['message'],
            'image_path' => (string) $result['image_path'],
            'url'        => (string) $result['url'],
            'model_used' => (string) $result['model_used'],
            'cost_usd'   => $result['cost_usd'],
            'width'      => $result['width'],
            'height'     => $result['height'],
        ];
    }

    /**
     * Build a context-aware prompt for product or free-topic posts.
     */
    public function buildSuggestedPrompt(?Product $product, ?string $topic, string $mediaType): string
    {
        $stylePrefix = match ($mediaType) {
            'reels' => 'Cinematic 9:16 vertical video frame, dynamic farm scene,',
            'story' => 'Vertical 9:16 quick-share farm photo, candid composition,',
            default => 'Square format, professional product photography,',
        };

        $subject = $product
            ? "Hero shot of {$product->name} from a Turkish village farm."
            : ($topic ?? 'Authentic Turkish village farm scene');

        return "{$stylePrefix} {$subject} Rustic countryside aesthetic, golden hour natural lighting, "
            . 'shallow depth of field, warm earthy tones, high detail, no text overlay.';
    }
}
