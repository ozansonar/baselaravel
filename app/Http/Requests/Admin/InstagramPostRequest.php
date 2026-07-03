<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\InstagramAspectRatio;
use Illuminate\Foundation\Http\FormRequest;

final class InstagramPostRequest extends FormRequest
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
        $mediaType = (string) $this->input('media_type', 'image');

        // Aspect ratio + dimension kuralları media_type'a göre değişir.
        // Feed (image): 4:5 (0.8) ile 1.91:1 (1.91) arası
        // Story:        9:16 (0.5625) civarı, 0.5-0.7 arası kabul edilir
        // Reels:        görsel değil video, kapsam dışı
        if ($mediaType === 'story') {
            $aspectRule = new InstagramAspectRatio(0.5, 0.7, 'Story');
            $dimensionRule = 'dimensions:min_width=320,min_height=320,max_width=1440';
        } else {
            // image (default)
            $aspectRule = new InstagramAspectRatio(0.8, 1.91, 'Feed Post');
            $dimensionRule = 'dimensions:min_width=320,min_height=320,max_width=1440';
        }

        $imageRules = [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:8192',
            $dimensionRule,
            $aspectRule,
        ];

        // image hiçbir zaman validation rules ile required edilmez —
        // gerekli olup olmadığı withValidator'da belirlenir, çünkü
        // ai_image_path geçerli formatta dolu mu kontrolünün de orada olması gerek.

        // Carousel ek görseller — sadece Image type için, Feed aspect ratio
        $additionalAspectRule = new InstagramAspectRatio(0.8, 1.91, 'Feed Post');

        // Reels: video zorunlu (create veya edit)
        // Story: video opsiyonel (görsel de kabul)
        $videoRules = [
            'nullable',
            'file',
            'mimetypes:video/mp4,video/quicktime',
            'max:102400', // 100 MB (KB cinsinden)
        ];

        if ($this->isMethod('POST') && $mediaType === 'reels') {
            $videoRules[0] = 'required';
        }

        // Süre kontrolü:
        //   1) Client-side: instagram-posts-form.js + <video> metadata
        //      → kullanıcıya anlık feedback, limit dışıysa input temizlenir
        //   2) Server-side: yok (shared hosting'de shell_exec disabled
        //      olduğu için FFprobe rule fatal veriyordu)
        //   3) Son güvence: Meta Graph API limiti aşan videoyu reject eder,
        //      hata AiLogObserver + OrderObserver gibi merkezi bildirim
        //      noktalarına otomatik düşer.

        // scheduled_at dolu ise her zaman gelecek olmalı — action ne olursa olsun
        // (save_draft action'ında bile kullanıcı geçmiş tarih giremesin).
        // 1 dakika tolerans submit anındaki saniye hesabı için.
        $scheduledRules = ['nullable', 'date'];
        if ($this->filled('scheduled_at')) {
            $scheduledRules[] = 'after:' . now()->subMinute()->toIso8601String();
        }

        return [
            'media_type'            => ['nullable', 'string', 'in:image,reels,story'],
            'image'                 => $imageRules,
            'video'                 => $videoRules,
            // Carousel ek görseller — opsiyonel, max 9 (toplam 10 ile sınırlı)
            // Sadece image type'da kullanılabilir — Feed aspect ratio (4:5 - 1.91:1)
            'additional_images'     => ['nullable', 'array', 'max:9'],
            'additional_images.*'   => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
                'dimensions:min_width=320,min_height=320,max_width=1440',
                $additionalAspectRule,
            ],
            // Mevcut ek görsellerden silinecek olanların ID'leri (edit için)
            'remove_image_ids'      => ['nullable', 'array'],
            'remove_image_ids.*'    => ['integer', 'exists:instagram_post_images,id'],
            // Vertex galeriden seçilen carousel görselleri (path'leri)
            'vertex_carousel_paths'   => ['nullable', 'array', 'max:9'],
            'vertex_carousel_paths.*' => ['string', 'max:500'],
            // AI üretimi veya otomatik kırpma sonucu üretilen görsel path'i.
            // Format: "instagram/<filename>" — strict allowlist.
            // Path traversal/prefix kontrolü hem burada hem controller'da
            // (resolveAiImagePath) yapılır — defense-in-depth.
            'ai_image_path'         => [
                'nullable',
                'string',
                'max:255',
                'regex:#^instagram/[A-Za-z0-9._\-/]+$#',
            ],
            'caption'               => ['nullable', 'string', 'max:2000'],
            'hashtags'              => ['nullable', 'string', 'max:1000'],
            'scheduled_at'          => $scheduledRules,
            'action'                => ['nullable', 'string', 'in:save_draft,schedule,publish_now'],
            'publish_to_facebook'   => ['nullable', 'in:0,1'],
        ];
    }

    /**
     * Cross-field validation:
     *   - Feed: file upload VEYA geçerli ai_image_path zorunlu (create)
     *   - Story: file/video upload VEYA geçerli ai_image_path zorunlu (create)
     *   - Reels/Story carousel'i desteklemez
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($v): void {
            $mediaType    = (string) $this->input('media_type', 'image');
            $hasImage     = $this->hasFile('image');
            $hasVideo     = $this->hasFile('video');
            $hasAiImage   = $this->hasValidAiImagePath();

            if ($this->isMethod('POST')) {
                if ($mediaType === 'image' && ! $hasImage && ! $hasAiImage) {
                    $v->errors()->add('image', 'Görsel yüklemelisiniz.');
                }

                if ($mediaType === 'story' && ! $hasImage && ! $hasVideo && ! $hasAiImage) {
                    $v->errors()->add('image', 'Story için görsel veya video yüklemelisiniz.');
                }
            }

            if (in_array($mediaType, ['reels', 'story'], true)) {
                if (is_array($this->file('additional_images'))) {
                    $v->errors()->add('additional_images', 'Reels ve Story için carousel desteklenmez. Sadece tek media yükleyin.');
                }
            }
        });
    }

    /**
     * Returns true only when ai_image_path is a non-empty string that fits
     * the strict allowlist format (`instagram/<safe-chars>`). Used by both
     * the validation rules and withValidator's cross-field guard so that an
     * attacker can't bypass the file-upload requirement by submitting a
     * garbage ai_image_path.
     */
    private function hasValidAiImagePath(): bool
    {
        $path = trim((string) $this->input('ai_image_path', ''));

        if ($path === '') {
            return false;
        }
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return false;
        }

        return (bool) preg_match('#^instagram/[A-Za-z0-9._\-/]+$#', $path);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required'       => 'Görsel yüklemelisiniz.',
            'image.image'          => 'Yüklenen dosya bir görsel olmalı.',
            'image.mimes'          => 'Sadece JPG, PNG veya WebP yüklenebilir.',
            'image.max'            => 'Görsel en fazla 8 MB olabilir.',
            'image.uploaded'       => 'Görsel sunucuya yüklenemedi. Sunucu PHP yükleme limiti aşılmış olabilir (genelde 2-8 MB). Lütfen daha küçük bir görsel seçin veya hosting ayarlarından upload_max_filesize / post_max_size değerlerini en az 16M yapın.',
            'image.dimensions'     => 'Görsel boyutu Instagram standartlarına uygun değil. En az 320×320, en çok 1440 px genişlik. Aspect ratio 4:5 (dikey) ile 1.91:1 (yatay) arasında olmalı.',
            'additional_images.*.uploaded' => 'Ek görsellerden biri sunucuya yüklenemedi. PHP yükleme limiti aşılmış olabilir — daha küçük dosya deneyin veya hosting limitlerini artırın.',
            'additional_images.*.max'      => 'Carousel görselleri en fazla 8 MB olabilir.',
            'additional_images.*.mimes'    => 'Carousel görselleri sadece JPG, PNG veya WebP olabilir.',
            'additional_images.*.image'    => 'Carousel için yüklenen dosya bir görsel olmalı.',
            'additional_images.*.dimensions' => 'Carousel görselleri Instagram standartlarına uygun değil. Min 320×320, max 1440 px, oran 4:5 ile 1.91:1 arası.',
            'video.required'       => 'Reels için video yüklemelisiniz.',
            'video.file'           => 'Yüklenen video geçerli bir dosya olmalı.',
            'video.mimetypes'      => 'Sadece MP4 veya MOV formatında video yüklenebilir.',
            'video.max'            => 'Video en fazla 100 MB olabilir.',
            'video.uploaded'       => 'Video sunucuya yüklenemedi. PHP yükleme limiti aşılmış olabilir (Reels için tipik dosya 20-80 MB). Hosting ayarlarından upload_max_filesize ve post_max_size değerlerini en az 128M yapın.',
            'caption.max'          => 'Caption en fazla 2000 karakter olabilir.',
            'hashtags.max'         => 'Hashtag alanı en fazla 1000 karakter olabilir.',
            'scheduled_at.date'    => 'Yayın tarihi geçerli bir tarih olmalı.',
            'scheduled_at.after'   => 'Planlanan yayın tarihi geçmişte olamaz. Lütfen ileri bir tarih seç.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function hashtagsArray(): array
    {
        $raw = (string) $this->input('hashtags', '');

        if ($raw === '') {
            return [];
        }

        // Split by comma, whitespace, or newline, filter empties
        $parts = preg_split('/[\s,]+/u', $raw) ?: [];
        $clean = [];

        foreach ($parts as $part) {
            $tag = trim($part, " \t\n\r\0\x0B#");
            if ($tag === '') {
                continue;
            }
            $clean[] = $tag;
        }

        // Instagram hard limit: 30 hashtags
        return array_slice(array_unique($clean), 0, 30);
    }
}
