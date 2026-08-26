<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTranslatedBlogCategoryRequest;
use App\Http\Requests\StoreBlogCategoryRequest;
use App\Http\Requests\UpdateBlogCategoryRequest;
use App\Models\BlogCategory;
use App\Services\BlogCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BlogCategoryController extends Controller
{
    public function __construct(
        private readonly BlogCategoryService $blogCategoryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BlogCategory::class);

        $perPage = in_array((int) $request->input('per_page', 25), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 25)
            : 25;

        $filters = $request->only(['status', 'search']);

        return view('admin.blog-categories.index', [
            'categories'   => $this->blogCategoryService->paginate($perPage, $filters),
            'stats'        => $this->blogCategoryService->getAdminStats(),
            'statusCounts' => $this->blogCategoryService->statusCounts(),
            'perPage'      => $perPage,
            // Keyed by code so the table can turn a group's locale list into
            // flags without hitting the database per row.
            'languages'    => $this->blogCategoryService->formLanguages()->keyBy('code'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BlogCategory::class);

        return view('admin.blog-categories.create', ['formLanguages' => $this->blogCategoryService->formLanguages()]);
    }

    public function store(StoreTranslatedBlogCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', BlogCategory::class);

        $this->blogCategoryService->createTranslated($request->validated('translations'));

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'İçerik kategorisi başarıyla oluşturuldu.');
    }

    public function edit(BlogCategory $blogCategory): View
    {
        $this->authorize('update', $blogCategory);

        return view('admin.blog-categories.edit', [
            'category'      => $blogCategory,
            'formLanguages' => $this->blogCategoryService->formLanguages(),
        ]);
    }

    public function update(StoreTranslatedBlogCategoryRequest $request, BlogCategory $blogCategory): RedirectResponse
    {
        $this->authorize('update', $blogCategory);

        $this->blogCategoryService->updateTranslated($blogCategory, $request->validated('translations'));

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'İçerik kategorisi başarıyla güncellendi.');
    }

    public function destroy(BlogCategory $blogCategory): RedirectResponse
    {
        $this->authorize('delete', $blogCategory);

        $this->blogCategoryService->delete($blogCategory);

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'İçerik kategorisi başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $category = BlogCategory::withTrashed()->findOrFail($id);
        $this->authorize('restore', $category);

        $this->blogCategoryService->restore($id);

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'İçerik kategorisi başarıyla geri yüklendi.');
    }
}
