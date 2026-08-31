<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConsentCategory;
use App\Http\Requests\TrackPageViewRequest;
use App\Services\AnalyticsService;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;

class AnalyticsTrackController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly ConsentService $consent,
    ) {}

    public function store(TrackPageViewRequest $request): JsonResponse
    {
        // Analitik rızası yoksa hiçbir şey kaydedilmiyor.
        //
        // Betik zaten rıza olmadan yüklenmiyor, ama bu uç nokta herkese açık:
        // doğrudan istek atan biri kaydı yine de oluşturabilirdi. Rıza kontrolü
        // verinin yazıldığı yerde de olmalı, yalnızca onu tetikleyen yerde
        // değil.
        if (! $this->consent->allows(ConsentCategory::Analytics, $request)) {
            return response()->json(['ok' => true, 'skipped' => 'consent'], 202);
        }

        // Admin/editor/moderator kullanıcıları istatistik bozmasın diye atla.
        $user = $request->user();
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return response()->json(['ok' => true, 'skipped' => 'staff'], 202);
        }

        // Cloudflare arkasında IP'yi doğru al.
        $ip = $request->header('CF-Connecting-IP')
            ?: $request->header('X-Forwarded-For')
            ?: $request->ip();
        if (is_string($ip) && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // Session olmayabilir (API-benzeri rota). try-catch ile güvenli al.
        $sessionId = '';
        try {
            if ($request->hasSession()) {
                $sessionId = (string) $request->session()->getId();
            }
        } catch (\Throwable) {
            $sessionId = '';
        }

        $result = $this->analyticsService->track([
            'url'           => $request->input('url'),
            'path'          => $request->input('path'),
            'referrer'      => $request->input('referrer'),
            'screen_width'  => $request->integer('screen_width') ?: null,
            'screen_height' => $request->integer('screen_height') ?: null,
            'ip'            => (string) $ip,
            'user_agent'    => (string) $request->userAgent(),
            'user_id'       => $user?->id,
            'session_id'    => $sessionId,
            'viewed_at'     => now(),
        ]);

        // DEBUG modda hatayı response'a ekle — prod'da sadece laravel.log'a yazılır.
        if (! $result['success']) {
            return response()->json([
                'ok'    => false,
                'error' => config('app.debug') ? ($result['error'] ?? 'tracking failed') : 'tracking failed',
            ], 500);
        }

        return response()->json(['ok' => true, 'id' => $result['id'] ?? null], 202);
    }
}
