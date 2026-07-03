<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VertexGeneration;
use App\Models\VertexPrompt;
use App\Services\VertexPlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

final class VertexPlanController extends Controller
{
    public function __construct(
        private readonly VertexPlannerService $planner,
    ) {}

    public function index(): View
    {
        $prompts = VertexPrompt::query()
            ->active()
            ->whereHas('generations', fn ($q) => $q->completed()->whereNotNull('output_image_path'))
            ->withCount(['generations' => fn ($q) => $q->completed()->whereNotNull('output_image_path')])
            ->orderBy('name')
            ->get(['id', 'name']);

        $stats = [
            'feed_available'  => VertexGeneration::completed()->whereIn('aspect_ratio', ['1:1', '4:5'])->whereNotNull('output_image_path')->count(),
            'story_available' => VertexGeneration::completed()->where('aspect_ratio', '9:16')->whereNotNull('output_image_path')->count(),
        ];

        return view('admin.instagram-posts.vertex-plan', [
            'prompts' => $prompts,
            'stats'   => $stats,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'from'          => ['required', 'date', 'after_or_equal:today'],
            'to'            => ['required', 'date', 'after_or_equal:from'],
            'include_feed'  => ['required', 'boolean'],
            'include_story' => ['required', 'boolean'],
            'feed_time'     => ['required', 'date_format:H:i'],
            'story_time'    => ['required', 'date_format:H:i'],
            'prompt_ids'    => ['nullable', 'array'],
            'prompt_ids.*'  => ['integer', 'exists:vertex_prompts,id'],
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();
        $includeFeed = (bool) $request->input('include_feed');
        $includeStory = (bool) $request->input('include_story');
        $feedTime = (string) $request->input('feed_time');
        $storyTime = (string) $request->input('story_time');

        $promptIds = $request->input('prompt_ids');
        $promptIds = is_array($promptIds) && $promptIds !== [] ? array_map('intval', $promptIds) : null;

        if (! $includeFeed && ! $includeStory) {
            return response()->json([
                'success' => false,
                'message' => 'En az bir gönderi tipi seçmelisin (Feed veya Story).',
            ], 422);
        }

        $result = $this->planner->preview(
            from: $from,
            to: $to,
            includeFeed: $includeFeed,
            includeStory: $includeStory,
            feedTime: $feedTime,
            storyTime: $storyTime,
            promptIds: $promptIds,
        );

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'posts'            => ['required', 'array', 'min:1'],
            'posts.*.scheduled_at'          => ['required', 'date'],
            'posts.*.media_type'            => ['required', 'string', 'in:image,story'],
            'posts.*.image_path'            => ['required', 'string'],
            'posts.*.vertex_generation_id'  => ['required', 'integer'],
            'posts.*.ig_caption'            => ['nullable', 'string'],
            'posts.*.ig_hashtags'           => ['nullable', 'string'],
        ]);

        $result = $this->planner->confirm(
            planData: (array) $request->input('posts'),
            userId: $request->user()->id,
        );

        if ($result['created'] === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Hiçbir gönderi oluşturulamadı. Aynı tarih/saatte zaten planlanmış gönderiler olabilir.',
            ], 422);
        }

        $msg = "{$result['created']} gönderi planlandı.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} tanesi atlandı (çakışma veya eksik görsel).";
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => $result,
        ]);
    }
}
