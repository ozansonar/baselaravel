<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MediaLibrary'ye görsel yüklerken kullanılan validation.
 *
 * Dropzone.js paralel olarak her dosya için ayrı POST atar — bu request
 * tek dosyalıdır. `product_name` form-level dropdown'dan seçilir,
 * "Genel" değeri controller'da null'a çevrilir.
 *
 * KARE 1:1 ZORUNLU — `dimensions:ratio=1/1` ile garantilenir. Minimum
 * 1080×1080 (Instagram Feed minimum); önerilen 1500×1500.
 */
final class UploadMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin route group middleware ile zaten korunuyor.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',                                                  // 4 MB
                'dimensions:min_width=1024,min_height=1024,ratio=1/1',
            ],
            'product_name' => ['nullable', 'string', 'max:191'],
            'title'        => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required'   => 'Görsel yüklemelisiniz.',
            'image.image'      => 'Yüklenen dosya bir görsel olmalı.',
            'image.mimes'      => 'Sadece JPG, PNG veya WebP yüklenebilir.',
            'image.max'        => 'Görsel en fazla 4 MB olabilir.',
            'image.uploaded'   => 'Görsel sunucuya yüklenemedi. PHP upload limiti aşılmış olabilir (upload_max_filesize, post_max_size en az 8M olmalı).',
            'image.dimensions' => 'Görsel kare olmalı (1:1 oran), en az 1024×1024 piksel. Önerilen: 1500×1500.',
            'title.max'        => 'Başlık en fazla 191 karakter olabilir.',
        ];
    }
}
