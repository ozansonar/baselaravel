<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreGoogleReviewRequest extends FormRequest
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
            'author_name' => ['required', 'string', 'max:255'],
            'text'        => ['required', 'string', 'max:5000'],
            'rating'      => ['required', 'integer', 'min:1', 'max:5'],
            'review_time' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'author_name.required' => 'Yazar adı zorunludur.',
            'author_name.max'      => 'Yazar adı en fazla 255 karakter olabilir.',
            'text.required'        => 'Yorum metni zorunludur.',
            'text.max'             => 'Yorum metni en fazla 5000 karakter olabilir.',
            'rating.required'      => 'Puan zorunludur.',
            'rating.min'           => 'Puan en az 1 olmalıdır.',
            'rating.max'           => 'Puan en fazla 5 olmalıdır.',
        ];
    }
}
