<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\InstagramPost;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

final class InstagramPostFailedNotification extends BaseMail
{
    public function __construct(
        public readonly InstagramPost $post,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Instagram] Post #{$this->post->id} kalıcı hataya geçti",
        );
    }

    protected function emailView(): string
    {
        return 'emails.instagram-post-failed';
    }

    protected function templateKey(): string
    {
        return 'instagram_post_failed';
    }

    /**
     * @return array<string, string|null>
     */
    protected function templateVariables(): array
    {
        return [
            'post_id'       => (string) $this->post->id,
            'caption'       => (string) ($this->post->caption ?? ''),
            'error_message' => (string) ($this->post->error_message ?? '—'),
            'retry_count'   => (string) $this->post->retry_count,
            'scheduled_at'  => $this->post->scheduled_at?->format('d.m.Y H:i') ?? '—',
            'post_url'      => url('/admin/instagram-posts/' . $this->post->id . '/edit'),
            'site_name'     => Setting::getValue('site_name', config('app.name')),
        ];
    }
}
