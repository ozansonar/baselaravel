<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PushNotification;
use App\Services\PushNotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Push duyurularının cron girişi.
 *
 * Birkaç dakikada bir uğrayıp sıradaki duyurunun bir sonraki parçasını
 * gönderiyor. Gönderim isteğin içinde yapılamıyor: paylaşımlı hosting'de
 * alt süreç açılamıyor, `queue:work` çalıştırılamıyor.
 */
final class DispatchPushNotifications extends Command
{
    protected $signature = 'push:dispatch
                            {--notification= : Yalnızca bu duyuruyu işle}';

    protected $description = 'Sıradaki push duyurularını parça parça gönderir';

    public function handle(PushNotificationDispatcher $dispatcher): int
    {
        $id = $this->option('notification');

        if ($id !== null) {
            return $this->single((int) $id, $dispatcher);
        }

        $result = $dispatcher->tick();

        if ($result['processed'] === 0) {
            $this->info('Sırada bekleyen duyuru yok.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d duyuru işlendi: %d cihaza ulaşıldı, %d başarısız.',
            $result['processed'],
            $result['sent'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }

    private function single(int $id, PushNotificationDispatcher $dispatcher): int
    {
        $notification = PushNotification::find($id);

        if ($notification === null) {
            $this->error("Duyuru bulunamadı: {$id}");

            return self::FAILURE;
        }

        $result = $dispatcher->sendBatch($notification);

        $this->info(sprintf(
            '"%s": %d ulaştı, %d başarısız, %d atlandı, %d cihaz sırada.',
            $notification->title,
            $result['sent'],
            $result['failed'],
            $result['skipped'],
            $result['remaining'],
        ));

        return self::SUCCESS;
    }
}
