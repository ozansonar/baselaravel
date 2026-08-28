<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AttachableContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentFileRequest;
use App\Models\ContentFile;
use App\Services\ContentFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * İçerik eklerinin kendi uçları — blog yazısı ve sayfa aynı yerden geçiyor.
 *
 * Dosyalar içerik formuyla birlikte gitmiyor; her biri kendi isteğiyle geliyor
 * (gerekçe: App\Services\ContentFileService). Bu yüzden yükleme ve kaldırma
 * içerik kaydetmeden de çalışır ve tek dosyanın başarısızlığı formu etkilemez.
 */
final class ContentFileController extends Controller
{
    public function __construct(
        private readonly ContentFileService $files,
    ) {}

    /**
     * Tek dosyayı yükler.
     *
     * Kayıtlı bir içeriğe yükleniyorsa ek doğrudan o satıra bağlanır: kullanıcı
     * kaydet'e basmasa bile dosya yerindedir. Satırı olmayan dilde (yeni içerik
     * ya da hiç çevrilmemiş sekme) belirteçle bekler.
     */
    public function store(StoreContentFileRequest $request): JsonResponse
    {
        $attachable = $this->resolveAttachable(
            $request->input('attachable_type'),
            $request->input('attachable_id'),
        );

        // Yetki eke değil içeriğe bakılarak veriliyor: ek içeriğin parçası.
        $attachable !== null
            ? $this->authorize('update', $attachable)
            : $this->authorizeCreatingSomething($request->input('attachable_type'));

        $file = $this->files->store($request->file('file'), $attachable, $request->user()?->id);

        return response()->json($this->files->payload($file));
    }

    /**
     * Kaydetmeden vazgeçilen bekleyen eki diskten de siler.
     */
    public function destroyPending(string $token): JsonResponse
    {
        return response()->json([
            'removed' => $this->files->discardPending($token, auth()->id()),
        ]);
    }

    /**
     * Bağlanmış eki kaldırır.
     */
    public function destroy(ContentFile $file): JsonResponse
    {
        $attachable = $file->attachable;

        abort_if($attachable === null, 404);

        $this->authorize('update', $attachable);

        $this->files->delete($file);

        return response()->json(['removed' => true]);
    }

    /**
     * İsteğin gösterdiği içeriği bulur.
     *
     * Sınıf adı istekten değil AttachableContent listesinden geliyor; istemci
     * sınıf adı yazabilseydi ekin sahibini uydurabilirdi. Bulunamayan kayıt 404:
     * içerik silinmiş olabilir, sessizce bekleyen dosyaya düşmek kullanıcıya
     * ekin bağlandığını sandırırdı.
     */
    private function resolveAttachable(mixed $type, mixed $id): ?Model
    {
        if (! is_string($type) || $type === '' || $id === null) {
            return null;
        }

        $content = AttachableContent::tryFrom($type);

        abort_if($content === null, 404);

        $modelClass = $content->modelClass();

        return $modelClass::findOrFail((int) $id);
    }

    /**
     * Henüz kaydedilmemiş içerik için yetki, o içeriği oluşturma yetkisidir.
     *
     * Tür bilinmiyorsa (eski bir ekran ya da bozuk istek) hiçbir şey
     * varsayılmıyor; blog yazısı yaratma yetkisi sayfa ekine izin vermemeli.
     */
    private function authorizeCreatingSomething(mixed $type): void
    {
        $content = is_string($type) ? AttachableContent::tryFrom($type) : null;

        abort_if($content === null, 422, 'Ekin hangi içeriğe ait olduğu belirtilmedi.');

        $this->authorize('create', $content->modelClass());
    }
}
