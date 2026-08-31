<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ProfileUpdateRequest;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\CloseAccountRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AccountDataService;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

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
        private readonly AccountDataService $data,
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

    /**
     * PUT /api/v1/account/password
     *
     * Şifre değiştirme. Ön yüzde bu iş profil formunun içinde; API'de ayrı,
     * çünkü profil güncelleme tam bir güncelleme ve yalnız şifresini
     * değiştirmek isteyen istemcinin bütün profili taşıması gerekirdi.
     */
    public function updatePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $current = $user->currentAccessToken();

        $user->forceFill(['password' => $request->string('password')->value()])->save();

        // İstenmişse öteki cihazlar düşüyor — bu isteği yapan jeton hariç.
        // Onu da düşürmek, şifresini değiştiren kişiyi anında dışarı atardı.
        if ($request->boolean('logout_other_devices')) {
            $query = $user->tokens();

            if ($current instanceof PersonalAccessToken) {
                $query->whereKeyNot($current->getKey());
            }

            $query->delete();
        }

        return ApiResponse::success(null, __('site.account.password_updated'));
    }

    /**
     * GET /api/v1/account/export
     *
     * Kişinin bütün verisi. Web'deki "verilerimi indir" ile aynı gövde; orada
     * dosya olarak iniyor, burada zarfın içinde JSON olarak dönüyor —
     * uygulama onu kendi paylaş menüsüne verebilsin.
     */
    public function export(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->data->export($user))
            // Kişisel veri: ara önbelleklerde durmamalı.
            ->withHeaders(['Cache-Control' => 'no-store, max-age=0']);
    }

    /**
     * DELETE /api/v1/account
     *
     * Hesabı kapatır. Mağazaların uygulama içi hesap silme şartının karşılığı
     * bu uç; şifre onayı isteniyor çünkü ele geçirilmiş bir jeton hesabı
     * kapatabilseydi bu, saldırganın elindeki en yıkıcı düğme olurdu.
     */
    public function destroy(CloseAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Panele erişebilen hesap buradan kapanmıyor; gerekçesi web tarafıyla
        // aynı: son yöneticinin kendini kapatması siteyi yönetilemez bırakır.
        if ($user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return ApiResponse::error(__('site.data.close_blocked_for_staff'), status: 403);
        }

        $this->data->closeAccount($user);

        return ApiResponse::success(null, __('site.data.closed'));
    }
}
