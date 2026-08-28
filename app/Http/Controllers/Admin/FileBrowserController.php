<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UploadedFile;
use App\Services\FileBrowserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Editörün dosya seçicisi.
 *
 * Zengin metin editöründen görsel eklerken kullanıcı ya adres yazmak ya da her
 * seferinde yeniden yüklemek zorundaydı; daha önce yüklediği bir dosyayı
 * seçmenin yolu yoktu. Bu uçlar public/uploads dizinini gezilebilir kılıyor.
 *
 * Yetki dosya yöneticisiyle aynı politikaya bağlı: burada görebilen orada da
 * görebilir, buradan silebilen orada da silebilir.
 */
final class FileBrowserController extends Controller
{
    public function __construct(
        private readonly FileBrowserService $browser,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UploadedFile::class);

        $validated = $request->validate([
            'folder' => ['nullable', 'string', 'max:255'],
            'type'   => ['nullable', 'string', Rule::in([...FileBrowserService::CATEGORIES, 'all'])],
            'search' => ['nullable', 'string', 'max:191'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json($this->browser->browse(
                (string) ($validated['folder'] ?? ''),
                (string) ($validated['type'] ?? ''),
                (string) ($validated['search'] ?? ''),
                (int) ($validated['page'] ?? 1),
            ));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', UploadedFile::class);

        $validated = $request->validate([
            'file'   => ['required', 'file', 'max:10240'],
            'folder' => ['nullable', 'string', 'max:255'],
        ], [
            'file.required' => 'Bir dosya seçin.',
            'file.max'      => 'Dosya en fazla 10 MB olabilir.',
        ]);

        try {
            return response()->json(
                $this->browser->upload($request->file('file'), (string) ($validated['folder'] ?? '')),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authorize('deleteAny', UploadedFile::class);

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:1024'],
        ]);

        try {
            $this->browser->delete($validated['path']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['deleted' => true]);
    }
}
