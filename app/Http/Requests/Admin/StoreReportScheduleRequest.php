<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use App\Rules\UserEmail;
use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Zamanlanmış rapor tanımı.
 *
 * Alıcılar virgülle ayrılmış tek alan olarak geliyor; tanımın kendisi dizi
 * saklıyor. Ayrıştırma burada, çünkü aynı dönüşümü hem oluşturma hem
 * güncelleme yolunda tekrarlamak gerekirdi.
 */
final class StoreReportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('recipients');

        if (is_string($raw)) {
            $this->merge([
                'recipients' => array_values(array_filter(array_map(
                    'trim',
                    preg_split('/[,;\n]+/', $raw) ?: [],
                ))),
            ]);
        }

        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type'      => ['required', Rule::enum(ReportType::class)],
            'frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'range'     => ['required', Rule::in(array_keys(ReportService::RANGES))],
            'format'    => ['required', Rule::in(['excel', 'pdf'])],
            // En az bir alıcı: kimseye gitmeyen bir zamanlanmış rapor,
            // sunucuda boşuna dönen bir iş.
            'recipients'   => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'string', 'email', 'max:' . UserEmail::MAX_LENGTH],
            'is_active'    => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipients.required' => 'En az bir alıcı e-posta adresi girin.',
            'recipients.max'      => 'En fazla 10 alıcı tanımlanabilir.',
            'recipients.*.email'  => 'Alıcı adreslerinden biri geçerli değil.',
        ];
    }
}
