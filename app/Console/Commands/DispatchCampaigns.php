<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CampaignDispatcher;
use Illuminate\Console\Command;

/**
 * The cron entry point for bulk mail.
 *
 * Runs every few minutes and sends only what the hourly limit still allows, so
 * a campaign trickles out across the hour instead of arriving as one burst the
 * mail host is likely to throttle.
 */
final class DispatchCampaigns extends Command
{
    protected $signature = 'campaigns:dispatch
                            {--campaign= : Yalnızca bu kampanyayı işle}';

    protected $description = 'Sıradaki kampanya maillerini saatlik limite göre gönderir';

    public function handle(CampaignDispatcher $dispatcher): int
    {
        $limit = $dispatcher->hourlyLimit();

        if ($limit < 1) {
            $this->warn('Saatlik gönderim limiti 0 — gönderim yapılmadı.');

            return self::SUCCESS;
        }

        $campaignId = $this->option('campaign');

        if ($campaignId !== null) {
            return $this->single((int) $campaignId, $dispatcher);
        }

        $result = $dispatcher->tick();

        $this->info(sprintf(
            '%d gönderildi, %d başarısız, %d tekrar denenecek. Bu saat için kalan kota: %d/%d. Bu turda %d kampanya işlendi.',
            $result['sent'],
            $result['failed'],
            $result['retrying'],
            $result['budget'],
            $limit,
            $result['campaigns'],
        ));

        return self::SUCCESS;
    }

    private function single(int $campaignId, CampaignDispatcher $dispatcher): int
    {
        $campaign = \App\Models\Campaign::find($campaignId);

        if ($campaign === null) {
            $this->error("Kampanya bulunamadı: {$campaignId}");

            return self::FAILURE;
        }

        $result = $dispatcher->sendBatch($campaign);

        $this->info(sprintf(
            '"%s": %d gönderildi, %d başarısız, %d kişi sırada.',
            $campaign->name,
            $result['sent'],
            $result['failed'],
            $campaign->refresh()->pendingCount(),
        ));

        return self::SUCCESS;
    }
}
