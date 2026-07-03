<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RecaptchaRule;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name'       => ['required', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'email'      => ['required', 'email', 'max:255'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'comment'              => ['required', 'string', 'min:10', 'max:1000'],
            'g-recaptcha-response' => app(RecaptchaService::class)->isEnabled()
                ? ['required', new RecaptchaRule()]
                : [],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Ürün bilgisi gereklidir.',
            'product_id.exists'   => 'Geçersiz ürün.',
            'name.required'       => 'Ad soyad zorunludur.',
            'name.max'            => 'Ad soyad en fazla 100 karakter olmalıdır.',
            'email.required'      => 'E-posta adresi zorunludur.',
            'email.email'         => 'Geçerli bir e-posta adresi giriniz.',
            'rating.required'     => 'Lütfen bir puan seçiniz.',
            'rating.min'          => 'Puan en az 1 olmalıdır.',
            'rating.max'          => 'Puan en fazla 5 olmalıdır.',
            'comment.required'    => 'Yorum yazmanız zorunludur.',
            'comment.min'         => 'Yorum en az 10 karakter olmalıdır.',
            'comment.max'                      => 'Yorum en fazla 1000 karakter olmalıdır.',
            'g-recaptcha-response.required'    => 'Lütfen robot olmadığınızı doğrulayın.',
        ];
    }
}
