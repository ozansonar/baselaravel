<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AiImagePrompt;
use App\Services\UploadService;

/**
 * Template silindiğinde referans görsel dosyasını da temizler.
 * Soft delete'te dosya kalır (restore edilirse tekrar kullanılsın); force delete'te silinir.
 */
final class AiImagePromptObserver
{
    public function __construct(private readonly UploadService $uploadService) {}

    public function deleted(AiImagePrompt $prompt): void
    {
        // Soft delete — dosya kalır
        if ($prompt->isForceDeleting()) {
            $this->cleanupReferenceImage($prompt);
        }
    }

    public function forceDeleted(AiImagePrompt $prompt): void
    {
        $this->cleanupReferenceImage($prompt);
    }

    private function cleanupReferenceImage(AiImagePrompt $prompt): void
    {
        $path = (string) $prompt->reference_image_path;
        if ($path === '') {
            return;
        }

        try {
            // deleteFile (uploadFile ile yüklenen tek dosya); deleteImage responsive
            // varyantları arar — bizde varyant yok, yanlış metod fail olabilir
            $this->uploadService->deleteFile($path);
        } catch (\Throwable) {
            // Sessizce geç — silinemediyse de model silinmeli
        }
    }
}
