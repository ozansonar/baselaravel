<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorChallengeService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TwoFactorChallengeService $challenge,
        private readonly TwoFactorService $twoFactor,
    ) {}

    /**
     * Show login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Handle login attempt.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $this->authService->verifyCredentials([
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        if ($user === null) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => __('site.login.failed')]);
        }

        // Şifre doğru ama kapı henüz açılmadı: ikinci adım burada devreye
        // giriyor ve oturum o adım geçilene kadar açılmıyor.
        if ($user->hasTwoFactorEnabled()) {
            $this->challenge->start($user, $request->boolean('remember'));

            return redirect()->route('login.two-factor');
        }

        $this->authService->loginUser($user, $request->boolean('remember'));

        return $this->afterLogin($user);
    }

    /**
     * Girişin ikinci adımı — kimlik doğrulayıcı kodu ya da kurtarma kodu.
     */
    public function showTwoFactorChallenge(): RedirectResponse|View
    {
        if ($this->challenge->pendingUser() === null) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $this->challenge->pendingUser();

        if ($user === null) {
            // Süre dolmuş ya da bekleme düşmüş: baştan başlamak gerekiyor.
            return redirect()->route('login')->withErrors(['email' => __('site.two_factor.expired')]);
        }

        if (! $this->twoFactor->challenge($user, $request->string('code')->value())) {
            // Yanlış kod bedava olmamalı: sayaç sınıra gelince bekleme düşüyor
            // ve şifreden başlamak gerekiyor.
            if (! $this->challenge->recordFailure()) {
                return redirect()->route('login')->withErrors(['email' => __('site.two_factor.expired')]);
            }

            return back()->withErrors(['code' => __('site.two_factor.invalid_code')]);
        }

        $remember = $this->challenge->shouldRemember();
        $this->challenge->forget();

        $this->authService->loginUser($user, $remember);

        return $this->afterLogin($user);
    }

    /**
     * Girişten sonra nereye: panele erişimi olan panele, ötekiler ana sayfaya.
     */
    private function afterLogin(User $user): RedirectResponse
    {
        if ($user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('home'));
    }

    /**
     * Show registration form.
     */
    public function showRegister(): View
    {
        if (Setting::getValue('registration_enabled', '1') !== '1') {
            throw new NotFoundHttpException();
        }

        return view('auth.register');
    }

    /**
     * Handle registration.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        if (Setting::getValue('registration_enabled', '1') !== '1') {
            throw new NotFoundHttpException();
        }

        $user = $this->authService->register($request->validated());

        auth()->login($user);

        return redirect()->route('verification.notice')
            ->with('success', __('site.register.created'));
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('home');
    }

    /**
     * Show forgot password form.
     */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = $this->authService->sendResetLink($request->string('email')->value());

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', __('site.password.link_sent'));
        }

        return back()
            ->withInput()
            ->withErrors(['email' => __('site.password.no_account')]);
    }

    /**
     * Show reset password form.
     */
    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Handle password reset.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = $this->authService->resetPassword($request->only(
            'email', 'password', 'password_confirmation', 'token'
        ));

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', __('site.password.reset_done'));
        }

        return back()->withErrors(['email' => __('site.password.reset_failed')]);
    }
}
