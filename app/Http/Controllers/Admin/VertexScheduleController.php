<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VertexPrompt;
use App\Models\VertexSchedule;
use App\Services\VertexScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VertexScheduleController extends Controller
{
    public function __construct(
        private readonly VertexScheduleService $scheduleService,
    ) {}

    public function index(): View
    {
        $schedules = VertexSchedule::query()
            ->with('prompt:id,name')
            ->orderByDesc('is_active')
            ->orderBy('time')
            ->get();

        $prompts = VertexPrompt::query()
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'prompt', 'random_options', 'aspect_ratio']);

        $stats = $this->scheduleService->getStats();

        return view('admin.vertex.schedules.index', [
            'schedules' => $schedules,
            'prompts'   => $prompts,
            'stats'     => $stats,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:120'],
            'prompt_id'             => ['required', 'integer', 'exists:vertex_prompts,id'],
            'variables'             => ['nullable', 'array'],
            'variables.*'           => ['nullable', 'string', 'max:5000'],
            'count'                 => ['required', 'integer', 'min:1', 'max:50'],
            'aspect_ratio_override' => ['nullable', 'string', 'in:1:1,4:5,9:16,16:9'],
            'frequency'             => ['required', 'string', 'in:daily,weekly,monthly'],
            'days_of_week'          => ['nullable', 'array'],
            'days_of_week.*'        => ['integer', 'min:1', 'max:7'],
            'day_of_month'          => ['nullable', 'integer', 'min:1', 'max:28'],
            'time'                  => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $data['created_by'] = $request->user()?->id;

        $schedule = $this->scheduleService->store($data);

        return response()->json([
            'success'  => true,
            'message'  => 'Zamanlama oluşturuldu.',
            'schedule' => $this->formatSchedule($schedule),
        ]);
    }

    public function update(Request $request, VertexSchedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:120'],
            'prompt_id'             => ['required', 'integer', 'exists:vertex_prompts,id'],
            'variables'             => ['nullable', 'array'],
            'variables.*'           => ['nullable', 'string', 'max:5000'],
            'count'                 => ['required', 'integer', 'min:1', 'max:50'],
            'aspect_ratio_override' => ['nullable', 'string', 'in:1:1,4:5,9:16,16:9'],
            'frequency'             => ['required', 'string', 'in:daily,weekly,monthly'],
            'days_of_week'          => ['nullable', 'array'],
            'days_of_week.*'        => ['integer', 'min:1', 'max:7'],
            'day_of_month'          => ['nullable', 'integer', 'min:1', 'max:28'],
            'time'                  => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $schedule = $this->scheduleService->update($schedule, $data);

        return response()->json([
            'success'  => true,
            'message'  => 'Zamanlama güncellendi.',
            'schedule' => $this->formatSchedule($schedule),
        ]);
    }

    public function toggle(VertexSchedule $schedule): JsonResponse
    {
        $this->scheduleService->toggle($schedule);

        return response()->json([
            'success'   => true,
            'message'   => $schedule->is_active ? 'Zamanlama aktif edildi.' : 'Zamanlama pasif edildi.',
            'is_active' => $schedule->is_active,
        ]);
    }

    public function runNow(Request $request, VertexSchedule $schedule): JsonResponse
    {
        if ($schedule->prompt === null || ! $schedule->prompt->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Bu zamanlamanın prompt şablonu aktif değil.',
            ], 422);
        }

        $this->scheduleService->executeSchedule($schedule);

        return response()->json([
            'success' => true,
            'message' => $schedule->count . ' görsel kuyruğa alındı.',
        ]);
    }

    public function destroy(VertexSchedule $schedule): JsonResponse
    {
        $this->scheduleService->destroy($schedule);

        return response()->json([
            'success' => true,
            'message' => 'Zamanlama silindi.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSchedule(VertexSchedule $schedule): array
    {
        $schedule->load('prompt:id,name');

        return [
            'id'              => $schedule->id,
            'name'            => $schedule->name,
            'prompt_name'     => $schedule->prompt?->name,
            'count'           => $schedule->count,
            'frequency_label' => $schedule->frequencyLabel(),
            'is_active'       => $schedule->is_active,
            'last_run_at'     => $schedule->last_run_at?->format('d.m.Y H:i'),
            'next_run_at'     => $schedule->next_run_at?->format('d.m.Y H:i'),
            'total_runs'      => $schedule->total_runs,
        ];
    }
}
