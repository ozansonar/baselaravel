<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

final class AuthService
{
    public function __construct(
        private readonly MailService $mailService,
    ) {}
    /**
     * Attempt to authenticate a user.
     *
     * @param array{email: string, password: string} $credentials
     */
    public function login(array $credentials, bool $remember = false): bool
    {
        if (! Auth::attempt($credentials, $remember)) {
            return false;
        }

        $user = Auth::user();

        if ($user instanceof User && ! $user->is_active) {
            Auth::logout();
            return false;
        }

        session()->regenerate();

        return true;
    }

    /**
     * Register a new user.
     *
     * @param array{first_name: string, last_name: string, email: string, password: string, phone?: string} $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => $data['password'],
                'phone'      => $data['phone'] ?? null,
                'is_active'  => true,
            ]);

            // Assign default 'user' role
            $userRole = \App\Models\Role::where('slug', 'user')->first();
            if ($userRole) {
                $user->roles()->attach($userRole->id);
            }

            return $user;
        });

        try {
            $this->mailService->queue($user->email, new WelcomeMail($user));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Welcome mail kuyruğa eklenemedi', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // Sends through the project's own mail pipeline; failures are logged
        // there and must not break the registration itself.
        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Log the user out.
     */
    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    /**
     * Send a password reset link.
     */
    public function sendResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        // Bağlantı isteği hiçbir satırı değiştirmiyor, yani gözlemci onu
        // göremiyor. Denetim açısından ise bir hesabı ele geçirme girişiminin
        // ilk adımı: kayıt tutulmazsa şifrenin neden değiştiği hiç bilinmez.
        if ($status === Password::RESET_LINK_SENT) {
            AuditLogger::custom('Şifre sıfırlama bağlantısı istendi', ['e-posta' => $email]);
        }

        return $status;
    }

    /**
     * Reset the user's password.
     *
     * @param array{email: string, password: string, password_confirmation: string, token: string} $data
     */
    public function resetPassword(array $data): string
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        // Kaydın kendisi gözlemciden de düşüyor ama "User #3 güncellendi"
        // diye. Şifre sıfırlaması denetim izinde adıyla görünmeli.
        if ($status === Password::PASSWORD_RESET) {
            AuditLogger::custom('Şifre sıfırlandı', ['e-posta' => (string) ($data['email'] ?? '')]);
        }

        return $status;
    }
}
