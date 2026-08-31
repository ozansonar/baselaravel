<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EmailChangedMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Adres değiştiğinde ESKİ adresi uyarır.
 *
 * Hesabı ele geçiren kişinin ilk yaptığı şey çoğu zaman e-posta adresini
 * değiştirmektir: o andan sonra şifre sıfırlama bağlantısı da bütün bildirimler
 * de saldırgana gider ve gerçek sahibin hesaptan haberi tamamen kesilir.
 * Değişiklik anında yeni adrese giden doğrulama maili bu senaryoda saldırganın
 * kendi kutusuna düşer — yani kimseyi uyarmaz.
 *
 * Bu bildirim o sessizliği bozan tek şey. Ve gönderilebileceği son an burası:
 * bir sonraki saniyede eski adres artık hesapta kayıtlı değil.
 *
 * Denetim izine ayrıca yazılmıyor; User zaten AuditObserver tarafından
 * izleniyor ve adres değişikliği oraya eski/yeni değeriyle birlikte düşüyor.
 * İkinci bir kayıt aynı olayı iki kez anlatırdı.
 */
final class EmailChangeNotifier
{
    public function __construct(
        private readonly MailService $mailService,
    ) {}

    public function notifyPreviousAddress(User $user, ?string $previousEmail): void
    {
        $previousEmail = trim((string) $previousEmail);

        // Yeni kaydın "önceki" adresi yoktur; aynı adres de bir değişiklik
        // değildir.
        if ($previousEmail === '' || $previousEmail === $user->email) {
            return;
        }

        try {
            $this->mailService->queue($previousEmail, new EmailChangedMail(
                userName: $user->full_name,
                previousEmail: $previousEmail,
                newEmail: (string) $user->email,
                changedAt: now()->format('d.m.Y H:i'),
            ));
        } catch (\Throwable $e) {
            // Uyarı gönderilemedi diye profil güncellemesi geri alınamaz —
            // kullanıcı hatayı anlamaz ve düzeltemez. Ama sessizce yutulmaz da:
            // gönderilemeyen bir güvenlik uyarısı incelenmesi gereken bir olay.
            Log::warning('E-posta değişikliği uyarısı kuyruğa eklenemedi', [
                'user_id'  => $user->id,
                'previous' => $previousEmail,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
