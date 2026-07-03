<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VertexScheduleService;
use Illuminate\Console\Command;

final class ProcessVertexSchedules extends Command
{
    protected $signature = 'vertex:process-schedules';

    protected $description = 'Zamanı gelen Vertex zamanlamalarını çalıştır ve batch oluştur.';

    public function handle(VertexScheduleService $service): int
    {
        $result = $service->processDueSchedules();

        if ($result['processed'] > 0 || $result['errors'] > 0) {
            $this->info(sprintf(
                'Zamanlama: %d işlendi, %d atlandı, %d hata.',
                $result['processed'],
                $result['skipped'],
                $result['errors'],
            ));
        }

        return self::SUCCESS;
    }
}
