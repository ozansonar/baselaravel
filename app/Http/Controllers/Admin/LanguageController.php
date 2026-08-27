<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLanguageRequest;
use App\Http\Requests\Admin\UpdateLanguageRequest;
use App\Models\Language;
use App\Services\LanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

final class LanguageController extends Controller
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    private const PER_PAGE = [10, 25, 50];

    /**
     * Listede seçilebilecek sıralamalar. İstekten gelen değer bu kümeyle
     * sınırlı; serbest bırakılsaydı sütun adı doğrudan sorguya girerdi.
     *
     * @var array<string, string>
     */
    private const SORT_OPTIONS = [
        'order'  => 'Sıra numarasına göre',
        'name'   => 'Ada göre (A-Z)',
        'code'   => 'Koda göre (A-Z)',
        'recent' => 'En son eklenen',
        'oldest' => 'İlk eklenen',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Language::class);

        $translated = $this->translatedLocales();
        $contentStats = $this->contentCounts();

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, self::PER_PAGE, true) ? $perPage : 10;

        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, self::SORT_OPTIONS) ? $sort : '';

        $filters = [
            'search'  => (string) $request->string('search')->trim()->value(),
            'status'  => (string) $request->string('status')->value(),
            'files'   => (string) $request->string('files')->value(),
            'content' => (string) $request->string('content')->value(),
            'sort'    => $sort,
        ];

        $query = Language::query();

        if ($filters['search'] !== '') {
            // Joker karakterler düz metin sayılıyor: "%" yazan biri tüm listeyi
            // getirmemeli.
            $term = '%' . addcslashes($filters['search'], '%_\\') . '%';

            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('native_name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            });
        }

        match ($filters['status']) {
            'active'   => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'default'  => $query->where('is_default', true),
            default    => null,
        };

        // "Interface files" is a filesystem fact, not a column, so it filters on
        // the codes found on disk rather than in SQL.
        match ($filters['files']) {
            'yes'   => $query->whereIn('code', $translated ?: ['']),
            'no'    => $query->whereNotIn('code', $translated ?: ['']),
            default => null,
        };

        // İçerik sayısı da sütun değil: dokuz tablodan toplanıyor, süzgeç o
        // toplamın sıfırdan büyük olduğu kodlar üzerinden çalışıyor.
        $withContent = array_keys(array_filter($contentStats, fn (int $count): bool => $count > 0));

        match ($filters['content']) {
            'yes'   => $query->whereIn('code', $withContent ?: ['']),
            'no'    => $query->whereNotIn('code', $withContent ?: ['']),
            default => null,
        };

        match ($filters['sort']) {
            'name'   => $query->orderBy('name'),
            'code'   => $query->orderBy('code'),
            'recent' => $query->latest('id'),
            'oldest' => $query->oldest('id'),
            // Varsayılan dil hep başta: listenin çıpası o, sıra numarası ondan
            // sonra geliyor.
            default  => $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name'),
        };

        $all = Language::all();

        return view('admin.languages.index', [
            'languages'    => $query->paginate($perPage)->withQueryString(),
            'translated'   => $translated,
            'contentStats' => $contentStats,
            'filters'      => $filters,
            'filtered'     => collect($filters)->except('sort')->filter(fn (string $value): bool => $value !== '')->isNotEmpty(),
            'sortOptions'  => self::SORT_OPTIONS,
            'perPage'      => $perPage,
            'perPageList'  => self::PER_PAGE,
            'stats'        => [
                'total'      => $all->count(),
                'active'     => $all->where('is_active', true)->count(),
                'inactive'   => $all->where('is_active', false)->count(),
                'default'    => $all->firstWhere('is_default', true)?->code,
                'translated' => $all->whereIn('code', $translated)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Language::class);

        return view('admin.languages.create', [
            'suggestions' => $this->suggestions(),
        ]);
    }

    public function edit(Language $language): View
    {
        $this->authorize('update', $language);

        return view('admin.languages.edit', [
            'language'     => $language,
            'hasFiles'     => $this->hasTranslationFiles($language->code),
            'contentCount' => $this->contentCounts()[$language->code] ?? 0,
        ]);
    }

    /**
     * Ekleme formunda tek tıkla dolan diller: kod, ad, yerel ad ve bayrak elle
     * aranmasın diye.
     *
     * Liste konuşan nüfusa göre yaygın otuz dil; hepsi ISO 639-1 iki harfli kod
     * taşıyor, çünkü form da onu bekliyor. Zaten eklenmiş diller ayıklanıyor:
     * tıklanınca "bu kod zaten var" hatası veren bir kutucuğun anlamı yok.
     *
     * @return array<int, array{code: string, name: string, native: string, flag: string}>
     */
    private function suggestions(): array
    {
        $existing = Language::pluck('code')->all();

        $common = [
            ['code' => 'en', 'name' => 'İngilizce',   'native' => 'English',           'flag' => '🇬🇧'],
            ['code' => 'tr', 'name' => 'Türkçe',      'native' => 'Türkçe',            'flag' => '🇹🇷'],
            ['code' => 'de', 'name' => 'Almanca',     'native' => 'Deutsch',           'flag' => '🇩🇪'],
            ['code' => 'fr', 'name' => 'Fransızca',   'native' => 'Français',          'flag' => '🇫🇷'],
            ['code' => 'es', 'name' => 'İspanyolca',  'native' => 'Español',           'flag' => '🇪🇸'],
            ['code' => 'it', 'name' => 'İtalyanca',   'native' => 'Italiano',          'flag' => '🇮🇹'],
            ['code' => 'ru', 'name' => 'Rusça',       'native' => 'Русский',           'flag' => '🇷🇺'],
            ['code' => 'ar', 'name' => 'Arapça',      'native' => 'العربية',             'flag' => '🇸🇦'],
            ['code' => 'zh', 'name' => 'Çince',       'native' => '中文',               'flag' => '🇨🇳'],
            ['code' => 'ja', 'name' => 'Japonca',     'native' => '日本語',             'flag' => '🇯🇵'],
            ['code' => 'pt', 'name' => 'Portekizce',  'native' => 'Português',         'flag' => '🇵🇹'],
            ['code' => 'nl', 'name' => 'Felemenkçe',  'native' => 'Nederlands',        'flag' => '🇳🇱'],
            ['code' => 'hi', 'name' => 'Hintçe',      'native' => 'हिन्दी',               'flag' => '🇮🇳'],
            ['code' => 'ko', 'name' => 'Korece',      'native' => '한국어',             'flag' => '🇰🇷'],
            ['code' => 'fa', 'name' => 'Farsça',      'native' => 'فارسی',              'flag' => '🇮🇷'],
            ['code' => 'pl', 'name' => 'Lehçe',       'native' => 'Polski',            'flag' => '🇵🇱'],
            ['code' => 'uk', 'name' => 'Ukraynaca',   'native' => 'Українська',        'flag' => '🇺🇦'],
            ['code' => 'az', 'name' => 'Azerbaycanca','native' => 'Azərbaycanca',      'flag' => '🇦🇿'],
            ['code' => 'sv', 'name' => 'İsveççe',     'native' => 'Svenska',           'flag' => '🇸🇪'],
            ['code' => 'el', 'name' => 'Yunanca',     'native' => 'Ελληνικά',          'flag' => '🇬🇷'],
            ['code' => 'ro', 'name' => 'Romence',     'native' => 'Română',            'flag' => '🇷🇴'],
            ['code' => 'bg', 'name' => 'Bulgarca',    'native' => 'Български',         'flag' => '🇧🇬'],
            ['code' => 'cs', 'name' => 'Çekçe',       'native' => 'Čeština',           'flag' => '🇨🇿'],
            ['code' => 'hu', 'name' => 'Macarca',     'native' => 'Magyar',            'flag' => '🇭🇺'],
            ['code' => 'sr', 'name' => 'Sırpça',      'native' => 'Српски',            'flag' => '🇷🇸'],
            ['code' => 'da', 'name' => 'Danca',       'native' => 'Dansk',             'flag' => '🇩🇰'],
            ['code' => 'fi', 'name' => 'Fince',       'native' => 'Suomi',             'flag' => '🇫🇮'],
            ['code' => 'no', 'name' => 'Norveççe',    'native' => 'Norsk',             'flag' => '🇳🇴'],
            ['code' => 'he', 'name' => 'İbranice',    'native' => 'עברית',              'flag' => '🇮🇱'],
            ['code' => 'id', 'name' => 'Endonezce',   'native' => 'Bahasa Indonesia',  'flag' => '🇮🇩'],
            ['code' => 'th', 'name' => 'Tayca',       'native' => 'ไทย',                'flag' => '🇹🇭'],
            ['code' => 'vi', 'name' => 'Vietnamca',   'native' => 'Tiếng Việt',        'flag' => '🇻🇳'],
        ];

        return array_values(array_filter(
            $common,
            fn (array $row): bool => ! in_array($row['code'], $existing, true),
        ));
    }

    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        $this->authorize('create', Language::class);

        // An unchecked checkbox is simply absent from the request, so the
        // default has to be false — with a default of true no language could
        // ever be switched off from the form.
        $language = $this->languages->create($request->validated() + [
            'is_active' => $request->boolean('is_active'),
        ]);

        $message = "{$language->name} eklendi.";

        // A language with no lang/{code} directory still works — the interface
        // falls back to the default — but the admin should know it will not be
        // translated until those files exist.
        if (! $this->hasTranslationFiles($language->code)) {
            $message .= " Arayüz çevirisi için lang/{$language->code}/ klasörü oluşturulmalı;"
                . ' o zamana kadar arayüz varsayılan dilde görünür.';
        }

        return redirect()
            ->route('admin.languages.index')
            ->with('success', $message);
    }

    public function update(UpdateLanguageRequest $request, Language $language): RedirectResponse
    {
        $this->authorize('update', $language);

        $this->languages->update($language, $request->validated() + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.languages.index')
            ->with('success', "{$language->name} güncellendi.");
    }

    /**
     * Move the default flag. Exactly one language keeps it.
     */
    public function makeDefault(Language $language): RedirectResponse
    {
        $this->authorize('update', $language);

        $this->languages->makeDefault($language);

        return redirect()
            ->route('admin.languages.index')
            ->with('success', "Varsayılan dil {$language->name} oldu.");
    }

    public function destroy(Language $language): RedirectResponse
    {
        $this->authorize('delete', $language);

        $result = $this->languages->delete($language);

        return redirect()
            ->route('admin.languages.index')
            ->with($result['deleted'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Which languages have interface translation files on disk.
     *
     * @return array<int, string>
     */
    private function translatedLocales(): array
    {
        if (! File::isDirectory(lang_path())) {
            return [];
        }

        return collect(File::directories(lang_path()))
            ->map(fn (string $path): string => basename($path))
            ->values()
            ->all();
    }

    private function hasTranslationFiles(string $code): bool
    {
        return File::exists(lang_path($code . '/site.php'));
    }

    /**
     * How much content exists in each language, so a language is not switched
     * off or deleted without knowing what goes with it.
     *
     * @return array<string, int>
     */
    private function contentCounts(): array
    {
        $tables = [
            'pages', 'blog_posts', 'blog_categories', 'gallery_categories',
            'gallery_items', 'faqs', 'sliders', 'popups', 'menus',
        ];

        $counts = [];

        foreach ($tables as $table) {
            foreach (
                \Illuminate\Support\Facades\DB::table($table)
                    ->selectRaw('locale, COUNT(*) as total')
                    ->whereNull('deleted_at')
                    ->groupBy('locale')
                    ->get() as $row
            ) {
                $locale = (string) $row->locale;
                $counts[$locale] = ($counts[$locale] ?? 0) + (int) $row->total;
            }
        }

        return $counts;
    }
}
