<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CategoryService
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function allActive(): Collection
    {
        return Cache::remember('categories.active', 3600, fn () =>
            Category::active()
                ->root()
                ->sorted()
                ->withCount(['products' => fn ($q) => $q->active()])
                ->with(['children' => fn ($q) => $q->active()->sorted()])
                ->get(),
        );
    }

    public function paginate(int $perPage = 15, ?array $filters = null): LengthAwarePaginator
    {
        $query = Category::query()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->with('parent');

        if ($filters) {
            $this->applyFilters($query, $filters);
        }

        return $query->orderBy('sort_order')->orderBy('id')->paginate($perPage);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Category> $query
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        if (isset($filters['status']) && $filters['status'] !== '') {
            match ($filters['status']) {
                'active'   => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default    => null,
            };
        }

        if (isset($filters['parent']) && $filters['parent'] !== '') {
            match ($filters['parent']) {
                'root'  => $query->whereNull('parent_id'),
                'child' => $query->whereNotNull('parent_id'),
                default => is_numeric($filters['parent'])
                    ? $query->where('parent_id', (int) $filters['parent'])
                    : null,
            };
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $query->where(function ($q) use ($filters): void {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }
    }

    /**
     * @return array{total: int, active: int, inactive: int, children: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('categories.admin_stats', 300, function (): array {
            $counts = Category::selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN parent_id IS NOT NULL THEN 1 ELSE 0 END) as children
            ")->first();

            return [
                'total'    => (int) $counts->total,
                'active'   => (int) $counts->active,
                'inactive' => (int) $counts->inactive,
                'children' => (int) $counts->children,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $counts = Category::selectRaw('is_active, count(*) as count')
            ->groupBy('is_active')
            ->pluck('count', 'is_active')
            ->toArray();

        $active   = (int) ($counts[1] ?? 0);
        $inactive = (int) ($counts[0] ?? 0);

        return [
            'all'      => $active + $inactive,
            'active'   => $active,
            'inactive' => $inactive,
        ];
    }

    /**
     * @return Collection<int, Category>
     */
    public function allForFilter(): Collection
    {
        return Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'parent_id']);
    }

    public function findBySlug(string $slug): Category
    {
        return Category::active()
            ->where('slug', $slug)
            ->with([
                'children' => fn ($q) => $q->active()->sorted(),
                'products' => fn ($q) => $q->active()->sorted()->limit(12),
            ])
            ->firstOrFail();
    }

    public function findById(int $id): Category
    {
        return Category::with('parent', 'children')->findOrFail($id);
    }

    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data): Category {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->uploadImage($data['image'], 'categories', $data['name']);
            }

            $category = Category::create($data);
            $this->clearCache();

            return $category;
        });
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data): Category {
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $data['image'] = $this->uploadService->replaceImage(
                    $data['image'],
                    'categories',
                    $data['name'] ?? $category->name,
                    $category->image,
                );
            }

            $category->update($data);
            $this->clearCache();

            return $category->refresh();
        });
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->delete();
            $this->clearCache();
        });
    }

    public function restore(int $id): Category
    {
        $category = Category::withTrashed()->findOrFail($id);
        $category->restore();
        $this->clearCache();

        return $category;
    }

    private function clearCache(): void
    {
        Cache::forget('categories.active');
        Cache::forget('categories.admin_stats');
        // Modül 7 — kategori değişikliği sitemap'e anında yansısın.
        Cache::forget('sitemap.urls');
        Cache::forget('sitemap_page.groups');
    }
}
