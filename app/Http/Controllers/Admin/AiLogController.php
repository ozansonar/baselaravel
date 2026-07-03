<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AiLogSource;
use App\Enums\AiLogStatus;
use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Services\AiLogService;
use App\Services\AiTopicService;
use App\Services\BlogGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AiLogController extends Controller
{
    public function __construct(
        private readonly AiLogService $aiLogService,
        private readonly BlogGenerationService $blogGenerator,
        private readonly AiTopicService $topicGenerator,
    ) {}

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'source', 'search', 'date_filter']);

        return view('admin.ai-logs.index', [
            'logs'         => $this->aiLogService->paginate($perPage, $filters),
            'stats'        => $this->aiLogService->getAdminStats(),
            'statusCounts' => $this->aiLogService->statusCounts(),
            'perPage'      => $perPage,
        ]);
    }

    public function show(AiLog $aiLog): View
    {
        $aiLog->load('blogPost');

        return view('admin.ai-logs.show', [
            'log' => $aiLog,
        ]);
    }

    /**
     * Hatalı log'u (ParseError / PostFailed / CategoryMissing) yeniden işle.
     * Log'un kaynağına (source) göre doğru servise dispatch eder:
     *   - TopicCluster        → AiTopicService::retryFromLog
     *   - Diğerleri (Blog/...) → BlogGenerationService::retryFromLog
     */
    public function retry(AiLog $aiLog): JsonResponse
    {
        $retryable = [
            AiLogStatus::ParseError,
            AiLogStatus::PostFailed,
            AiLogStatus::CategoryMissing,
        ];

        if (! in_array($aiLog->status, $retryable, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu log retry edilemez. Sadece parse/post/kategori hatası olan log\'lar yeniden işlenebilir.',
            ], 422);
        }

        // Source'a göre dispatch — array yanıtlar (topic) blog parser'a uymaz.
        if ($aiLog->source === AiLogSource::TopicCluster) {
            $result = $this->topicGenerator->retryFromLog($aiLog);
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'log_id'  => $result['log']->id,
                'status'  => $result['log']->status->value,
                'count'   => isset($result['topics']) ? count($result['topics']) : 0,
            ], $result['success'] ? 200 : 422);
        }

        // Default: blog akışı
        $result = $this->blogGenerator->retryFromLog($aiLog);

        return response()->json([
            'success'  => $result['success'],
            'message'  => $result['message'],
            'log_id'   => $result['log']->id,
            'status'   => $result['log']->status->value,
            'post_id'  => $result['post']->id ?? null,
            'post_url' => isset($result['post'])
                ? route('admin.blog-posts.edit', $result['post'])
                : null,
        ], $result['success'] ? 200 : 422);
    }
}
