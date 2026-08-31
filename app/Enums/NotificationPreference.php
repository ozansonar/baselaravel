<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kullanıcının kapatabileceği e-posta türleri.
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

    public function label(): string
    {
        return match ($this) {
            self::CommentUpdates => __('site.notifications.comment_updates'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CommentUpdates => __('site.notifications.comment_updates_hint'),
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
