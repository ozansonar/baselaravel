<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sitenin yayında olan bir dili.
 *
 * Mobil uygulamanın dil menüsü bunu okur: hangi kodları gönderebileceğini ve
 * kullanıcıya hangi adla göstereceğini başka türlü bilemez.
 *
 * @mixin Language
 */
final class LanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code'        => $this->code,
            'name'        => $this->name,
            'native_name' => $this->native_name,
            'flag'        => $this->flag,
            'is_default'  => $this->is_default,
            'sort_order'  => $this->sort_order,
        ];
    }
}
