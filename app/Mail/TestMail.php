<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

final class TestMail extends BaseMail
{
    public function __construct(
        public readonly string $mailSubject,
        public readonly string $mailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    /** Alıcı yönetici; panel tek dilde. */
    protected function resolveLocale(): string
    {
        return $this->defaultLocale();
    }

    protected function emailView(): string
    {
        return 'emails.test';
    }

    protected function templateKey(): string
    {
        return 'test';
    }

    protected function templateVariables(): array
    {
        return [
            'mail_subject' => $this->mailSubject,
            'mail_body'    => $this->mailBody,
            'site_name'    => Setting::getValue('site_name', config('app.name')),
        ];
    }
}
