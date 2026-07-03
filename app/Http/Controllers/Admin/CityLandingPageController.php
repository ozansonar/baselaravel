<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkCreateCityRequest;
use App\Http\Requests\StoreCityLandingPageRequest;
use App\Http\Requests\UpdateCityLandingPageRequest;
use App\Models\CityLandingPage;
use App\Services\CityLandingPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CityLandingPageController extends Controller
{
    public function __construct(
        private readonly CityLandingPageService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CityLandingPage::class);

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page')
            : 25;

        return view('admin.city-pages.index', [
            'pages'        => $this->service->paginate($perPage, $request->only([
                'status', 'tier', 'search',
            ])),
            'stats'        => $this->service->getAdminStats(),
            'statusCounts' => $this->service->getStatusCounts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CityLandingPage::class);

        return view('admin.city-pages.create');
    }

    public function store(StoreCityLandingPageRequest $request): RedirectResponse
    {
        $this->authorize('create', CityLandingPage::class);

        $this->service->create($request->validated());

        return redirect()
            ->route('admin.city-pages.index')
            ->with('success', 'Şehir sayfası başarıyla oluşturuldu.');
    }

    public function edit(CityLandingPage $cityPage): View
    {
        $this->authorize('update', $cityPage);

        return view('admin.city-pages.edit', [
            'page' => $cityPage,
        ]);
    }

    public function update(UpdateCityLandingPageRequest $request, CityLandingPage $cityPage): RedirectResponse
    {
        $this->authorize('update', $cityPage);

        $this->service->update($cityPage, $request->validated());

        return redirect()
            ->route('admin.city-pages.index')
            ->with('success', 'Şehir sayfası başarıyla güncellendi.');
    }

    public function destroy(CityLandingPage $cityPage): RedirectResponse
    {
        $this->authorize('delete', $cityPage);

        $this->service->delete($cityPage);

        return redirect()
            ->route('admin.city-pages.index')
            ->with('success', 'Şehir sayfası başarıyla silindi.');
    }

    public function bulkCreatePage(): View
    {
        $this->authorize('create', CityLandingPage::class);

        return view('admin.city-pages.bulk-create', [
            'cityCatalog' => $this->cityCatalog(),
        ]);
    }

    public function bulkStore(BulkCreateCityRequest $request): RedirectResponse
    {
        $this->authorize('create', CityLandingPage::class);

        $fillWithAi = $request->boolean('fill_with_ai');

        // AI-fill mode hits Gemini synchronously per city (~30-60s each),
        // so allow up to 15 minutes for the full bulk operation. Kullanıcı
        // form'da uyarıldı.
        if ($fillWithAi) {
            @set_time_limit(900);
        }

        $result = $this->service->bulkCreate($request->input('cities'), $fillWithAi);

        $message = "{$result['created']} şehir sayfası oluşturuldu.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} şehir zaten mevcut olduğu için atlandı.";
        }
        if ($fillWithAi) {
            $message .= " AI ile {$result['ai_filled']} şehre içerik üretildi";
            if ($result['ai_failed'] > 0) {
                $message .= " ({$result['ai_failed']} şehirde AI başarısız oldu, sayfaları açıp tekrar deneyebilirsiniz)";
            }
            $message .= '.';
        }

        return redirect()
            ->route('admin.city-pages.index')
            ->with('success', $message);
    }

    public function restore(int $id): RedirectResponse
    {
        $page = CityLandingPage::withTrashed()->findOrFail($id);
        $this->authorize('restore', $page);

        $this->service->restore($id);

        return redirect()
            ->route('admin.city-pages.index')
            ->with('success', 'Şehir sayfası geri yüklendi.');
    }

    /**
     * Predefined city catalog grouped by tier (per SEO spec).
     *
     * @return array<int, array{tier: int, label: string, cities: list<array{name: string, region: string}>}>
     */
    private function cityCatalog(): array
    {
        return [
            [
                'tier'   => 1,
                'label'  => 'Tier 1 — Büyük Metropoller',
                'cities' => [
                    ['name' => 'İstanbul', 'region' => 'Marmara'],
                    ['name' => 'Ankara',   'region' => 'İç Anadolu'],
                    ['name' => 'İzmir',    'region' => 'Ege'],
                    ['name' => 'Bursa',    'region' => 'Marmara'],
                    ['name' => 'Antalya',  'region' => 'Akdeniz'],
                ],
            ],
            [
                'tier'   => 2,
                'label'  => 'Tier 2 — Büyük Şehirler',
                'cities' => [
                    ['name' => 'Konya',     'region' => 'İç Anadolu'],
                    ['name' => 'Adana',     'region' => 'Akdeniz'],
                    ['name' => 'Gaziantep', 'region' => 'Güneydoğu Anadolu'],
                    ['name' => 'Kayseri',   'region' => 'İç Anadolu'],
                    ['name' => 'Samsun',    'region' => 'Karadeniz'],
                ],
            ],
            [
                'tier'   => 3,
                'label'  => 'Tier 3 — Orta Ölçek',
                'cities' => [
                    ['name' => 'Trabzon',    'region' => 'Karadeniz'],
                    ['name' => 'Eskişehir',  'region' => 'İç Anadolu'],
                    ['name' => 'Denizli',    'region' => 'Ege'],
                    ['name' => 'Sakarya',    'region' => 'Marmara'],
                    ['name' => 'Mersin',     'region' => 'Akdeniz'],
                ],
            ],
            [
                'tier'   => 4,
                'label'  => 'Tier 4 — Çorum Çevresi',
                'cities' => [
                    ['name' => 'Çorum',     'region' => 'Karadeniz'],
                    ['name' => 'Amasya',    'region' => 'Karadeniz'],
                    ['name' => 'Çankırı',   'region' => 'İç Anadolu'],
                    ['name' => 'Tokat',     'region' => 'Karadeniz'],
                    ['name' => 'Yozgat',    'region' => 'İç Anadolu'],
                ],
            ],
        ];
    }
}
