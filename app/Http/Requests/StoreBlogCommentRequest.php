<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RecaptchaRule;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBlogCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blog_post_id' => ['required', 'integer', 'exists:blog_posts,id'],
            'parent_id'    => ['nullable', 'integer', 'exists:blog_comments,id'],
            'name'         => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-zA-ZçÇğĞıİöÖşŞüÜ\s]+$/u'],
            'email'        => ['required', 'email', 'max:191'],
            'body'                 => ['required', 'string', 'min:3', 'max:2000'],
            'g-recaptcha-response' => app(RecaptchaService::class)->isEnabled()
                ? ['required', new RecaptchaRule()]
                : [],
        ];
    }

    /**
     * Uyarı metinleri panelden yönetiliyor (Dil Yazıları).
     *
     * Koda gömülü olduklarında İngilizce ziyaretçi Türkçe uyarı görüyordu ve
     * yönetici metni değiştiremiyordu. Sayılar :min / :max ile kuraldan
     * geliyor: elle yazılan sayı, kural değişince yalan söylüyor — nitekim
     * söylemişti: iletişim formu sınır 191'ken "255" diyordu.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'                 => __('site.blog.comment_name_required'),
            'name.min'                      => __('site.blog.comment_name_min'),
            'name.regex'                    => __('site.forms.name_letters'),
            'name.max'                      => __('site.blog.comment_name_max'),
            'email.required'                => __('site.blog.comment_email_required'),
            'email.email'                   => __('site.forms.email_invalid_formal'),
            'body.required'                 => __('site.blog.comment_body_required'),
            'body.min'                      => __('site.blog.comment_body_min'),
            'body.max'                      => __('site.blog.comment_body_max'),
            'blog_post_id.required'         => __('site.blog.comment_post_required'),
            'blog_post_id.exists'           => __('site.blog.comment_post_invalid'),
            'parent_id.exists'              => __('site.blog.comment_parent_missing'),
            'g-recaptcha-response.required' => __('site.forms.recaptcha'),
        ];
    }
}
