<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\SearchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Site araması süzgeçleri.
 *
 * Terim burada ZORUNLU: ön yüzde arama sayfası terimsiz de açılabiliyor
 * (kutuyu gösteriyor), API'de ise terimsiz bir arama isteğinin karşılığı yok.
 */
final class SearchRequest extends FormRequest
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
            'q' => [
                'required', 'string',
                'min:' . config('search.min_length', 2),
                'max:' . config('search.max_length', 100),
            ],
            'type' => ['nullable', 'string', Rule::enum(SearchType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => __('api.search.term_required'),
            'q.min'      => __('api.search.term_min'),
            'q.max'      => __('api.search.term_max'),
        ];
    }
}
