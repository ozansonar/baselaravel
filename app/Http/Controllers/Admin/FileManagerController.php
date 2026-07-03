<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFileRequest;
use App\Http\Requests\Admin\UploadFileRequest;
use App\Models\UploadedFile;
use App\Services\FileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Dosya Yöneticisi.
 *
 *   GET    /admin/files               → liste + filtre + arama (index)
 *   POST   /admin/files/upload        → Dropzone tek dosya (JSON)
 *   GET    /admin/files/{file}        → detay (URL kopyalama)
 *   PATCH  /admin/files/{file}        → metadata düzenle (title, alt_text)
 *   DELETE /admin/files/{file}        → soft delete + dosya sil
 */
final class FileManagerController extends Controller
{
    public function __construct(
        private readonly FileManagerService $files,
    ) {}

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [12, 24, 48, 96], true)
            ? (int) $request->query('per_page')
            : 24;

        $filters = $request->only(['q', 'category', 'date_from', 'date_to']);

        return view('admin.files.index', [
            'files'    => $this->files->paginate($perPage, $filters),
            'stats'    => $this->files->stats(),
            'filters'  => $filters,
            'perPage'  => $perPage,
            'categories' => [
                UploadedFile::CATEGORY_IMAGE    => 'Görsel',
                UploadedFile::CATEGORY_DOCUMENT => 'Belge',
                UploadedFile::CATEGORY_VIDEO    => 'Video',
                UploadedFile::CATEGORY_AUDIO    => 'Ses',
                UploadedFile::CATEGORY_ARCHIVE  => 'Arşiv',
                UploadedFile::CATEGORY_OTHER    => 'Diğer',
            ],
        ]);
    }

    /**
     * Dropzone her dosya için ayrı POST atar — tek dosya işler, JSON döner.
     */
    public function upload(UploadFileRequest $request): JsonResponse
    {
        $result = $this->files->upload(
            file:    $request->file('file'),
            title:   $request->filled('title') ? (string) $request->input('title') : null,
            altText: $request->filled('alt_text') ? (string) $request->input('alt_text') : null,
            userId:  $request->user()?->id,
        );

        $file = $result['file'];

        return response()->json([
            'success'         => true,
            'duplicate'       => $result['duplicate'],
            'file_id'         => $file->id,
            'original_name'   => $file->original_name,
            'public_url'      => $file->publicUrl(),
            'thumbnail_url'   => $file->thumbnailUrl(),
            'category'        => $file->category,
            'category_label'  => $file->categoryLabel(),
            'icon_class'      => $file->iconClass(),
            'human_size'      => $file->humanSize(),
            'message'         => $result['duplicate']
                ? 'Bu dosya daha önce yüklenmiş — mevcut kayıt kullanılıyor.'
                : 'Dosya yüklendi.',
        ]);
    }

    public function show(UploadedFile $file): View
    {
        $file->load('uploader');

        return view('admin.files.show', [
            'file' => $file,
        ]);
    }

    public function update(UpdateFileRequest $request, UploadedFile $file): RedirectResponse
    {
        $file->update($request->validated());

        return back()->with('success', 'Dosya bilgileri güncellendi.');
    }

    public function destroy(UploadedFile $file): RedirectResponse
    {
        $this->files->delete($file);

        return redirect()
            ->route('admin.files.index')
            ->with('success', 'Dosya silindi.');
    }
}
