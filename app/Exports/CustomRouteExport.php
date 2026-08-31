<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\CustomRoute;
use App\Services\CustomRouteService;
use App\Support\Export\ExportColumn;
use App\Support\Export\ListExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Panelden yönetilen adreslerin dışa aktarma tanımı.
 *
 * Bu liste taşınmaya en çok ihtiyaç duyulanlardan biri: bir siteyi devralan
 * ekibin ilk sorduğu şey hangi adresin nereye baktığı, ve bu bilgi çoğu zaman
 * panelin dışında (yönlendirme planı, SEO denetimi) okunuyor.
 */
final class CustomRouteExport extends ListExport
{
    public function __construct(
        private readonly CustomRouteService $routes,
    ) {}

    public function title(): string
    {
        return 'Özel Adresler';
    }

    public function authorize(): void
    {
        Gate::authorize('viewAny', CustomRoute::class);
    }

    /** @return list<string> */
    public function filterKeys(): array
    {
        return $this->routes->filterKeys();
    }

    public function query(array $filters): Builder
    {
        return $this->routes->query($filters);
    }

    /** @return list<ExportColumn> */
    public function columns(): array
    {
        return [
            ExportColumn::make('Adres', static fn (CustomRoute $route): string => '/' . $route->slug)->width(28),
            ExportColumn::make(
                'Dil',
                // Dili boş olan kayıt bütün dilleri kapsıyor; boş hücre bunu
                // söylemiyor, "Tüm diller" söylüyor.
                static fn (CustomRoute $route): string => $route->locale === null
                    ? 'Tüm diller'
                    : strtoupper($route->locale),
            )->width(10),
            ExportColumn::make(
                'Hedef',
                static fn (CustomRoute $route): string => (string) $route->target_route,
            )->width(26),
            ExportColumn::make(
                'Parametreler',
                static fn (CustomRoute $route): string => $route->target_params === null
                    ? ''
                    : http_build_query($route->target_params, '', ', '),
            )->width(20),
            ExportColumn::make(
                'Davranış',
                static fn (CustomRoute $route): string => $route->type->label(),
            )->width(18),
            ExportColumn::make('Durum', static fn (CustomRoute $route): string => match (true) {
                $route->trashed() => 'Silinmiş',
                $route->is_active => 'Aktif',
                default           => 'Pasif',
            })->width(10),
            ExportColumn::make('Not', static fn (CustomRoute $route): string => (string) $route->note)->width(28),
            ExportColumn::make(
                'Oluşturulma',
                static fn (CustomRoute $route): ?\DateTimeInterface => $route->created_at,
            )->asDateTime()->width(16),
        ];
    }
}
