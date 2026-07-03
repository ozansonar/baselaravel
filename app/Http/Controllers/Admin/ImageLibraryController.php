<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BatchAutoTagImagesJob;
use App\Jobs\ProcessBulkImportRowJob;
use App\Models\BulkImport;
use App\Models\MediaLibraryImage;
use App\Services\ImageLibraryExporter;
use App\Services\ImageLibraryPlanBuilder;
use App\Services\MediaLibraryAutoTagger;
use App\Services\MediaLibraryGapAnalyzer;
use App\Services\UploadService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * /admin/gorsel-kutuphanesi — Genel görsel kütüphanesi (Hibrit kütüphane sistemi).
 *
 * Faz 1: Liste + manuel upload + tag/tema/mood düzenleme + silme + bulk import eşleşme
 * Faz 2 (sonra): AI auto-tag (Gemini Vision)
 * Faz 3 (sonra): /analiz — gap raporu + AI öneri
 * Faz 4 (sonra): "Kütüphaneden Plan Oluştur" bulk akışı
 */
final class ImageLibraryController extends Controller
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    public function index(Request $request): View
    {
        $query = MediaLibraryImage::query()->with('creator');

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->string('media_type')->toString());
        }
        if ($request->filled('theme')) {
            $query->where('theme', $request->string('theme')->toString());
        }
        if ($request->filled('mood')) {
            $query->where('mood', $request->string('mood')->toString());
        }
        if ($request->filled('season')) {
            $query->where('season', $request->string('season')->toString());
        }
        if ($request->filled('usage')) {
            match ($request->string('usage')->toString()) {
                'never' => $query->where('usage_count', 0),
                'used'  => $query->where('usage_count', '>', 0),
                default => null,
            };
        }
        if ($request->filled('visibility')) {
            $query->where('visibility', $request->string('visibility')->toString());
        }
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($qb) use ($q): void {
                $qb->where('description', 'like', "%{$q}%")
                   ->orWhere('alt_text', 'like', "%{$q}%")
                   ->orWhereRaw('LOWER(JSON_UNQUOTE(tags)) LIKE ?', ['%' . mb_strtolower($q) . '%']);
            });
        }

        $images = $query->orderByDesc('id')->paginate(48)->withQueryString();

        return view('admin.image-library.index', [
            'images' => $images,
            'stats'  => $this->buildStats(),
        ]);
    }

    public function uploadForm(): View
    {
        return view('admin.image-library.upload');
    }

    /**
     * DropzoneJS için tek dosya upload endpoint — her POST 1 dosya işler,
     * paralel istekler async çalışır.
     *
     * Form-level fields (media_type, theme, mood, ...) her request'te tekrar gelir.
     * Video için 'poster_path' (önceden ayrı yüklenmiş poster path'i) zorunlu.
     */
    public function uploadSingle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'        => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:102400'],
            'media_type'  => ['required', 'string', 'in:feed,reels,story'],
            'theme'       => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::THEMES)],
            'mood'        => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::MOODS)],
            'season'      => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::SEASONS)],
            'tags'        => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'alt_text'    => ['nullable', 'string', 'max:250'],
            'visibility'  => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::VISIBILITIES)],
            'poster_path' => ['nullable', 'string'], // Video için önceden upload edilmiş poster path
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $isVideo = in_array($ext, ['mp4', 'mov'], true);

        if ($isVideo && empty($validated['poster_path'])) {
            return response()->json([
                'success' => false,
                'message' => 'Video için poster (thumbnail) görseli zorunlu — önce poster yükle.',
            ], 422);
        }

        $tagsArray = $this->parseTagString($validated['tags'] ?? null);

        if ($isVideo) {
            $videoPath = $this->uploadService->uploadVideo(
                $file,
                'image-library',
                'il-vid-' . now()->format('Ymd-His') . '-' . substr((string) bin2hex(random_bytes(3)), 0, 6),
            );

            $duration = $this->uploadService->probeVideoDuration($videoPath);

            $img = MediaLibraryImage::create([
                'image_path'             => $validated['poster_path'],
                'video_path'             => $videoPath,
                'is_video'               => true,
                'video_duration_seconds' => $duration,
                'media_type'             => $validated['media_type'],
                'tags'                   => $tagsArray !== [] ? $tagsArray : null,
                'theme'                  => $validated['theme']  ?? null,
                'mood'                   => $validated['mood']   ?? null,
                'season'                 => $validated['season'] ?? null,
                'description'            => $validated['description'] ?? null,
                'alt_text'               => $validated['alt_text']    ?? null,
                'visibility'             => $validated['visibility']  ?? MediaLibraryImage::VISIBILITY_PRIVATE,
                'created_by'             => $request->user()?->id,
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Video eklendi.',
                'id'        => $img->id,
                'thumbnail' => upload_url($img->image_path, 'thumb'),
                'is_video'  => true,
                'duration'  => $duration,
            ]);
        }

        $path = $this->uploadService->uploadImage(
            $file,
            'image-library',
            'il-' . now()->format('Ymd-His') . '-' . substr((string) bin2hex(random_bytes(3)), 0, 6),
        );

        $img = MediaLibraryImage::create([
            'image_path'  => $path,
            'is_video'    => false,
            'media_type'  => $validated['media_type'],
            'tags'        => $tagsArray !== [] ? $tagsArray : null,
            'theme'       => $validated['theme']  ?? null,
            'mood'        => $validated['mood']   ?? null,
            'season'      => $validated['season'] ?? null,
            'description' => $validated['description'] ?? null,
            'alt_text'    => $validated['alt_text']    ?? null,
            'visibility'  => $validated['visibility']  ?? MediaLibraryImage::VISIBILITY_PRIVATE,
            'created_by'  => $request->user()?->id,
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Görsel eklendi.',
            'id'        => $img->id,
            'thumbnail' => upload_url($path, 'thumb'),
            'is_video'  => false,
        ]);
    }

    /**
     * Video poster (thumbnail) için ayrı upload endpoint.
     * Önce poster yüklenir, dönen path video upload'larında kullanılır.
     */
    public function uploadVideoPoster(Request $request): JsonResponse
    {
        $request->validate([
            'poster' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $path = $this->uploadService->uploadImage(
            $request->file('poster'),
            'image-library/posters',
            'poster-' . now()->format('Ymd-His'),
        );

        return response()->json([
            'success'    => true,
            'poster_path'=> $path,
            'thumbnail'  => upload_url($path, 'thumb'),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files'       => ['required', 'array', 'min:1', 'max:30'],
            'files.*'     => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:102400'], // 100 MB (video için)
            'media_type'  => ['required', 'string', 'in:feed,reels,story'],
            'theme'       => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::THEMES)],
            'mood'        => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::MOODS)],
            'season'      => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::SEASONS)],
            'tags'        => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'alt_text'    => ['nullable', 'string', 'max:250'],
            'visibility'  => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::VISIBILITIES)],
            'poster'      => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'files.required' => 'En az 1 dosya seçmelisin.',
            'files.max'      => 'Tek seferde en fazla 30 dosya yükleyebilirsin.',
            'files.*.mimes'  => 'Sadece görsel (jpg/png/webp) veya video (mp4/mov) yükleyebilirsin.',
            'files.*.max'    => 'Her dosya en fazla 100 MB olabilir.',
            'poster.image'   => 'Poster görsel olarak yüklenmeli.',
        ]);

        $tagsArray = $this->parseTagString($validated['tags'] ?? null);
        $uploaded = [];

        // Tek poster varsa tüm video'lar için kullanılır (basit varsayım — kullanıcı tek tek upload tercih edebilir)
        $sharedPosterPath = null;
        if ($request->hasFile('poster')) {
            $sharedPosterPath = $this->uploadService->uploadImage(
                $request->file('poster'),
                'image-library/posters',
                'poster-' . now()->format('Ymd-His'),
            );
        }

        // Video varsa poster zorunlu (default placeholder yoksa hata olmasın)
        $hasVideoFile = false;
        foreach ($request->file('files', []) as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['mp4', 'mov'], true)) {
                $hasVideoFile = true;
                break;
            }
        }
        if ($hasVideoFile && $sharedPosterPath === null) {
            return response()->json([
                'success' => false,
                'message' => 'Video yükledin — poster (thumbnail) görseli zorunlu. "Video Posteri" alanına 1 görsel yükle.',
            ], 422);
        }

        DB::transaction(function () use ($request, $validated, $tagsArray, $sharedPosterPath, &$uploaded): void {
            foreach ($request->file('files', []) as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($ext, ['mp4', 'mov'], true);

                if ($isVideo) {
                    // Video upload — poster gerekli (varsa shared, yoksa default)
                    $videoPath = $this->uploadService->uploadVideo(
                        $file,
                        'image-library',
                        'il-vid-' . now()->format('Ymd-His') . '-' . substr((string) bin2hex(random_bytes(3)), 0, 6),
                    );

                    $duration = $this->uploadService->probeVideoDuration($videoPath);

                    // Poster: shared (üstte zorunlu kontrol var, null olamaz)
                    $imagePath = $sharedPosterPath;

                    $img = MediaLibraryImage::create([
                        'image_path'             => $imagePath,
                        'video_path'             => $videoPath,
                        'is_video'               => true,
                        'video_duration_seconds' => $duration,
                        'media_type'             => $validated['media_type'],
                        'tags'                   => $tagsArray !== [] ? $tagsArray : null,
                        'theme'                  => $validated['theme']  ?? null,
                        'mood'                   => $validated['mood']   ?? null,
                        'season'                 => $validated['season'] ?? null,
                        'description'            => $validated['description'] ?? null,
                        'alt_text'               => $validated['alt_text']    ?? null,
                        'visibility'             => $validated['visibility']  ?? MediaLibraryImage::VISIBILITY_PRIVATE,
                        'created_by'             => $request->user()?->id,
                    ]);

                    $uploaded[] = [
                        'id'        => $img->id,
                        'url'       => upload_url($imagePath, 'md'),
                        'is_video'  => true,
                        'duration'  => $duration,
                    ];
                } else {
                    // Görsel upload (mevcut akış)
                    $path = $this->uploadService->uploadImage(
                        $file,
                        'image-library',
                        'il-' . now()->format('Ymd-His') . '-' . substr((string) bin2hex(random_bytes(3)), 0, 6),
                    );

                    $img = MediaLibraryImage::create([
                        'image_path'  => $path,
                        'is_video'    => false,
                        'media_type'  => $validated['media_type'],
                        'tags'        => $tagsArray !== [] ? $tagsArray : null,
                        'theme'       => $validated['theme']  ?? null,
                        'mood'        => $validated['mood']   ?? null,
                        'season'      => $validated['season'] ?? null,
                        'description' => $validated['description'] ?? null,
                        'alt_text'    => $validated['alt_text']    ?? null,
                        'visibility'  => $validated['visibility']  ?? MediaLibraryImage::VISIBILITY_PRIVATE,
                        'created_by'  => $request->user()?->id,
                    ]);

                    $uploaded[] = [
                        'id'       => $img->id,
                        'url'      => upload_url($path, 'md'),
                        'is_video' => false,
                    ];
                }
            }
        });

        $videoCount = count(array_filter($uploaded, static fn ($u) => $u['is_video'] ?? false));
        $imageCount = count($uploaded) - $videoCount;
        $msg = '';
        if ($imageCount > 0) $msg .= "{$imageCount} görsel ";
        if ($videoCount > 0) $msg .= "{$videoCount} video ";
        $msg .= 'kütüphaneye eklendi.';

        return response()->json([
            'success'  => true,
            'message'  => $msg,
            'uploaded' => $uploaded,
        ]);
    }

    public function edit(MediaLibraryImage $image): View
    {
        // Kullanım geçmişi: bu görseli (image_path veya video_path) kullanan
        // InstagramPost kayıtları (path eşleşmesi ile)
        $usageHistory = \App\Models\InstagramPost::query()
            ->where(function ($q) use ($image) {
                $q->where('image_path', $image->image_path);
                if ($image->is_video && $image->video_path) {
                    $q->orWhere('video_path', $image->video_path);
                }
            })
            ->orderByDesc('scheduled_at')
            ->limit(50)
            ->get(['id', 'caption', 'media_type', 'scheduled_at', 'published_at', 'status']);

        // Benzer görseller — tag overlap + tema + mood eşleşmesi
        $similar = $this->findSimilarImages($image, 8);

        return view('admin.image-library.edit', [
            'image'        => $image,
            'usageHistory' => $usageHistory,
            'similar'      => $similar,
        ]);
    }

    /**
     * Benzer görsel bulma — tag overlap + tema + mood + tip skoru.
     * Vision embedding değil — pratik tag-bazlı eşleşme. Maliyet $0.
     *
     * @return \Illuminate\Support\Collection<int, MediaLibraryImage>
     */
    private function findSimilarImages(MediaLibraryImage $image, int $limit = 8): \Illuminate\Support\Collection
    {
        $tags = $image->tagList();

        $query = MediaLibraryImage::query()
            ->where('id', '!=', $image->id);

        if ($tags === [] && ! $image->theme) {
            // Hiç meta yok — sadece tip eşleşmesi
            return $query->where('media_type', $image->media_type)->leastUsed()->limit($limit)->get();
        }

        // Skorlama: tag overlap + tema eşleşmesi + mood + tip
        // En basit yaklaşım: aynı tema VEYA tag overlap olan görseller
        $candidates = $query
            ->where(function ($q) use ($image, $tags) {
                if ($image->theme) {
                    $q->where('theme', $image->theme);
                }
                if ($tags !== []) {
                    $q->orWhere(function ($subQ) use ($tags) {
                        $subQ->withAnyTag($tags);
                    });
                }
            })
            ->limit(50) // pre-filter
            ->get();

        // Memory'de skorla
        $scored = $candidates->map(function (MediaLibraryImage $cand) use ($image, $tags) {
            $score = 0;

            // Tag overlap (ana kriter)
            $candTags = $cand->tagList();
            $overlap = count(array_intersect($tags, $candTags));
            $score += $overlap * 10;

            // Tema eşleşmesi
            if ($cand->theme && $cand->theme === $image->theme) $score += 5;

            // Mood eşleşmesi
            if ($cand->mood && $cand->mood === $image->mood) $score += 3;

            // Tip eşleşmesi (tercihen aynı tip)
            if ($cand->media_type === $image->media_type) $score += 2;

            // Mevsim eşleşmesi
            if ($cand->season && $cand->season === $image->season) $score += 2;

            $cand->similarity_score = $score;
            return $cand;
        })->filter(fn ($c) => $c->similarity_score > 0)
          ->sortByDesc('similarity_score')
          ->take($limit)
          ->values();

        return $scored;
    }

    public function update(Request $request, MediaLibraryImage $image): RedirectResponse
    {
        $validated = $request->validate([
            'media_type'        => ['required', 'string', 'in:feed,reels,story'],
            'theme'             => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::THEMES)],
            'mood'              => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::MOODS)],
            'season'            => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::SEASONS)],
            'tags'              => ['nullable', 'string', 'max:500'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'alt_text'          => ['nullable', 'string', 'max:250'],
            'sort_order'        => ['nullable', 'integer', 'min:0', 'max:65535'],
            'ai_generated_tags' => ['nullable', 'boolean'],
            'visibility'        => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::VISIBILITIES)],
        ]);

        $tagsArray = $this->parseTagString($validated['tags'] ?? null);

        $image->update([
            'media_type'        => $validated['media_type'],
            'tags'              => $tagsArray !== [] ? $tagsArray : null,
            'theme'             => $validated['theme']  ?? null,
            'mood'              => $validated['mood']   ?? null,
            'season'            => $validated['season'] ?? null,
            'description'       => $validated['description'] ?? null,
            'alt_text'          => $validated['alt_text']    ?? null,
            'sort_order'        => $validated['sort_order']  ?? $image->sort_order,
            'ai_generated_tags' => (bool) ($validated['ai_generated_tags'] ?? $image->ai_generated_tags),
            'visibility'        => $validated['visibility']  ?? $image->visibility,
        ]);

        return redirect()
            ->route('admin.image-library.index')
            ->with('success', 'Görsel güncellendi.');
    }

    /**
     * Toplu düzenleme — birden fazla görselin tema/mood/mevsim/tip'i tek seferde değiştirilir.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'       => ['required', 'array', 'min:1', 'max:200'],
            'ids.*'     => ['integer'],
            'action'    => ['required', 'string', 'in:set_theme,set_mood,set_season,set_media_type,delete'],
            'value'     => ['nullable', 'string', 'max:60'],
        ]);

        $images = MediaLibraryImage::whereIn('id', $validated['ids'])->get();
        if ($images->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Geçerli görsel bulunamadı.'], 422);
        }

        $action = $validated['action'];
        $value  = $validated['value'] ?? null;

        // Validation per action
        $allowedValues = match ($action) {
            'set_theme'      => MediaLibraryImage::THEMES,
            'set_mood'       => MediaLibraryImage::MOODS,
            'set_season'     => MediaLibraryImage::SEASONS,
            'set_media_type' => MediaLibraryImage::MEDIA_TYPES,
            'delete'         => null,
            default          => null,
        };

        if ($allowedValues !== null && $value !== null && $value !== '' && ! in_array($value, $allowedValues, true)) {
            return response()->json([
                'success' => false,
                'message' => "Değer geçersiz: '{$value}' (beklenen: " . implode('|', $allowedValues) . ')',
            ], 422);
        }

        $count = 0;

        foreach ($images as $img) {
            if ($action === 'delete') {
                try {
                    $this->uploadService->deleteImage($img->image_path);
                } catch (\Throwable) {}
                $img->delete();
                $count++;
                continue;
            }

            $field = match ($action) {
                'set_theme'      => 'theme',
                'set_mood'       => 'mood',
                'set_season'     => 'season',
                'set_media_type' => 'media_type',
            };

            // Boş value → null'a çek (tip hariç — tip zorunlu)
            $newValue = ($value === null || $value === '') ? null : $value;
            if ($field === 'media_type' && $newValue === null) {
                continue; // tip null olamaz, atla
            }

            $img->update([$field => $newValue]);
            $count++;
        }

        $messages = [
            'set_theme'      => "{$count} görselin teması güncellendi",
            'set_mood'       => "{$count} görselin mood'u güncellendi",
            'set_season'     => "{$count} görselin mevsimi güncellendi",
            'set_media_type' => "{$count} görselin tipi güncellendi",
            'delete'         => "{$count} görsel silindi",
        ];

        return response()->json([
            'success' => true,
            'message' => $messages[$action] ?? 'İşlem tamamlandı.',
            'count'   => $count,
        ]);
    }

    public function destroy(MediaLibraryImage $image): JsonResponse
    {
        try {
            $this->uploadService->deleteImage($image->image_path);
        } catch (\Throwable) {
            // Disk silinmedi — DB'den silmeye devam
        }
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Görsel silindi.']);
    }

    /**
     * Görsel-İlk plan oluşturucu — tarih aralığı seç + tip dağılımı → plan preview.
     */
    public function buildPlan(Request $request, ImageLibraryPlanBuilder $builder): JsonResponse
    {
        $validated = $request->validate([
            'start_date'    => ['required', 'date_format:Y-m-d'],
            'end_date'      => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'post_per_day'  => ['required', 'integer', 'min:1', 'max:3'],
            'feed_pct'      => ['required', 'numeric', 'min:0', 'max:1'],
            'story_pct'     => ['required', 'numeric', 'min:0', 'max:1'],
            'reels_pct'     => ['required', 'numeric', 'min:0', 'max:1'],
            'theme'         => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::THEMES)],
            'mood'          => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::MOODS)],
            'season'        => ['nullable', 'string', 'in:' . implode(',', MediaLibraryImage::SEASONS)],
        ]);

        $sum = $validated['feed_pct'] + $validated['story_pct'] + $validated['reels_pct'];
        if (abs($sum - 1.0) > 0.05) {
            return response()->json([
                'success' => false,
                'message' => 'Tip oranları toplamı 1.0 olmalı (şu an: ' . round($sum, 2) . ')',
            ], 422);
        }

        $result = $builder->build([
            'start_date'   => $validated['start_date'],
            'end_date'     => $validated['end_date'],
            'post_per_day' => $validated['post_per_day'],
            'distribution' => [
                'feed'  => $validated['feed_pct'],
                'story' => $validated['story_pct'],
                'reels' => $validated['reels_pct'],
            ],
            'theme'  => $validated['theme']  ?? null,
            'mood'   => $validated['mood']   ?? null,
            'season' => $validated['season'] ?? null,
        ]);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Plan'ı bulk import job olarak dispatch et.
     */
    public function executePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan'                => ['required', 'array', 'min:1', 'max:500'],
            'plan.*.date'         => ['required', 'date_format:Y-m-d'],
            'plan.*.time'         => ['required', 'string'],
            'plan.*.media_type'   => ['required', 'string', 'in:image,reels,story'],
            'plan.*.image_id'     => ['required', 'integer', 'exists:media_library_images,id'],
            'plan.*.suggested_konu' => ['nullable', 'string', 'max:500'],
        ]);

        $bulkImport = BulkImport::create([
            'created_by' => $request->user()?->id,
            'status'     => BulkImport::STATUS_PENDING,
            'total_rows' => count($validated['plan']),
            'errors'     => null,
            'zip_path'   => null,
        ]);

        $rowNumber = 1;
        foreach ($validated['plan'] as $row) {
            // ProcessBulkImportRowJob beklediği rowData formatına çevir
            $scheduledAt = $row['date'] . ' ' . $row['time'] . ':00';

            $rowData = [
                'row_number'      => $rowNumber++,
                'tarih'           => $row['date'],
                'saat'            => $row['time'],
                'tip'             => $row['media_type'],
                'konu'            => (string) ($row['suggested_konu'] ?? ''),
                'caption'         => '',
                'hashtags'        => [],
                'gorsel_kaynagi'  => 'kutuphane',
                'gorsel_degeri'   => '',
                'facebook'        => true,
                'prompt_template' => '',
                'scheduled_at'    => $scheduledAt,
            ];

            ProcessBulkImportRowJob::dispatch($bulkImport->id, $rowData);
        }

        return response()->json([
            'success'   => true,
            'message'   => count($validated['plan']) . ' post işleme alındı.',
            'bulk_id'   => $bulkImport->id,
            'redirect'  => route('admin.instagram-posts.bulk.show', $bulkImport->id),
        ]);
    }

    /**
     * Gap analiz sayfası — kütüphane stok durumu + eksik tip/tema önerileri.
     */
    public function analyze(MediaLibraryGapAnalyzer $analyzer): View
    {
        $analysis = $analyzer->analyze();

        return view('admin.image-library.analyze', [
            'analysis' => $analysis,
        ]);
    }

    /**
     * AI'dan eksik kategori için çekim konusu önerisi al.
     */
    public function aiSuggestions(Request $request, MediaLibraryGapAnalyzer $analyzer): JsonResponse
    {
        $analysis = $analyzer->analyze();

        // En çok eksik kategorileri çıkar (theme × type matrisi → flat list)
        $gaps = [];
        foreach ($analysis['by_theme_type'] as $type => $themes) {
            foreach ($themes as $theme => $stats) {
                if ($stats['missing'] > 0) {
                    $gaps[] = ['type' => $type, 'theme' => $theme, 'missing' => $stats['missing']];
                }
            }
        }
        usort($gaps, static fn ($a, $b) => $b['missing'] <=> $a['missing']);

        $result = $analyzer->aiSuggestions($gaps);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Tüm kütüphaneyi ZIP olarak export et (backup veya başka kuruluma taşıma).
     */
    public function export(ImageLibraryExporter $exporter): StreamedResponse
    {
        $zipPath = $exporter->export();

        $filename = 'image-library-export-' . now()->format('Ymd-His') . '.zip';

        return response()->streamDownload(static function () use ($zipPath): void {
            readfile($zipPath);
            @unlink($zipPath);
        }, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * ZIP dosyası import et — manifest.json + images/* + videos/*
     */
    public function import(Request $request, ImageLibraryExporter $exporter): RedirectResponse
    {
        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:524288'], // 512 MB
        ], [
            'zip_file.required' => 'ZIP dosyası seç.',
            'zip_file.mimes'    => 'Sadece .zip dosyası kabul edilir.',
            'zip_file.max'      => 'ZIP en fazla 512 MB olabilir.',
        ]);

        $tmpPath = $request->file('zip_file')->getRealPath();

        $result = $exporter->import($tmpPath);

        return redirect()->route('admin.image-library.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Toplu auto-tag — etiketsiz veya AI-etiketsiz görselleri arka planda tag'le.
     */
    public function batchAutoTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:untagged,all,selected'],
            'ids'   => ['nullable', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $scope = $validated['scope'] ?? 'untagged';

        $query = MediaLibraryImage::query();
        if ($scope === 'untagged') {
            $query->where('ai_generated_tags', false);
        } elseif ($scope === 'selected' && ! empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }

        $imageIds = $query->limit(200)->pluck('id')->all();

        if ($imageIds === []) {
            return response()->json([
                'success' => false,
                'message' => 'Tag\'lenecek görsel bulunamadı (' . $scope . ').',
            ], 422);
        }

        $estCost = round(count($imageIds) * \App\Services\MediaLibraryAutoTagger::COST_PER_IMAGE, 4);

        BatchAutoTagImagesJob::dispatch($imageIds, $request->user()?->id);

        return response()->json([
            'success'  => true,
            'message'  => count($imageIds) . ' görsel arka planda etiketleniyor (~$' . number_format($estCost, 4) . '). Bittiğinde Telegram\'a bildirim gelecek.',
            'count'    => count($imageIds),
            'est_cost' => $estCost,
        ]);
    }

    /**
     * Tek görsel için AI auto-tag analizi → JSON öneri (DB'ye yazmaz).
     * Kullanıcı önerileri inceleyip onaylar → "Uygula" butonu DB'ye yazar.
     */
    public function autoTag(MediaLibraryImage $image, MediaLibraryAutoTagger $tagger): JsonResponse
    {
        $absolutePath = public_path('uploads/' . $image->image_path);

        $result = $tagger->analyzeImage($absolutePath);

        return response()->json($result, $result['success'] ? 200 : 422);
    }


    /**
     * Üst widget'a kütüphane stok istatistikleri.
     *
     * @return array<string, mixed>
     */
    private function buildStats(): array
    {
        $byType = MediaLibraryImage::query()
            ->select('media_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('media_type')
            ->pluck('cnt', 'media_type')
            ->all();

        $byTheme = MediaLibraryImage::query()
            ->whereNotNull('theme')
            ->select('theme', DB::raw('COUNT(*) as cnt'))
            ->groupBy('theme')
            ->pluck('cnt', 'theme')
            ->all();

        return [
            'total'       => MediaLibraryImage::count(),
            'by_type'     => [
                'feed'  => (int) ($byType['feed']  ?? 0),
                'reels' => (int) ($byType['reels'] ?? 0),
                'story' => (int) ($byType['story'] ?? 0),
            ],
            'by_theme'    => $byTheme,
            'never_used'  => MediaLibraryImage::where('usage_count', 0)->count(),
            'total_usage' => (int) MediaLibraryImage::sum('usage_count'),
        ];
    }

    /**
     * "süt, sabah, kahvaltı" → ["süt", "sabah", "kahvaltı"]
     *
     * @return list<string>
     */
    private function parseTagString(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn (string $t): string => trim($t),
            explode(',', $raw),
        )));
    }
}
