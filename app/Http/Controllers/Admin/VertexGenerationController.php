<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InstagramMediaType;
use App\Http\Controllers\Controller;
use App\Models\VertexGeneration;
use App\Services\VertexGenerationService;
use App\Services\VertexShareService;
use App\Services\VertexZipExporter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vertex AI üretim geçmişi — read-only liste + detay + sil.
 */
final class VertexGenerationController extends Controller
{
    public function __construct(
        private readonly VertexGenerationService $generationService,
        private readonly VertexShareService $shareService,
        private readonly VertexZipExporter $zipExporter,
    ) {}

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [12, 24, 48, 96], true)
            ? (int) $request->query('per_page')
            : 24;

        $status = (string) $request->query('status', '');
        $promptId = $request->query('prompt_id');
        $aspectRatio = (string) $request->query('aspect_ratio', '');
        $shareStatus = (string) $request->query('share_status', '');

        $query = VertexGeneration::query()
            ->with(['prompt', 'author', 'instagramPosts:id,vertex_generation_id,media_type,status,published_at,scheduled_at,permalink,fb_permalink,fb_published_at,tt_published_at,tt_permalink']);

        if (in_array($status, [
            VertexGeneration::STATUS_PENDING,
            VertexGeneration::STATUS_PROCESSING,
            VertexGeneration::STATUS_COMPLETED,
            VertexGeneration::STATUS_FAILED,
        ], true)) {
            $query->where('status', $status);
        }

        if ($status === VertexGeneration::STATUS_COMPLETED) {
            $query->orderByDesc('finished_at');
        } else {
            $query->latest();
        }
        $query->orderByDesc('id');

        if ($promptId !== null && $promptId !== '') {
            $query->where('prompt_id', (int) $promptId);
        }

        if ($aspectRatio !== '') {
            $query->where('aspect_ratio', $aspectRatio);
        }

        match ($shareStatus) {
            'not_shared'   => $query->whereDoesntHave('instagramPosts'),
            'ig_published' => $query->whereHas('instagramPosts', fn ($q) => $q->whereNotNull('permalink')),
            'fb_published' => $query->whereHas('instagramPosts', fn ($q) => $q->whereNotNull('fb_published_at')),
            'tt_published' => $query->whereHas('instagramPosts', fn ($q) => $q->whereNotNull('tt_published_at')),
            'scheduled'    => $query->whereHas('instagramPosts', fn ($q) => $q->where('status', 'scheduled')),
            'any_shared'   => $query->whereHas('instagramPosts', fn ($q) => $q->where('status', 'published')),
            default        => null,
        };

        $generations = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total'      => VertexGeneration::count(),
            'completed'  => VertexGeneration::completed()->count(),
            'failed'     => VertexGeneration::failed()->count(),
            'pending'    => VertexGeneration::whereIn('status', [VertexGeneration::STATUS_PENDING, VertexGeneration::STATUS_PROCESSING])->count(),
            'shared_ig'  => VertexGeneration::completed()->whereHas('instagramPosts', fn ($q) => $q->whereNotNull('permalink'))->count(),
            'shared_fb'  => VertexGeneration::completed()->whereHas('instagramPosts', fn ($q) => $q->whereNotNull('fb_published_at'))->count(),
            'shared_tt'  => VertexGeneration::completed()->whereHas('instagramPosts', fn ($q) => $q->whereNotNull('tt_published_at'))->count(),
            'scheduled'  => VertexGeneration::completed()->whereHas('instagramPosts', fn ($q) => $q->where('status', 'scheduled'))->count(),
            'not_shared' => VertexGeneration::completed()->whereDoesntHave('instagramPosts')->count(),
        ];

        $prompts = \App\Models\VertexPrompt::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $aspectRatios = VertexGeneration::query()
            ->whereNotNull('aspect_ratio')
            ->distinct()
            ->pluck('aspect_ratio')
            ->sort()
            ->values();

        return view('admin.vertex.generations.index', [
            'generations'  => $generations,
            'stats'        => $stats,
            'status'       => $status,
            'prompts'      => $prompts,
            'aspectRatios' => $aspectRatios,
            'filters'      => [
                'prompt_id'    => $promptId,
                'aspect_ratio' => $aspectRatio,
                'share_status' => $shareStatus,
            ],
        ]);
    }

    public function show(VertexGeneration $generation): JsonResponse
    {
        $generation->load(['prompt', 'instagramPosts']);

        $referenceImages = [];
        if (is_array($generation->reference_images)) {
            foreach ($generation->reference_images as $img) {
                if (! empty($img['path'])) {
                    $referenceImages[] = [
                        'url'  => upload_url($img['path']),
                        'name' => basename($img['path']),
                        'mime' => $img['mime'] ?? 'image/png',
                    ];
                }
            }
        }

        return response()->json([
            'id'                 => $generation->id,
            'status'             => $generation->status,
            'prompt_used'          => $generation->prompt_used,
            'resolved_variables'   => $generation->resolved_variables,
            'reference_images'     => $referenceImages,
            'image_url'          => $generation->output_image_path ? upload_url($generation->output_image_path) : null,
            'model'              => $generation->model,
            'aspect_ratio'       => $generation->aspect_ratio,
            'image_size'         => $generation->image_size,
            'mime_type'          => $generation->mime_type,
            'width'              => $generation->width,
            'height'             => $generation->height,
            'generation_time_ms' => $generation->generation_time_ms,
            'token_count'        => $generation->token_count,
            'usage_count'        => $generation->usage_count,
            'ig_title'           => $generation->ig_title,
            'ig_caption'         => $generation->ig_caption,
            'ig_hashtags'        => $generation->ig_hashtags,
            'error_message'      => $generation->error_message,
            'created_at'         => $generation->created_at?->format('d.m.Y H:i:s'),
            'prompt'             => $generation->prompt ? [
                'id'   => $generation->prompt->id,
                'name' => $generation->prompt->name,
            ] : null,
            'shares'             => $generation->instagramPosts->map(fn ($p) => [
                'id'            => $p->id,
                'status'        => $p->status?->value,
                'media_type'    => $p->media_type?->value ?? 'image',
                'ig_permalink'  => $p->permalink,
                'fb_permalink'  => $p->fb_permalink,
                'published_at'  => $p->published_at?->format('d.m.Y H:i'),
                'scheduled_at'  => $p->scheduled_at?->format('d.m.Y H:i'),
                'fb_published'  => $p->fb_published_at !== null,
                'ig_published'  => $p->permalink !== null,
            ])->values(),
        ]);
    }

    public function downloadJpg(VertexGeneration $generation): BinaryFileResponse
    {
        abort_unless($generation->output_image_path, 404);

        $srcPath = public_path('uploads/' . $generation->output_image_path);
        abort_unless(file_exists($srcPath), 404);

        $tmpPath = sys_get_temp_dir() . '/vtx_' . $generation->id . '_' . time() . '.jpg';

        $image = match (true) {
            str_ends_with($srcPath, '.webp') => imagecreatefromwebp($srcPath),
            str_ends_with($srcPath, '.png')  => imagecreatefrompng($srcPath),
            default                          => imagecreatefromstring(file_get_contents($srcPath)),
        };

        abort_unless($image !== false, 422, 'Görsel okunamadı.');

        imagejpeg($image, $tmpPath, 92);
        imagedestroy($image);

        $filename = 'vertex-' . $generation->id . '.jpg';

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'image/jpeg',
        ])->deleteFileAfterSend();
    }

    public function share(Request $request, VertexGeneration $generation): JsonResponse
    {
        $request->validate([
            'platforms'    => ['required', 'array', 'min:1'],
            'platforms.*'  => ['string', 'in:instagram,facebook'],
            'media_type'   => ['sometimes', 'string', 'in:image,story'],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        abort_unless($generation->output_image_path, 422, 'Bu üretimin görseli yok.');

        $mediaType = $request->input('media_type') === 'story'
            ? InstagramMediaType::Story
            : InstagramMediaType::Image;

        $scheduledAt = $request->filled('scheduled_at')
            ? Carbon::parse($request->input('scheduled_at'))
            : null;

        $result = $this->shareService->share(
            generation: $generation,
            platforms: (array) $request->input('platforms'),
            userId: $request->user()?->id,
            mediaType: $mediaType,
            scheduledAt: $scheduledAt,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function retry(Request $request, VertexGeneration $generation): JsonResponse
    {
        abort_unless($generation->isFailed(), 422, 'Sadece başarısız üretimler tekrar denenebilir.');

        $generation->retryGeneration($request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Yeniden kuyruğa alındı. Kısa sürede üretilecek.',
        ]);
    }

    public function destroy(VertexGeneration $generation): RedirectResponse|JsonResponse
    {
        $this->generationService->destroy($generation);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Üretim kaydı silindi.']);
        }

        return back()->with('success', 'Üretim kaydı silindi.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $count = $this->generationService->bulkDestroy($request->input('ids'));

        return response()->json([
            'success' => true,
            'message' => $count . ' üretim kaydı silindi.',
            'deleted_count' => $count,
        ]);
    }

    public function bulkDownload(Request $request): StreamedResponse
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $generations = VertexGeneration::query()
            ->whereIn('id', $request->input('ids'))
            ->where('status', VertexGeneration::STATUS_COMPLETED)
            ->whereNotNull('output_image_path')
            ->with('prompt:id,name')
            ->get();

        abort_if($generations->isEmpty(), 422, 'İndirilecek tamamlanmış görsel bulunamadı.');

        $zipPath = $this->zipExporter->export($generations);

        $filename = 'vertex-gorueller-' . now()->format('Y-m-d-His') . '.zip';

        return response()->streamDownload(static function () use ($zipPath): void {
            readfile($zipPath);
            @unlink($zipPath);
        }, $filename, ['Content-Type' => 'application/zip']);
    }

    public function downloadByFilter(Request $request): StreamedResponse
    {
        $request->validate([
            'prompt_id'    => ['nullable', 'integer', 'exists:vertex_prompts,id'],
            'aspect_ratio' => ['nullable', 'string', 'max:10'],
        ]);

        $query = VertexGeneration::query()
            ->where('status', VertexGeneration::STATUS_COMPLETED)
            ->whereNotNull('output_image_path')
            ->with('prompt:id,name');

        $promptId = $request->query('prompt_id');
        $aspectRatio = $request->query('aspect_ratio');

        if ($promptId !== null && $promptId !== '') {
            $query->where('prompt_id', (int) $promptId);
        }

        if ($aspectRatio !== null && $aspectRatio !== '') {
            $query->where('aspect_ratio', (string) $aspectRatio);
        }

        $generations = $query->get();

        abort_if($generations->isEmpty(), 422, 'İndirilecek görsel bulunamadı.');

        $zipPath = $this->zipExporter->exportJpgOnly($generations);

        $promptName = $promptId ? ($generations->first()?->prompt?->name ?? 'filtre') : 'tum';
        $slug = mb_substr(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($promptName, 'UTF-8')) ?? $promptName, 0, 30);
        $filename = "vertex-jpg-{$slug}-" . now()->format('Y-m-d') . '.zip';

        return response()->streamDownload(static function () use ($zipPath): void {
            readfile($zipPath);
            @unlink($zipPath);
        }, $filename, ['Content-Type' => 'application/zip']);
    }
}
