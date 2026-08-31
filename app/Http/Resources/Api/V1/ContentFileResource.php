<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\FileKind;
use App\Models\ContentFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * İçeriğe iliştirilmiş tek dosya.
 *
 * İndirme adresi dosyanın kendi yolu değil, indirme rotası: sayaç ve yetki
 * denetimi orada — doğrudan /uploads adresi verilseydi ikisi de atlanırdı.
 *
 * @mixin ContentFile
 */
final class ContentFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $kind = FileKind::fromExtension($this->extension);

        return [
            'id'        => $this->id,
            'name'      => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size'      => $this->size,
            'kind'      => $kind->value,
            'url'       => route('content.files.download', ['file' => $this->id]),
        ];
    }
}
