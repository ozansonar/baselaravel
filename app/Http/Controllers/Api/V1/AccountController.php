<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

/**
 * Kullanıcının kendi hesabı.
 *
 * Doğrulama kuralları ön yüzle ortak: {@see ProfileUpdateRequest} aynı sınıf.
 * Ayrı bir istek sınıfı yazılsaydı iki taraf zamanla farklı şeyler kabul
 * ederdi — ve aynı sütuna yazıyorlar.
 *
 * Bunun bir sonucu var: güncelleme tamdır, parçalı değil. Ad, soyad ve e-posta
 * her istekte gönderilir; gönderilmeyen alan "değiştirme" değil "boş" demektir.
 * Ön yüz formu da böyle çalışıyor.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $account,
    ) {}

    /**
     * PUT /api/v1/account/profile
     *
     * Avatar aynı istekte, çok parçalı gövdeyle (`multipart/form-data`)
     * gönderiliyor. Ayrı bir uç olsaydı istemci profili kaydetmek için iki
     * istek atmak ve birinin başarısız olması hâlini ayrıca ele almak zorunda
     * kalırdı.
     *
     * Not: PHP çok parçalı gövdeyi yalnız POST'ta ayrıştırıyor. İstemci
     * dosyayla birlikte gönderiyorsa POST + `_method=PUT` kullanmalı.
     */
    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();

        // Kaydetmeden önce sorulmalı: sonrasında eski değer elde kalmıyor.
        $emailChanged = $data['email'] !== $user->email;

        if ($request->hasFile('avatar')) {
            $this->account->handleAvatarUpload(
                $user,
                $request->file('avatar'),
                $data['first_name'] . '-' . $data['last_name'],
            );
        }

        if ($request->boolean('remove_avatar')) {
            $this->account->removeAvatar($user);
        }

        $user = $this->account->updateProfile($user, $data);

        // Adres değiştiyse doğrulama damgası düştü (UserObserver) ve bu uç bir
        // sonraki istekte 403 verecek. İstemcinin bunu yanıttan öğrenmesi
        // gerekiyor: `data.email_verified` false döner, mesaj da sebebi söyler.
        return ApiResponse::success(
            UserResource::make($user->loadMissing('roles')),
            $emailChanged
                ? __('site.account.email_changed')
                : __('site.account.profile_updated'),
        );
    }
}
