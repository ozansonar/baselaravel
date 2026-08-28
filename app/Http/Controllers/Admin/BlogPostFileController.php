<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogPostFileRequest;
use App\Models\BlogPost;
use App\Models\BlogPostFile;
use App\Services\BlogPostFileService;
use Illuminate\Http\JsonResponse;

/**
 * İçerik eklerinin kendi uçları.
 *
 * Dosyalar içerik formuyla birlikte gitmiyor; her biri kendi isteğiyle geliyor
 * (gerekçe: App\Services\BlogPostFileService). Bu yüzden yükleme ve kaldırma
 * içerik kaydetmeden de çalışır ve tek dosyanın başarısızlığı formu etkilemez.
 */
final class BlogPostFileController extends Controller
{
    public function __construct(
        private readonly BlogPostFileService $files,
    ) {}

    /**
     * Tek dosyayı yükler.
     *
     * Çevirisi kayıtlı bir dile yükleniyorsa ek doğrudan o satıra bağlanır:
     * kullanıcı kaydet'e basmasa bile dosya yerindedir. Satırı olmayan dilde
     * (yeni içerik ya da hiç çevrilmemiş sekme) belirteçle bekler.
     */
    public function store(StoreBlogPostFileRequest $request): JsonResponse
    {
        $post = $request->filled('blog_post_id')
            ? BlogPost::findOrFail((int) $request->input('blog_post_id'))
            : null;

        // Yetki eke değil içeriğe bakılarak veriliyor: ek içeriğin parçası.
        $post !== null
            ? $this->authorize('update', $post)
            : $this->authorize('create', BlogPost::class);

        $file = $this->files->store($request->file('file'), $post, $request->user()?->id);

        return response()->json($this->files->payload($file));
    }

    /**
     * Kaydetmeden vazgeçilen bekleyen eki diskten de siler.
     */
    public function destroyPending(string $token): JsonResponse
    {
        $this->authorize('create', BlogPost::class);

        return response()->json([
            'removed' => $this->files->discardPending($token, auth()->id()),
        ]);
    }

    /**
     * Bağlanmış eki kaldırır.
     */
    public function destroy(BlogPostFile $file): JsonResponse
    {
        $post = $file->post;

        abort_if($post === null, 404);

        $this->authorize('update', $post);

        $this->files->delete($file);

        return response()->json(['removed' => true]);
    }
}
