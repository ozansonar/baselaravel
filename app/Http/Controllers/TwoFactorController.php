<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Account\ConfirmTwoFactorRequest;
use App\Http\Requests\Account\PasswordConfirmationRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Hesap alanındaki güvenlik ekranı: iki adımlı doğrulamanın kurulumu.
 *
 * Kurulum iki isteğe bölünmüş — önce anahtar üretilip QR gösteriliyor, sonra
 * kullanıcının girdiği ilk kod doğrulanınca açılıyor. Tek adımda açılsaydı
 * QR'ı okutmayı beceremeyen kişi kendi hesabından kilitlenirdi.
 *
 * Kapatma ve kurtarma kodlarını yenileme şifre istiyor: ele geçirilmiş bir
 * oturum, sahibinin ikinci adımını sessizce kaldırabilseydi 2FA'nın koruduğu
 * şey kalmazdı.
 */
final class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function show(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $secret = $user->two_factor_secret;

        return view('account.security', [
            'user'    => $user,
            'enabled' => $user->hasTwoFactorEnabled(),
            // Kurulum yarıda kaldıysa QR yeniden gösteriliyor; kullanıcı
            // ekranı kapatıp döndüğünde baştan başlamak zorunda kalmasın.
            'pending'   => $secret !== null && ! $user->hasTwoFactorEnabled(),
            'secret'    => $secret,
            'qrCodeSvg' => $secret !== null && ! $user->hasTwoFactorEnabled()
                ? $this->twoFactor->qrCodeSvg($user, $secret)
                : null,
            // Yalnız kurulumun hemen ardından ve yenilemede gösteriliyor;
            // veritabanından her ziyarette basılsaydı omuz üstünden bakan
            // biri için hazır bir liste olurdu.
            'recoveryCodes' => session('two_factor.recovery_codes'),
            'required'      => $this->twoFactor->requiredForAdmins()
                && $user->hasAnyRole(['admin', 'editor', 'moderator']),
        ]);
    }

    public function enable(): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('account.security');
        }

        $this->twoFactor->beginSetup($user);

        return redirect()->route('account.security');
    }

    public function confirm(ConfirmTwoFactorRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $codes = $this->twoFactor->confirm($user, $request->string('code')->value());

        if ($codes === null) {
            return back()->withErrors(['code' => __('site.two_factor.invalid_code')]);
        }

        return redirect()->route('account.security')
            ->with('success', __('site.two_factor.enabled'))
            ->with('two_factor.recovery_codes', $codes);
    }

    public function disable(PasswordConfirmationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Zorunluluk açıkken yönetici kendi ikinci adımını kaldıramıyor:
        // kaldırabilseydi ayar bir kural değil, bir öneri olurdu.
        if ($this->twoFactor->requiredForAdmins() && $user->hasAnyRole(['admin', 'editor', 'moderator'])) {
            return back()->withErrors(['password' => __('site.two_factor.required_by_admin')]);
        }

        $this->twoFactor->disable($user);

        return redirect()->route('account.security')->with('success', __('site.two_factor.disabled'));
    }

    public function recoveryCodes(PasswordConfirmationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('account.security');
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        return redirect()->route('account.security')
            ->with('success', __('site.two_factor.codes_regenerated'))
            ->with('two_factor.recovery_codes', $codes);
    }
}
