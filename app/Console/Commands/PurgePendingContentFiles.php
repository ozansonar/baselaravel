<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ContentFileService;
use Illuminate\Console\Command;

/**
 * İçeriğe bağlanmadan kalan ekleri temizler (blog yazısı ve sayfa).
 *
 * Henüz çevirisi olmayan bir dil sekmesinde ek, içerik kaydedilmeden önce
 * yükleniyor: kullanıcı dosyaları bırakıp formu kaydetmeden çıkarsa hem satır
 * hem dosya ortada kalıyor. Kimse fark etmediği için temizliği cron yapıyor;
 * taze bekleyenlere dokunulmuyor, kullanıcı hâlâ formda olabilir.
 */
final class PurgePendingContentFiles extends Command
{
    protected $signature = 'content-files:purge
                            {--hours=24 : Bu saatten eski bekleyen ekler silinir}';

    protected $description = 'İçeriğe bağlanmadan kalan ekleri diskten ve veritabanından siler';

    public function handle(ContentFileService $files): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $removed = $files->purgeStalePending($hours);

        $this->info($removed . ' bekleyen içerik eki temizlendi.');

        return self::SUCCESS;
    }
}
