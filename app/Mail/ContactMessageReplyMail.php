<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

final class ContactMessageReplyMail extends BaseMail
{
    public function __construct(
        public readonly ContactMessage $contactMessage,
        public readonly string $replyBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.contact_reply.subject', ['subject' => $this->contactMessage->subject]),
            replyTo: [config('mail.from.address')],
        );
    }

    /**
     * Ziyaretçinin formu doldururken bulunduğu dil. Yanıt panelden yazılıyor
     * ve panel Türkçeye sabit, o yüzden isteğin dili burada yanlış cevap.
     */
    protected function resolveLocale(): string
    {
        return $this->contactMessage->locale ?? $this->defaultLocale();
    }

    protected function emailView(): string
    {
        return 'emails.contact-reply';
    }

    protected function templateKey(): string
    {
        return 'contact_reply';
    }

    protected function templateVariables(): array
    {
        return [
            'contact_name'     => $this->contactMessage->name,
            'contact_subject'  => $this->contactMessage->subject,
            'contact_message'  => $this->contactMessage->message,
            'reply_body'       => nl2br(e($this->replyBody)),
            'site_name'        => Setting::getValue('site_name', config('app.name')),
            'site_url'         => config('app.url'),
        ];
    }
}
