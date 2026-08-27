<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bildirim listesinde seçilen kayıtlar üzerinde toplu işlem.
 *
 * Hangi bildirimlerin gerçekten silinip okunacağı sorgu tarafında (forUser)
 * ayrıca sınırlanır; burada yalnızca gelen verinin biçimi doğrulanır.
 */
final class BulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Yetki kontrolü controller'daki policy çağrısında.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'İşlem için bildirim seçilmedi.',
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', $this->validated()['ids']));
    }
}
