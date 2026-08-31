<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationPreference;
use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\UserNotificationPreference;

/**
 * "Hangi e-postaları alayım?" sorusunun tek cevap yeri.
 *
 * Kullanıcının e-postaları kapatabildiği tek yer bülten mailinin altındaki
 * çıkış bağlantısıydı; onun dışındaki her tür, kişi istese de istemese de
 * gidiyordu. Yeni bir e-posta türü eklendiğinde aynı sorun tekrar doğuyordu.
 *
 * Gönderim öncesi tek kapı: {@see allows()}. Yeni tür eklemek enum'a bir case
 * ve gönderim yerine bir koşul demek.
 *
 * Bülten bilerek bu tablonun dışında: kaynağı `subscribers` tablosu ve orada
 * zaten bir durum var. İkinci bir bayrak tutmak, ikisinin çelişmesi demekti —
 * kullanıcı ekranda "kapalı" görüp posta almaya devam ederdi.
 */
final class NotificationPreferenceService
{
    public function __construct(
        private readonly SubscriberService $subscribers,
    ) {}

    /**
     * Bu kullanıcı bu türü alıyor mu?
     *
     * Kaydı olmayan için enum'un varsayılanı geçerli: satır yalnız kişi
     * varsayılandan saptığında yazılıyor.
     */
    public function allows(User $user, NotificationPreference $type): bool
    {
        $row = UserNotificationPreference::where('user_id', $user->getKey())
            ->where('type', $type->value)
            ->first();

        return $row?->enabled ?? $type->defaultEnabled();
    }

    /**
     * Ekranın çizdiği tablo: her tür ve kişinin o türdeki kararı.
     *
     * @return array<string, bool>
     */
    public function all(User $user): array
    {
        $stored = UserNotificationPreference::where('user_id', $user->getKey())
            ->pluck('enabled', 'type');

        $result = [];

        foreach (NotificationPreference::cases() as $type) {
            $value = $stored[$type->value] ?? null;
            $result[$type->value] = $value === null ? $type->defaultEnabled() : (bool) $value;
        }

        return $result;
    }

    public function set(User $user, NotificationPreference $type, bool $enabled): void
    {
        // withTrashed: benzersizlik kısıtı yumuşak silinmiş satırı da
        // sayıyor, üstüne yeni satır atmak hata verirdi.
        $row = UserNotificationPreference::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('type', $type->value)
            ->first();

        if ($row === null) {
            UserNotificationPreference::create([
                'user_id' => $user->getKey(),
                'type'    => $type->value,
                'enabled' => $enabled,
            ]);

            return;
        }

        $row->restore();
        $row->update(['enabled' => $enabled]);
    }

    /**
     * Bültenin durumu — abone tablosundan okunuyor.
     */
    public function newsletterEnabled(User $user): bool
    {
        return Subscriber::where('email', $user->email)
            ->where('status', SubscriberStatus::Subscribed)
            ->exists();
    }

    /**
     * Bülten anahtarı. Açmak yeni bir abonelik, kapatmak çıkış — ikisi de
     * abone servisinden geçiyor ki kaynak tek kalsın.
     */
    public function setNewsletter(User $user, bool $enabled): void
    {
        if ($enabled) {
            $this->subscribers->subscribe(
                $user->email,
                $user->first_name,
                $user->last_name,
                source: \App\Enums\SubscriberSource::Form->value,
            );

            return;
        }

        $this->subscribers->unsubscribeByEmail($user->email);
    }
}
