<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubscribeNewsletterRequest;
use App\Http\Responses\ApiResponse;
use App\Services\SubscriberListService;
use App\Services\SubscriberService;
use Illuminate\Http\JsonResponse;

/**
 * Bülten aboneliği.
 *
 * Abonelikten çıkma burada yok: çıkış bağlantısı her kampanya mailinin altında
 * yer alıyor, imzalı bir adres ve giriş gerektirmiyor. Uygulamaya taşımak
 * çıkışı zorlaştırmaktan başka bir işe yaramazdı.
 */
final class NewsletterController extends Controller
{
    public function __construct(
        private readonly SubscriberService $subscribers,
        private readonly SubscriberListService $lists,
    ) {}

    /**
     * POST /api/v1/newsletter/subscribe
     */
    public function subscribe(SubscribeNewsletterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Formdan gelen herkes varsayılan işaretli listeye düşüyor — ön yüzle
        // aynı kural. İşaretli liste yoksa kayıt yine açılıyor, sadece hiçbir
        // listeye girmiyor.
        $defaultList = $this->lists->default();

        $this->subscribers->subscribe(
            $validated['email'],
            $validated['first_name'] ?? null,
            $validated['last_name'] ?? null,
            app()->getLocale(),
            'form',
            $defaultList !== null ? [$defaultList->id] : [],
        );

        return ApiResponse::success(null, __('site.newsletter.subscribed'));
    }
}
