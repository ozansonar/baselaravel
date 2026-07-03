<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InstagramPostStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AutoFixImageRequest;
use App\Http\Requests\Admin\InstagramPostRequest;
use App\Models\InstagramPost;
use App\Models\Product;
use App\Models\Setting;
use App\Services\FacebookPageService;
use App\Services\InstagramCaptionAiService;
use App\Services\InstagramImageAiService;
use App\Services\InstagramImageProcessor;
use App\Services\InstagramService;
use App\Services\TiktokService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class InstagramPostController extends Controller
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly InstagramService $instagramService,
        private readonly InstagramCaptionAiService $captionAiService,
        private readonly InstagramImageAiService $imageAiService,
        private readonly InstagramImageProcessor $imageProcessor,
        private readonly FacebookPageService $facebookService,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->query('per_page')
            : 25;

        // additionalImages eager load: hem N+1 fix (count + galeri için kolleksiyon)
        // hem de görsel galeri lightbox'ı için tüm carousel görselleri tek sorguda gelir
        $query = InstagramPost::query()->with(['author', 'additionalImages'])->latest();

        if ($status === 'trashed') {
            $query->onlyTrashed();
        } elseif ($status !== '') {
            $query->byStatus($status);
        }

        $posts = $query->paginate($perPage)->withQueryString();

        $statusCounts = [
            'draft'     => InstagramPost::where('status', InstagramPostStatus::Draft->value)->count(),
            'scheduled' => InstagramPost::where('status', InstagramPostStatus::Scheduled->value)->count(),
            'published' => InstagramPost::where('status', InstagramPostStatus::Published->value)->count(),
            'failed'    => InstagramPost::where('status', InstagramPostStatus::Failed->value)->count(),
            'trashed'   => InstagramPost::onlyTrashed()->count(),
        ];

        $stats = [
            'total'     => InstagramPost::count(),
            'scheduled' => $statusCounts['scheduled'],
            'published' => $statusCounts['published'],
            'failed'    => $statusCounts['failed'],
        ];

        return view('admin.instagram-posts.index', [
            'posts'        => $posts,
            'stats'        => $stats,
            'statusCounts' => $statusCounts,
            'perPage'      => $perPage,
            'cronSummary'  => $this->instagramCronSummary(),
        ]);
    }

    /**
     * /admin/instagram-posts widget'ı için Instagram cron sağlık + zamanlama bilgileri.
     *
     * @return array{
     *     last_run: ?\Carbon\Carbon,
     *     last_run_human: ?string,
     *     next_run: \Carbon\Carbon,
     *     next_run_in_seconds: int,
     *     interval_label: string,
     *     interval_minutes: int,
     *     healthy: bool,
     *     warning: bool,
     *     scheduled_due: int,
     *     scheduled_total: int,
     *     failed_active: int,
     *     last_published_at: ?\Carbon\Carbon,
     *     other_crons: list<array{name: string, schedule: string, command: string}>,
     * }
     */
    private function instagramCronSummary(): array
    {
        $intervalMinutes = 5;

        $lastRunRaw = (string) \App\Models\Setting::getValue('instagram_cron_last_run', '');
        $lastRun = null;
        if ($lastRunRaw !== '') {
            try { $lastRun = \Carbon\Carbon::parse($lastRunRaw); } catch (\Throwable) {}
        }

        // Sıradaki çalışma — şimdiki dakikayı 5'in katına yuvarla (yukarı)
        $now = now();
        $nextRun = $now->copy()->second(0)->addMinutes($intervalMinutes - ($now->minute % $intervalMinutes));

        // Sağlık eşikleri (5dk aralık için):
        //  - 15dk (3 cycle): warning — ardışık 2 tur kaçmış olabilir
        //  - 60dk: error — cron tamamen durmuş, müdahale şart
        $healthy = false;
        $warning = false;
        if ($lastRun !== null) {
            if ($lastRun->gt($now->copy()->subMinutes(15))) {
                $healthy = true;
            } elseif ($lastRun->gt($now->copy()->subMinutes(60))) {
                $warning = true;
            }
        }

        // Yayın bekleyenler (scheduled_at <= now → bu tur'da denenecek olanlar)
        $scheduledDue = InstagramPost::where('status', InstagramPostStatus::Scheduled->value)
            ->where('scheduled_at', '<=', $now)
            ->count();

        $scheduledTotal = InstagramPost::where('status', InstagramPostStatus::Scheduled->value)->count();

        $failedActive = InstagramPost::where('status', InstagramPostStatus::Failed->value)
            ->where('retry_count', '<', 3)
            ->count();

        $lastPublishedAt = InstagramPost::where('status', InstagramPostStatus::Published->value)
            ->latest('published_at')
            ->first()?->published_at;

        // Diğer IG cron'ları — bilgi amaçlı (routes/console.php ile senkron)
        $otherCrons = [
            ['name' => 'Token Yenileme',     'schedule' => 'Her gün 04:00', 'command' => 'instagram:refresh-token'],
            ['name' => 'Engagement Sync',    'schedule' => 'Her gün 04:30', 'command' => 'instagram:sync-engagement'],
            ['name' => 'API Log Temizliği',  'schedule' => 'Her gün 03:30', 'command' => 'instagram:prune-logs --days=30'],
        ];

        return [
            'last_run'            => $lastRun,
            'last_run_human'      => $lastRun?->diffForHumans(),
            'next_run'            => $nextRun,
            'next_run_in_seconds' => max(0, $now->diffInSeconds($nextRun, false)),
            'interval_label'      => 'Her ' . $intervalMinutes . ' dakikada bir',
            'interval_minutes'    => $intervalMinutes,
            'healthy'             => $healthy,
            'warning'             => $warning,
            'scheduled_due'       => $scheduledDue,
            'scheduled_total'     => $scheduledTotal,
            'failed_active'       => $failedActive,
            'last_published_at'   => $lastPublishedAt,
            'other_crons'         => $otherCrons,
        ];
    }

    public function create(): View
    {
        return view('admin.instagram-posts.create', [
            'products'        => $this->aiProductOptions(),
            'previewSettings' => $this->previewSettings(),
            'fbConfigured'    => $this->isFacebookConfigured(),
        ]);
    }

    /**
     * Calendar view — planlanmış ve yayınlanmış postları takvim formatında göster.
     */
    public function calendar(Request $request): View
    {
        $month = (string) $request->query('month', now()->format('Y-m'));

        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        $posts = InstagramPost::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                  ->orWhereBetween('published_at', [$start, $end]);
            })
            ->orderBy('scheduled_at')
            ->orderBy('published_at')
            ->get();

        // Group by date (Y-m-d)
        $byDate = $posts->groupBy(function (InstagramPost $p) {
            $date = $p->published_at ?? $p->scheduled_at;

            return $date?->format('Y-m-d') ?? '';
        })->forget('');

        return view('admin.instagram-posts.calendar', [
            'month'      => $start->format('Y-m'),
            'monthStart' => $start,
            'monthEnd'   => $end,
            'prevMonth'  => $start->copy()->subMonth()->format('Y-m'),
            'nextMonth'  => $start->copy()->addMonth()->format('Y-m'),
            'byDate'     => $byDate,
            'posts'      => $posts,
        ]);
    }

    /**
     * AJAX: AI ile caption + hashtag üret.
     * - product_id verilirse ürünü context olarak kullanır
     * - topic verilirse serbest tema metnini kullanır
     */
    public function generateCaption(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'topic'      => 'nullable|string|max:500',
            'tone'       => 'nullable|string|in:samimi ve doğal,profesyonel,eğlenceli ve enerjik,nostaljik ve duygusal',
        ]);

        @set_time_limit(120);

        if (! empty($validated['product_id'])) {
            $product = Product::findOrFail($validated['product_id']);
            $result  = $this->captionAiService->generateForProduct($product, $validated['tone'] ?? null);
        } elseif (! empty($validated['topic'])) {
            $result = $this->captionAiService->generateFromTopic($validated['topic'], $validated['tone'] ?? null);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Ürün veya konu seçmelisin.',
            ], 422);
        }

        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }

    /**
     * AJAX: AI ile görsel üret (Imagen 3 / Gemini Image fallback).
     * - product_id veya topic ile prompt context'i zenginleştirir
     * - Üretilen görsel public/uploads/instagram/ altına kaydedilir,
     *   relatif path + URL döner
     * - Form submit'te ai_image_path hidden field varsa controller bu
     *   path'i image_path olarak kullanır (ek upload yapılmaz)
     */
    public function generateImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_type'   => 'required|string|in:image,reels,story',
            'aspect_ratio' => 'nullable|string|in:1:1,3:4,4:3,9:16,16:9',
            'prompt'       => 'nullable|string|max:2000',
            'product_id'   => 'nullable|integer|exists:products,id',
            'topic'        => 'nullable|string|max:500',
        ]);

        @set_time_limit(120);

        $aspectRatio = $validated['aspect_ratio']
            ?? $this->imageAiService->defaultAspectRatio($validated['media_type']);

        // Feed Post Instagram aspect ratio aralığı 4:5 (0.8) ile 1.91:1 arası kabul eder.
        // 9:16 (0.5625) veya 16:9 (1.78 — sınırda ama dik vertical formatlar reddedilir)
        // bu aralığa uymaz → Image type için bu oranlar zorla 1:1'e çekilir.
        if ($validated['media_type'] === 'image' && in_array($aspectRatio, ['9:16'], true)) {
            $aspectRatio = '1:1';
        }
        // Reels / Story için ters durum: 1:1 / 3:4 / 4:3 / 16:9 yerine 9:16 zorla
        if (in_array($validated['media_type'], ['reels', 'story'], true) && $aspectRatio !== '9:16') {
            $aspectRatio = '9:16';
        }

        // Prompt yoksa product/topic context'inden öneri üret
        $prompt = trim((string) ($validated['prompt'] ?? ''));
        if ($prompt === '') {
            $product = ! empty($validated['product_id']) ? Product::find($validated['product_id']) : null;
            $prompt = $this->imageAiService->buildSuggestedPrompt(
                $product,
                $validated['topic'] ?? null,
                $validated['media_type'],
            );
        }

        $result = $this->imageAiService->generate($prompt, $aspectRatio);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * AJAX: Görsel otomatik veya manuel kırp + Instagram standartlarına resize.
     * - mode=auto:   center crop (media_type'a göre 1:1 / 4:5 / 1.91:1 / 9:16 hedef)
     * - mode=manual: CropperJS pixel koordinatları ile kırp
     *
     * Result: ai_image_path olarak hidden input'a yazılır (mevcut AI image akışı
     * ile uyumlu — store/update controller'ı zaten bu path'i tanıyor).
     */
    public function autoFixImage(AutoFixImageRequest $request): JsonResponse
    {
        @set_time_limit(60);

        $mediaType = (string) $request->input('media_type');
        $mode      = (string) $request->input('mode');
        $file      = $request->file('image');

        try {
            if ($mode === 'manual') {
                $processed = $this->imageProcessor->manualCrop($file, $mediaType, [
                    'x'      => (int) $request->input('crop_x'),
                    'y'      => (int) $request->input('crop_y'),
                    'width'  => (int) $request->input('crop_width'),
                    'height' => (int) $request->input('crop_height'),
                ]);
            } else {
                $processed = $this->imageProcessor->autoFix($file, $mediaType);
            }

            // Pipeline: WebP convert + responsive variants (uploadImage zaten yapıyor)
            $imagePath = $this->uploadService->uploadImage(
                $processed,
                'instagram',
                'crop-' . now()->format('Ymd-His'),
            );

            // Temp dosya cleanup (UploadedFile move edilmediyse)
            if (is_file($processed->getPathname())) {
                @unlink($processed->getPathname());
            }

            return response()->json([
                'success'    => true,
                'message'    => $mode === 'manual'
                    ? 'Görsel manuel kırpma ile düzeltildi.'
                    : 'Görsel otomatik kırpma ile düzeltildi.',
                'image_path' => $imagePath,
                'url'        => upload_url($imagePath, 'lg'),
                'mode'       => $mode,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Image auto-fix failed', [
                'error' => $e->getMessage(),
                'mode'  => $mode,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Görsel işlenirken beklenmedik hata: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(InstagramPostRequest $request): RedirectResponse
    {
        $action     = (string) $request->input('action', 'save_draft');
        $mediaType  = \App\Enums\InstagramMediaType::from((string) $request->input('media_type', 'image'));

        $imagePath = null;
        $videoPath = null;
        $videoDurationSeconds = null;

        // Yalnızca aktif media type için ilgili dosyayı kaydet — JS d-none'a güvenmiyoruz,
        // kullanıcı seçim değiştirmeden önce yüklediği dosya hâlâ submit edilebilir.
        $imageAllowed = in_array($mediaType, [
            \App\Enums\InstagramMediaType::Image,
            \App\Enums\InstagramMediaType::Story,
        ], true);
        $videoAllowed = in_array($mediaType, [
            \App\Enums\InstagramMediaType::Reels,
            \App\Enums\InstagramMediaType::Story,
        ], true);

        if ($imageAllowed && $request->hasFile('image')) {
            $imagePath = $this->uploadService->uploadImage(
                $request->file('image'),
                'instagram',
                'post-' . now()->format('Ymd-His'),
            );
        } elseif ($imageAllowed) {
            // AI ile önceden üretilmiş görsel — generateImage endpoint'i zaten
            // public/uploads/instagram/ altına kaydetmiş, sadece path doğrula
            $aiPath = $this->resolveAiImagePath($request->input('ai_image_path'));
            if ($aiPath !== null) {
                $imagePath = $aiPath;
            }
        }

        if ($videoAllowed && $request->hasFile('video')) {
            $videoPath = $this->uploadService->uploadVideo(
                $request->file('video'),
                'instagram-videos',
                'post-' . now()->format('Ymd-His'),
            );
            $videoDurationSeconds = $this->uploadService->probeVideoDuration($videoPath);
        }

        [$status, $scheduledAt] = $this->resolveStatusAndSchedule($action, $request->input('scheduled_at'));

        // Reels/Story Facebook cross-post desteklemez — kullanıcı seçse bile zorla off
        $publishToFacebook = (string) $request->input('publish_to_facebook', '0') === '1'
            && $mediaType->supportsFacebookCrossPost();

        $post = InstagramPost::create([
            'media_type'             => $mediaType,
            'image_path'             => $imagePath,
            'video_path'             => $videoPath,
            'video_duration_seconds' => $videoDurationSeconds,
            'caption'                => $request->input('caption'),
            'hashtags'                => $request->hashtagsArray(),
            'scheduled_at'           => $scheduledAt,
            'status'                 => $status,
            'created_by'             => $request->user()?->id,
            'publish_to_facebook'    => $publishToFacebook,
        ]);

        // Carousel: ek görseller sadece Image type'ında, Reels/Story'de görmezden gel
        if ($mediaType->supportsCarousel()) {
            $this->saveAdditionalImages($post, $request);
        }

        if ($action === 'publish_now') {
            return $this->publishAndRedirect($post);
        }

        return redirect()
            ->route('admin.instagram-posts.index')
            ->with('success', $this->successMessageFor($status));
    }

    private function saveAdditionalImages(InstagramPost $post, InstagramPostRequest $request): void
    {
        $base = $post->additionalImages()->max('sort_order') ?? 0;

        // Vertex galeriden seçilen carousel görselleri (path zaten mevcut)
        $vertexPaths = $request->input('vertex_carousel_paths', []);
        if (is_array($vertexPaths) && $vertexPaths !== []) {
            $vtxAdded = 0;
            foreach ($vertexPaths as $path) {
                if (trim((string) $path) === '') {
                    continue;
                }
                $post->additionalImages()->create([
                    'image_path' => $path,
                    'sort_order' => $base + $vtxAdded + 1,
                ]);
                $vtxAdded++;
            }
            $base += $vtxAdded;
        }

        // Dosya yükleme ile eklenen carousel görselleri
        $files = $request->file('additional_images') ?? [];
        if (! is_array($files) || $files === []) {
            return;
        }

        foreach ($files as $index => $file) {
            $path = $this->uploadService->uploadImage(
                $file,
                'instagram',
                'post-' . $post->id . '-extra-' . ($base + $index + 1) . '-' . now()->format('His'),
            );

            $post->additionalImages()->create([
                'image_path' => $path,
                'sort_order' => $base + $index + 1,
            ]);
        }
    }

    private function removeAdditionalImages(InstagramPost $post, InstagramPostRequest $request): void
    {
        $ids = $request->input('remove_image_ids', []);
        if (! is_array($ids) || $ids === []) {
            return;
        }

        $images = $post->additionalImages()->whereIn('id', $ids)->get();
        foreach ($images as $img) {
            $this->uploadService->deleteImage($img->image_path);
            $img->delete();
        }
    }

    public function edit(InstagramPost $instagramPost): View|RedirectResponse
    {
        // Yayınlanmış postlar düzenlenemez — show sayfasına yönlendir
        if ($instagramPost->status === InstagramPostStatus::Published) {
            return redirect()->route('admin.instagram-posts.show', $instagramPost->id);
        }

        // Yayın anı — cron çalışıyor, race condition önlemi
        if ($instagramPost->status === InstagramPostStatus::Publishing) {
            return redirect()->route('admin.instagram-posts.index')
                ->with('warning', 'Bu post şu an yayınlanıyor (~30 saniye sürer). Yayın bittikten sonra tekrar dene.');
        }

        // Yayın tarihi geçmiş Scheduled post — cron'un alması bekleniyor, edit anlamsız.
        // Failed durumunda edit serbest (manuel müdahale akışı).
        if ($instagramPost->status === InstagramPostStatus::Scheduled
            && $instagramPost->scheduled_at !== null
            && $instagramPost->scheduled_at->isPast()) {
            return redirect()->route('admin.instagram-posts.index')
                ->with('warning', 'Bu postun yayın tarihi geçmiş. Cron en yakın zamanda otomatik yayınlayacak. '
                    . 'Düzenlemek için silip yeniden oluştur veya cron çalışmasını bekle.');
        }

        return view('admin.instagram-posts.edit', [
            'post'            => $instagramPost->load(['logs', 'additionalImages']),
            'products'        => $this->aiProductOptions(),
            'previewSettings' => $this->previewSettings(),
            'fbConfigured'    => $this->isFacebookConfigured(),
        ]);
    }

    public function show(InstagramPost $instagramPost): View
    {
        return view('admin.instagram-posts.show', [
            'post' => $instagramPost->load(['logs', 'additionalImages', 'author']),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    private function aiProductOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()
            ->where('status', ProductStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Form'un Instagram önizleme + paylaşım hedefi paneli için site ayarları.
     *
     * @return array{site_name: string, logo_url: ?string, ig_username: string}
     */
    private function previewSettings(): array
    {
        $logoPath = Setting::getValue('site_logo');

        return [
            'site_name'   => Setting::getValue('site_name', 'Orhan Babanın Çiftliği') ?? 'Orhan Babanın Çiftliği',
            'logo_url'    => $logoPath ? upload_url($logoPath) : null,
            'ig_username' => Setting::getValue('instagram_username', 'orhanbabaninciftligi') ?? 'orhanbabaninciftligi',
        ];
    }

    private function isFacebookConfigured(): bool
    {
        return ! empty(Setting::getValue('instagram_facebook_page_id'))
            && ! empty(Setting::getValue('instagram_facebook_page_token'));
    }

    public function update(InstagramPostRequest $request, InstagramPost $instagramPost): RedirectResponse
    {
        // Race condition koruması: kullanıcı edit ekranını açıkken durum değişmiş olabilir
        $instagramPost->refresh();

        if ($instagramPost->status === InstagramPostStatus::Publishing) {
            return redirect()->route('admin.instagram-posts.index')
                ->with('warning', 'Bu post şu an yayınlanıyor — değişiklikler kaydedilmedi. Yayın bittikten sonra (yayın başarılıysa kullanılamaz, başarısızsa edit edilebilir).');
        }
        if ($instagramPost->status === InstagramPostStatus::Published) {
            return redirect()->route('admin.instagram-posts.show', $instagramPost->id)
                ->with('warning', 'Bu post zaten yayınlandı — değişiklikler kaydedilmedi.');
        }

        // Defansif: Scheduled durumda + scheduled_at geçmiş → edit penceresi açılmış
        // ama save bekleniyorken cron yakalamış olabilir. update'i reddet.
        if ($instagramPost->status === InstagramPostStatus::Scheduled
            && $instagramPost->scheduled_at !== null
            && $instagramPost->scheduled_at->isPast()) {
            return redirect()->route('admin.instagram-posts.index')
                ->with('warning', 'Bu postun yayın tarihi geçmiş — güncelleme yapılamaz. Cron yakında işleyecek veya silip yeniden oluşturun.');
        }

        $action = (string) $request->input('action', 'save_draft');

        // Media type değişiklik desteği — yayınlanmamışsa kullanıcı değiştirebilir
        $currentMediaType = $instagramPost->media_type ?? \App\Enums\InstagramMediaType::Image;
        $mediaType = $instagramPost->status !== InstagramPostStatus::Published
            ? \App\Enums\InstagramMediaType::from((string) $request->input('media_type', $currentMediaType->value))
            : $currentMediaType;

        $payload = [
            'caption'             => $request->input('caption'),
            'hashtags'            => $request->hashtagsArray(),
            'media_type'          => $mediaType,
            // Reels/Story Facebook desteklemez — zorla kapat
            'publish_to_facebook' => (string) $request->input('publish_to_facebook', '0') === '1'
                && $mediaType->supportsFacebookCrossPost(),
        ];

        // Yalnızca aktif media type için dosya kabul et (JS d-none'a güvenmiyoruz)
        $imageAllowed = in_array($mediaType, [
            \App\Enums\InstagramMediaType::Image,
            \App\Enums\InstagramMediaType::Story,
        ], true);
        $videoAllowed = in_array($mediaType, [
            \App\Enums\InstagramMediaType::Reels,
            \App\Enums\InstagramMediaType::Story,
        ], true);

        if ($imageAllowed && $request->hasFile('image')) {
            $payload['image_path'] = $this->uploadService->replaceImage(
                $request->file('image'),
                'instagram',
                'post-' . now()->format('Ymd-His'),
                $instagramPost->image_path,
            );
        } elseif ($imageAllowed) {
            $aiPath = $this->resolveAiImagePath($request->input('ai_image_path'));
            if ($aiPath !== null) {
                // AI ile yeni üretilmiş görsel: eskisini sil, yenisini ata
                if (! empty($instagramPost->image_path) && $instagramPost->image_path !== $aiPath) {
                    $this->uploadService->deleteImage($instagramPost->image_path);
                }
                $payload['image_path'] = $aiPath;
            }
        }

        if ($videoAllowed && $request->hasFile('video')) {
            if (! empty($instagramPost->video_path)) {
                $this->uploadService->deleteFile($instagramPost->video_path);
            }
            $payload['video_path'] = $this->uploadService->uploadVideo(
                $request->file('video'),
                'instagram-videos',
                'post-' . now()->format('Ymd-His'),
            );
            $payload['video_duration_seconds'] = $this->uploadService->probeVideoDuration($payload['video_path']);
        }

        // Do not allow editing a post that's already published
        if ($instagramPost->status !== InstagramPostStatus::Published) {
            [$status, $scheduledAt] = $this->resolveStatusAndSchedule($action, $request->input('scheduled_at'));
            $payload['status'] = $status;
            $payload['scheduled_at'] = $scheduledAt;

            // Reset error message + retry/notify when user re-schedules or moves back to draft
            // (kullanıcı manuel müdahale ediyor → fresh start, kalıcı fail uyarısı tekrar tetiklenebilsin)
            if (in_array($status, [InstagramPostStatus::Draft, InstagramPostStatus::Scheduled], true)) {
                $payload['error_message']      = null;
                $payload['retry_count']        = 0;
                $payload['notified_failed_at'] = null;
            }
        }

        $instagramPost->update($payload);

        // Carousel ek görsel yönetimi: sadece Image type + yayınlanmamışsa
        if ($instagramPost->status !== InstagramPostStatus::Published && $mediaType->supportsCarousel()) {
            $this->removeAdditionalImages($instagramPost, $request);
            $this->saveAdditionalImages($instagramPost, $request);
        }

        if ($action === 'publish_now' && $instagramPost->status !== InstagramPostStatus::Published) {
            return $this->publishAndRedirect($instagramPost->refresh());
        }

        return redirect()
            ->route('admin.instagram-posts.index')
            ->with('success', 'Instagram gönderisi güncellendi.');
    }

    public function publishNow(InstagramPost $instagramPost): RedirectResponse
    {
        if ($instagramPost->status === InstagramPostStatus::Published) {
            return redirect()
                ->route('admin.instagram-posts.index')
                ->with('error', 'Bu gönderi zaten yayınlanmış.');
        }

        return $this->publishAndRedirect($instagramPost);
    }

    /**
     * "Kalıcı Hata" konumundaki gönderiyi yeniden cron akışına sok:
     * retry_count=0, status=Scheduled, error_message=NULL, notified_failed_at=NULL.
     * 5dk içinde cron tekrar dener (bu fix sonrası caption limiti zaten doğru).
     */
    public function resetRetry(InstagramPost $instagramPost): RedirectResponse
    {
        if ($instagramPost->status === InstagramPostStatus::Published) {
            return redirect()
                ->route('admin.instagram-posts.edit', $instagramPost)
                ->with('error', 'Yayınlanmış gönderiler için retry sıfırlanamaz.');
        }

        $instagramPost->update([
            'status'             => InstagramPostStatus::Scheduled,
            'retry_count'        => 0,
            'error_message'      => null,
            'notified_failed_at' => null,
            // scheduled_at NULL ise hemen yayınlanabilsin
            'scheduled_at'       => $instagramPost->scheduled_at ?? now(),
        ]);

        return redirect()
            ->route('admin.instagram-posts.edit', $instagramPost)
            ->with('success', 'Retry sayısı sıfırlandı, gönderi tekrar planlandı. Cron 5 dakika içinde yeniden deneyecek.');
    }

    /**
     * TikTok cross-post: manuel olarak şimdi tetikle.
     * docs/tiktok.md Bölüm 6.5.
     */
    public function tiktokPublishNow(InstagramPost $instagramPost, TiktokService $tiktokService): RedirectResponse
    {
        if ($instagramPost->status !== InstagramPostStatus::Published) {
            return back()->with('error', 'Önce Instagram\'da yayınlanmış olmalı.');
        }
        if (! $instagramPost->publish_to_tiktok) {
            return back()->with('error', 'Bu post için "TikTok\'a paylaş" işaretli değil.');
        }
        if ($instagramPost->tt_post_id !== null) {
            return back()->with('info', 'TikTok\'ta zaten yayınlanmış.');
        }

        try {
            $result = $tiktokService->publish($instagramPost);

            if ($result['success']) {
                $instagramPost->update([
                    'tt_post_id'       => $result['tt_post_id'] ?? null,
                    'tt_permalink'     => $result['tt_permalink'] ?? null,
                    'tt_published_at'  => isset($result['tt_post_id']) || isset($result['tt_inbox_id']) ? now() : null,
                    'tt_inbox_id'      => $result['tt_inbox_id'] ?? null,
                    'tt_error_message' => null,
                ]);
                $msg = isset($result['tt_inbox_id'])
                    ? 'TikTok Inbox\'a düştü. Mobilde yayınla.'
                    : 'TikTok\'ta yayınlandı.';
                return back()->with('success', $msg);
            }

            $instagramPost->update([
                'tt_error_message' => $result['message'],
                'tt_retry_count'   => $instagramPost->tt_retry_count + 1,
            ]);
            return back()->with('error', 'TikTok paylaşımı başarısız: ' . $result['message']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TT manual publish exception', [
                'post_id' => $instagramPost->id,
                'err'     => $e->getMessage(),
            ]);
            return back()->with('error', 'Beklenmedik hata: ' . $e->getMessage());
        }
    }

    /**
     * TikTok cross-post: retry sayacı sıfırla (cron yeniden denesin).
     */
    public function tiktokResetRetry(InstagramPost $instagramPost): RedirectResponse
    {
        if (! $instagramPost->publish_to_tiktok) {
            return back()->with('error', 'Bu post için TikTok aktif değil.');
        }

        $instagramPost->update([
            'tt_retry_count'   => 0,
            'tt_error_message' => null,
        ]);

        return back()->with('success', 'TikTok retry sayacı sıfırlandı. Cron 5 dakika içinde yeniden deneyecek.');
    }

    public function destroy(InstagramPost $instagramPost): RedirectResponse
    {
        $instagramPost->delete();

        return redirect()
            ->route('admin.instagram-posts.index')
            ->with('success', 'Instagram gönderisi silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $post = InstagramPost::onlyTrashed()->findOrFail($id);
        $post->restore();

        return redirect()
            ->route('admin.instagram-posts.index')
            ->with('success', 'Gönderi geri yüklendi.');
    }

    /**
     * Bulk action: birden fazla post için aynı işlemi yap.
     * Desteklenen action'lar: delete, restore, mark_draft.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bulk_action' => 'required|string|in:delete,restore,mark_draft',
            'ids'         => 'required|array|min:1',
            'ids.*'       => 'integer|exists:instagram_posts,id',
        ]);

        $action = $validated['bulk_action'];
        $ids    = $validated['ids'];
        $count  = 0;

        match ($action) {
            'delete' => $count = $this->bulkDelete($ids),
            'restore' => $count = $this->bulkRestore($ids),
            'mark_draft' => $count = $this->bulkMarkDraft($ids),
        };

        return redirect()
            ->route('admin.instagram-posts.index')
            ->with('success', "{$count} gönderi için işlem tamamlandı.");
    }

    /**
     * @param  list<int>  $ids
     */
    private function bulkDelete(array $ids): int
    {
        return InstagramPost::query()->whereIn('id', $ids)->delete();
    }

    /**
     * @param  list<int>  $ids
     */
    private function bulkRestore(array $ids): int
    {
        $posts = InstagramPost::onlyTrashed()->whereIn('id', $ids)->get();
        $count = 0;
        foreach ($posts as $post) {
            $post->restore();
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<int>  $ids
     */
    private function bulkMarkDraft(array $ids): int
    {
        // Sadece yayınlanmamış postları taslağa al — yayınlananlara dokunma
        return InstagramPost::query()
            ->whereIn('id', $ids)
            ->whereNotIn('status', [InstagramPostStatus::Published->value, InstagramPostStatus::Publishing->value])
            ->update([
                'status'        => InstagramPostStatus::Draft->value,
                'scheduled_at'  => null,
                'error_message' => null,
            ]);
    }

    // ── Helpers ──

    /**
     * @return array{0: InstagramPostStatus, 1: \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null}
     */
    /**
     * AI ile üretilmiş görsel path'i validate et.
     * Sadece "instagram/" prefix'inde, public/uploads altında, gerçek dosya olan
     * path'leri kabul eder. Path traversal saldırılarını engeller.
     */
    private function resolveAiImagePath(mixed $candidate): ?string
    {
        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        // Path traversal koruma: relative segment'ler veya absolute path reddet
        if (str_contains($candidate, '..') || str_starts_with($candidate, '/')) {
            return null;
        }

        // Sadece "instagram/" altındaki dosyalar (generateImage endpoint'i bu folder'a kaydeder)
        if (! str_starts_with($candidate, 'instagram/')) {
            return null;
        }

        $absolute = public_path('uploads/' . $candidate);
        if (! is_file($absolute)) {
            return null;
        }

        return $candidate;
    }

    private function resolveStatusAndSchedule(string $action, mixed $scheduledAtInput): array
    {
        $scheduledAt = null;

        if (! empty($scheduledAtInput)) {
            try {
                $scheduledAt = \Carbon\Carbon::parse((string) $scheduledAtInput);
            } catch (\Throwable) {
                $scheduledAt = null;
            }
        }

        $status = match ($action) {
            'schedule'    => $scheduledAt !== null ? InstagramPostStatus::Scheduled : InstagramPostStatus::Draft,
            'publish_now' => InstagramPostStatus::Publishing,
            default       => InstagramPostStatus::Draft,
        };

        if ($status === InstagramPostStatus::Draft) {
            $scheduledAt = null;
        }

        return [$status, $scheduledAt];
    }

    private function successMessageFor(InstagramPostStatus $status): string
    {
        return match ($status) {
            InstagramPostStatus::Scheduled  => 'Gönderi planlandı, cron zamanı geldiğinde otomatik yayınlayacak.',
            InstagramPostStatus::Publishing => 'Gönderi yayına alınıyor...',
            default                         => 'Gönderi taslak olarak kaydedildi.',
        };
    }

    private function publishAndRedirect(InstagramPost $post): RedirectResponse
    {
        $igResult = $this->instagramService->publish($post);

        // Facebook paylaşımı (bağımsız çalışır — Instagram başarısız olsa bile dener)
        $fbResult = ['success' => true, 'message' => 'Facebook paylaşımı kapalı.'];
        if ($post->publish_to_facebook) {
            $fbResult = $this->facebookService->publish($post->refresh());
        }

        // Manuel "Şimdi Paylaş" da 3+ fail tetikleyebilir — bildirimi cron'la
        // tutarlı tutmak için aynı helper'ı çağır.
        \App\Support\InstagramPostNotifier::notifyIfPermanentlyFailed($post->refresh());

        // Mesaj birleştir
        if ($igResult['success'] && (! $post->publish_to_facebook || $fbResult['success'])) {
            $msg = $igResult['message'];
            if ($post->publish_to_facebook && $fbResult['success']) {
                $msg .= ' Facebook\'ta da paylaşıldı.';
            }

            return redirect()
                ->route('admin.instagram-posts.index')
                ->with('success', $msg);
        }

        $errMsg = '';
        if (! $igResult['success']) {
            $errMsg .= 'Instagram: ' . $igResult['message'];
        }
        if ($post->publish_to_facebook && ! $fbResult['success']) {
            $errMsg .= ($errMsg !== '' ? ' | ' : '') . 'Facebook: ' . $fbResult['message'];
        }

        return redirect()
            ->route('admin.instagram-posts.edit', $post->id)
            ->with('error', $errMsg !== '' ? $errMsg : 'Bilinmeyen hata.');
    }
}
