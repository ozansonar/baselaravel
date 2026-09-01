<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Mail\Mailables\Envelope;

final class WelcomeMail extends BaseMail
{
    public function __construct(
        public readonly User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.welcome.subject', ['site' => Setting::getValue('site_name', config('app.name'))]),
        );
    }

    protected function emailView(): string
    {
        return 'emails.welcome';
    }

    protected function templateKey(): string
    {
        return 'welcome';
    }

    protected function templateVariables(): array
    {
        $siteName = Setting::getValue('site_name', config('app.name'));

        return [
            'user_name' => $this->user->full_name,
            'site_name' => $siteName,
        ];
    }
}
