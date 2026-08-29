<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MailLogStatus;
use App\Models\MailLog;
use App\Services\MailLogService;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Projeden çıkan her mailin kaydı.
 *
 * Kural basit: gönderilen hiçbir mail kayıtsız kalmaz. Gönderimi başlatan taraf
 * kaydı önceden açtıysa (mailde X-Mail-Log-Id başlığı vardır) o kayıt kapatılır;
 * açmadıysa kayıt burada açılır. Böylece kendi mailer'ını doğrudan çağıran bir
 * yol da — kampanya test maili gibi — panelde görünür.
 *
 * Mesajın kendisinden okunur: gövde, gerçekten gönderilen HTML'dir.
 */
final class LogOutgoingMail
{
    public function __construct(
        private readonly MailLogService $mailLogService,
    ) {}

    public function handleSent(MessageSent $event): void
    {
        try {
            $message = $event->sent->getOriginalMessage();

            if (! $message instanceof Email) {
                return;
            }

            $this->record($message, $event->data, success: true, error: null);
        } catch (\Throwable $e) {
            $this->reportFailure($e, 'sent');
        }
    }

    public function handleFailed(MessageFailed $event): void
    {
        try {
            $this->record(
                $event->message,
                $event->data,
                success: false,
                // Sürücünün fırlattığı hata olayla gelmiyor; kaydın boş
                // kalmaması için genel bir açıklama yazılıyor.
                error: 'Mail sunucusu gönderimi kabul etmedi.',
            );
        } catch (\Throwable $e) {
            $this->reportFailure($e, 'failed');
        }
    }

    /**
     * Kaydı kapat, yoksa aç.
     *
     * @param array<string, mixed> $data
     */
    private function record(Email $message, array $data, bool $success, ?string $error): void
    {
        $mailLogId = $this->existingLogId($message);

        if ($mailLogId !== null) {
            $this->closeExisting($mailLogId, $message, $success, $error);

            return;
        }

        $this->mailLogService->logMail(
            to: $this->addresses($message->getTo()),
            subject: $message->getSubject(),
            from: $this->addresses($message->getFrom()) ?: null,
            success: $success,
            error: $error,
            body: $this->body($message),
            cc: $this->addresses($message->getCc()) ?: null,
            bcc: $this->addresses($message->getBcc()) ?: null,
            replyTo: $this->addresses($message->getReplyTo()) ?: null,
            mailableClass: $this->mailableClass($data),
        );
    }

    /**
     * Gönderimden önce açılmış kaydı sonuçlandırır.
     *
     * Yalnız bekleyen kayıt kapatılır: gönderimi başlatan taraf kaydı çoktan
     * sonuçlandırdıysa üzerine yazılmaz.
     */
    private function closeExisting(int $mailLogId, Email $message, bool $success, ?string $error): void
    {
        $update = $success
            ? ['status' => MailLogStatus::Sent, 'sent_at' => now(), 'error_message' => null]
            : ['status' => MailLogStatus::Failed, 'error_message' => mb_substr((string) $error, 0, 500)];

        // Gövde ancak gerçekten gönderilen mesajdan okunabiliyor; kayıt onu
        // önceden yazamadıysa burada tamamlanır.
        $body = $this->body($message);

        if ($body !== null) {
            $update['body'] = $body;
        }

        $log = MailLog::find($mailLogId);

        // Konu da gönderim anında belli oluyor: kayıt gönderimden önce
        // açılıyor, envelope() ile content() ise gönderim sırasında çalışıyor
        // ve şablon kullanan maillerde konuyu belirleyen de o. Kayda sınıf
        // adı ("BlogCommentReceivedMail") düşüyordu.
        //
        // Yalnız o durumda yazılıyor: kaydı açan taraf anlamlı bir konu
        // yazdıysa dokunulmuyor. Yeniden gönderimde kaydın konusu
        // "[Yeniden] ..." oluyor ama giden iletide özgün konu duruyor;
        // koşulsuz yazılsaydı işaret kaybolurdu.
        $subject = $message->getSubject();

        if ($log !== null && $subject !== null && $subject !== '' && $this->subjectIsPlaceholder($log)) {
            $update['subject'] = $subject;
        }

        $updated = MailLog::where('id', $mailLogId)
            ->where('status', MailLogStatus::Pending)
            ->update($update);

        if ($updated) {
            Cache::forget('admin.mail_logs.stats');
        }
    }

    /**
     * Kayıttaki konu gerçek bir konu mu, yoksa yer tutucu mu?
     *
     * Konu bilinmediğinde MailLogService sınıf adını yazıyor; asıl konunun
     * üzerine yazılacağı tek durum bu.
     */
    private function subjectIsPlaceholder(MailLog $log): bool
    {
        $subject = (string) $log->subject;

        if ($subject === '') {
            return true;
        }

        return $log->mailable_class !== null
            && $subject === class_basename($log->mailable_class);
    }

    private function existingLogId(Email $message): ?int
    {
        $header = $message->getHeaders()->get('X-Mail-Log-Id');

        if ($header === null) {
            return null;
        }

        $value = (int) $header->getBodyAsString();

        return $value > 0 ? $value : null;
    }

    private function body(Email $message): ?string
    {
        $html = $message->getHtmlBody();

        if (is_string($html) && $html !== '') {
            return $html;
        }

        $text = $message->getTextBody();

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * @param array<int, Address> $addresses
     */
    private function addresses(array $addresses): string
    {
        return implode(', ', array_map(
            static fn (Address $address): string => $address->getAddress(),
            $addresses,
        ));
    }

    /**
     * Hangi mailable'dan çıktığı, olayın kendi verisinde taşınıyor.
     *
     * Ham gönderimlerde (Mail::raw / Mail::html) böyle bir sınıf yoktur; kayıt
     * o zaman mailable'sız yazılır.
     *
     * @param array<string, mixed> $data
     */
    private function mailableClass(array $data): ?string
    {
        $mailable = $data['__laravel_mailable'] ?? null;

        return is_string($mailable) && $mailable !== '' ? $mailable : null;
    }

    private function reportFailure(\Throwable $e, string $stage): void
    {
        Log::warning('Giden mail kaydedilemedi', [
            'stage' => $stage,
            'error' => $e->getMessage(),
        ]);
    }
}
