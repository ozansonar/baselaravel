<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialDay;
use App\Models\SpecialDayImage;
use App\Services\SpecialDaysAiService;
use App\Services\SpecialDaysProvider;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * /admin/ozel-gunler — Türk özel gün takvimi yönetimi.
 *
 * Özellikler:
 *  - Tarih aralığı filtresi (default: bu yıl)
 *  - CRUD (manuel ekleme/düzenleme/silme)
 *  - "AI'dan hicri günleri getir" butonu (yıllık 1 kez)
 *  - "Yıl için sabit + kural günleri seed et" butonu
 *  - Görsel galeri yönetimi (her güne 0-N görsel — Story/Feed/Reels)
 */
final class SpecialDayController extends Controller
{
    public function __construct(
        private readonly SpecialDaysProvider $provider,
        private readonly SpecialDaysAiService $aiService,
        private readonly UploadService $uploadService,
    ) {}

    public function index(Request $request): View
    {
        $currentYear = (int) now()->format('Y');

        $year = (int) $request->query('year', (string) $currentYear);
        if ($year < 2024 || $year > 2050) {
            $year = $currentYear;
        }

        $from = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $to   = Carbon::createFromDate($year, 12, 31)->endOfDay();

        // Sabit + kural günleri (formül-bazlı) seed et — idempotent
        $this->provider->ensureFormulaBasedDaysExist($from, $to);

        $days = SpecialDay::query()
            ->inRange($from, $to)
            ->withCount('images')
            ->orderBy('date')
            ->get();

        $missingHicriYears = $this->provider->getYearsMissingHicriData($from, $to);

        // Galeri durumu özetleri (her gün için)
        $galleryCoverage = $days->map(static function (SpecialDay $d): array {
            return [
                'day_id' => $d->id,
                'feed'   => $d->images()->ofMediaType('feed')->count(),
                'reels'  => $d->images()->ofMediaType('reels')->count(),
                'story'  => $d->images()->ofMediaType('story')->count(),
            ];
        })->keyBy('day_id');

        $availableYears = range($currentYear - 1, $currentYear + 3);

        return view('admin.special-days.index', [
            'days'              => $days,
            'galleryCoverage'   => $galleryCoverage,
            'year'              => $year,
            'availableYears'    => $availableYears,
            'missingHicriYears' => $missingHicriYears,
        ]);
    }

    public function create(): View
    {
        return view('admin.special-days.form', [
            'day' => new SpecialDay(['date' => now()->toDateString(), 'category' => 'genel', 'source' => 'manual']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);
        $validated['slug'] = $this->buildUniqueSlug($validated['name'], $validated['date']);
        $validated['source'] = SpecialDay::SOURCE_MANUAL;
        $validated['is_movable'] = (bool) ($validated['is_movable'] ?? false);

        $day = SpecialDay::create($validated);

        return redirect()
            ->route('admin.special-days.gallery', $day->id)
            ->with('success', "'{$day->name}' özel günü eklendi. Şimdi galerine görsel ekleyebilirsin.");
    }

    public function edit(SpecialDay $specialDay): View
    {
        return view('admin.special-days.form', ['day' => $specialDay]);
    }

    public function update(Request $request, SpecialDay $specialDay): RedirectResponse
    {
        $validated = $this->validateForm($request, $specialDay->id);
        $validated['slug'] = $this->buildUniqueSlug($validated['name'], $validated['date'], $specialDay->id);
        $validated['is_movable'] = (bool) ($validated['is_movable'] ?? false);

        // Manuel düzenlendiğinde source 'manual' yapma — kullanıcı isterse override edebilir
        if ($specialDay->source === SpecialDay::SOURCE_AI && $request->user() !== null) {
            $validated['verified_at'] = now();
            $validated['verified_by'] = $request->user()->id;
        }

        $specialDay->update($validated);

        return redirect()
            ->route('admin.special-days.index', ['year' => substr((string) $validated['date'], 0, 4)])
            ->with('success', "'{$specialDay->name}' güncellendi.");
    }

    public function destroy(SpecialDay $specialDay): RedirectResponse
    {
        $name = $specialDay->name;
        $year = $specialDay->date->year;
        $specialDay->delete();

        return redirect()
            ->route('admin.special-days.index', ['year' => $year])
            ->with('success', "'{$name}' silindi.");
    }

    /**
     * Yıl için AI'dan hicri günleri çekip DB'ye yazar (idempotent).
     */
    public function fetchAi(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', (int) now()->format('Y'));

        $result = $this->aiService->fetchAndStoreForYear($year);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Yıl için sabit + kural günleri seed eder (Anneler/Babalar/Yılbaşı vs.).
     */
    public function seedFormulas(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', (int) now()->format('Y'));
        if ($year < 2024 || $year > 2050) {
            return response()->json(['success' => false, 'message' => 'Yıl aralığı: 2024-2050'], 422);
        }

        $count = $this->provider->seedYearAllFormulas($year);

        return response()->json([
            'success' => true,
            'message' => "{$year} yılı için {$count} yeni gün eklendi (sabit + kural-bazlı).",
            'count'   => $count,
        ]);
    }

    // ─── Galeri ──────────────────────────────────────────────────

    public function gallery(SpecialDay $specialDay): View
    {
        return view('admin.special-days.gallery', [
            'day'    => $specialDay,
            'images' => $specialDay->images()
                ->orderBy('media_type')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function uploadImages(Request $request, SpecialDay $specialDay): JsonResponse
    {
        $validated = $request->validate([
            'images'     => ['required', 'array', 'min:1', 'max:20'],
            'images.*'   => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'media_type' => ['required', 'string', 'in:feed,reels,story'],
            'alt_text'   => ['nullable', 'string', 'max:250'],
        ], [
            'images.required'     => 'En az 1 görsel seçmelisin.',
            'images.max'          => 'Tek seferde en fazla 20 görsel yükleyebilirsin.',
            'images.*.image'      => 'Geçerli bir görsel dosyası gerekli.',
            'images.*.max'        => 'Her görsel en fazla 8 MB olabilir.',
            'media_type.required' => 'Medya tipi seç (Story/Feed/Reels).',
        ]);

        $uploaded = [];

        DB::transaction(function () use ($specialDay, $request, $validated, &$uploaded): void {
            $maxSort = (int) $specialDay->images()
                ->where('media_type', $validated['media_type'])
                ->max('sort_order');

            foreach ($request->file('images', []) as $file) {
                $path = $this->uploadService->uploadImage(
                    $file,
                    'special-days/' . $specialDay->id,
                    'sd-' . $specialDay->id . '-' . Str::random(6),
                );

                $img = SpecialDayImage::create([
                    'special_day_id' => $specialDay->id,
                    'image_path'     => $path,
                    'media_type'     => $validated['media_type'],
                    'usage_count'    => 0,
                    'sort_order'     => ++$maxSort,
                    'alt_text'       => $validated['alt_text'] ?? null,
                ]);

                $uploaded[] = [
                    'id'         => $img->id,
                    'url'        => upload_url($path, 'md'),
                    'media_type' => $img->media_type,
                ];
            }
        });

        return response()->json([
            'success'  => true,
            'message'  => count($uploaded) . ' görsel yüklendi.',
            'uploaded' => $uploaded,
        ]);
    }

    public function updateImage(Request $request, SpecialDayImage $image): JsonResponse
    {
        $validated = $request->validate([
            'media_type' => ['nullable', 'string', 'in:feed,reels,story'],
            'alt_text'   => ['nullable', 'string', 'max:250'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $image->update(array_filter($validated, static fn ($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Güncellendi.']);
    }

    public function destroyImage(SpecialDayImage $image): JsonResponse
    {
        try {
            $this->uploadService->deleteImage($image->image_path);
        } catch (\Throwable) {
            // disk silinmedi — DB'den silmeye devam
        }
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Görsel silindi.']);
    }

    // ─── helpers ─────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateForm(Request $request, ?int $excludeId = null): array
    {
        return $request->validate([
            'date'       => ['required', 'date_format:Y-m-d'],
            'name'       => ['required', 'string', 'min:2', 'max:150'],
            'category'   => ['required', 'string', 'in:' . implode(',', SpecialDay::CATEGORIES)],
            'theme'      => ['nullable', 'string', 'max:1000'],
            'is_movable' => ['nullable', 'boolean'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ], [
            'date.required'     => 'Tarih zorunlu.',
            'date.date_format'  => 'Tarih formatı YYYY-MM-DD olmalı.',
            'name.required'     => 'Özel gün adı zorunlu.',
            'category.required' => 'Kategori seç (ulusal/dini/özel/sezonsal/genel).',
        ]);
    }

    private function buildUniqueSlug(string $name, string $date, ?int $excludeId = null): string
    {
        $year = substr($date, 0, 4);
        $base = Str::slug($name) . '-' . $year;
        $slug = $base;
        $i = 2;

        while (SpecialDay::query()
            ->where('date', $date)
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
