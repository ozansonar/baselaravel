<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsentRequest;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;

final class ConsentController extends Controller
{
    public function __construct(
        private readonly ConsentService $consent,
    ) {}

    /**
     * Ziyaretçinin çerez tercihini kaydeder.
     *
     * Düz form gönderimi, JSON değil. İki sebebi var: band JavaScript
     * olmadan da çalışmalı — aksi hâlde betiği engelleyen ziyaretçi hiç
     * sorulmadan izlenirdi — ve izin verilen izleme betikleri sunucuda
     * basıldığı için sayfanın zaten yeniden yüklenmesi gerekiyor.
     */
    public function store(StoreConsentRequest $request): RedirectResponse
    {
        $result = $this->consent->store($request->categories(), $request);

        return back()->withCookie($result['cookie']);
    }
}
