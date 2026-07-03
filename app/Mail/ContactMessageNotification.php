<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

final class ContactMessageNotification extends BaseMail
{
    public function __construct(
        public readonly ContactMessage $contactMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Yeni İletişim Mesajı - {$this->contactMessage->subject}",
        );
    }

    protected function emailView(): string
    {
        return 'emails.contact-message';
    }

    protected function templateKey(): string
    {
        return 'contact_message';
    }

    protected function templateVariables(): array
    {
        return [
            'contact_name'    => $this->contactMessage->name,
            'contact_email'   => $this->contactMessage->email,
            'contact_phone'   => $this->contactMessage->phone ?? '-',
            'contact_subject' => $this->contactMessage->subject,
            'contact_message' => $this->contactMessage->message,
            'contact_date'    => $this->contactMessage->created_at->format('d.m.Y H:i'),
            'message_url'     => url('/admin/contact-messages/' . $this->contactMessage->id),
            'site_name'       => Setting::getValue('site_name', config('app.name')),
        ];
    }
}
