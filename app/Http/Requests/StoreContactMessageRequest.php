<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\RecaptchaRule;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;

final class StoreContactMessageRequest extends FormRequest
{
    protected $redirectRoute = 'contact';

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
            'name'    => ['required', 'string', 'max:191'],
            'email'   => ['required', 'string', 'email:rfc,dns', 'max:191'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:191'],
            'message'              => ['required', 'string', 'min:10', 'max:5000'],
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
            'name.required'    => 'Ad soyad zorunludur.',
            'name.max'         => 'Ad soyad en fazla 255 karakter olabilir.',
            'email.required'   => 'E-posta adresi zorunludur.',
            'email.email'      => 'Geçerli bir e-posta adresi giriniz.',
            'subject.required' => 'Konu zorunludur.',
            'subject.max'      => 'Konu en fazla 255 karakter olabilir.',
            'message.required' => 'Mesaj zorunludur.',
            'message.min'      => 'Mesaj en az 10 karakter olmalıdır.',
            'message.max'                      => 'Mesaj en fazla 5.000 karakter olabilir.',
            'g-recaptcha-response.required'    => 'Lütfen robot olmadığınızı doğrulayın.',
        ];
    }
}
