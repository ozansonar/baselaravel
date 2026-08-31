<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContactMessageRequest;
use App\Http\Resources\Api\V1\ContactMessageResource;
use App\Http\Responses\ApiResponse;
use App\Services\ContactMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * İletişim formu gönderimi.
 *
 * Kayıt ön yüzdekiyle aynı servisten geçiyor: aynı tabloya yazılıyor, yönetici
 * bildirimi aynı yerden çıkıyor ve panel iki kaynağı ayırt etmek zorunda
 * kalmıyor.
 */
final class ContactController extends Controller
{
    public function __construct(
        private readonly ContactMessageService $messages,
    ) {}

    /**
     * POST /api/v1/contact
     */
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $message = $this->messages->create($request->validated());

        // Loga mesajın gövdesi değil kimliği yazılıyor. Gövde zaten
        // veritabanında; loga da kopyalanırsa kişisel veri saklama süresi
        // olmayan bir yere düşer ve log dosyaları paylaşıldığında sızar.
        Log::info('API iletişim mesajı alındı', [
            'message_id' => $message->id,
            'ip'         => $request->ip(),
            'locale'     => app()->getLocale(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return ApiResponse::created(
            ContactMessageResource::make($message),
            __('site.contact.sent'),
        );
    }
}
