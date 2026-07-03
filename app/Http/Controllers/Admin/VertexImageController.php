<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\VertexBatch;
use App\Models\VertexGeneration;
use App\Models\VertexPrompt;
use App\Services\VertexBatchService;
use App\Services\Vertex\PromptVariableParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VertexImageController extends Controller
{
    public function __construct(
        private readonly VertexBatchService $batchService,
    ) {}

    public function index(): View
    {
        $prompts = VertexPrompt::query()
            ->active()
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $recentGenerations = VertexGeneration::query()
            ->with('prompt', 'batch')
            ->latest()
            ->limit(12)
            ->get();

        $activeBatches = VertexBatch::query()
            ->active()
            ->with('prompt')
            ->oldest()
            ->get();

        $recentBatches = VertexBatch::query()
            ->with('prompt')
            ->latest()
            ->paginate(10, ['*'], 'batch_page');

        $stats = [
            'total'     => VertexGeneration::count(),
            'completed' => VertexGeneration::completed()->count(),
            'failed'    => VertexGeneration::failed()->count(),
            'prompts'   => VertexPrompt::active()->count(),
        ];

        $apiKeySet = trim((string) Setting::getValue('vertex_api_key', '')) !== '';

        return view('admin.vertex.index', [
            'prompts'           => $prompts,
            'recentGenerations' => $recentGenerations,
            'activeBatches'     => $activeBatches,
            'recentBatches'     => $recentBatches,
            'stats'             => $stats,
            'apiKeySet'         => $apiKeySet,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'prompt_id'   => ['required', 'integer', 'exists:vertex_prompts,id'],
            'count'       => ['nullable', 'integer', 'min:1'],
            'variables'   => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:5000'],
        ]);

        $template = VertexPrompt::findOrFail((int) $request->input('prompt_id'));

        if (trim((string) Setting::getValue('vertex_api_key', '')) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Vertex API Key tanımlı değil. Admin → Ayarlar → AI → Google Vertex AI bölümünden ekleyin.',
            ], 422);
        }

        $variables = (array) $request->input('variables', []);
        $randomOptions = is_array($template->random_options) ? $template->random_options : [];
        $templateVars = PromptVariableParser::parse($template->prompt);
        foreach ($templateVars as $varName) {
            $val = $variables[$varName] ?? '';
            if ($val === '__random__') {
                if (empty($randomOptions[$varName])) {
                    return response()->json([
                        'success' => false,
                        'message' => PromptVariableParser::humanize($varName) . ' için rastgele seçenek listesi tanımlı değil.',
                    ], 422);
                }
                continue;
            }
            if (empty($val)) {
                return response()->json([
                    'success' => false,
                    'message' => PromptVariableParser::humanize($varName) . ' alanı zorunlu.',
                ], 422);
            }
        }

        $count = max(1, (int) ($request->input('count') ?? 1));

        $batch = $this->batchService->createBatch(
            template: $template,
            count: $count,
            variables: $variables,
            userId: $request->user()?->id,
        );

        $msg = $count === 1
            ? 'Görsel kuyruğa alındı. Cron tarafından üretilecek.'
            : "{$count} görsel kuyruğa alındı.";

        return response()->json([
            'success'    => true,
            'message'    => $msg,
            'batch_id'   => $batch->id,
            'status_url' => route('admin.vertex.batch-status', $batch->id),
        ]);
    }

    public function batchStatus(VertexBatch $batch): JsonResponse
    {
        return response()->json([
            'id'              => $batch->id,
            'status'          => $batch->status,
            'total_count'     => $batch->total_count,
            'completed_count' => $batch->completed_count,
            'failed_count'    => $batch->failed_count,
            'progress'        => $batch->progress(),
            'remaining'       => $batch->remainingCount(),
            'duration'        => $batch->formattedDuration(),
            'prompt_name'     => $batch->prompt?->name,
            'started_at'      => $batch->started_at?->toIso8601String(),
            'finished_at'     => $batch->finished_at?->toIso8601String(),
        ]);
    }

    public function cancelBatch(VertexBatch $batch): JsonResponse
    {
        if (! $batch->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Bu batch zaten tamamlanmış veya iptal edilmiş.',
            ], 422);
        }

        $batch->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Batch iptal edildi.',
        ]);
    }

    public function retryBatch(Request $request, VertexBatch $batch): JsonResponse
    {
        $failedCount = $batch->generations()
            ->where('status', VertexGeneration::STATUS_FAILED)
            ->count();

        if ($failedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu batch\'te yeniden denenecek hatalı üretim yok.',
            ], 422);
        }

        $result = $batch->retryFailed($request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => $result['retried_count'] . ' hatalı üretim yeniden kuyruğa alındı.',
        ]);
    }

    public function status(VertexGeneration $generation): JsonResponse
    {
        return response()->json([
            'id'                 => $generation->id,
            'status'             => $generation->status,
            'output_image_path'  => $generation->output_image_path,
            'image_url'          => $generation->output_image_path ? upload_url($generation->output_image_path, 'lg') : null,
            'thumb_url'          => $generation->output_image_path ? upload_url($generation->output_image_path, 'thumb') : null,
            'error_message'      => $generation->error_message,
            'generation_time_ms' => $generation->generation_time_ms,
            'prompt_name'        => $generation->prompt?->name,
            'aspect_ratio'       => $generation->aspect_ratio,
            'width'              => $generation->width,
            'height'             => $generation->height,
            'ig_caption'           => $generation->ig_caption,
            'ig_title'             => $generation->ig_title,
            'ig_hashtags'          => $generation->ig_hashtags,
            'resolved_variables'   => $generation->resolved_variables,
        ]);
    }

    public function gallery(Request $request): JsonResponse
    {
        $request->validate([
            'aspect_ratio' => ['nullable', 'string'],
            'prompt_id'    => ['nullable', 'integer'],
            'search'       => ['nullable', 'string', 'max:200'],
            'page'         => ['nullable', 'integer', 'min:1'],
        ]);

        $aspectRatio = (string) $request->query('aspect_ratio', '1:1');
        $promptId = $request->query('prompt_id') ? (int) $request->query('prompt_id') : null;
        $search = $request->query('search');
        $perPage = 24;

        $query = VertexGeneration::query()
            ->completed()
            ->whereNotNull('output_image_path')
            ->with('prompt:id,name')
            ->latest();

        if ($aspectRatio) {
            $ratios = array_filter(explode(',', $aspectRatio));
            if (count($ratios) > 1) {
                $query->whereIn('aspect_ratio', $ratios);
            } else {
                $query->where('aspect_ratio', $aspectRatio);
            }
        }
        if ($promptId) {
            $query->where('prompt_id', $promptId);
        }
        if ($search) {
            $query->where('prompt_used', 'LIKE', '%' . $search . '%');
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (VertexGeneration $g) => [
            'id'           => $g->id,
            'thumb_url'    => upload_url($g->output_image_path, 'thumb'),
            'image_url'    => upload_url($g->output_image_path, 'md'),
            'full_url'     => upload_url($g->output_image_path, 'lg'),
            'image_path'   => $g->output_image_path,
            'prompt_name'  => $g->prompt?->name,
            'aspect_ratio' => $g->aspect_ratio,
            'ig_caption'   => $g->ig_caption,
            'ig_title'     => $g->ig_title,
            'ig_hashtags'          => $g->ig_hashtags,
            'resolved_variables'   => $g->resolved_variables,
            'usage_count'          => $g->usage_count ?? 0,
            'created_at'           => $g->created_at->format('d.m.Y H:i'),
        ]);

        $prompts = VertexPrompt::query()
            ->active()
            ->whereHas('generations', fn ($q) => $q->completed()->whereNotNull('output_image_path'))
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return response()->json([
            'items'        => $items,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
            'prompts'      => $prompts->map(fn (VertexPrompt $p) => ['id' => $p->id, 'name' => $p->name]),
        ]);
    }

    public function systemStatus(): JsonResponse
    {
        return response()->json($this->batchService->getSystemStatus());
    }
}
