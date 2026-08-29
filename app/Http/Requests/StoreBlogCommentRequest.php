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
            'name'         => ['required', 'string', 'min:2', 'max:100'],
            'email'        => ['required', 'email', 'max:191'],
            'body'                 => ['required', 'string', 'min:3', 'max:2000'],
            'g-recaptcha-response' => app(RecaptchaService::class)->isEnabled()
                ? ['required', new RecaptchaRule()]
                : [],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'         => 'Ad Soyad alanı zorunludur.',
            'name.min'              => 'Ad Soyad en az 2 karakter olmalıdır.',
            'name.max'              => 'Ad Soyad en fazla 100 karakter olabilir.',
            'email.required'        => 'E-posta alanı zorunludur.',
            'email.email'           => 'Geçerli bir e-posta adresi giriniz.',
            'body.required'         => 'Yorum alanı zorunludur.',
            'body.min'              => 'Yorum en az 3 karakter olmalıdır.',
            'body.max'              => 'Yorum en fazla 2000 karakter olabilir.',
            'blog_post_id.required' => 'Yazı bilgisi eksik.',
            'blog_post_id.exists'   => 'Geçersiz yazı.',
            'parent_id.exists'                  => 'Yanıtlanan yorum bulunamadı.',
            'g-recaptcha-response.required'     => 'Lütfen robot olmadığınızı doğrulayın.',
        ];
    }
}
