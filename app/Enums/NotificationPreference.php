<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kullanıcının kapatabileceği bildirim türleri.
 *
 * Burada olmayan e-postalar kapatılamaz ve bu bilinçli: şifre sıfırlama,
 * e-posta doğrulama ve adres değişikliği uyarısı hesabın güvenliğine dair —
 * kapatılabilseydi hesabı ele geçiren biri ilk iş onları susturur, sahibi de
 * olan bitenden habersiz kalırdı.
 *
 * Bülten bu listede yok, ama ekranda var: onun kaynağı `subscribers` tablosu.
 * İki yerde iki ayrı bayrak tutmak, er ya da geç birinin ötekiyle çelişmesi
 * demek — kullanıcı "kapalı" gördüğü hâlde posta almaya devam eder.
 */
enum NotificationPreference: string
{
    /** Yorumu yayınlandığında ya da yanıtlandığında gelen bildirim. */
    case CommentUpdates = 'comment_updates';

    /**
     * Panelden gönderilen duyuru bildirimleri (push).
     *
     * Buradaki tek şey duyuru: hesabın güvenliğine dair bir push varsa o bu
     * anahtarla susturulamaz. Duyuru ise pazarlama iletisi sayılıyor ve
     * kapatılabilmesi gerekiyor.
     */
    case PushAnnouncements = 'push_announcements';

    public function label(): string
    {
        return match ($this) {
            self::CommentUpdates    => __('site.notifications.comment_updates'),
            self::PushAnnouncements => __('site.notifications.push_announcements'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CommentUpdates    => __('site.notifications.comment_updates_hint'),
            self::PushAnnouncements => __('site.notifications.push_announcements_hint'),
        };
    }

    /**
     * Kayıt yokken geçerli olan değer.
     *
     * Varsayılan açık: kullanıcı kendi yorumunun yayınlandığını öğrenmek
     * ister. Kapalı başlasaydı özellik, varlığından haberi olmayan kimseye
     * ulaşmazdı.
     */
    public function defaultEnabled(): bool
    {
        return true;
    }
}
