<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CustomRouteType;
use App\Models\CustomRoute;
use App\Services\CustomRouteService;
use App\Services\LanguageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panelden açılan bir adres.
 *
 * Hedef serbest metin değil, listeden seçiliyor: yazım hatasıyla hiçbir yere
 * gitmeyen bir adres açılamıyor. Slug da dil ön ekinden arındırılıyor —
 * yönetici alışkanlıkla "/en/contact" yazsa bile kayıt "contact" oluyor,
 * yoksa adres "/en/en/contact" olurdu.
 */
class StoreCustomRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'editor']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $languages = app(LanguageService::class)->activeCodes();

        return [
            'slug' => [
                'required', 'string', 'max:191', 'regex:~^[a-z0-9]+(?:[-/][a-z0-9]+)*$~',
                // Aynı dilde aynı slug iki kez açılamaz; ikincisi hiçbir zaman
                // eşleşmez ve yönetici neden çalışmadığını anlayamaz.
                Rule::unique('custom_routes', 'slug')
                    ->where(fn ($query) => $query->where('locale', $this->normalisedLocale())->whereNull('deleted_at'))
                    ->ignore($this->routeModel()?->id),
            ],
            'locale'        => ['nullable', Rule::in([...$languages, 'all', ''])],
            'target_route'  => ['required', 'string', Rule::in(array_keys(app(CustomRouteService::class)->availableTargets()))],
            'target_params' => ['nullable', 'array'],
            'target_params.*' => ['nullable', 'string', 'max:191'],
            'type'          => ['required', Rule::enum(CustomRouteType::class)],
            'is_active'     => ['nullable', 'boolean'],
            'note'          => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug'      => app(CustomRouteService::class)->normaliseSlug((string) $this->input('slug', '')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $service = app(CustomRouteService::class);
            $target = (string) $this->input('target_route');
            $verilen = array_filter((array) $this->input('target_params', []), static fn ($v): bool => $v !== null && $v !== '');

            foreach ($service->parametersFor($target) as $gerekli) {
                if (! array_key_exists($gerekli, $verilen)) {
                    // Eksik parametreyle kaydedilen adres çalışmaz ve hata
                    // ancak ziyaretçi tıklayınca görünürdü.
                    $validator->errors()->add(
                        "target_params.{$gerekli}",
                        "Seçtiğiniz hedef için \"{$gerekli}\" değeri gerekli.",
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required'       => 'Adres zorunludur.',
            'slug.regex'          => 'Adres yalnızca küçük harf, rakam, tire ve eğik çizgi içerebilir.',
            'slug.unique'         => 'Bu adres bu dilde zaten tanımlı.',
            'slug.max'            => 'Adres en fazla :max karakter olabilir.',
            'target_route.required' => 'Hedef sayfayı seçin.',
            'target_route.in'     => 'Seçilen hedef geçerli değil.',
            'type.required'       => 'Yönlendirme türünü seçin.',
            'locale.in'           => 'Seçilen dil geçerli değil.',
            'note.max'            => 'Not en fazla :max karakter olabilir.',
        ];
    }

    protected function normalisedLocale(): ?string
    {
        $locale = $this->input('locale');

        return ($locale === null || $locale === '' || $locale === 'all') ? null : (string) $locale;
    }

    protected function routeModel(): ?CustomRoute
    {
        $model = $this->route('custom_route');

        return $model instanceof CustomRoute ? $model : null;
    }
}
