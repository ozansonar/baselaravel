<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Service Account JSON dosyası yükleme validasyonu.
 *  - .json uzantısı + 64KB maksimum (anahtar genelde 2-3KB)
 *  - geçerli JSON parse edilebilmeli
 *  - client_email, private_key, project_id alanları olmalı
 *  - private_key "-----BEGIN PRIVATE KEY-----" ile başlamalı
 */
final class UploadGoogleServiceAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_account_file' => [
                'required',
                'file',
                'mimetypes:application/json,text/plain,application/octet-stream',
                'max:64',
                $this->jsonContentRule(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_account_file.required'  => 'Lütfen Service Account JSON dosyasını seç.',
            'service_account_file.file'      => 'Geçerli bir dosya yükle.',
            'service_account_file.mimetypes' => 'Dosya .json formatında olmalı.',
            'service_account_file.max'       => 'Dosya 64 KB\'dan büyük olamaz.',
        ];
    }

    private function jsonContentRule(): ValidationRule
    {
        return new class implements ValidationRule {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    return;
                }

                $original = strtolower((string) $value->getClientOriginalExtension());
                if ($original !== 'json') {
                    $fail('Dosya uzantısı .json olmalı.');

                    return;
                }

                $raw = @file_get_contents($value->getRealPath());
                if ($raw === false || $raw === '') {
                    $fail('Dosya okunamadı veya boş.');

                    return;
                }

                $data = json_decode($raw, true);
                if (! is_array($data)) {
                    $fail('Dosya geçerli bir JSON değil.');

                    return;
                }

                $required = ['client_email', 'private_key', 'project_id', 'type'];
                foreach ($required as $key) {
                    if (! isset($data[$key]) || ! is_string($data[$key]) || $data[$key] === '') {
                        $fail("JSON içinde '{$key}' alanı eksik veya boş.");

                        return;
                    }
                }

                if ($data['type'] !== 'service_account') {
                    $fail('Bu dosya bir Service Account anahtarı değil. Google Cloud → Service Account → Keys → JSON ile indirilen dosyayı yükle.');

                    return;
                }

                if (! str_contains($data['private_key'], '-----BEGIN PRIVATE KEY-----')) {
                    $fail("'private_key' alanı geçerli bir RSA özel anahtarı değil.");

                    return;
                }

                if (! str_contains($data['client_email'], '@') || ! str_contains($data['client_email'], '.iam.gserviceaccount.com')) {
                    $fail("'client_email' alanı geçerli bir Service Account email'i değil.");
                }
            }
        };
    }
}
