<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\InstagramMediaType;
use App\Enums\InstagramPostStatus;
use App\Models\AiGeneratedImage;
use App\Models\AiImagePrompt;
use App\Models\BulkImport;
use App\Models\InstagramPost;
use App\Models\VertexGeneration;
use App\Services\Ai\AiImageGenerator;
use App\Services\InstagramCaptionAiService;
use App\Services\InstagramImageAiService;
use App\Services\TelegramNotifier;
use App\Services\UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bulk Schedule — tek bir satırı işleyen async job.
 *
 * Akış:
 *   1. Görseli kaynağa göre hazırla (ai/url/upload/panel)
 *      - panel modunda görsel/video atlanır → post Draft statüsünde oluşur
 *   2. Caption boşsa konu varsa AI üret (panel modu hariç — boş bırakılır)
 *   3. Hashtag boşsa AI'dan al
 *   4. InstagramPost::create + status=Scheduled (panel ise Draft)
 *   5. BulkImport sayaçlarını güncelle
 *
 * Hata durumunda: BulkImport.errors JSON'una eklenir, post oluşturulmaz.
 */
final class ProcessBulkImportRowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1; // Her satır tek deneme — kullanıcı raporu görüp manuel düzeltebilir

    public int $timeout = 180; // 3 dk (AI image üretim 30 sn olabilir)

    /**
     * @param  array<string, mixed>  $rowData  Validated row data from BulkScheduleImporter
     */
    public function __construct(
        public readonly int $bulkImportId,
        public readonly array $rowData,
    ) {}

    public function handle(
        UploadService $uploadService,
        InstagramImageAiService $imageAiService,
        InstagramCaptionAiService $captionAiService,
        AiImageGenerator $imageGenerator,
    ): void {
        $bulkImport = BulkImport::find($this->bulkImportId);
        if ($bulkImport === null) {
            return;
        }

        // İlk job çalıştığında status=processing'e çek
        if ($bulkImport->status === BulkImport::STATUS_PENDING) {
            $bulkImport->update([
                'status' => BulkImport::STATUS_PROCESSING,
                'started_at' => now(),
            ]);
        }

        $rowNumber = (int) ($this->rowData['row_number'] ?? 0);

        try {
            $mediaType = InstagramMediaType::from($this->rowData['tip']);
            $source    = (string) ($this->rowData['gorsel_kaynagi'] ?? 'panel');
            $isPanelMode = $source === 'panel';

            // 1. Görsel/video hazırla — panel mode'da atlanır
            $media = $this->prepareMedia(
                $bulkImport,
                $mediaType,
                $uploadService,
                $imageAiService,
                $imageGenerator,
            );
            $imagePath        = $media['image_path'];
            $videoPath        = $media['video_path'];
            $aiImageId        = $media['ai_image_id'] ?? null;
            $extraImagePaths  = $media['extra_image_paths'] ?? [];

            // 2. Caption + hashtag
            //    Öncelik sırası:
            //    a) Excel'de caption/hashtag dolu → direkt kullan
            //    b) Vertex kaynağından ig_caption/ig_hashtags geliyorsa → kullan (AI çağrısı yok)
            //    c) konu varsa → AI'dan üret
            //    d) panel mode → boş bırak
            $caption = (string) ($this->rowData['caption'] ?? '');
            /** @var list<string> $hashtags */
            $hashtags = (array) ($this->rowData['hashtags'] ?? []);
            $konu = (string) ($this->rowData['konu'] ?? '');

            $vertexCaption = (string) ($media['ig_caption'] ?? '');
            $vertexHashtags = (string) ($media['ig_hashtags'] ?? '');

            if ($caption === '' && $vertexCaption !== '') {
                $caption = $vertexCaption;
            }
            if ($hashtags === [] && $vertexHashtags !== '') {
                $hashtags = array_values(array_filter(
                    preg_split('/[\s,]+/', $vertexHashtags) ?: [],
                    static fn (string $h): bool => $h !== '',
                ));
            }

            if ($caption === '' && $konu !== '') {
                $aiResult = $captionAiService->generateFromTopic($konu);
                if ($aiResult['success'] ?? false) {
                    $caption = (string) ($aiResult['caption'] ?? '');
                    if ($hashtags === []) {
                        $hashtags = (array) ($aiResult['hashtags'] ?? []);
                    }
                } elseif (! $isPanelMode) {
                    throw new \RuntimeException('AI caption üretilemedi: ' . ($aiResult['message'] ?? 'bilinmeyen hata'));
                }
            }

            // 3. Video duration (FFprobe varsa)
            $videoDuration = null;
            if ($videoPath !== null) {
                $videoDuration = $uploadService->probeVideoDuration($videoPath);
            }

            // 4. Post oluştur — panel mode'da Draft (kullanıcı görsel ekleyip planlayacak)
            $post = InstagramPost::create([
                'media_type'             => $mediaType,
                'image_path'             => $imagePath,
                'video_path'             => $videoPath,
                'video_duration_seconds' => $videoDuration,
                'caption'                => $caption,
                'hashtags'               => $hashtags,
                'scheduled_at'           => $this->rowData['scheduled_at'],
                'status'                 => $isPanelMode ? InstagramPostStatus::Draft : InstagramPostStatus::Scheduled,
                'created_by'             => $bulkImport->created_by,
                'publish_to_facebook'    => (bool) $this->rowData['facebook'] && $mediaType->supportsFacebookCrossPost(),
                'bulk_import_id'         => $bulkImport->id,
            ]);

            // AI görsel üretildiyse, AiGeneratedImage'ı bu post'a bağla — bulk
            // durum sayfası ve /admin/ai-images detay modal'larında izlenebilir.
            if ($aiImageId !== null) {
                $aiImageRecord = AiGeneratedImage::find($aiImageId);
                AiGeneratedImage::where('id', $aiImageId)->update(['instagram_post_id' => $post->id]);

                // Defansif kurtarma: post.image_path bir şekilde boş kaldıysa
                // (race condition, beklenmedik null) AiGeneratedImage'taki path'le doldur
                if (empty($post->image_path) && $aiImageRecord !== null && ! empty($aiImageRecord->image_path)) {
                    Log::warning('Bulk: post.image_path empty after create, recovering from AiGeneratedImage', [
                        'bulk_import_id'  => $this->bulkImportId,
                        'row'             => $rowNumber,
                        'post_id'         => $post->id,
                        'ai_image_id'     => $aiImageId,
                        'ai_image_path'   => $aiImageRecord->image_path,
                    ]);
                    $post->update(['image_path' => $aiImageRecord->image_path]);
                }
            }

            // Carousel için ek görseller (galeri kaynağı + carousel_count > 1)
            if (! empty($extraImagePaths) && is_array($extraImagePaths)) {
                foreach (array_values($extraImagePaths) as $idx => $path) {
                    \App\Models\InstagramPostImage::create([
                        'instagram_post_id' => $post->id,
                        'image_path'        => $path,
                        'sort_order'        => $idx + 1,
                    ]);
                }
            }

            $vertexGenId = $media['vertex_generation_id'] ?? null;
            if ($vertexGenId !== null) {
                VertexGeneration::where('id', $vertexGenId)->increment('usage_count');
            }

            Log::info('Bulk row created successfully', [
                'bulk_import_id' => $this->bulkImportId,
                'row'            => $rowNumber,
                'post_id'        => $post->id,
                'media_type'     => $mediaType->value,
                'image_path'     => $post->image_path,
                'has_caption'    => $caption !== '',
                'hashtag_count'  => count($hashtags),
                'status'         => $post->status->value,
            ]);

            $bulkImport->recordRowResult(true);
        } catch (\Throwable $e) {
            Log::warning('Bulk import row failed', [
                'bulk_import_id' => $this->bulkImportId,
                'row'            => $rowNumber,
                'error'          => $e->getMessage(),
            ]);

            $bulkImport->recordRowResult(false, [
                'row'     => $rowNumber,
                'message' => 'Satır işlenemedi: ' . $e->getMessage(),
            ]);

            // Telegram bildirim — cron tabanlı bulk import arka planda çalıştığı
            // için kullanıcı hatayı görmez; Telegram'a anında düşsün.
            $detailUrl = rescue(
                fn () => route('admin.instagram-posts.bulk.show', $this->bulkImportId),
                null,
                false,
            );
            TelegramNotifier::notifyAdminError(
                'Bulk import satır hatası',
                [
                    'bulk_id' => '#' . $this->bulkImportId,
                    'satir'   => $rowNumber,
                    'tarih'   => (string) ($this->rowData['tarih'] ?? '—'),
                    'kaynak'  => (string) ($this->rowData['gorsel_kaynagi'] ?? '—'),
                    'hata'    => $e->getMessage(),
                ],
                $detailUrl,
                emoji: '⚠️',
                cacheKey: "bulk_row_fail:{$this->bulkImportId}:{$rowNumber}",
            );
        }
    }

    /**
     * Queue worker job'u tries limitini aştığında veya timeout/fatal exception
     * durumunda Laravel bu metodu çağırır. handle() try/catch dışına çıkan
     * hatalar (örn. process timeout, OOM) burada yakalanır — aksi halde
     * BulkImport sonsuza pending kalırdı.
     */
    public function failed(\Throwable $exception): void
    {
        $bulkImport = BulkImport::find($this->bulkImportId);
        if ($bulkImport === null) {
            return;
        }

        $rowNumber = (int) ($this->rowData['row_number'] ?? 0);

        Log::error('Bulk import row job failed (timeout/fatal)', [
            'bulk_import_id' => $this->bulkImportId,
            'row'            => $rowNumber,
            'error'          => $exception->getMessage(),
        ]);

        $bulkImport->recordRowResult(false, [
            'row'     => $rowNumber,
            'message' => 'Satır işlenemedi (zaman aşımı veya kritik hata): ' . $exception->getMessage(),
        ]);

        // Kritik hata (timeout/OOM/fatal) → Telegram'a yüksek öncelikli bildirim
        $detailUrl = rescue(
            fn () => route('admin.instagram-posts.bulk.show', $this->bulkImportId),
            null,
            false,
        );
        TelegramNotifier::notifyAdminError(
            'Bulk import job KRİTİK HATA (timeout/fatal)',
            [
                'bulk_id' => '#' . $this->bulkImportId,
                'satir'   => $rowNumber,
                'tarih'   => (string) ($this->rowData['tarih'] ?? '—'),
                'hata'    => $exception->getMessage(),
            ],
            $detailUrl,
            cacheKey: "bulk_row_fatal:{$this->bulkImportId}:{$rowNumber}",
        );
    }

    /**
     * Görsel/video kaynağa göre hazırla.
     *
     * @return array{image_path: ?string, video_path: ?string, ai_image_id: ?int}
     *   ai_image_id : Üretilen AiGeneratedImage kayıt id'si (post create edildikten
     *                 sonra instagram_post_id'ye bağlamak için kullanılır)
     *
     * @throws \RuntimeException
     */
    private function prepareMedia(
        BulkImport $bulkImport,
        InstagramMediaType $mediaType,
        UploadService $uploadService,
        InstagramImageAiService $imageAiService,
        AiImageGenerator $imageGenerator,
    ): array {
        $source = (string) $this->rowData['gorsel_kaynagi'];
        $value  = (string) $this->rowData['gorsel_degeri'];
        $konu   = (string) $this->rowData['konu'];

        $isVideo = $mediaType === InstagramMediaType::Reels
            || ($mediaType === InstagramMediaType::Story && str_ends_with(mb_strtolower($value), '.mp4'));

        $base = ['image_path' => null, 'video_path' => null, 'ai_image_id' => null, 'gallery_image_id' => null, 'extra_image_paths' => []];

        return match ($source) {
            'ai'        => $this->prepareViaAi($bulkImport, $mediaType, $value !== '' ? $value : $konu, $imageAiService, $imageGenerator) + $base,
            'url'       => $this->prepareViaUrl($value, $isVideo, $uploadService) + $base,
            'upload'    => $this->prepareViaUpload($bulkImport, $value, $isVideo, $uploadService) + $base,
            'galeri'    => $this->prepareViaGallery($mediaType) + $base,
            'kutuphane' => $this->prepareViaImageLibrary($mediaType, $konu) + $base,
            'vertex'    => $this->prepareViaVertex($mediaType, $value !== '' ? $value : $konu) + $base,
            'panel'     => $base,
            default     => throw new \RuntimeException("Bilinmeyen görsel kaynağı: {$source}"),
        };
    }

    /**
     * Görsel kütüphanesi akışı: konu metni ile akıllı eşleşme yapar,
     * least-used görseli seçer.
     *
     * @return array{image_path: ?string, video_path: null, ai_image_id: null, gallery_image_id: null, extra_image_paths: list<string>, library_image_id: int}
     *
     * @throws \RuntimeException
     */
    private function prepareViaImageLibrary(InstagramMediaType $mediaType, string $konu): array
    {
        $mediaTypeStr  = $mediaType->value === 'image' ? 'feed' : $mediaType->value;
        $carouselCount = max(1, min((int) ($this->rowData['carousel_count'] ?? 1), 10));

        /** @var \App\Services\MediaLibraryMatcher $matcher */
        $matcher = app(\App\Services\MediaLibraryMatcher::class);

        if ($carouselCount > 1 && $mediaType === InstagramMediaType::Image) {
            $images = $matcher->pickMultiple($konu, 'feed', $carouselCount);
            if ($images->isEmpty()) {
                throw new \RuntimeException(
                    "Kütüphanede '{$mediaTypeStr}' tipinde görsel yok. " .
                    '/admin/gorsel-kutuphanesi sayfasından görsel ekle veya gorsel_kaynagi=ai/url/upload kullan.',
                );
            }

            $primary = $images->first();
            $extra = $images->slice(1)->map(fn ($i) => (string) $i->image_path)->values()->all();

            foreach ($images as $img) {
                $matcher->markUsed($img);
            }

            return [
                'image_path'        => (string) $primary->image_path,
                'video_path'        => null,
                'ai_image_id'       => null,
                'gallery_image_id'  => null,
                'extra_image_paths' => $extra,
                'library_image_id'  => $primary->id,
            ];
        }

        $img = $matcher->pickBest(
            konu: $konu,
            mediaType: $mediaTypeStr,
            explicitTheme: (string) ($this->rowData['theme'] ?? '') ?: null,
            explicitMood: (string) ($this->rowData['mood'] ?? '') ?: null,
        );

        if ($img === null) {
            throw new \RuntimeException(
                "Kütüphanede '{$mediaTypeStr}' tipinde uygun görsel bulunamadı (konu: '{$konu}'). " .
                '/admin/gorsel-kutuphanesi sayfasından bu konuya uygun tag\'li görsel ekle.',
            );
        }

        $matcher->markUsed($img);

        return [
            // Görsel veya video poster
            'image_path'        => (string) $img->image_path,
            // Video varsa video_path doldur (Reels akışı için)
            'video_path'        => $img->is_video && $img->video_path ? (string) $img->video_path : null,
            'ai_image_id'       => null,
            'gallery_image_id'  => null,
            'extra_image_paths' => [],
            'library_image_id'  => $img->id,
        ];
    }

    /**
     * Galeri akışı: özel günün galerisinden least-used görsel seçer.
     *
     * @return array{image_path: ?string, video_path: null, ai_image_id: null, gallery_image_id: int, extra_image_paths: list<string>}
     *
     * @throws \RuntimeException
     */
    private function prepareViaGallery(InstagramMediaType $mediaType): array
    {
        $tarih = (string) ($this->rowData['tarih'] ?? '');
        if ($tarih === '') {
            throw new \RuntimeException('galeri kaynağı için tarih boş olamaz.');
        }

        $specialDay = \App\Models\SpecialDay::query()
            ->whereDate('date', $tarih)
            ->orderBy('id')
            ->first();

        if ($specialDay === null) {
            throw new \RuntimeException("'{$tarih}' tarihi için özel gün kaydı yok. /admin/ozel-gunler sayfasından eklemen gerek.");
        }

        $mediaTypeStr = $mediaType->value === 'image' ? 'feed' : $mediaType->value;
        $carouselCount = max(1, min((int) ($this->rowData['carousel_count'] ?? 1), 10));

        /** @var \App\Services\SpecialDayImageSelector $selector */
        $selector = app(\App\Services\SpecialDayImageSelector::class);

        if ($carouselCount > 1 && $mediaType === InstagramMediaType::Image) {
            // Carousel — N görsel seç
            $images = $selector->pickMultiple($specialDay, 'feed', $carouselCount);
            if ($images->isEmpty()) {
                throw new \RuntimeException("'{$specialDay->name}' galerisinde Feed görseli yok. /admin/ozel-gunler/{$specialDay->id}/galeri sayfasından ekle.");
            }

            $primary = $images->first();
            $extra = $images->slice(1)->map(fn ($i) => (string) $i->image_path)->values()->all();

            // Tüm görsellerin usage_count'ını artır
            foreach ($images as $img) {
                $selector->markUsed($img);
            }

            return [
                'image_path'        => (string) $primary->image_path,
                'video_path'        => null,
                'ai_image_id'       => null,
                'gallery_image_id'  => $primary->id,
                'extra_image_paths' => $extra,
            ];
        }

        $img = $selector->pickOne($specialDay, $mediaTypeStr);
        if ($img === null) {
            throw new \RuntimeException("'{$specialDay->name}' galerisinde '{$mediaTypeStr}' tipinde görsel yok. /admin/ozel-gunler/{$specialDay->id}/galeri sayfasından ekle.");
        }

        $selector->markUsed($img);

        return [
            'image_path'        => (string) $img->image_path,
            'video_path'        => null,
            'ai_image_id'       => null,
            'gallery_image_id'  => $img->id,
            'extra_image_paths' => [],
        ];
    }

    /**
     * Vertex galerisi akışı: tag, prompt adı, keyword veya generation ID ile eşleştir.
     *
     * gorsel_degeri formatları:
     *  - Sayısal ID → doğrudan o generation'ı kullan
     *  - Metin → tag > prompt adı > keyword sırasıyla eşleştir
     *
     * @return array{image_path: ?string, video_path: null, ai_image_id: null, gallery_image_id: null, extra_image_paths: list<string>, vertex_generation_id: int, ig_caption: ?string, ig_title: ?string, ig_hashtags: ?string}
     *
     * @throws \RuntimeException
     */
    private function prepareViaVertex(InstagramMediaType $mediaType, string $value): array
    {
        $aspectRatio = match ($mediaType) {
            InstagramMediaType::Image => '1:1',
            InstagramMediaType::Story => '9:16',
            InstagramMediaType::Reels => '9:16',
        };

        $carouselCount = max(1, min((int) ($this->rowData['carousel_count'] ?? 1), 10));

        /** @var \App\Services\VertexImageSelector $selector */
        $selector = app(\App\Services\VertexImageSelector::class);

        if ($carouselCount > 1 && $mediaType === InstagramMediaType::Image) {
            $images = $selector->pickMultiple($value, $aspectRatio, $carouselCount);
            if ($images->isEmpty()) {
                throw new \RuntimeException(
                    "Vertex galerisinde '{$value}' ile eşleşen {$aspectRatio} görsel bulunamadı. " .
                    "Önce /admin/vertex sayfasından ilgili konuda görsel üretin.",
                );
            }

            $primary = $images->first();
            $extra = $images->slice(1)->map(fn ($g) => (string) $g->output_image_path)->values()->all();

            return [
                'image_path'           => (string) $primary->output_image_path,
                'video_path'           => null,
                'ai_image_id'          => null,
                'gallery_image_id'     => null,
                'extra_image_paths'    => $extra,
                'vertex_generation_id' => $primary->id,
                'ig_caption'           => $primary->ig_caption,
                'ig_title'             => $primary->ig_title,
                'ig_hashtags'          => $primary->ig_hashtags,
            ];
        }

        $generation = $selector->pickBest($value, $aspectRatio);

        if ($generation === null) {
            throw new \RuntimeException(
                "Vertex galerisinde '{$value}' ile eşleşen {$aspectRatio} görsel bulunamadı. " .
                "Önce /admin/vertex sayfasından ilgili konuda görsel üretin veya prompt'a tag ekleyin.",
            );
        }

        return [
            'image_path'           => (string) $generation->output_image_path,
            'video_path'           => null,
            'ai_image_id'          => null,
            'gallery_image_id'     => null,
            'extra_image_paths'    => [],
            'vertex_generation_id' => $generation->id,
            'ig_caption'           => $generation->ig_caption,
            'ig_title'             => $generation->ig_title,
            'ig_hashtags'          => $generation->ig_hashtags,
        ];
    }

    /**
     * AI ile görsel hazırla. Template seçilmişse:
     *  - Template prompt'unda {topic} placeholder'ı varsa promptOrTopic ile replace edilir
     *  - Template'in reference_image_path'i varsa image-to-image (enhance) flow'u
     *  - Yoksa text-to-image (generate)
     * Template yoksa: legacy davranış — promptOrTopic doğrudan generate'a gider.
     *
     * @return array{image_path: ?string, video_path: ?string, ai_image_id: ?int}
     */
    private function prepareViaAi(
        BulkImport $bulkImport,
        InstagramMediaType $mediaType,
        string $promptOrTopic,
        InstagramImageAiService $service,
        AiImageGenerator $generator,
    ): array {
        if ($mediaType === InstagramMediaType::Reels) {
            throw new \RuntimeException('Reels için AI görsel üretim desteklenmiyor (video AI yok). url, upload veya panel kullanın.');
        }

        $aspectRatio = $service->defaultAspectRatio($mediaType->value);
        $template    = $this->resolvePromptTemplate($bulkImport);
        $finalPrompt = $this->buildFinalPrompt($template, $promptOrTopic);

        // Reference image varsa image-to-image, yoksa text-to-image
        $hasReferenceImage = $template !== null
            && ! empty($template->reference_image_path)
            && is_file(public_path('uploads/' . $template->reference_image_path));

        Log::info('Bulk AI image generation start', [
            'bulk_import_id'    => $this->bulkImportId,
            'row'               => (int) ($this->rowData['row_number'] ?? 0),
            'media_type'        => $mediaType->value,
            'aspect_ratio'      => $aspectRatio,
            'mode'              => $hasReferenceImage ? 'enhance (image-to-image)' : 'generate (text-to-image)',
            'template_id'       => $template?->id,
            'template_name'     => $template?->name,
            'has_reference_img' => $hasReferenceImage,
            'prompt_chars'      => mb_strlen($finalPrompt),
        ]);

        if ($hasReferenceImage) {
            $result = $generator->enhance(
                sourceAbsolutePath: public_path('uploads/' . $template->reference_image_path),
                prompt: $finalPrompt,
                folder: 'instagram',
                slugSuffix: 'bulk-' . $this->bulkImportId,
                source: AiGeneratedImage::SOURCE_INSTAGRAM_CREATE,
                userId: $bulkImport->created_by,
                promptTemplateId: $template->id,
                aspectRatio: $aspectRatio,
            );
        } else {
            $result = $generator->generate(
                prompt: $finalPrompt,
                aspectRatio: $aspectRatio,
                folder: 'instagram',
                slugSuffix: 'bulk-' . $this->bulkImportId,
                source: AiGeneratedImage::SOURCE_INSTAGRAM_CREATE,
                userId: $bulkImport->created_by,
                promptTemplateId: $template?->id,
            );
        }

        if (! ($result['success'] ?? false)) {
            Log::warning('Bulk AI image generation failed', [
                'bulk_import_id' => $this->bulkImportId,
                'row'            => (int) ($this->rowData['row_number'] ?? 0),
                'message'        => $result['message'] ?? 'unknown',
                'aspect_ratio'   => $aspectRatio,
            ]);
            throw new \RuntimeException('AI görsel: ' . ($result['message'] ?? 'üretim başarısız'));
        }

        /** @var AiGeneratedImage $aiImage */
        $aiImage = $result['image'];

        Log::info('Bulk AI image generation success', [
            'bulk_import_id' => $this->bulkImportId,
            'row'            => (int) ($this->rowData['row_number'] ?? 0),
            'ai_image_id'    => $aiImage->id,
            'image_path'     => $result['image_path'] ?? null,
            'model_used'     => $result['model_used'] ?? null,
            'dimensions'     => ($result['width'] ?? '?') . 'x' . ($result['height'] ?? '?'),
            'aspect_ratio'   => $aspectRatio,
            'cost_usd'       => $result['cost_usd'] ?? null,
        ]);

        return [
            'image_path'  => (string) $result['image_path'],
            'video_path'  => null,
            'ai_image_id' => $aiImage->id,
        ];
    }

    /**
     * Per-row override > bulk form-level default > null.
     */
    private function resolvePromptTemplate(BulkImport $bulkImport): ?AiImagePrompt
    {
        // 1. Per-row override (Excel'den)
        $rowName = trim((string) ($this->rowData['prompt_template'] ?? ''));
        if ($rowName !== '') {
            $byName = AiImagePrompt::active()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($rowName, 'UTF-8')])
                ->first();
            if ($byName !== null) {
                return $byName;
            }
            // İsim eşleşmedi → form default'a düş
        }

        // 2. Form-level default (bulk_imports.prompt_template_id)
        if ($bulkImport->prompt_template_id !== null) {
            return AiImagePrompt::find($bulkImport->prompt_template_id);
        }

        return null;
    }

    /**
     * Template prompt'undaki {topic} / {konu} / {prompt} placeholder'larını
     * row'un konu veya gorsel_degeri değeri ile replace eder.
     * Template yoksa promptOrTopic aynen döner.
     */
    private function buildFinalPrompt(?AiImagePrompt $template, string $promptOrTopic): string
    {
        if ($template === null) {
            return $promptOrTopic;
        }

        $replacements = [
            '{topic}'  => $promptOrTopic,
            '{konu}'   => $promptOrTopic,
            '{prompt}' => $promptOrTopic,
            '{value}'  => $promptOrTopic,
        ];

        $prompt = (string) $template->prompt;
        $prompt = strtr($prompt, $replacements);

        // Eğer template'te placeholder yoksa ve promptOrTopic boş değilse,
        // sona ekle ki konu bilgisi tamamen kaybolmasın
        if ($promptOrTopic !== '' && $prompt === (string) $template->prompt) {
            $prompt .= "\n\nKonu / Bağlam: " . $promptOrTopic;
        }

        return $prompt;
    }

    /**
     * @return array{image_path: ?string, video_path: ?string}
     */
    private function prepareViaUrl(string $url, bool $isVideo, UploadService $uploadService): array
    {
        // SSRF koruma: localhost/internal IP'leri reddet
        $this->assertSafeRemoteUrl($url);

        // Public URL → indir → temp dosyaya yaz → UploadedFile sarmalı → uploadImage/uploadVideo
        // Boyut limiti: video için 100MB, görsel için 8MB (max:5)
        $maxBytes = $isVideo ? 104_857_600 : 8_388_608;

        $response = Http::timeout(60)
            ->retry(2, 2000)
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("URL'den indirilemedi (HTTP {$response->status()}): {$url}");
        }

        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) < 100) {
            throw new \RuntimeException("URL'den indirilen dosya boş veya çok küçük: {$url}");
        }

        if (strlen($bytes) > $maxBytes) {
            throw new \RuntimeException(sprintf(
                "URL'den indirilen dosya çok büyük (%.1f MB). Max %.0f MB.",
                strlen($bytes) / 1_048_576,
                $maxBytes / 1_048_576,
            ));
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk-');
        if ($tmpPath === false || file_put_contents($tmpPath, $bytes) === false) {
            throw new \RuntimeException('Geçici dosya yazılamadı.');
        }

        try {
            $mime = (string) (function_exists('mime_content_type') ? mime_content_type($tmpPath) : 'application/octet-stream');
            $extension = $this->extensionFromUrl($url) ?: $this->extensionFromMime($mime);
            $filename = 'bulk-' . Str::random(8) . '.' . $extension;

            $upload = new UploadedFile($tmpPath, $filename, $mime, null, true);

            if ($isVideo) {
                if (! in_array($mime, ['video/mp4', 'video/quicktime'], true)) {
                    throw new \RuntimeException("Beklenen video formatı (mp4/mov) değil, gelen: {$mime}");
                }
                $videoPath = $uploadService->uploadVideo($upload, 'instagram-videos', 'bulk-' . now()->format('YmdHis'));
                return ['image_path' => null, 'video_path' => $videoPath];
            }

            if (! str_starts_with($mime, 'image/')) {
                throw new \RuntimeException("Beklenen görsel formatı değil, gelen: {$mime}");
            }

            $imagePath = $uploadService->uploadImage($upload, 'instagram', 'bulk-' . now()->format('YmdHis'));
            return ['image_path' => $imagePath, 'video_path' => null];
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * ZIP içindeki dosyayı extract edip kaydet.
     *
     * @return array{image_path: ?string, video_path: ?string}
     */
    private function prepareViaUpload(BulkImport $bulkImport, string $filename, bool $isVideo, UploadService $uploadService): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Sunucuda php-zip extension yüklü değil. upload mode kullanılamıyor — hosting yöneticisinden zip extension istenmeli.');
        }

        if (empty($bulkImport->zip_path)) {
            throw new \RuntimeException('upload mode için ZIP dosyası yüklenmedi.');
        }

        $zipFullPath = public_path('uploads/' . $bulkImport->zip_path);
        if (! is_file($zipFullPath)) {
            throw new \RuntimeException("ZIP dosyası bulunamadı: {$bulkImport->zip_path}");
        }

        // Path traversal koruma
        if (str_contains($filename, '..') || str_starts_with($filename, '/')) {
            throw new \RuntimeException("Güvenli olmayan dosya adı: {$filename}");
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFullPath) !== true) {
            throw new \RuntimeException('ZIP arşivi açılamadı.');
        }

        try {
            $bytes = $zip->getFromName($filename);
            if ($bytes === false) {
                throw new \RuntimeException("ZIP içinde '{$filename}' bulunamadı.");
            }
        } finally {
            $zip->close();
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk-zip-');
        if ($tmpPath === false || file_put_contents($tmpPath, $bytes) === false) {
            throw new \RuntimeException('Geçici dosya yazılamadı.');
        }

        try {
            $mime = (string) (function_exists('mime_content_type') ? mime_content_type($tmpPath) : 'application/octet-stream');
            $upload = new UploadedFile($tmpPath, basename($filename), $mime, null, true);

            if ($isVideo) {
                if (! in_array($mime, ['video/mp4', 'video/quicktime'], true)) {
                    throw new \RuntimeException("ZIP içindeki '{$filename}' beklenen video formatı değil ({$mime}).");
                }
                $videoPath = $uploadService->uploadVideo($upload, 'instagram-videos', 'bulk-' . now()->format('YmdHis'));
                return ['image_path' => null, 'video_path' => $videoPath];
            }

            if (! str_starts_with($mime, 'image/')) {
                throw new \RuntimeException("ZIP içindeki '{$filename}' beklenen görsel formatı değil ({$mime}).");
            }

            $imagePath = $uploadService->uploadImage($upload, 'instagram', 'bulk-' . now()->format('YmdHis'));
            return ['image_path' => $imagePath, 'video_path' => null];
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    private function extensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'], true) ? $ext : null;
    }

    /**
     * SSRF koruması: yerel/internal IP'lere veya hostname'lere istek atılmasın.
     * RFC 1918 (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16), loopback, link-local,
     * yerel hostname ve metadata service IP'leri reddedilir.
     *
     * @throws \RuntimeException
     */
    private function assertSafeRemoteUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw new \RuntimeException("Geçersiz URL: {$url}");
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException("Sadece http/https URL'leri kabul edilir: {$scheme}://...");
        }

        $host = strtolower($parts['host']);

        // Yerel hostname'ler
        if (in_array($host, ['localhost', '0.0.0.0', '::1'], true) || str_ends_with($host, '.local')) {
            throw new \RuntimeException("Yerel/internal hostname kabul edilmiyor: {$host}");
        }

        // IP adresi mi? Internal range kontrolü
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // RFC1918 + loopback + link-local + AWS metadata + diğer reserved
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException("Internal/private IP kabul edilmiyor: {$host}");
            }
        }
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'video/mp4'       => 'mp4',
            'video/quicktime' => 'mov',
            default           => 'bin',
        };
    }
}
