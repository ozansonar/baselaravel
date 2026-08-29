<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\BlogComment;
use App\Models\Setting;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Yorum onaylandığında yazan kişiye giden bilgilendirme.
 *
 * "Değerlendiriyoruz" mailinin karşılığı: kişi yorumunun yayımlandığını
 * siteyi tekrar tekrar açmadan öğreniyor.
 */
final class BlogCommentApprovedMail extends BaseMail
{
    public function __construct(
        public readonly BlogComment $comment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yorumunuz Yayınlandı — ' . Setting::getValue('site_name', config('app.name')),
        );
    }

    protected function emailView(): string
    {
        return 'emails.blog-comment-approved';
    }

    protected function templateKey(): string
    {
        return 'blog_comment_approved';
    }

    protected function templateVariables(): array
    {
        return [
            'comment_author' => $this->comment->name,
            'comment_body'   => $this->comment->body,
            'post_title'     => $this->comment->post?->title ?? '-',
            'post_url'       => $this->postUrl(),
            'site_name'      => Setting::getValue('site_name', config('app.name')),
        ];
    }

    /** Yazının ön yüzdeki adresi; yazı silinmişse site kökü. */
    private function postUrl(): string
    {
        $post = $this->comment->post;

        if ($post === null || $post->category === null) {
            return url('/');
        }

        return route('blog.show', [
            'locale'       => $post->locale,
            'categorySlug' => $post->category->slug,
            'slug'         => $post->slug,
        ]) . '#comments';
    }
}
