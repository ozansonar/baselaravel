<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiPromptSettingUpdateRequest;
use App\Services\AiPromptSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AiPromptSettingController extends Controller
{
    public function __construct(
        private readonly AiPromptSettingService $aiPromptSettingService,
    ) {}

    public function edit(): View
    {
        return view('admin.ai-prompt-settings.edit', [
            'setting' => $this->aiPromptSettingService->get(),
        ]);
    }

    public function update(AiPromptSettingUpdateRequest $request): RedirectResponse
    {
        $this->aiPromptSettingService->update($request->validated());

        return back()->with('success', 'AI içerik ayarları başarıyla güncellendi.');
    }

    public function reset(): RedirectResponse
    {
        $this->aiPromptSettingService->resetToDefaults();

        return back()->with('success', 'Ayarlar varsayılan değerlere sıfırlandı.');
    }

    public function resetRotation(): RedirectResponse
    {
        $this->aiPromptSettingService->resetRotationIndex();

        return back()->with('success', 'Şehir rotasyon sayacı sıfırlandı. Sıradaki blog yazısı listenin ilk şehrinden başlayacak.');
    }
}
