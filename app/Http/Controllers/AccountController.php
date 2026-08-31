<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\PasswordConfirmationRequest;
use App\Http\Requests\Account\ProfileUpdateRequest;
use App\Models\User;
use App\Enums\NotificationPreference;
use App\Services\AccountDataService;
use App\Services\AccountDeviceService;
use App\Services\AccountService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountDeviceService $devices,
        private readonly AccountDataService $data,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * Account dashboard - profile overview.
     */
    public function dashboard(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('account.dashboard', compact('user'));
    }

    /**
     * Show profile edit form.
     */
    public function profile(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('account.profile', compact('user'));
    }

    /**
     * Update profile.
     */
    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $data = $request->validated();

        // Adresin değişip değişmediği kaydetmeden önce sorulmalı: kaydedildikten
        // sonra eski değer elde kalmıyor.
        $emailChanged = $data['email'] !== $user->email;

        if ($request->hasFile('avatar')) {
            $this->accountService->handleAvatarUpload(
                $user,
                $request->file('avatar'),
                $data['first_name'] . '-' . $data['last_name'],
            );
        }

        if ($request->boolean('remove_avatar')) {
            $this->accountService->removeAvatar($user);
        }

        $this->accountService->updateProfile($user, $data);

        // Adres değiştiyse doğrulama damgası düştü (UserObserver) ve /hesabim
        // artık kapalı. Oraya yönlendirmek kullanıcıyı sebebini görmeden
        // doğrulama ekranına savurur; doğrudan oraya, açıklamasıyla gidiyor.
        if ($emailChanged) {
            return redirect()->route('verification.notice')
                ->with('success', __('site.account.email_changed'));
        }

        return redirect()->route('account.profile')
            ->with('success', __('site.account.profile_updated'));
    }

    /**
     * Cihazlarım — açık tarayıcı oturumları ve API jetonları.
     */
    public function devices(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('account.devices', [
            'user'              => $user,
            'sessions'          => $this->devices->browserSessions($user, $request->session()->getId()),
            'sessionsSupported' => $this->devices->browserSessionsSupported(),
            'tokens'            => $this->devices->apiTokens($user),
        ]);
    }

    /**
     * Tek bir tarayıcı oturumunu kapatır.
     */
    public function destroySession(Request $request, string $session): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $revoked = $this->devices->revokeBrowserSession($user, $session, $request->session()->getId());

        return redirect()->route('account.devices')->with(
            $revoked ? 'success' : 'error',
            $revoked ? __('site.devices.session_revoked') : __('site.devices.not_found'),
        );
    }

    /**
     * Tek bir uygulama jetonunu kapatır.
     */
    public function destroyToken(int $token): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $revoked = $this->devices->revokeApiToken($user, $token);

        return redirect()->route('account.devices')->with(
            $revoked ? 'success' : 'error',
            $revoked ? __('site.devices.token_revoked') : __('site.devices.not_found'),
        );
    }

    /**
     * Bu tarayıcı hariç her yerden çıkış.
     */
    public function destroyOtherDevices(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $count = $this->devices->revokeOthers($user, $request->session()->getId());

        return redirect()->route('account.devices')
            ->with('success', __('site.devices.others_revoked', ['count' => $count]));
    }

    /**
     * Verilerim: indirme ve hesabı kapatma.
     */
    public function data(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('account.data', ['user' => $user]);
    }

    /**
     * Kişinin bütün verisi tek dosyada.
     *
     * Akış olarak değil doğrudan gövdeyle dönüyor: veri bir kişinin kendi
     * kayıtları, yani birkaç yüz kilobayt. Dosyaya yazıp indirtmek, sunucuda
     * kişisel veri taşıyan geçici dosyalar bırakırdı.
     */
    public function downloadData(): Response
    {
        /** @var User $user */
        $user = auth()->user();

        $json = json_encode(
            $this->data->export($user),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return response((string) $json, 200, [
            'Content-Type'        => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $this->data->exportFileName($user) . '"',
            // İndirilen dosya kişisel veri taşıyor; ara önbelleklerde
            // durmaması gerekiyor.
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * Hesabı kapatır. Şifre onayı zorunlu.
     */
    public function closeAccount(PasswordConfirmationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Panele erişebilen hesap buradan kapanmıyor: son yöneticinin kendi
        // hesabını kapatması siteyi yönetilemez hâle getirirdi. Onların
        // hesabını başka bir yönetici paneldeki kullanıcılar ekranından siler.
        if ($user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return back()->withErrors(['password' => __('site.data.close_blocked_for_staff')]);
        }

        $this->data->closeAccount($user);

        // Oturum zaten düşürüldü; çerezi de temizlemek gerekiyor, yoksa
        // tarayıcı kapalı bir hesabın kimliğini taşımaya devam eder.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('site.data.closed'));
    }

    /**
     * Bildirim tercihleri.
     */
    public function notifications(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('account.notifications', [
            'user'        => $user,
            'preferences' => $this->preferences->all($user),
            'newsletter'  => $this->preferences->newsletterEnabled($user),
        ]);
    }

    /**
     * Tercihleri kaydeder.
     *
     * Form bütün anahtarları her seferinde gönderiyor (işaretsiz kutu hiç
     * gönderilmediği için her anahtarın önünde gizli bir 0 var), yani gelen
     * gövde kararın tamamı: eksik anahtar "değiştirme" değil "kapat" demek
     * değil — hiç gelmeyen tür zaten ekranda yoktur.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        foreach (NotificationPreference::cases() as $type) {
            if ($request->has('preferences.' . $type->value)) {
                $this->preferences->set($user, $type, $request->boolean('preferences.' . $type->value));
            }
        }

        if ($request->has('newsletter')) {
            $this->preferences->setNewsletter($user, $request->boolean('newsletter'));
        }

        return redirect()->route('account.notifications')
            ->with('success', __('site.notifications.saved'));
    }
}
