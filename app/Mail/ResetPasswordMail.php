<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends BaseMail
{
    public string $resetUrl;

    public function __construct(
        string $resetUrl,
    ) {
        $this->resetUrl = $resetUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.reset.subject', ['site' => Setting::getValue('site_name', config('app.name'))]),
        );
    }

    protected function emailView(): string
    {
        return 'emails.reset-password';
    }

    protected function templateKey(): string
    {
        return 'reset_password';
    }

    protected function templateVariables(): array
    {
        return [
            'reset_url' => $this->resetUrl,
            'site_name' => Setting::getValue('site_name', config('app.name')),
        ];
    }
}
