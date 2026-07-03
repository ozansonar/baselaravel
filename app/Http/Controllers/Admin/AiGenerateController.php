<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiGenerateRequest;
use App\Models\BlogCategory;
use App\Services\AiPromptSettingService;
use App\Services\BlogGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AiGenerateController extends Controller
{
    public function __construct(
        private readonly BlogGenerationService $generationService,
        private readonly AiPromptSettingService $aiPromptSettingService,
    ) {}

    public function create(): View
    {
        return view('admin.ai-generate.create', [
            'categories' => BlogCategory::active()->sorted()->get(['id', 'name', 'slug']),
            'setting'    => $this->aiPromptSettingService->get(),
        ]);
    }

    public function store(AiGenerateRequest $request): RedirectResponse
    {
        $category = BlogCategory::findOrFail($request->integer('category_id'));

        // Increase execution time for long AI calls + exponential retry (up to ~5 min)
        @set_time_limit(300);

        // 'city' her zaman geçirilir (null/string). Bu sayede manuel üretim
        // CityRotationService.nextCity() çağrısını tetiklemez ve cron rotation
        // index'i bozulmaz. Kullanıcı şehir seçmediyse generic post üretilir.
        $result = $this->generationService->generate([
            'category_slug' => $category->slug,
            'topic'         => $request->input('topic'),
            'city'          => $request->input('city'),
            'force'         => true, // manual generation overrides is_active toggle
        ]);

        if (! $result['success']) {
            return back()
                ->withInput()
                ->with('error', $result['message'])
                ->with('ai_log_id', $result['log']->id);
        }

        return redirect()
            ->route('admin.blog-posts.edit', $result['post']->id)
            ->with('success', "AI ile blog yazısı oluşturuldu: {$result['post']->title}");
    }
}
