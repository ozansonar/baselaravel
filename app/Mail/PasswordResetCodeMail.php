<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mobil uygulamadan istenen şifre sıfırlama kodu.
 *
 * Web'deki {@see ResetPasswordMail} bir bağlantı gönderiyor; bağlantı tarayıcıda
 * açılıyor ve orada bir form var. Mobil uygulamada tarayıcıya çıkmak akışı
 * ikiye bölüyor — kullanıcı şifresini web'de değiştirip uygulamaya dönmek
 * zorunda kalıyor. Kod ile böyle bir kopukluk yok: kullanıcı altı haneyi
 * uygulamaya yazıyor ve yeni şifresini orada belirliyor.
 *
 * İki akış aynı tabloyu ve aynı geçerlilik süresini paylaşıyor
 * (`password_reset_tokens`, config/auth.php'deki `expire`), yani bir kullanıcı
 * için aynı anda tek bir sıfırlama isteği yaşıyor.
 */
class PasswordResetCodeMail extends BaseMail
{
    public function __construct(
        public string $code,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.reset_code.subject', ['site' => Setting::getValue('site_name', config('app.name'))]),
        );
    }

    protected function emailView(): string
    {
        return 'emails.password-reset-code';
    }

    protected function templateKey(): string
    {
        return 'password_reset_code';
    }

    protected function templateVariables(): array
    {
        return [
            'code'       => $this->code,
            'expires_in' => (string) $this->expiresInMinutes,
            'site_name'  => Setting::getValue('site_name', config('app.name')),
        ];
    }
}
