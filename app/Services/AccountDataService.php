<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogComment;
use App\Models\Consent;
use App\Models\ContactMessage;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kişinin kendi verisi üzerindeki iki hakkı: taşınabilirlik ve silinme.
 *
 * KVKK ve GDPR'ın istediği şey aynı: kişi verisinin kopyasını alabilmeli ve
 * hesabını kapatabilmeli. Mağazalar (App Store, Play) bunun ikincisini
 * uygulama içinde şart koşuyor — bu yol olmadan mobil uygulama yayınlanamıyor.
 *
 * Toplama sırasında iki kural var:
 *
 *   1. Yalnız kişinin kendi verisi. Yorumu var mı, bülteni açık mı, hangi
 *      çerezlere izin verdi — hepsi onun. Ama bir yorumun altındaki başka
 *      kullanıcının yanıtı onun değil.
 *   2. Anahtar hiçbir zaman dosyaya girmiyor: şifre, 2FA anahtarı, kurtarma
 *      kodları, jetonlar. İndirilen dosya bir kez sızarsa bunlar da sızardı.
 *
 * İletişim mesajları ve bülten kaydı e-postayla eşleşiyor, kimlikle değil:
 * ikisi de giriş yapmadan doldurulabiliyor.
 */
final class AccountDataService
{
    public function __construct(
        private readonly SessionRevoker $sessions,
    ) {}

    /**
     * Kişinin bütün verisi, indirilebilir bir yapı hâlinde.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'meta' => [
                'exported_at' => now()->toIso8601String(),
                'site'        => (string) config('app.url'),
                'note'        => __('site.data.export_note'),
            ],

            'profile' => [
                'id'                => $user->getKey(),
                'first_name'        => $user->first_name,
                'last_name'         => $user->last_name,
                'email'             => $user->email,
                'phone'             => $user->phone,
                'birth_date'        => $user->birth_date?->toDateString(),
                'gender'            => $user->gender?->value,
                'location'          => $user->location,
                'department'        => $user->department?->value,
                'bio'               => $user->bio,
                'avatar'            => $user->avatar !== null ? upload_url($user->avatar) : null,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at'        => $user->created_at?->toIso8601String(),
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            ],

            'roles' => $user->roles()->pluck('name')->all(),

            // Yorumlar da e-postayla eşleşiyor: tabloda user_id yok, yorum
            // girişsiz de bırakılabiliyor ve kişiyi bağlayan tek şey adresi.
            'blog_comments' => BlogComment::withTrashed()
                ->where('email', $user->email)
                ->get(['id', 'blog_post_id', 'body', 'status', 'created_at'])
                ->map(fn (BlogComment $comment): array => [
                    'id'         => $comment->getKey(),
                    'post_id'    => $comment->blog_post_id,
                    'body'       => $comment->body,
                    'status'     => $comment->status?->value ?? (string) $comment->status,
                    'created_at' => $comment->created_at?->toIso8601String(),
                ])->all(),

            'contact_messages' => ContactMessage::withTrashed()
                ->where('email', $user->email)
                ->get(['id', 'subject', 'message', 'created_at'])
                ->map(fn (ContactMessage $message): array => [
                    'id'         => $message->getKey(),
                    'subject'    => $message->subject,
                    'message'    => $message->message,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->all(),

            'newsletter' => Subscriber::withTrashed()
                ->where('email', $user->email)
                ->get(['id', 'status', 'source', 'created_at'])
                ->map(fn (Subscriber $subscriber): array => [
                    'status'     => $subscriber->status?->value ?? (string) $subscriber->status,
                    'source'     => $subscriber->source?->value ?? (string) $subscriber->source,
                    'created_at' => $subscriber->created_at?->toIso8601String(),
                ])->all(),

            'cookie_consents' => Consent::where('user_id', $user->getKey())
                ->get(['categories', 'version', 'created_at'])
                ->map(fn (Consent $consent): array => [
                    'categories' => $consent->categories,
                    'version'    => $consent->version,
                    'created_at' => $consent->created_at?->toIso8601String(),
                ])->all(),

            // Jetonların kendisi değil, yalnız hangi cihazın ne zaman
            // bağlandığı: jeton dosyaya girseydi indirme, hesabın anahtarını
            // taşıyan bir dosya olurdu.
            'devices' => $user->tokens()
                ->get(['name', 'last_used_at', 'created_at'])
                ->map(fn ($token): array => [
                    'name'         => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at'   => $token->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * İndirilecek dosyanın adı. Tarih içeriyor ki arka arkaya alınan iki
     * kopya birbirinin üstüne yazmasın.
     */
    public function exportFileName(User $user): string
    {
        return 'hesap-verilerim-' . $user->getKey() . '-' . now()->format('Y-m-d') . '.json';
    }

    /**
     * Hesabı kapatır.
     *
     * Yumuşak silme (SoftDeletes) bilerek: yanlışlıkla ya da öfkeyle basılan
     * düğmenin geri dönüşü olmalı ve yönetici kaydı bir süre görebilmeli.
     * Ama "kapalı" gerçekten kapalı: oturumlar ve jetonlar aynı anda düşüyor,
     * e-posta serbest kalıyor (silinen satırlar benzersizlik kısıtının
     * dışında), kişi bir daha giriş yapamıyor.
     *
     * 2FA anahtarı da siliniyor: hesap kapalıyken orada durmasının bir işlevi
     * yok, sızma yüzeyi olmaktan başka.
     */
    public function closeAccount(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'two_factor_secret'         => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at'   => null,
                'is_active'                 => false,
            ])->saveQuietly();

            // Gözlemci silme sonrası oturumları zaten düşürüyor; burada
            // çağrılması, gözlemcinin bir gün kaldırılması hâlinde kapının
            // açık kalmaması için.
            $user->delete();

            $this->sessions->revoke($user);
        });
    }
}
