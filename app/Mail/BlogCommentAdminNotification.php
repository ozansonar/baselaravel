<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BlogComment;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Yeni bir yorum geldiğinde yöneticiye giden bilgilendirme.
 *
 * Yorumlar onaya düşüyor; kimse panele bakmazsa günlerce bekleyebiliyorlar.
 * Metni "Mail Temaları" ekranından düzenlenebilsin diye şablon anahtarı
 * taşıyor — gövde burada gömülü değil.
 */
final class BlogCommentAdminNotification extends BaseMail
{
    public function __construct(
        public readonly BlogComment $comment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.comment_admin.subject', [
                'post' => $this->comment->post?->title ?? '-',
                'site' => Setting::getValue('site_name', config('app.name')),
            ]),
        );
    }

    /** Alıcı yönetici; panel tek dilde. */
    protected function resolveLocale(): string
    {
        return $this->defaultLocale();
    }

    protected function emailView(): string
    {
        return 'emails.blog-comment-admin';
    }

    protected function templateKey(): string
    {
        return 'blog_comment_admin';
    }

    protected function templateVariables(): array
    {
        return [
            'comment_author' => $this->comment->name,
            'comment_email'  => $this->comment->email,
            'comment_body'   => $this->comment->body,
            'comment_date'   => $this->comment->created_at?->format('d.m.Y H:i') ?? '-',
            'post_title'     => $this->comment->post?->title ?? '-',
            'comment_url'    => url('/admin/blog-comments/' . $this->comment->id),
            'site_name'      => Setting::getValue('site_name', config('app.name')),
        ];
    }
}
