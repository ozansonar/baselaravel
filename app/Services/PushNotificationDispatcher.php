<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationPreference;
use App\Enums\PushAudience;
use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;
use App\Models\PushToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Panelden yazılan bildirimleri cihazlara ulaştırır.
 *
 * Gönderim isteğin içinde değil, cron turlarında yapılıyor — kampanya
 * gönderimiyle aynı desen ve aynı sebep: paylaşımlı hosting'de alt süreç
 * açılamıyor, `queue:work` çalıştırılamıyor. "Gönder" düğmesi kaydı sıraya
 * alıyor, `push:dispatch` her beş dakikada bir kaldığı yerden devam ediyor.
 *
 * Tek istekte göndermek de mümkündü ama beş yüz cihazlı bir kurulumda o istek
 * beş yüz HTTP çağrısı demek: tarayıcı zaman aşımına düşüyor, yönetici
 * gönderimin olup olmadığını bilmiyor ve düğmeye bir kez daha basıyor.
 */
final class PushNotificationDispatcher
{
    /** Cron turları arası dakika — routes/console.php ile aynı olmalı. */
    public const RUN_INTERVAL_MINUTES = 5;

    /** Tek turda denenecek en fazla cihaz. */
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly PushNotificationService $push,
    ) {}

    /**
     * Cron turu: bekleyen bildirimleri ilerletir.
     *
     * @return array{processed: int, sent: int, failed: int}
     */
    public function tick(): array
    {
        $ozet = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        /** @var PushNotification|null $bildirim */
        $bildirim = PushNotification::query()->pending()->first();

        if ($bildirim === null) {
            return $ozet;
        }

        $sonuc = $this->sendBatch($bildirim);

        $ozet['processed'] = 1;
        $ozet['sent'] = $sonuc['sent'];
        $ozet['failed'] = $sonuc['failed'];

        return $ozet;
    }

    /**
     * Bir bildirimin sıradaki parçasını gönderir.
     *
     * @return array{sent: int, failed: int, skipped: int, remaining: int}
     */
    public function sendBatch(PushNotification $bildirim): array
    {
        if ($bildirim->status === PushNotificationStatus::Queued) {
            $bildirim->forceFill([
                'status'        => PushNotificationStatus::Sending,
                'started_at'    => now(),
                'total_devices' => $this->tokenQuery($bildirim)->count(),
            ])->save();
        }

        // Hedefte hiç cihaz yok: gönderilecek bir şey olmadığını söylemek,
        // sonsuza kadar "gönderiliyor" görünmekten iyi.
        if ($bildirim->total_devices === 0) {
            $this->finish($bildirim, 'Hedefte bildirime izin vermiş cihaz yok.');

            return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => 0];
        }

        $tokenlar = $this->tokenQuery($bildirim)
            ->where('push_tokens.id', '>', $bildirim->cursor)
            ->orderBy('push_tokens.id')
            ->limit(self::BATCH_SIZE)
            ->get();

        if ($tokenlar->isEmpty()) {
            $this->finish($bildirim);

            return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => 0];
        }

        $sonuc = $this->push->send(
            $tokenlar,
            $bildirim->title,
            $bildirim->body,
            $bildirim->link !== null ? ['link' => $bildirim->link] : [],
        );

        // İmleç, gönderim başarılı olmasa da ilerliyor: aynı cihaza sonsuza
        // kadar denemek, kalan cihazlara hiç ulaşmamak demek.
        $bildirim->forceFill([
            'cursor'        => (int) $tokenlar->last()->getKey(),
            'sent_count'    => $bildirim->sent_count + $sonuc['sent'],
            'failed_count'  => $bildirim->failed_count + $sonuc['failed'],
            'skipped_count' => $bildirim->skipped_count + $sonuc['skipped'],
        ])->save();

        $kalan = $this->tokenQuery($bildirim)
            ->where('push_tokens.id', '>', $bildirim->cursor)
            ->count();

        if ($kalan === 0) {
            $this->finish($bildirim);
        }

        return [
            'sent'      => $sonuc['sent'],
            'failed'    => $sonuc['failed'],
            'skipped'   => $sonuc['skipped'],
            'remaining' => $kalan,
        ];
    }

    /**
     * Hedefteki cihaz sayısı — form ekranında "kaç kişiye gidecek" bilgisi.
     */
    public function audienceSize(PushAudience $audience, ?int $audienceId = null): int
    {
        $sahte = new PushNotification([
            'audience'    => $audience,
            'audience_id' => $audienceId,
        ]);

        return $this->tokenQuery($sahte)->count();
    }

    /**
     * Hedefin cihaz jetonları.
     *
     * Üç süzgeç birlikte çalışıyor:
     *  - hedef kitle (herkes / rol / tek kullanıcı),
     *  - hesabın açık olması — pasife alınmış kullanıcıya duyuru gitmemeli,
     *  - kullanıcının duyuru bildirimlerini kapatmamış olması.
     *
     * @param  PushNotification $bildirim
     * @return Builder<PushToken>
     */
    private function tokenQuery(PushNotification $bildirim): Builder
    {
        $query = PushToken::query()
            ->whereHas('user', function (Builder $user) use ($bildirim): void {
                $user->where('is_active', true);

                if ($bildirim->audience === PushAudience::User) {
                    $user->whereKey($bildirim->audience_id);
                }

                if ($bildirim->audience === PushAudience::Role) {
                    $user->whereHas('roles', fn (Builder $rol) => $rol->whereKey($bildirim->audience_id));
                }
            });

        // Duyuruyu kapatmış kullanıcılar dışarıda. Tercih kaydı olmayan
        // kullanıcı açık sayılıyor: varsayılan kapalı olsaydı özellik,
        // varlığından haberi olmayan kimseye ulaşmazdı.
        return $query->whereDoesntHave('user.notificationPreferences', function (Builder $tercih): void {
            $tercih->where('type', NotificationPreference::PushAnnouncements->value)
                ->where('enabled', false);
        });
    }

    /**
     * Gönderimi kapatır.
     */
    private function finish(PushNotification $bildirim, ?string $hata = null): void
    {
        $bildirim->forceFill([
            'status'       => $hata === null ? PushNotificationStatus::Sent : PushNotificationStatus::Failed,
            'completed_at' => now(),
            'last_error'   => $hata,
        ])->save();

        if ($hata !== null) {
            Log::info('Push bildirimi gönderilemedi', [
                'notification' => $bildirim->getKey(),
                'reason'       => $hata,
            ]);
        }
    }
}
