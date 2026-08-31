<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * API üzerinden blog yorumu.
 *
 * Kurallar ön yüzdeki {@see \App\Http\Requests\StoreBlogCommentRequest} ile
 * aynı sınırları koyuyor — aynı tabloya yazılıyor. Tek fark reCAPTCHA:
 * tarayıcıya bağlı bir denetim, mobil istemcide karşılığı yok.
 *
 * Yorum alanları spam'in birinci hedefi ve buradaki tek fren hız sınırı
 * (`throttle:api-comment`). İkinci bir fren daha var ama gecikmeli: yorum
 * `Pending` olarak kaydediliyor, yani hiçbir gönderim doğrudan yayına girmiyor —
 * spam yayına değil moderasyon kuyruğuna düşüyor.
 */
final class StoreBlogCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'blog_post_id' => ['required', 'integer', 'exists:blog_posts,id'],
            'parent_id'    => ['nullable', 'integer', 'exists:blog_comments,id'],
            'name'         => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'email'        => ['required', 'email', 'max:191'],
            'body'         => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'         => __('site.blog.comment_name_required'),
            'name.min'              => __('site.blog.comment_name_min'),
            'name.max'              => __('site.blog.comment_name_max'),
            'name.regex'            => __('site.forms.name_letters'),
            'email.required'        => __('site.blog.comment_email_required'),
            'email.email'           => __('site.forms.email_invalid_formal'),
            'body.required'         => __('site.blog.comment_body_required'),
            'body.min'              => __('site.blog.comment_body_min'),
            'body.max'              => __('site.blog.comment_body_max'),
            'blog_post_id.required' => __('site.blog.comment_post_required'),
            'blog_post_id.exists'   => __('site.blog.comment_post_invalid'),
            'parent_id.exists'      => __('site.blog.comment_parent_missing'),
        ];
    }
}
