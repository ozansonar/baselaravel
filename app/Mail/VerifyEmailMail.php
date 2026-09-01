<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Mail\Mailables\Envelope;

final class VerifyEmailMail extends BaseMail
{
    public function __construct(
        public readonly User $user,
        public readonly string $verificationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.verify.subject', ['site' => Setting::getValue('site_name', config('app.name'))]),
        );
    }

    protected function emailView(): string
    {
        return 'emails.verify-email';
    }

    protected function templateKey(): string
    {
        return 'verify_email';
    }

    protected function templateVariables(): array
    {
        return [
            'user_name'        => $this->user->full_name,
            'site_name'        => Setting::getValue('site_name', config('app.name')),
            'verification_url' => $this->verificationUrl,
        ];
    }
}
