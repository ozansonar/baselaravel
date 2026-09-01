<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PushAudience;
use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;
use App\Models\PushToken;
use App\Support\LikeSearch;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Panelden gönderilen duyuruların iş mantığı.
 *
 * Gönderimin kendisi {@see PushNotificationDispatcher}'da; burası "hangi
 * kayıtlar listelenecek", "yeni duyuru nasıl kurulacak", "iptal edilebilir mi"
 * sorularının yeri. Ayrım, denetleyicinin ikisini de bilmek zorunda kalmaması
 * için: ekran kaydı kuruyor, cron gönderiyor.
 */
final class PushBroadcastService
{
    /**
     * Listede süzgeç olarak kabul edilen anahtarlar.
     *
     * Dışa aktarma da bu listeyi kullanıyor: dosyaya inen kayıtlar ekranda
     * görünenle birebir aynı olsun.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['search', 'status', 'audience', 'from', 'to', 'sort'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış duyuru sorgusu.
     *
     * @param  array<string, mixed> $filters
     * @return Builder<PushNotification>
     */
    public function query(array $filters = []): Builder
    {
        $query = PushNotification::query()->with('sender');

        if (($status = PushNotificationStatus::tryFrom((string) ($filters['status'] ?? ''))) !== null) {
            $query->where('status', $status);
        }

        if (($audience = PushAudience::tryFrom((string) ($filters['audience'] ?? ''))) !== null) {
            $query->where('audience', $audience);
        }

        if (($filters['search'] ?? '') !== '') {
            // Joker karakterler düz metin sayılıyor: "%" yazan biri süzgeç
            // yaptığını sanarak tüm listeye bakmamalı.
            $term = LikeSearch::term((string) $filters['search']);

            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw(LikeSearch::clause('title'), [$term])
                    ->orWhereRaw(LikeSearch::clause('body'), [$term]);
            });
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        // Sıralama seçenekleri sabit: istekten gelen değer doğrudan sütun adı
        // olarak sorguya giremiyor.
        match ($filters['sort'] ?? '') {
            'oldest'  => $query->oldest('id'),
            'title'   => $query->orderBy('title'),
            'devices' => $query->orderByDesc('total_devices')->orderByDesc('id'),
            'sent'    => $query->orderByDesc('sent_count')->orderByDesc('id'),
            default   => $query->latest('id'),
        };

        return $query;
    }

    /**
     * Liste başındaki sayaçlar.
     *
     * Tek sorguda toplanıyor: dört ayrı `count()` aynı tabloyu dört kez
     * taramak demekti.
     *
     * @return array{total: int, pending: int, sent: int, devices: int}
     */
    public function stats(): array
    {
        $row = PushNotification::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending', [
                PushNotificationStatus::Queued->value,
                PushNotificationStatus::Sending->value,
            ])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent', [
                PushNotificationStatus::Sent->value,
            ])
            ->selectRaw('SUM(sent_count) as devices')
            ->first();

        return [
            'total'   => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'sent'    => (int) ($row->sent ?? 0),
            'devices' => (int) ($row->devices ?? 0),
        ];
    }

    /**
     * Bildirim kaydı olan cihaz sayısı — ekranda "kaç cihaz kayıtlı" bilgisi.
     */
    public function registeredDevices(): int
    {
        return PushToken::query()->count();
    }

    /**
     * Yeni duyuruyu sıraya alır.
     *
     * Kayıt doğrudan `queued` doğuyor: taslak durumu yok, çünkü duyuru kısa
     * bir metin ve yarım bırakılanı saklamanın karşılığı listede gönderilmemiş
     * satır taşımak olurdu.
     *
     * @param array{title: string, body: string, link: ?string, audience: PushAudience, audience_id: ?int, user_id: ?int} $data
     */
    public function create(array $data): PushNotification
    {
        return DB::transaction(static fn (): PushNotification => PushNotification::create([
            'user_id'     => $data['user_id'] ?? null,
            'title'       => $data['title'],
            'body'        => $data['body'],
            'link'        => $data['link'] ?? null,
            'audience'    => $data['audience'],
            // Hedef "herkes" ise seçilen rol/kullanıcı taşınmıyor: form
            // hedefi değiştirip göndermişse eski seçim kayda geçmemeli.
            'audience_id' => $data['audience']->needsTarget() ? $data['audience_id'] : null,
            'status'      => PushNotificationStatus::Queued,
        ]));
    }

    /**
     * Gönderim başlamadan durdurur.
     *
     * Başlamış bir gönderim iptal edilemiyor: cihaza ulaşmış bildirim geri
     * alınamaz ve yarısı gitmiş bir duyuruyu "iptal edildi" diye göstermek
     * olanı yanlış anlatır.
     *
     * @throws RuntimeException
     */
    public function cancel(PushNotification $notification): void
    {
        if (! $notification->status->isCancellable()) {
            throw new RuntimeException('Gönderimi başlamış bir duyuru iptal edilemez.');
        }

        $notification->forceFill([
            'status'       => PushNotificationStatus::Cancelled,
            'completed_at' => now(),
        ])->save();
    }
}
