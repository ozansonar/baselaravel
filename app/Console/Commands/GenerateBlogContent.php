<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BlogGenerationService;
use Illuminate\Console\Command;

final class GenerateBlogContent extends Command
{
    protected $signature = 'blog:generate';

    protected $description = 'Gemini API ile otomatik blog içeriği oluşturur';

    public function handle(BlogGenerationService $generationService): int
    {
        $this->info('Blog içeriği oluşturuluyor...');

        $result = $generationService->generate();

        if ($result['success']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
