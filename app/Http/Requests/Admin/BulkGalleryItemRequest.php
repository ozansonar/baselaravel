<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Galeri listesinde seçilen öğeler üzerinde toplu işlem.
 *
 * Kimlikler formdan geldiği için hiçbiri güvenilir sayılmıyor: var olmayan
 * ya da uydurulmuş bir kimlik doğrulamada eleniyor. Silinmişler de kabul
 * ediliyor — geri yükleme çöpteki satırlar üzerinde çalışıyor.
 */
final class BulkGalleryItemRequest extends FormRequest
{
    /** Tek seferde işlenebilecek en çok öğe; listenin en büyük sayfa boyutu. */
    private const MAX_ITEMS = 100;

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
            'ids'   => ['required', 'array', 'min:1', 'max:' . self::MAX_ITEMS],
            'ids.*' => ['integer', 'exists:gallery_items,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Hiçbir öğe seçilmedi.',
            'ids.max'      => 'Tek seferde en fazla ' . self::MAX_ITEMS . ' öğe seçilebilir.',
            'ids.*.exists' => 'Seçilen öğelerden biri bulunamadı.',
        ];
    }

    /**
     * Seçilen kimlikler, tekilleştirilmiş.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
