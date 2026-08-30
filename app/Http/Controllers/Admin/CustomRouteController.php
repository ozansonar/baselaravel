<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ReturnsToList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkCustomRouteRequest;
use App\Http\Requests\Admin\StoreCustomRouteRequest;
use App\Http\Requests\Admin\UpdateCustomRouteRequest;
use App\Models\CustomRoute;
use App\Services\CustomRouteService;
use App\Services\LanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panelden yönetilen adresler.
 *
 * Yönetici bir slug açıp onu var olan bir rotaya bağlıyor: "bize-ulas",
 * "iletisimx" ve "contact" aynı iletişim sayfasına bakabiliyor, her biri
 * kendi dilinde.
 */
final class CustomRouteController extends Controller
{
    use ReturnsToList;

    /** Listede sayfa başına gösterilebilecek kayıt sayıları. */
    private const PER_PAGE = [15, 30, 50, 100];

    public function __construct(
        private readonly CustomRouteService $routes,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomRoute::class);

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 15;

        return view('admin.custom-routes.index', [
            'routes'       => $this->routes->paginate($perPage, $request->only($this->routes->filterKeys())),
            'statusCounts' => $this->routes->statusCounts(),
            'targets'      => $this->routes->availableTargets(),
            'languages'    => app(LanguageService::class)->active(),
            'filters'      => $request->only($this->routes->filterKeys()),
            'perPage'      => $perPage,
            'perPageOptions' => self::PER_PAGE,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CustomRoute::class);

        return view('admin.custom-routes.create', $this->formData());
    }

    public function store(StoreCustomRouteRequest $request): RedirectResponse
    {
        $this->authorize('create', CustomRoute::class);

        $this->routes->create($request->validated());

        return redirect()
            ->route('admin.custom-routes.index')
            ->with('success', 'Adres eklendi.');
    }

    public function edit(CustomRoute $customRoute): View
    {
        $this->authorize('update', $customRoute);

        return view('admin.custom-routes.edit', $this->formData(['route' => $customRoute]));
    }

    public function update(UpdateCustomRouteRequest $request, CustomRoute $customRoute): RedirectResponse
    {
        $this->authorize('update', $customRoute);

        $this->routes->update($customRoute, $request->validated());

        return redirect()
            ->route('admin.custom-routes.index')
            ->with('success', 'Adres güncellendi.');
    }

    public function destroy(CustomRoute $customRoute): RedirectResponse
    {
        $this->authorize('delete', $customRoute);

        $this->routes->delete($customRoute);

        return redirect()
            ->route('admin.custom-routes.index')
            ->with('success', 'Adres silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $route = CustomRoute::withTrashed()->findOrFail($id);

        $this->authorize('restore', $route);

        $this->routes->restore($id);

        return redirect()
            ->route('admin.custom-routes.index')
            ->with('success', 'Adres geri yüklendi.');
    }

    public function bulkDestroy(BulkCustomRouteRequest $request): RedirectResponse
    {
        $this->authorize('delete', new CustomRoute());

        $silinen = $this->routes->deleteMany($request->ids());

        return $this->backToList($request, 'admin.custom-routes.index')->with(
            $silinen > 0 ? 'success' : 'error',
            $silinen > 0 ? "{$silinen} adres silindi." : 'Hiçbir adres silinemedi.',
        );
    }

    public function bulkRestore(BulkCustomRouteRequest $request): RedirectResponse
    {
        $this->authorize('restore', new CustomRoute());

        $geriYuklenen = $this->routes->restoreMany($request->ids());

        return $this->backToList($request, 'admin.custom-routes.index')->with(
            $geriYuklenen > 0 ? 'success' : 'error',
            $geriYuklenen > 0 ? "{$geriYuklenen} adres geri yüklendi." : 'Hiçbir adres geri yüklenemedi.',
        );
    }

    /**
     * Form ekranının ihtiyacı olan her şey.
     *
     * Hedeflerin beklediği parametreler de gidiyor: form, seçilen hedefe göre
     * hangi alanları soracağını buradan biliyor.
     *
     * @param  array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function formData(array $extra = []): array
    {
        $targets = $this->routes->availableTargets();

        return array_merge([
            'route'      => null,
            'targets'    => $targets,
            'parameters' => array_map(
                fn (string $name): array => $this->routes->parametersFor($name),
                array_combine(array_keys($targets), array_keys($targets)),
            ),
            'types'      => \App\Enums\CustomRouteType::cases(),
            'languages'  => app(LanguageService::class)->active(),
        ], $extra);
    }
}
