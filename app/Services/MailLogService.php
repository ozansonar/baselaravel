<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MailLogStatus;
use App\Models\MailLog;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class MailLogService
{
    /**
     * Mailable sınıfı olmayan kayıtların süzgeçteki anahtarı.
     *
     * Boş değer "süzgeç kapalı" anlamına geldiği için null'ı adres olarak
     * kullanamıyoruz; ham mailler bu adla seçiliyor.
     */
    public const RAW_MAILABLE = 'raw';

    /**
     * Log a mail attempt (mailable or raw).
     */
    public function logMail(
        string|array $to,
        ?Mailable $mailable = null,
        ?string $subject = null,
        ?string $from = null,
        bool $success = true,
        ?string $error = null,
        ?array $metadata = null,
        ?string $body = null,
        string|array|null $cc = null,
        string|array|null $bcc = null,
        ?string $replyTo = null,
        ?string $ipAddress = null,
        bool $pending = false,
        ?string $mailableClass = null,
    ): MailLog {
        $resolvedSubject = $mailable?->subject ?? $subject ?? class_basename($mailable ?? 'Raw Mail');
        $status = $pending ? MailLogStatus::Pending : ($error ? MailLogStatus::Failed : ($success ? MailLogStatus::Sent : MailLogStatus::Pending));

        $resolvedIp = $ipAddress ?? request()?->ip();

        $mailLog = MailLog::create([
            'to'             => is_array($to) ? implode(', ', $to) : $to,
            'cc'             => $cc ? (is_array($cc) ? implode(', ', $cc) : $cc) : null,
            'bcc'            => $bcc ? (is_array($bcc) ? implode(', ', $bcc) : $bcc) : null,
            'from'           => $from ?? config('mail.from.address'),
            'reply_to'       => $replyTo,
            'subject'        => $resolvedSubject,
            'body'           => $body ?? $this->renderBody($mailable, $pending),
            'mailable_class' => $mailable !== null ? $mailable::class : $mailableClass,
            'status'         => $status,
            'error_message'  => $error,
            'sent_at'        => $status === MailLogStatus::Sent ? now() : null,
            'metadata'       => $metadata,
            'ip_address'     => $resolvedIp,
            'user_id'        => Auth::id(),
        ]);

        Cache::forget('admin.mail_logs.stats');

        return $mailLog;
    }

    /**
     * Gönderim döndükten sonra bekleyen kaydı kapatır.
     *
     * Kaydı normalde mail olayı kapatıyor; olayın doğmadığı durumlarda (mailer
     * taklit edildiğinde ya da sürücü olay yaymadığında) kayıt "bekliyor" diye
     * asılı kalmasın diye burada da kapatılıyor. Olay çoktan yazdıysa
     * dokunulmaz: yalnız eksik alanlar tamamlanır.
     *
     * @param array<string, mixed> $extra
     */
    public function finalize(MailLog $mailLog, ?Mailable $mailable = null, array $extra = []): void
    {
        $mailLog->refresh();

        $update = $extra;

        if ($mailLog->status === MailLogStatus::Pending) {
            $update['status'] = MailLogStatus::Sent;
            $update['sent_at'] = now();
        }

        if ($mailLog->body === null && ($body = $this->renderBody($mailable, false)) !== null) {
            $update['body'] = $body;
        }

        if ($update === []) {
            return;
        }

        $mailLog->update($update);

        Cache::forget('admin.mail_logs.stats');
    }

    /**
     * Gövdeyi mailable'dan üretir.
     *
     * Kaydı yazan taraf gövdeyi hazır verdiyse ona dokunulmaz; vermediyse
     * burada üretilir. Kendi mailer'ını çağıran yollar (kampanya gönderimi
     * gibi) aksi hâlde "hangi maili gönderdik" sorusuna yanıt vermeyen,
     * içeriksiz kayıtlar bırakıyordu.
     *
     * Kuyruğa alınan mailde atlanır: gövde orada, kuyruk serileştirmesi
     * bittikten sonra ayrıca yazılıyor. Üretim başarısız olursa kayıt yine de
     * açılır — log, gönderimi bloke etmemeli.
     */
    private function renderBody(?Mailable $mailable, bool $pending): ?string
    {
        if ($mailable === null || $pending) {
            return null;
        }

        try {
            return $mailable->render();
        } catch (\Throwable $e) {
            Log::warning('Mail gövdesi log için üretilemedi', [
                'mailable' => $mailable::class,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Paginate mail logs with filters.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, MailLog>
     */
    /**
     * Liste ekranının tanıdığı süzgeç anahtarları.
     *
     * Ekran da dışa aktarma da bu listeyi okur; iki yerde ayrı yazılsaydı
     * dosyaya inen ile ekranda görünen zamanla ayrışırdı.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return ['status', 'search', 'mailable', 'recipient', 'user_id', 'date_filter', 'from', 'to'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış sorgu.
     *
     * @param array<string, mixed>|null $filters
     * @return Builder<MailLog>
     */
    public function query(?array $filters = null): Builder
    {
        return $this->filtered($filters ?? [])
            ->with('user:id,first_name,last_name,email')
            ->recent();
    }

    /**
     * @param array<string, mixed>|null $filters
     */
    public function paginate(int $perPage = 25, ?array $filters = null): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Get status counts for tabs.
     *
     * Sayılar açık süzgeçlere göre hesaplanır; durum sekmesinin kendisi hariç
     * tutulur, aksi hâlde her sekme yalnızca kendi sayısını gösterirdi.
     *
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function statusCounts(?array $filters = null): array
    {
        $scoped = $filters ?? [];
        unset($scoped['status']);

        /** @var array<string, int> $counts */
        $counts = $this->filtered($scoped)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return $counts;
    }

    /**
     * Süzgeçteki mail türü listesi.
     *
     * Seçenekler var olan kayıtlardan kurulur: kullanılmamış bir mailable
     * sınıfı listede yer alıp boş sonuç döndürmez.
     *
     * @return array<string, array{label: string, count: int}> sınıf adı => bilgi
     */
    public function mailableOptions(): array
    {
        return MailLog::query()
            ->selectRaw('mailable_class, count(*) as total')
            ->groupBy('mailable_class')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(static fn ($row): array => [
                // Mailable sınıfı olmayan kayıtlar ham mail; süzgeçte kendi
                // anahtarıyla seçilebilsin diye "raw" adını alıyor.
                (string) ($row->mailable_class ?? self::RAW_MAILABLE) => [
                    'label' => MailLog::labelForClass($row->mailable_class),
                    'count' => (int) $row->total,
                ],
            ])
            ->all();
    }

    /**
     * Süzgeçteki alıcı listesi — en çok mail giden adresler önde.
     *
     * @return array<string, int> adres => adet
     */
    public function recipientOptions(int $limit = 100): array
    {
        /** @var array<string, int> $options */
        $options = MailLog::query()
            ->select('to')
            ->selectRaw('count(*) as total')
            ->groupBy('to')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'to')
            ->toArray();

        return $options;
    }

    /**
     * Süzgeçteki kullanıcı listesi — yalnızca mail kaydı olan kullanıcılar.
     *
     * @return Collection<int, User>
     */
    public function userOptions(): Collection
    {
        return User::query()
            ->select('id', 'first_name', 'last_name', 'email')
            ->whereIn('id', MailLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get admin stats for stat cards.
     *
     * @return array{total: int, sent: int, failed: int, pending: int, today: int}
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.mail_logs.stats', 300, function (): array {
            $counts = MailLog::query()
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as sent', [MailLogStatus::Sent->value])
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [MailLogStatus::Failed->value])
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as pending', [MailLogStatus::Pending->value])
                ->selectRaw('sum(case when date(created_at) = ? then 1 else 0 end) as today', [today()->toDateString()])
                ->first();

            return [
                'total'   => (int) $counts->total,
                'sent'    => (int) $counts->sent,
                'failed'  => (int) $counts->failed,
                'pending' => (int) $counts->pending,
                'today'   => (int) $counts->today,
            ];
        });
    }

    /**
     * Arama terimini LIKE kalıbına çevirir.
     *
     * Kullanıcının yazdığı % ve _ joker değil, harf sayılmalı. Kaçış karakteri
     * olarak ünlem seçildi: ters bölüyü MySQL kendiliğinden kaçış sayarken
     * SQLite saymıyor, ünlem ikisinde de düz karakter.
     */
    private function likeTerm(string $value): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
    }

    /**
     * Süzgeçlerin uygulandığı temel sorgu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<MailLog>
     */
    private function filtered(array $filters): Builder
    {
        $query = MailLog::query();

        if (($filters['status'] ?? '') !== '') {
            $status = MailLogStatus::tryFrom((string) $filters['status']);

            if ($status !== null) {
                $query->byStatus($status);
            }
        }

        if (($filters['mailable'] ?? '') !== '') {
            if ((string) $filters['mailable'] === self::RAW_MAILABLE) {
                $query->whereNull('mailable_class');
            } else {
                $query->where('mailable_class', $filters['mailable']);
            }
        }

        if (($filters['recipient'] ?? '') !== '') {
            $query->where('to', $filters['recipient']);
        }

        if (($filters['user_id'] ?? '') !== '') {
            // "0" seçeneği kullanıcısı olmayan kayıtlar için: zamanlanmış
            // görevlerin ve ziyaretçi formlarının maillerinde user_id boştur.
            if ((string) $filters['user_id'] === '0') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', (int) $filters['user_id']);
            }
        }

        $from = (string) ($filters['from'] ?? '');
        $to   = (string) ($filters['to'] ?? '');

        if ($from !== '') {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to !== '') {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        // Hazır aralık yalnızca elle tarih verilmediğinde geçerli: iki tarih
        // süzgeci birbirini daraltırsa kullanıcı hangisinin işlediğini bilemez.
        if ($from === '' && $to === '' && ($filters['date_filter'] ?? '') !== '') {
            match ($filters['date_filter']) {
                'today'   => $query->whereDate('created_at', today()),
                'week'    => $query->where('created_at', '>=', now()->startOfWeek()),
                'month'   => $query->where('created_at', '>=', now()->startOfMonth()),
                'quarter' => $query->where('created_at', '>=', now()->subMonths(3)),
                default   => $query,
            };
        }

        if (($filters['search'] ?? '') !== '') {
            $term = $this->likeTerm((string) $filters['search']);

            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw("`to` LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("cc LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("bcc LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("subject LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("mailable_class LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("error_message LIKE ? ESCAPE '!'", [$term]);
            });
        }

        return $query;
    }
}
