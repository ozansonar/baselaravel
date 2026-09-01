<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SocialProvider;
use App\Exceptions\AccountDeactivatedException;
use App\Exceptions\EmailNotVerifiedBySocialProviderException;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialIdentity;
use App\Services\Social\SocialIdentityVerifier;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sosyal kimlikle giriş ve hesap eşleştirme.
 *
 * Üç durum var ve ayrımı yanlış yapmak hesap devralmaya açık kapı bırakıyor:
 *
 *  1. **Bu sosyal hesap zaten bağlı** → sahibiyle giriş yapılıyor. Anahtar
 *     `sub`, e-posta değil: sağlayıcıda adres değişse de hesap aynı kalıyor.
 *  2. **Bağlı değil ama aynı e-postayla bir hesap var** → yalnız sağlayıcı o
 *     adresi *doğruladıysa* bağlanıyor. Doğrulanmamış adresle bağlamak, o
 *     adresi kendi sağlayıcı hesabına yazan birine sitedeki hesabı teslim
 *     etmek olurdu — sosyal girişin en bilinen hesap devralma yolu budur.
 *  3. **Hiçbiri** → yeni hesap açılıyor.
 *
 * İkinci durumda doğrulanmamış adres geldiğinde hesap açmak da yanlış olurdu
 * (aynı adresle ikinci hesap), o yüzden istek reddediliyor ve kullanıcıya
 * şifresiyle girip hesabını bağlaması söyleniyor.
 */
final class SocialAuthService
{
    public function __construct(
        private readonly SocialIdentityVerifier $verifier,
        private readonly ApiAuthService $apiAuth,
    ) {}

    /**
     * Jetonu doğrular, hesabı bulur/açar ve API jetonu üretir.
     *
     * @param  list<string> $abilities
     * @return array{user: User, token: string, expires_at: string|null, abilities: list<string>}|null
     *         Jeton geçersizse null.
     *
     * @throws AccountDeactivatedException Hesap pasifse.
     * @throws EmailNotVerifiedBySocialProviderException Adres doğrulanmamışsa ve
     *         aynı adresle bir hesap zaten varsa.
     */
    public function login(
        SocialProvider $provider,
        string $idToken,
        ?string $deviceName = null,
        array $abilities = [],
        ?string $firstName = null,
        ?string $lastName = null,
    ): ?array {
        $identity = $this->verifier->verify($provider, $idToken);

        if ($identity === null) {
            return null;
        }

        $user = $this->resolveUser($identity, $firstName, $lastName);

        if (! $user->is_active) {
            throw new AccountDeactivatedException();
        }

        // Giriş olayı denetim izine düşüyor; şifreyle girişle aynı yol.
        event(new Login('api', $user, false));

        return $this->apiAuth->issueTokenFor($user, $deviceName, $abilities);
    }

    /**
     * Doğrulanmış kimliğe karşılık gelen hesap.
     *
     * @throws EmailNotVerifiedBySocialProviderException
     */
    private function resolveUser(SocialIdentity $identity, ?string $firstName, ?string $lastName): User
    {
        return DB::transaction(function () use ($identity, $firstName, $lastName): User {
            $existing = SocialAccount::query()
                ->where('provider', $identity->provider)
                ->where('provider_user_id', $identity->subject)
                ->first();

            if ($existing !== null && $existing->user !== null) {
                $existing->forceFill([
                    // Adres sağlayıcıda değişmiş olabilir; kayıt tazeleniyor.
                    'email'         => $identity->email,
                    'last_login_at' => now(),
                ])->save();

                return $existing->user;
            }

            $byEmail = $identity->email !== null
                ? User::where('email', $identity->email)->first()
                : null;

            if ($byEmail !== null) {
                if (! $identity->emailVerified) {
                    throw new EmailNotVerifiedBySocialProviderException($identity->provider);
                }

                $this->link($byEmail, $identity);

                return $byEmail;
            }

            return $this->createUser($identity, $firstName, $lastName);
        });
    }

    /**
     * Yeni hesap.
     *
     * Şifre rastgele: sosyal girişle açılan hesabın şifresi yok, ama sütun
     * boş kalamıyor. Kullanıcı isterse "şifremi unuttum" ile kendi şifresini
     * kurabiliyor ve o zaman iki yolla da girebiliyor.
     */
    private function createUser(SocialIdentity $identity, ?string $firstName, ?string $lastName): User
    {
        [$fromToken, $fromTokenLast] = $identity->splitName();

        $user = User::create([
            // Apple adı yalnız ilk yetkilendirmede gönderiyor, o yüzden
            // istemcinin ilettiği ad önce geliyor.
            'first_name'        => $firstName ?: ($fromToken ?: 'Kullanıcı'),
            'last_name'         => $lastName ?: $fromTokenLast,
            'email'             => $identity->email ?? $this->placeholderEmail($identity),
            'password'          => Str::random(48),
            'is_active'         => true,
        ]);

        // Hesap doğrulanmış sayılıyor mu?
        //
        // İki durumda evet, ve ikisi ayrı sebeple:
        //
        //  - Sağlayıcı adresi doğruladıysa: aynı adresi ikinci kez
        //    doğrulatmanın anlamı yok.
        //  - Sağlayıcı hiç adres vermediyse (Apple'ın "adresimi gizle"si):
        //    adres bizim ürettiğimiz yer tutucu, kimse onu sahiplenemez.
        //    Damga konmazsa bu hesap `verified` kapısını hiç geçemiyor —
        //    yani kişi kendi hesabını yönetemiyor, silemiyor bile. Kimliği
        //    zaten sağlayıcının imzalı jetonu kanıtladı; e-posta tıklaması
        //    ondan zayıf bir kanıt.
        //
        // Doğrulanmamış *gerçek* bir adres bilerek dışarıda: onu doğrulanmış
        // saymak, o adresi kendi sağlayıcı hesabına yazan birine adresi
        // sahiplendirirdi.
        //
        // forceFill: email_verified_at fillable değil, toplu atamayla
        // dışarıdan doğrulanmış sayılmak istenmiyor.
        if ($identity->emailVerified || $identity->email === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $role = Role::where('slug', 'user')->first();

        if ($role !== null) {
            $user->roles()->attach($role->id);
        }

        $this->link($user, $identity);

        return $user;
    }

    private function link(User $user, SocialIdentity $identity): SocialAccount
    {
        return SocialAccount::create([
            'user_id'          => $user->getKey(),
            'provider'         => $identity->provider,
            'provider_user_id' => $identity->subject,
            'email'            => $identity->email,
            'last_login_at'    => now(),
        ]);
    }

    /**
     * Sağlayıcı adres vermediğinde kullanılan yer tutucu alan adı.
     *
     * Apple, kullanıcı "adresimi gizle" derse gerçek adresi vermiyor ve bazı
     * durumlarda hiç adres göndermiyor. E-posta sütunu boş kalamadığı için
     * yer tutucu üretiliyor. Bu adrese mail gitmiyor — dolayısıyla o hesap
     * "şifremi unuttum" ile kendini kurtaramıyor ve son sosyal bağlantısını
     * koparması engelleniyor.
     */
    public const PLACEHOLDER_DOMAIN = 'sosyal.yerel';

    /**
     * Bu adres gerçek mi, yoksa bizim ürettiğimiz yer tutucu mu?
     */
    public static function isPlaceholderEmail(string $email): bool
    {
        return str_ends_with($email, '@' . self::PLACEHOLDER_DOMAIN);
    }

    private function placeholderEmail(SocialIdentity $identity): string
    {
        return $identity->provider->value . '-' . $identity->subject . '@' . self::PLACEHOLDER_DOMAIN;
    }
}
