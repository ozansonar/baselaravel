<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\VertexPrompt;
use App\Services\SpecialDayVertexService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class GenerateSpecialDayImages extends Command
{
    protected $signature = 'vertex:generate-special-days
        {--month= : Ay numarası (1-12)}
        {--year= : Yıl (varsayılan: bu yıl)}
        {--feed-prompt= : Feed şablon ID}
        {--story-prompt= : Story şablon ID}
        {--count=2 : Gün başına görsel sayısı}';

    protected $description = 'Özel günler için Vertex AI ile toplu görsel üretim batch\'leri oluştur.';

    public function handle(SpecialDayVertexService $service): int
    {
        if (trim((string) Setting::getValue('vertex_api_key', '')) === '') {
            $this->error('Vertex API Key tanımlı değil. Admin → Ayarlar → AI bölümünden ekleyin.');
            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?? now()->year);
        $month = $this->option('month');

        if ($month !== null) {
            $from = Carbon::create($year, (int) $month, 1)->startOfMonth();
            $to = $from->copy()->endOfMonth();
        } else {
            $from = Carbon::create($year, 1, 1)->startOfYear();
            $to = $from->copy()->endOfYear();
        }

        $feedPromptId = $this->option('feed-prompt');
        $storyPromptId = $this->option('story-prompt');

        if ($feedPromptId === null || $storyPromptId === null) {
            $prompts = VertexPrompt::active()->orderBy('sort_order')->get(['id', 'name']);
            if ($prompts->isEmpty()) {
                $this->error('Aktif Vertex prompt şablonu bulunamadı.');
                return self::FAILURE;
            }

            $this->table(['ID', 'Ad'], $prompts->map(fn ($p) => [$p->id, $p->name])->toArray());

            if ($feedPromptId === null) {
                $feedPromptId = $this->ask('Feed (1:1) şablon ID seçin');
            }
            if ($storyPromptId === null) {
                $storyPromptId = $this->ask('Story (9:16) şablon ID seçin');
            }
        }

        $feedPrompt = VertexPrompt::find((int) $feedPromptId);
        $storyPrompt = VertexPrompt::find((int) $storyPromptId);

        if ($feedPrompt === null || $storyPrompt === null) {
            $this->error('Geçersiz şablon ID.');
            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));

        $this->info(sprintf(
            'Dönem: %s — %s | Feed: %s | Story: %s | Adet/gün: %d',
            $from->format('d.m.Y'),
            $to->format('d.m.Y'),
            $feedPrompt->name,
            $storyPrompt->name,
            $count,
        ));

        $result = $service->generateForPeriod(
            from: $from,
            to: $to,
            feedPrompt: $feedPrompt,
            storyPrompt: $storyPrompt,
            countPerDay: $count,
            userId: null,
        );

        $this->info(sprintf(
            '✓ %d özel gün işlendi, %d batch oluşturuldu, %d tanesi atlandı (zaten mevcut).',
            $result['days_processed'],
            $result['batches_created'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
