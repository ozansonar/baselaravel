<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiPromptSetting;

final class AiPromptSettingService
{
    public function __construct(
        private readonly CityRotationService $cityRotationService,
    ) {}

    public function get(): AiPromptSetting
    {
        return AiPromptSetting::current();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): AiPromptSetting
    {
        $setting = $this->get();

        // Normalize products: trim, drop empty values, keep unique order
        if (isset($data['products']) && is_array($data['products'])) {
            $data['products'] = array_values(array_unique(array_filter(
                array_map(static fn ($v) => trim((string) $v), $data['products']),
                static fn ($v) => $v !== ''
            )));
        }

        // Same normalization for target_cities
        if (array_key_exists('target_cities', $data) && is_array($data['target_cities'])) {
            $data['target_cities'] = array_values(array_unique(array_filter(
                array_map(static fn ($v) => trim((string) $v), $data['target_cities']),
                static fn ($v) => $v !== ''
            )));
        }

        $setting->update($data);

        return $setting->fresh();
    }

    public function resetToDefaults(): AiPromptSetting
    {
        $setting = $this->get();
        $setting->update(AiPromptSetting::defaultAttributes());

        return $setting->fresh();
    }

    public function resetRotationIndex(): AiPromptSetting
    {
        $this->cityRotationService->resetIndex();

        return $this->get()->fresh();
    }
}
