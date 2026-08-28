<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\ContentFile;
use App\Models\Page;
use App\Services\UploadService;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Eki kullanıcının yüklediği adla indirir.
 *
 * Dosya public/uploads altında olduğu için doğrudan adresinden de açılabilir;
 * bu uç iki şey ekliyor: dosya "rapor-2026-a1b2c3d4e5.xlsx" değil kullanıcının
 * verdiği adla iniyor ve yayımlanmamış bir içeriğin eki adresi bilinse bile
 * servis edilmiyor.
 */
final class ContentFileDownloadController extends Controller
{
    public function __invoke(ContentFile $file): BinaryFileResponse
    {
        $attachable = $file->attachable;

        if ($attachable === null || ! $this->isPubliclyVisible($attachable)) {
            abort(404);
        }

        $path = UploadService::basePath($file->path);

        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $file->original_name);
    }

    /**
     * İçerik gerçekten yayında mı.
     *
     * Tanınmayan tür yayında sayılmıyor: yeni bir model ek taşımaya başladığında
     * burası da güncellenene kadar dosyaları servis edilmesin — sessizce açmak,
     * sessizce kapatmaktan tehlikeli.
     */
    private function isPubliclyVisible(Model $attachable): bool
    {
        return match (true) {
            $attachable instanceof BlogPost => BlogPost::published()->whereKey($attachable->getKey())->exists(),
            $attachable instanceof Page     => Page::published()->whereKey($attachable->getKey())->exists(),
            default                         => false,
        };
    }
}
