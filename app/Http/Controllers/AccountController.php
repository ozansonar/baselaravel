<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AccountDeviceService;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountDeviceService $devices,
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
}
