<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\LanguageService;
use App\Services\UploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Toplu yüklemede bırakılan tek görsel.
 *
 * Dropzone her dosyayı ayrı istekle gönderiyor; bu istek de tek dosyalık. Yüz
 * dosya tek POST'ta gitseydi gövde post_max_size'ı aşar, PHP gövdeyi komple atar
 * ve CSRF alanı da onunla gittiği için istek 419 dönerdi.
 *
 * Ortak alanlar (dil, kategori, durum, sıra) her istekle birlikte geliyor:
 * kullanıcı üstteki seçimi yükleme sırasında değiştirirse sonraki dosyalar yeni
 * seçimle kaydedilsin.
 */
final class StoreGalleryBulkImageRequest extends FormRequest
{
    /** @var list<string> Tekli formla aynı liste; ikisi ayrışmamalı. */
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Dropzone'un acceptedFiles biçimi: ".jpg,.png,..." */
    public static function acceptAttribute(): string
    {
        return implode(',', array_map(static fn (string $e): string => '.' . $e, self::ALLOWED_EXTENSIONS));
    }

    /**
     * Görsel başına tavan.
     *
     * Uygulamanın 4 MB'ı ile sunucunun ini sınırının küçüğü geçerli; paylaşımlı
     * hosting'de ini'yi yukarı zorlamak mümkün değil ve ekranda yazan sayı kabul
     * edilenden büyük olmamalı.
     */
    public static function maxBytes(): int
    {
        return app(UploadService::class)->limits(4 * 1024 * 1024, 1)['per_file'];
    }

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
            'image' => [
                'required',
                'image',
                'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
                'max:' . (int) floor(self::maxBytes() / 1024),
            ],
            // Öğe bu dilin satırı olarak doğuyor; çevirisi sonradan yapılır.
            'locale' => ['required', 'string', Rule::in(app(LanguageService::class)->activeCodes())],
            // Kategori de çevrilmiş: seçilen kategorinin aynı dilde olması gerek,
            // yoksa Türkçe öğe İngilizce kategoriye bağlanırdı.
            'gallery_category_id' => [
                'nullable',
                'integer',
                Rule::exists('gallery_categories', 'id')->where('locale', $this->input('locale')),
            ],
            'is_active'  => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Dosya alınamadı.',
            'image.image'    => 'Yalnızca görsel yükleyebilirsiniz.',
            'image.mimes'    => 'İzin verilen biçimler: JPG, PNG, WebP.',
            'image.max'      => 'Görsel sunucunun kabul ettiği boyutu aşıyor.',
            'image.uploaded' => 'Görsel yüklenemedi; sunucu sınırını aşıyor olabilir.',
            'gallery_category_id.exists' => 'Seçilen kategori bu dile ait değil.',
        ];
    }
}
