<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

/**
 * Kampanyaya bağlanmadan kalan ekleri temizler.
 *
 * Ek, kampanya kaydedilmeden önce yükleniyor: kullanıcı dosyaları seçip formu
 * kaydetmeden çıkarsa hem satır hem dosya ortada kalıyor. Kimse fark etmediği
 * için temizliği cron yapıyor; taze bekleyenlere dokunulmuyor, kullanıcı hâlâ
 * formda olabilir.
 */
final class PurgePendingAttachments extends Command
{
    protected $signature = 'campaigns:purge-attachments
                            {--hours=24 : Bu saatten eski bekleyen ekler silinir}';

    protected $description = 'Kampanyaya bağlanmadan kalan ekleri diskten ve veritabanından siler';

    public function handle(CampaignService $campaigns): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $silinen = $campaigns->purgeStalePendingAttachments($hours);

        $this->info($silinen . ' bekleyen ek temizlendi.');

        return self::SUCCESS;
    }
}
