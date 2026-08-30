<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomRouteType;
use App\Models\CustomRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Panelden açılmış adreslerin çözümü.
 *
 * Her istekte veritabanına gidilmiyor: bütün liste tek seferde okunup iki
 * yönlü bir haritaya çevriliyor ve önbellekte duruyor. Liste küçük (yönetici
 * elle açıyor), tamamını taşımak tek tek sorgulamaktan hem hızlı hem basit —
 * istek başına sıfır sorgu.
 *
 * İki yön:
 *   gelen  → (dil, slug) hangi rotaya gidiyor
 *   giden  → (rota, dil) hangi slug'la yazılıyor
 *
 * İkincisi olmadan sistem yarım kalırdı: /en/contact açılır ama menüdeki
 * bağlantı hâlâ /en/iletisim derdi.
 */
final class CustomRouteService
{
    private const CACHE_KEY = 'custom_routes.map';

    private const CACHE_TTL = 86400;

    /**
     * İstek başına bir kez okunan harita.
     *
     * @var array{incoming: array<string, array<string, mixed>>, outgoing: array<string, string>}|null
     */
    private ?array $memo = null;

    // ── Çözümleme ──

    /**
     * Bu dil ve slug için tanımlı adres.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $locale, string $slug): ?array
    {
        $map = $this->map();

        // Dile özgü kayıt, tüm dilleri kapsayandan önce gelir: genel bir kural
        // koyup tek bir dilde ayrıksı davranmak mümkün olsun.
        return $map['incoming'][$locale . '|' . $slug]
            ?? $map['incoming']['*|' . $slug]
            ?? null;
    }

    /**
     * Bu rotanın bu dildeki adresi — tanımlıysa.
     *
     * Menü ve bağlantı üretimi burayı soruyor: /en sayfasındaki "İletişim"
     * bağlantısı /en/contact demeli, /en/iletisim değil.
     *
     * @param array<string, mixed> $params
     */
    public function slugFor(string $routeName, string $locale, array $params = []): ?string
    {
        $map = $this->map();

        return $map['outgoing'][$this->outgoingKey($routeName, $locale, $params)]
            ?? $map['outgoing'][$this->outgoingKey($routeName, '*', $params)]
            ?? null;
    }

    /**
     * @return array{incoming: array<string, array<string, mixed>>, outgoing: array<string, string>}
     */
    public function map(): array
    {
        return $this->memo ??= Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $incoming = [];
            $outgoing = [];

            $rows = CustomRoute::query()
                ->active()
                // Aynı (dil, slug) için birden çok kayıt olursa ilki kazanır;
                // sıra kimliğe göre sabit, yani sonuç isteğe göre değişmiyor.
                ->orderBy('id')
                ->get(['id', 'locale', 'slug', 'target_route', 'target_params', 'type']);

            foreach ($rows as $row) {
                $locale = $row->locale ?? '*';
                $params = $row->target_params ?? [];

                $incoming[$locale . '|' . $row->slug] ??= [
                    'slug'   => $row->slug,
                    'route'  => $row->target_route,
                    'params' => $params,
                    'type'   => $row->type->value,
                ];

                // Giden yön yalnız "bu adreste göster" için anlamlı:
                // yönlendirme kaydı bir bağlantının yazılacağı yer değil,
                // eski bir adresin yeni adrese taşınması.
                if ($row->type === CustomRouteType::Render) {
                    $outgoing[$this->outgoingKey($row->target_route, $locale, $params)] ??= $row->slug;
                }
            }

            return ['incoming' => $incoming, 'outgoing' => $outgoing];
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->memo = null;
    }

    /**
     * Giden yön anahtarı.
     *
     * Parametreler de anahtara giriyor: pages.show iki farklı sayfa için iki
     * ayrı kayıt olabilir, ikisi aynı rotayı gösterir ama farklı slug'ları
     * vardır.
     *
     * @param array<string, mixed> $params
     */
    private function outgoingKey(string $routeName, string $locale, array $params): string
    {
        ksort($params);

        return $routeName . '|' . $locale . '|' . json_encode($params);
    }

    // ── Hedef listesi ──

    /**
     * Panelde seçilebilecek hedefler.
     *
     * Serbest metin değil: yönetici listeden seçiyor, yazım hatasıyla hiçbir
     * yere gitmeyen bir adres açamıyor. Var olmayan bir rota listeye
     * girmiyor — kod değişip rota kalkarsa seçenek de kendiliğinden kalkıyor.
     *
     * @return array<string, string>
     */
    public function availableTargets(): array
    {
        $targets = [
            'home'                    => 'Anasayfa',
            'blog.index'              => 'Blog listesi',
            'gallery'                 => 'Galeri',
            'contact'                 => 'İletişim',
            'faq'                     => 'Sıkça Sorulan Sorular',
            'pages.show'              => 'Dinamik sayfa (slug gerekir)',
            'blog.category'           => 'Blog kategorisi (categorySlug gerekir)',
            'blog.show'               => 'Blog yazısı (categorySlug + slug gerekir)',
            'login'                   => 'Giriş',
            'register'                => 'Kayıt',
            'account.dashboard'       => 'Hesabım',
        ];

        return array_filter($targets, static fn (string $name): bool => Route::has($name), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Hedefin beklediği parametre adları.
     *
     * Panel formu bunları soruyor; eksik parametreyle kaydedilen bir adres
     * çalışmaz ve hata ancak ziyaretçi tıklayınca görünürdü.
     *
     * @return list<string>
     */
    public function parametersFor(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            return [];
        }

        // {locale} rotanın kendi ön eki; yönetici onu doldurmuyor.
        return array_values(array_diff($route->parameterNames(), ['locale']));
    }

    // ── Liste ve kayıt ──

    /**
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['status', 'locale', 'target', 'search'];
    }

    /**
     * @param  array<string, mixed> $filters
     * @return Builder<CustomRoute>
     */
    public function query(array $filters = []): Builder
    {
        $query = CustomRoute::withTrashed()->orderByDesc('id');

        if (($filters['status'] ?? null) === 'trashed') {
            $query->onlyTrashed();
        } elseif (($filters['status'] ?? null) === 'passive') {
            $query->whereNull('deleted_at')->where('is_active', false);
        } elseif (($filters['status'] ?? null) === 'active') {
            $query->whereNull('deleted_at')->where('is_active', true);
        } else {
            $query->whereNull('deleted_at');
        }

        if (! empty($filters['locale'])) {
            $filters['locale'] === 'all'
                ? $query->whereNull('locale')
                : $query->where('locale', $filters['locale']);
        }

        if (! empty($filters['target'])) {
            $query->where('target_route', $filters['target']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('slug', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, CustomRoute>
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = CustomRoute::withTrashed()->selectRaw('
            SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as passive,
            SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as trashed
        ')->first();

        return [
            'active'  => (int) $counts->active,
            'passive' => (int) $counts->passive,
            'trashed' => (int) $counts->trashed,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): CustomRoute
    {
        return DB::transaction(function () use ($data): CustomRoute {
            $route = CustomRoute::create($this->prepare($data));
            $this->clearCache();

            return $route;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(CustomRoute $route, array $data): CustomRoute
    {
        return DB::transaction(function () use ($route, $data): CustomRoute {
            $route->update($this->prepare($data));
            $this->clearCache();

            return $route->refresh();
        });
    }

    public function delete(CustomRoute $route): void
    {
        $route->delete();
        $this->clearCache();
    }

    public function restore(int $id): CustomRoute
    {
        $route = CustomRoute::withTrashed()->findOrFail($id);
        $route->restore();
        $this->clearCache();

        return $route->refresh();
    }

    /**
     * @param  list<int> $ids
     * @return int       silinen kayıt sayısı
     */
    public function deleteMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $silinen = DB::transaction(fn (): int => CustomRoute::whereIn('id', $ids)->delete());

        if ($silinen > 0) {
            $this->clearCache();
        }

        return $silinen;
    }

    /**
     * @param  list<int> $ids
     * @return int       geri yüklenen kayıt sayısı
     */
    public function restoreMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $geriYuklenen = DB::transaction(fn (): int => CustomRoute::onlyTrashed()->whereIn('id', $ids)->restore());

        if ($geriYuklenen > 0) {
            $this->clearCache();
        }

        return $geriYuklenen;
    }

    /**
     * Kaydedilmeden önce alanları düzeltir.
     *
     * Slug baştaki eğik çizgiden ve dil ön ekinden arındırılıyor: yönetici
     * alışkanlıkla "/en/contact" yazsa da kayıt "contact" olarak duruyor,
     * yoksa adres "/en/en/contact" olurdu.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        if (isset($data['slug'])) {
            $data['slug'] = $this->normaliseSlug((string) $data['slug']);
        }

        if (($data['locale'] ?? null) === '' || ($data['locale'] ?? null) === 'all') {
            $data['locale'] = null;
        }

        $data['target_params'] = array_filter((array) ($data['target_params'] ?? []), static fn ($value): bool => $value !== null && $value !== '');

        return $data;
    }

    public function normaliseSlug(string $slug): string
    {
        $slug = trim($slug, " \t\n\r\0\x0B/");
        $parts = explode('/', $slug, 2);

        if ($parts[0] !== '' && app(LanguageService::class)->isSupported($parts[0])) {
            $slug = $parts[1] ?? '';
        }

        return $slug;
    }
}
