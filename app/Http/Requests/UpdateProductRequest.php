<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_featured'   => $this->boolean('is_featured'),
            'is_new'        => $this->boolean('is_new'),
            'allow_reviews' => $this->boolean('allow_reviews'),
            'show_related'  => $this->boolean('show_related'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id'       => ['required', 'integer', Rule::exists('categories', 'id')],
            'name'              => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description'       => ['nullable', 'string', 'max:50000'],
            'price'             => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'discounted_price'  => ['nullable', 'numeric', 'min:0', 'gt:price'],
            'stock'             => ['nullable', 'integer', 'min:0'],
            'unit'              => ['nullable', 'string', 'max:50'],
            'sku'               => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'status'            => ['nullable', new Enum(ProductStatus::class)],
            'is_featured'       => ['nullable', 'boolean'],
            'is_new'            => ['nullable', 'boolean'],
            'allow_reviews'     => ['nullable', 'boolean'],
            'show_related'      => ['nullable', 'boolean'],
            'image'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'images'            => ['nullable', 'array', 'max:10'],
            'images.*'          => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
            'meta_title'             => ['nullable', 'string', 'max:60'],
            'meta_description'       => ['nullable', 'string', 'max:160'],
            'nutritions'             => ['nullable', 'array', 'max:30'],
            'nutritions.*.name'      => ['required_with:nutritions', 'string', 'max:100'],
            'nutritions.*.per_value' => ['nullable', 'string', 'max:50'],
            'nutritions.*.daily_value' => ['nullable', 'string', 'max:50'],
            'shipping_items'             => ['nullable', 'array', 'max:20'],
            'shipping_items.*.type'      => ['required_with:shipping_items', 'string', 'in:shipping,return'],
            'shipping_items.*.content'   => ['required_with:shipping_items', 'string', 'max:255'],
            'features'                   => ['nullable', 'array', 'max:10'],
            'features.*.icon'            => ['required_with:features', 'string', 'max:100'],
            'features.*.text'            => ['required_with:features', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required'     => 'Kategori seçimi zorunludur.',
            'category_id.exists'       => 'Seçilen kategori bulunamadı.',
            'name.required'            => 'Ürün adı zorunludur.',
            'price.required'           => 'Fiyat zorunludur.',
            'price.min'                => 'Fiyat 0\'dan küçük olamaz.',
            'discounted_price.gt'      => 'Karşılaştırma fiyatı satış fiyatından yüksek olmalıdır.',
            'sku.unique'               => 'Bu stok kodu zaten kullanılıyor.',
            'image.max'                => 'Görsel en fazla 2 MB olabilir.',
            'images.max'               => 'En fazla 10 galeri görseli yükleyebilirsiniz.',
            'images.*.max'             => 'Her görsel en fazla 2 MB olabilir.',
            'nutritions.*.name.required_with' => 'Besin değeri adı zorunludur.',
            'shipping_items.*.content.required_with' => 'Kargo/İade bilgisi içeriği zorunludur.',
            'features.*.text.required_with' => 'Özellik metni zorunludur.',
        ];
    }
}
