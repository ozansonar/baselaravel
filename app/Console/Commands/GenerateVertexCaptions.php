<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VertexGeneration;
use App\Services\Vertex\VertexImageService;
use Illuminate\Console\Command;

final class GenerateVertexCaptions extends Command
{
    protected $signature = 'vertex:generate-captions
                            {--limit=50 : Maksimum işlenecek görsel sayısı}
                            {--force : Caption\'ı olanları da yeniden üret}';

    protected $description = 'Tüm tamamlanmış Vertex görselleri için Gemini ile caption/title/hashtag üretir';

    public function handle(VertexImageService $service): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $query = VertexGeneration::query()
            ->select(['id', 'output_image_path', 'ig_caption', 'ig_title', 'ig_hashtags', 'aspect_ratio', 'status'])
            ->where('status', VertexGeneration::STATUS_COMPLETED)
            ->whereNotNull('output_image_path');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('ig_caption')->orWhere('ig_caption', '')
                    ->orWhereNull('ig_title')->orWhere('ig_title', '')
                    ->orWhereNull('ig_hashtags')->orWhere('ig_hashtags', '');
            });
        }

        $total = $query->count();
        $processLimit = min($total, $limit);

        if ($processLimit === 0) {
            $this->info('Caption üretilecek görsel bulunamadı.');
            return self::SUCCESS;
        }

        $this->info("Toplam {$processLimit} görsel için caption üretilecek...");

        $bar = $this->output->createProgressBar($processLimit);
        $bar->start();

        $success = 0;
        $failed = 0;
        $processed = 0;

        $query->orderBy('id', 'desc')->chunk(10, function ($generations) use ($service, $force, &$success, &$failed, &$processed, $processLimit, $bar) {
            foreach ($generations as $generation) {
                if ($processed >= $processLimit) {
                    return false;
                }

                $captionData = $service->generateCaptionFromImage($generation->output_image_path);

                if ($captionData !== null) {
                    $filtered = array_filter($captionData);
                    if (! $force) {
                        $filtered = array_filter($filtered, static function ($val, $key) use ($generation): bool {
                            return empty($generation->{$key});
                        }, ARRAY_FILTER_USE_BOTH);
                    }
                    if ($filtered !== []) {
                        $generation->update($filtered);
                    }
                    $success++;
                } else {
                    $failed++;
                }

                $processed++;
                $bar->advance();
                usleep(500_000);
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Tamamlandı: {$success} başarılı, {$failed} başarısız.");

        return self::SUCCESS;
    }
}
