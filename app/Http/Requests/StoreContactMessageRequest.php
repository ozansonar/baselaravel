<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\EmailAddress;
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
            // İstemci maskesi harf dışını yazdırmıyor; sunucu da aynı şeyi söylemeli,
            // yoksa formu atlayan bir istek rakamlı ad geçirebilir.
            //
            // Desen Türkçe harflerle sınırlıydı: site çok dilli ama "José" ya da
            // "Anaïs" reddediliyordu. \p{L} her dilin harfini kabul ediyor;
            // kesme ve tire de ada ait ("O'Brien", "Jean-Luc"), rakam ve
            // işaretler hâlâ dışarıda.
            'name'    => ['required', 'string', 'min:2', 'max:191', 'regex:/^[\p{L}\p{M}\s\'’-]+$/u'],
            'email'   => ['required', 'string', ...EmailAddress::rules(), 'max:191'],
            'phone'   => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s\-\+\(\).]+$/'],
            'subject' => ['required', 'string', 'max:191'],
            'message'              => ['required', 'string', 'min:10', 'max:5000'],
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
            'name.required'                 => __('site.contact.name_required'),
            'name.min'                      => __('site.contact.name_min'),
            'name.regex'                    => __('site.forms.name_letters'),
            'phone.regex'                   => __('site.forms.phone_format'),
            'name.max'                      => __('site.contact.name_max'),
            'email.required'                => __('site.forms.email_required'),
            'email.email'                   => __('site.forms.email_invalid_formal'),
            'subject.required'              => __('site.contact.subject_required'),
            'subject.max'                   => __('site.contact.subject_max'),
            'message.required'              => __('site.contact.message_required'),
            'message.min'                   => __('site.contact.message_min'),
            'message.max'                   => __('site.contact.message_max'),
            'g-recaptcha-response.required' => __('site.forms.recaptcha'),
        ];
    }
}
