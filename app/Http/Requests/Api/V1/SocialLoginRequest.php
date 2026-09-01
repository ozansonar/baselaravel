<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sağlayıcıdan alınan kimlik jetonuyla giriş.
 *
 * Jetonun kendisi doğrulanmadan hiçbir şey ifade etmiyor; buradaki kurallar
 * yalnız biçim denetimi. Asıl doğrulama {@see \App\Services\Social\SocialIdentityVerifier}.
 */
final class SocialLoginRequest extends FormRequest
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
            // JWT: üç base64url parça, aralarında nokta. Uzunluk sınırı
            // sağlayıcıların ürettiğinin çok üstünde ve devasa bir gövdeyi
            // imza doğrulamasına sokmadan kesiyor.
            'id_token'    => ['required', 'string', 'max:4096', 'regex:/^[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+$/'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'abilities'   => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:50'],
            // Apple adı yalnız ilk yetkilendirmede gönderiyor ve jetonun
            // içinde değil, ayrı bir alanda; istemci onu bize iletiyor.
            'first_name'  => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}\p{M}\s\'’-]+$/u'],
            'last_name'   => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}\p{M}\s\'’-]+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_token.required' => __('api.social.token_required'),
            'id_token.regex'    => __('api.social.token_invalid'),
        ];
    }
}
