<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SpecialDay;
use App\Models\SpecialDayImage;
use App\Models\VertexBatch;
use App\Models\VertexPrompt;
use App\Services\SpecialDayVertexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

final class SpecialDayVertexController extends Controller
{
    public function __construct(
        private readonly SpecialDayVertexService $service,
    ) {}

    public function index(Request $request): View
    {
        $year = (int) ($request->query('year') ?? now()->year);
        $month = $request->query('month');

        if ($month !== null) {
            $from = Carbon::create($year, (int) $month, 1)->startOfMonth();
            $to = $from->copy()->endOfMonth();
        } else {
            $from = Carbon::create($year, 1, 1)->startOfYear();
            $to = $from->copy()->endOfYear();
        }

        $specialDays = $this->service->getSpecialDaysInRange($from, $to);

        $activeBatches = VertexBatch::query()
            ->whereNotNull('special_day_id')
            ->active()
            ->with('prompt', 'specialDay')
            ->oldest()
            ->get();

        $recentBatches = VertexBatch::query()
            ->whereNotNull('special_day_id')
            ->with('prompt', 'specialDay')
            ->latest()
            ->limit(20)
            ->get();

        $prompts = VertexPrompt::query()->active()->orderBy('sort_order')->get();

        $apiKeySet = trim((string) Setting::getValue('vertex_api_key', '')) !== '';

        $stats = [
            'total_days'    => $specialDays->count(),
            'with_feed'     => $specialDays->filter(fn ($d) => ($d->feed_count ?? 0) > 0)->count(),
            'with_story'    => $specialDays->filter(fn ($d) => ($d->story_count ?? 0) > 0)->count(),
            'pending_batch' => $activeBatches->count(),
        ];

        return view('admin.vertex.special-days', [
            'specialDays'   => $specialDays,
            'activeBatches' => $activeBatches,
            'recentBatches' => $recentBatches,
            'prompts'       => $prompts,
            'apiKeySet'     => $apiKeySet,
            'stats'         => $stats,
            'year'          => $year,
            'month'         => $month,
            'from'          => $from,
            'to'            => $to,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'feed_prompt_id'  => ['required', 'integer', 'exists:vertex_prompts,id'],
            'story_prompt_id' => ['required', 'integer', 'exists:vertex_prompts,id'],
            'from'            => ['required', 'date'],
            'to'              => ['required', 'date', 'after_or_equal:from'],
            'count_per_day'   => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        if (trim((string) Setting::getValue('vertex_api_key', '')) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Vertex API Key tanımlı değil.',
            ], 422);
        }

        $feedPrompt = VertexPrompt::findOrFail((int) $request->input('feed_prompt_id'));
        $storyPrompt = VertexPrompt::findOrFail((int) $request->input('story_prompt_id'));
        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();
        $countPerDay = max(1, (int) ($request->input('count_per_day') ?? 2));

        $result = $this->service->generateForPeriod(
            from: $from,
            to: $to,
            feedPrompt: $feedPrompt,
            storyPrompt: $storyPrompt,
            countPerDay: $countPerDay,
            userId: $request->user()?->id,
        );

        if ($result['days_processed'] === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Seçilen tarih aralığında özel gün bulunamadı.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                '%d özel gün için %d batch kuyruğa alındı. %d tanesi zaten mevcut olduğundan atlandı.',
                $result['days_processed'],
                $result['batches_created'],
                $result['skipped'],
            ),
            'data' => $result,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();

        $days = $this->service->getSpecialDaysInRange($from, $to);
        $batchMap = $this->service->loadExistingBatchMap($days->pluck('id')->all());

        $preview = $days->map(fn (SpecialDay $d) => [
            'id'          => $d->id,
            'date'        => $d->date->format('d.m.Y'),
            'name'        => $d->name,
            'category'    => $d->categoryLabel(),
            'feed_count'  => $d->feed_count ?? 0,
            'story_count' => $d->story_count ?? 0,
            'has_feed_batch'  => isset($batchMap[$d->id . ':' . SpecialDayImage::MEDIA_FEED]),
            'has_story_batch' => isset($batchMap[$d->id . ':' . SpecialDayImage::MEDIA_STORY]),
        ]);

        return response()->json([
            'success'    => true,
            'days'       => $preview->values(),
            'total'      => $days->count(),
        ]);
    }
}
