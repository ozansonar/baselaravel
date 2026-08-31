<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConsentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ziyaretçinin çerez tercihi.
 *
 * Hangi kategorilere izin verildiğini `choice` belirliyor, kutular değil:
 *
 *   - `all`       → isteğe bağlı kategorilerin tamamı
 *   - `necessary` → hiçbiri
 *   - `custom`    → işaretlenen kutular
 *
 * Kararı sunucunun vermesi JavaScript'siz çalışmanın koşulu. Betik olmadan
 * "Tümünü kabul et" düğmesi yalnızca o an işaretli olan kutuları gönderirdi —
 * yani hiçbirini — ve düğme yazdığının tersini yapardı.
 */
final class StoreConsentRequest extends FormRequest
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
            'choice'       => ['required', 'string', Rule::in(['all', 'necessary', 'custom'])],
            'categories'   => ['sometimes', 'array'],
            'categories.*' => [
                'string',
                Rule::in(array_map(
                    static fn (ConsentCategory $case): string => $case->value,
                    ConsentCategory::optional(),
                )),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'choice.required'    => __('site.consent.invalid'),
            'choice.in'          => __('site.consent.invalid'),
            'categories.array'   => __('site.consent.invalid'),
            'categories.*.in'    => __('site.consent.invalid'),
        ];
    }

    /**
     * Ziyaretçinin izin verdiği isteğe bağlı kategoriler.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        return match ($this->string('choice')->value()) {
            'all' => array_map(
                static fn (ConsentCategory $case): string => $case->value,
                ConsentCategory::optional(),
            ),
            'necessary' => [],
            default => array_values(array_filter(
                (array) $this->input('categories', []),
                static fn (mixed $value): bool => is_string($value),
            )),
        };
    }
}
