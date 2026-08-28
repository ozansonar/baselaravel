<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTranslatedBlogPostRequest;
use App\Http\Requests\StoreBlogPostRequest;
use App\Http\Requests\UpdateBlogPostRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostFile;
use App\Services\BlogPostFileService;
use App\Services\BlogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class BlogPostController extends Controller
{
    public function __construct(
        private readonly BlogService $blogService,
        private readonly BlogPostFileService $blogPostFiles,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BlogPost::class);

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page')
            : 25;

        return view('admin.blog-posts.index', [
            'posts'        => $this->blogService->paginate($perPage, $request->only($this->blogService->filterKeys())),
            'categories'   => BlogCategory::active()->sorted()->get(),
            'stats'        => $this->blogService->getAdminStats(),
            'statusCounts' => $this->blogService->getStatusCounts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BlogPost::class);

        return view('admin.blog-posts.create', [
            // Every language's categories; each tab shows only its own.
            'categories'    => BlogCategory::active()->sorted()->get(),
            'formLanguages' => $this->blogService->formLanguages(),
            // Ek kutusunun gösterdiği tavan sunucununkiyle aynı olmalı; iki
            // yerde ayrı yazılsaydı ekranda yazan sayı ile kabul edilen boyut
            // zamanla ayrışırdı.
            'fileLimits'    => $this->blogPostFiles->limits(),
            'pendingFiles'  => $this->pendingFilesFromOldInput(),
        ]);
    }

    public function store(StoreTranslatedBlogPostRequest $request): RedirectResponse
    {
        $this->authorize('create', BlogPost::class);

        $this->blogService->createTranslated(
            $request->validated('translations'),
            $this->sharedFields($request),
        );

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'İçerik başarıyla oluşturuldu.');
    }

    public function show(BlogPost $blogPost): View
    {
        $this->authorize('view', $blogPost);

        $blogPost->load(['category', 'author', 'comments']);

        return view('admin.blog-posts.show', [
            'post' => $blogPost,
        ]);
    }

    public function edit(BlogPost $blogPost): View
    {
        $this->authorize('update', $blogPost);

        return view('admin.blog-posts.edit', [
            'post'          => $blogPost,
            'categories'    => BlogCategory::active()->sorted()->get(),
            'formLanguages' => $this->blogService->formLanguages(),
            'fileLimits'    => $this->blogPostFiles->limits(),
            'pendingFiles'  => $this->pendingFilesFromOldInput(),
        ]);
    }

    /**
     * Doğrulama hatasından sonra forma geri dönen bekleyen ekler.
     *
     * Yüklenen dosyanın satırını JS çiziyor; sayfa yeniden yüklenince o satır
     * kayboluyor. Başlığı boş bırakıp kaydeden kullanıcı, hatayı düzeltip
     * tekrar kaydettiğinde az önce yüklediği beş dosyayı listede bulamıyor ve
     * dosyalar bir gün sonra temizliğe takılana kadar sahipsiz bekliyordu.
     *
     * Bekleyenler sahibiyle aranıyor: belirteç uydurulsa bile başkasının
     * dosyası forma geri gelmiyor.
     *
     * @return array<string, \Illuminate\Support\Collection<int, BlogPostFile>> locale => files
     */
    private function pendingFilesFromOldInput(): array
    {
        $userId = auth()->id();
        $restored = [];

        foreach ((array) old('translations', []) as $locale => $fields) {
            $tokens = is_array($fields) ? ($fields['file_tokens'] ?? null) : null;

            if (! is_array($tokens) || $tokens === []) {
                continue;
            }

            $restored[$locale] = BlogPostFile::query()
                ->pending($userId)
                ->whereIn('token', $tokens)
                ->sorted()
                ->get();
        }

        return $restored;
    }

    public function update(StoreTranslatedBlogPostRequest $request, BlogPost $blogPost): RedirectResponse
    {
        $this->authorize('update', $blogPost);

        $this->blogService->updateTranslated(
            $blogPost,
            $request->validated('translations'),
            $this->sharedFields($request, $blogPost),
        );

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'İçerik başarıyla güncellendi.');
    }

    /**
     * Fields that belong to the post rather than to one translation: who wrote
     * it and whether it is published.
     *
     * @return array<string, mixed>
     */
    private function sharedFields(StoreTranslatedBlogPostRequest $request, ?BlogPost $post = null): array
    {
        // The publish state now lives in each language block; only the author
        // is shared across them.
        return ['user_id' => $request->user()->id];
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $this->authorize('delete', $blogPost);

        $this->blogService->delete($blogPost);

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'İçerik başarıyla silindi.');
    }

    public function restore(int $id): RedirectResponse
    {
        $post = BlogPost::withTrashed()->findOrFail($id);
        $this->authorize('restore', $post);

        $this->blogService->restore($id);

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'İçerik başarıyla geri yüklendi.');
    }

}
