<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Hesabınızın e-posta adresi değiştirildi" — ESKİ adrese gider.
 *
 * Hesabı ele geçiren kişinin ilk yaptığı şey çoğu zaman adresi kendi adresiyle
 * değiştirmektir: o andan sonra şifre sıfırlama bağlantısı da, bütün bildirimler
 * de ona gider ve gerçek sahibin hesaptan haberi kesilir. Yeni adrese giden
 * doğrulama maili bu durumda saldırganın kendi kutusuna düşer, yani kimseyi
 * uyarmaz.
 *
 * Bu mail o sessizliği bozuyor. Değişiklikten haberi olması gereken tek yer eski
 * adres ve ona ulaşmanın son anı da bu: adres artık hesapta kayıtlı değil.
 *
 * Yeni adres maskelenmiş olarak yazılıyor (`a***t@ornek.com`). Tamamen
 * gizlenseydi sahibi neyin olduğunu anlatamaz, olduğu gibi yazılsaydı bu mail
 * bir adresi başka bir adrese sızdırmanın yolu olurdu — kimin hangi adrese
 * geçtiğini öğrenmek için hesap ele geçirmek yetiyordu.
 */
final class EmailChangedMail extends BaseMail
{
    public string $maskedNewEmail;

    /**
     * Sahibi "ben yapmadım" diyecekse gidebileceği tek yer.
     */
    public string $supportEmail;

    public function __construct(
        public readonly string $userName,
        public readonly string $previousEmail,
        string $newEmail,
        public readonly string $changedAt,
    ) {
        $this->maskedNewEmail = self::mask($newEmail);
        $this->supportEmail = (string) (Setting::getValue('contact_email') ?? config('mail.from.address'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.email_changed.subject', ['site' => Setting::getValue('site_name', config('app.name'))]),
        );
    }

    protected function emailView(): string
    {
        return 'emails.email-changed';
    }

    protected function templateKey(): string
    {
        return 'email_changed';
    }

    protected function templateVariables(): array
    {
        return [
            'user_name'      => $this->userName,
            'site_name'      => Setting::getValue('site_name', config('app.name')),
            'previous_email' => $this->previousEmail,
            'new_email'      => $this->maskedNewEmail,
            'changed_at'     => $this->changedAt,
            'support_email'  => $this->supportEmail,
        ];
    }

    /**
     * Adresin yalnız tanınmaya yetecek kadarı: ilk harf, son harf ve alan adı.
     *
     * Çok kısa yerel adlarda (tek harf) baş ve son aynı karaktere denk geliyor;
     * o durumda tek yıldız bırakılıyor, yoksa maskeleme adresin tamamını
     * göstermiş olurdu.
     */
    private static function mask(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return '***';
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at);

        if (mb_strlen($local) <= 2) {
            return '***' . $domain;
        }

        return mb_substr($local, 0, 1) . '***' . mb_substr($local, -1) . $domain;
    }
}
