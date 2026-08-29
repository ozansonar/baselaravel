<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Setting;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
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

        if (!$this->authService->login(
            ['email' => $validated['email'], 'password' => $validated['password']],
            $request->boolean('remember'),
        )) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'E-posta veya şifre hatalı.']);
        }

        $user = $request->user();

        if ($user?->hasAnyRole(['admin', 'editor', 'moderator'])) {
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
            ->withErrors(['email' => 'Bu e-posta adresiyle kayıtlı bir hesap bulunamadı.']);
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

        return back()->withErrors(['email' => 'Şifre sıfırlama başarısız. Lütfen tekrar deneyin.']);
    }
}
