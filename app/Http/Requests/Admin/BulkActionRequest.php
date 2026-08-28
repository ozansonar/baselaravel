<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Liste ekranlarında seçilen satırlar üzerinde toplu işlem.
 *
 * Kimlikler formdan geliyor, dolayısıyla hiçbiri güvenilir sayılmıyor: var
 * olmayan ya da uydurulmuş bir kimlik doğrulamada eleniyor. Silinmiş satırlar
 * da kabul ediliyor — geri yükleme çöpteki kayıtlar üzerinde çalışıyor.
 *
 * Her modül yalnız tablosunu söylüyor; kural, sınır ve mesajlar burada tek
 * yerde duruyor.
 */
abstract class BulkActionRequest extends FormRequest
{
    /**
     * Tek seferde işlenebilecek en çok satır.
     *
     * Listelerin en büyük sayfa boyutuyla aynı: ekranda seçilemeyecek kadar
     * çok kimlik gelmişse istek formdan değil, elle kurulmuştur.
     */
    protected const MAX_ITEMS = 100;

    /** Kimliklerin ait olduğu tablo. */
    abstract protected function table(): string;

    public function authorize(): bool
    {
        // Yetki controller'da politikadan soruluyor; burada yalnız girdinin
        // biçimi denetleniyor.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1', 'max:' . static::MAX_ITEMS],
            'ids.*' => ['integer', 'exists:' . $this->table() . ',id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Hiçbir kayıt seçilmedi.',
            'ids.max'      => 'Tek seferde en fazla ' . static::MAX_ITEMS . ' kayıt seçilebilir.',
            'ids.*.exists' => 'Seçilen kayıtlardan biri bulunamadı.',
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
