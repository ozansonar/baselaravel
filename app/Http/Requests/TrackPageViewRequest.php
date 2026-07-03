<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackPageViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url'           => ['required', 'string', 'max:500'],
            'path'          => ['required', 'string', 'max:255'],
            'referrer'      => ['nullable', 'string', 'max:500'],
            'screen_width'  => ['nullable', 'integer', 'min:0', 'max:10000'],
            'screen_height' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
