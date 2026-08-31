<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Yazı listesinin süzgeçleri.
 *
 * `per_page` bilerek burada yok: sayfa boyutu hata değil kırpma konusu.
 * Uydurma bir değer 422 verseydi istemci liste için iki gidiş dönüş yapardı;
 * {@see \App\Http\Controllers\Api\V1\Concerns\ResolvesPagination} onu sessizce
 * makul bir aralığa çekiyor.
 *
 * Arama terimi ise sınırlanıyor: uzunluğu sınırsız bırakılan bir LIKE kalıbı,
 * her istekte bütün tabloyu tarayan bir sorguya dönüşebilir.
 */
final class BlogPostIndexRequest extends FormRequest
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
            'search'   => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max'   => __('api.blog.search_max'),
            'category.max' => __('api.blog.category_max'),
        ];
    }

    /**
     * Süzgeç değeri — boş dize "süzgeç yok" demek.
     */
    public function filter(string $key): ?string
    {
        $value = trim((string) $this->query($key, ''));

        return $value === '' ? null : $value;
    }
}
