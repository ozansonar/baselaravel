<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Bir kampanyanın alıcı listesinin dışa aktarma tanımı.
 *
 * Hangi kampanya olduğu adres satırındaki campaign değerinden geliyor; ekranda
 * da liste her kampanyanın kendi sayfasında duruyor.
 */
final class CampaignRecipientExport extends ListExport
{
    public function __construct(
        private readonly CampaignService $campaigns,
    ) {}

    public function title(): string
    {
        return 'Kampanya Alıcıları';
    }

    public function authorize(): void
    {
        Gate::authorize('view', $this->campaign());
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return array_merge(['campaign'], $this->campaigns->recipientFilterKeys());
    }

    public function query(array $filters): Builder
    {
        return $this->campaigns->recipientQuery($this->campaign(), $filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('E-posta', static fn (CampaignRecipient $recipient): string => (string) $recipient->email)->width(28),
            ExportColumn::make('Ad', static fn (CampaignRecipient $recipient): string => (string) $recipient->first_name)->width(16),
            ExportColumn::make('Soyad', static fn (CampaignRecipient $recipient): string => (string) $recipient->last_name)->width(16),
            ExportColumn::make('Durum', static fn (CampaignRecipient $recipient): string => $recipient->status?->label() ?? '')->width(14),
            ExportColumn::make('Deneme', static fn (CampaignRecipient $recipient): int => (int) $recipient->attempts)
                ->asNumber()
                ->width(9),
            ExportColumn::make('Gönderim', static fn (CampaignRecipient $recipient): ?\DateTimeInterface => $recipient->sent_at)
                ->asDateTime()
                ->width(14),
            // Gitmeyen adresin nedeni listenin yanında dursun; CSV indirmesi de
            // bu sütunu taşıyor.
            ExportColumn::make('Hata', static fn (CampaignRecipient $recipient): string => (string) $recipient->error)->width(30),
        ];
    }

    /**
     * Adres satırındaki kampanya.
     *
     * Kampanya belirtilmeden bu liste anlamlı değil: hangi gönderimin alıcıları
     * olduğu bilinmeden dosya da okunamaz.
     */
    private function campaign(): Campaign
    {
        $id = (int) request()->query('campaign');

        $campaign = $id > 0 ? Campaign::find($id) : null;

        if ($campaign === null) {
            throw new NotFoundHttpException('Dışa aktarılacak kampanya bulunamadı.');
        }

        return $campaign;
    }
}
