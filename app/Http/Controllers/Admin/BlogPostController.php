<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlogPostRequest;
use App\Http\Requests\UpdateBlogPostRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\InstagramPost;
use App\Services\BlogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class BlogPostController extends Controller
{
    public function __construct(
        private readonly BlogService $blogService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BlogPost::class);

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page')
            : 25;

        return view('admin.blog-posts.index', [
            'posts'        => $this->blogService->paginate($perPage, $request->only([
                'status', 'category_id', 'search',
            ])),
            'categories'   => BlogCategory::active()->sorted()->get(),
            'stats'        => $this->blogService->getAdminStats(),
            'statusCounts' => $this->blogService->getStatusCounts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BlogPost::class);

        return view('admin.blog-posts.create', [
            'categories' => BlogCategory::active()->sorted()->get(),
        ]);
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $this->authorize('create', BlogPost::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['is_published'] = (bool) $request->input('is_published', false);

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $this->blogService->create($data);

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
            'post'       => $blogPost,
            'categories' => BlogCategory::active()->sorted()->get(),
        ]);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blogPost): RedirectResponse
    {
        $this->authorize('update', $blogPost);

        $data = $request->validated();
        $data['is_published'] = (bool) $request->input('is_published', false);

        if ($data['is_published'] && empty($data['published_at']) && !$blogPost->published_at) {
            $data['published_at'] = now();
        }

        $this->blogService->update($blogPost, $data);

        return redirect()
            ->route('admin.blog-posts.index')
            ->with('success', 'İçerik başarıyla güncellendi.');
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

    /**
     * Blog yazısını sosyal medya gönderisine dönüştür — taslak InstagramPost
     * oluşturur, kullanıcıyı edit sayfasına yönlendirir. Orada caption düzenler,
     * hashtag ekler, Facebook cross-post seçer, yayınlar.
     *
     * Görsel: BlogPost.image (MediaLibrary kareli — Instagram için ideal).
     * Caption default: title + excerpt + blog URL.
     */
    public function shareToSocial(Request $request, BlogPost $blogPost): RedirectResponse
    {
        $this->authorize('view', $blogPost);

        if ($blogPost->image === null || $blogPost->image === '') {
            return back()->with('error', 'Bu blog yazısının kapak görseli yok — Instagram\'da paylaşılamaz. Önce kapak görseli ekle.');
        }

        $blogPost->loadMissing('category');

        $caption = $this->buildBlogShareCaption($blogPost);

        $instagramPost = InstagramPost::create([
            'image_path'          => $blogPost->image,
            'media_type'          => 'image',
            'caption'             => $caption,
            'hashtags'            => [],
            'status'              => \App\Enums\InstagramPostStatus::Draft,
            'publish_to_facebook' => false,
            'created_by'          => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.instagram-posts.edit', $instagramPost->id)
            ->with('success', 'Blog yazısı sosyal medya taslağına dönüştürüldü. Caption ve hashtag\'leri düzenleyip yayınla butonuna bas.');
    }

    /**
     * Blog için sosyal medya caption taslağı.
     * Format: başlık + boş satır + özet/snippet + boş satır + URL.
     */
    private function buildBlogShareCaption(BlogPost $blogPost): string
    {
        $title = trim((string) $blogPost->title);
        $rawExcerpt = trim((string) ($blogPost->excerpt ?? ''));

        if ($rawExcerpt === '' && $blogPost->body) {
            $rawExcerpt = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $blogPost->body)));
        }
        $excerpt = mb_substr($rawExcerpt, 0, 800, 'UTF-8');

        $url = route('blog.show', [$blogPost->category->slug, $blogPost->slug]);

        return trim($title . "\n\n" . $excerpt . "\n\nDevamını oku: " . $url);
    }
}
