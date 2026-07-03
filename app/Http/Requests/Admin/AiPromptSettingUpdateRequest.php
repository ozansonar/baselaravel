<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AiPromptSettingUpdateRequest extends FormRequest
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
            'system_instruction'        => ['required', 'string', 'max:20000'],
            'user_prompt'               => ['required', 'string', 'max:10000'],
            'category_slug'             => ['required', 'string', 'max:100'],
            'max_words'                 => ['required', 'integer', 'min:100', 'max:5000'],
            'is_active'                 => ['sometimes', 'boolean'],
            'products'                  => ['required', 'array', 'min:1'],
            'products.*'                => ['required', 'string', 'max:255'],
            'target_cities'             => ['nullable', 'array', 'max:50'],
            'target_cities.*'           => ['nullable', 'string', 'max:100'],
            'general_post_every_n_runs' => ['required', 'integer', 'min:0', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'system_instruction.required'        => 'Sistem talimatı boş bırakılamaz.',
            'system_instruction.max'             => 'Sistem talimatı en fazla 20.000 karakter olabilir.',
            'user_prompt.required'               => 'Kullanıcı promptu boş bırakılamaz.',
            'user_prompt.max'                    => 'Kullanıcı promptu en fazla 10.000 karakter olabilir.',
            'category_slug.required'             => 'Hedef kategori slug bilgisi gereklidir.',
            'max_words.required'                 => 'Maksimum kelime sayısı gereklidir.',
            'max_words.min'                      => 'Maksimum kelime sayısı en az 100 olmalıdır.',
            'max_words.max'                      => 'Maksimum kelime sayısı en fazla 5000 olabilir.',
            'products.required'                  => 'En az bir ürün tanımlamalısınız.',
            'products.min'                       => 'En az bir ürün tanımlamalısınız.',
            'products.*.required'                => 'Ürün adı boş olamaz.',
            'products.*.max'                     => 'Ürün adı en fazla 255 karakter olabilir.',
            'target_cities.max'                  => 'En fazla 50 şehir tanımlayabilirsiniz.',
            'target_cities.*.max'                => 'Şehir adı en fazla 100 karakter olabilir.',
            'general_post_every_n_runs.required' => 'Genel yazı sıklığı (kaç çalıştırmada 1) zorunludur.',
            'general_post_every_n_runs.min'     => 'Genel yazı sıklığı 0 veya daha büyük olmalıdır (0 = hiç genel yazı yok).',
            'general_post_every_n_runs.max'     => 'Genel yazı sıklığı en fazla 200 olabilir.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if (is_array($data) && isset($data['products']) && is_array($data['products'])) {
            $data['products'] = array_values(array_filter(
                array_map(static fn ($v) => trim((string) $v), $data['products']),
                static fn ($v) => $v !== ''
            ));
        }

        if (is_array($data) && array_key_exists('target_cities', $data) && is_array($data['target_cities'])) {
            $data['target_cities'] = array_values(array_unique(array_filter(
                array_map(static fn ($v) => trim((string) $v), $data['target_cities']),
                static fn ($v) => $v !== ''
            )));
        }

        if (is_array($data)) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
