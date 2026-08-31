<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PushPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cihazın bildirim adresini bırakması.
 *
 * Jeton uzunluğu sütunla aynı sayıyı söylüyor (191): daha uzun bir jeton
 * kabul edilseydi veritabanında sessizce kırpılır ve bildirim hiç ulaşmazdı.
 */
final class StorePushTokenRequest extends FormRequest
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
            'token'       => ['required', 'string', 'max:191'],
            'platform'    => ['required', Rule::enum(PushPlatform::class)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
