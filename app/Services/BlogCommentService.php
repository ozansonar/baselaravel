<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommentStatus;
use App\Mail\BlogCommentAdminNotification;
use App\Mail\BlogCommentApprovedMail;
use App\Mail\BlogCommentReceivedMail;
use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

final class BlogCommentService
{
    public function __construct(
        private readonly MailService $mailService,
    ) {}

    // ── Frontend ──

    /**
     * @return Collection<int, BlogComment>
     */
    public function getApprovedComments(BlogPost $post): Collection
    {
        return $post->approvedComments()
            ->with('approvedReplies')
            ->get();
    }

    public function store(array $data, ?string $ipAddress = null): BlogComment
    {
        $data['status'] = CommentStatus::Pending->value;

        if ($ipAddress !== null) {
            $data['ip_address'] = $ipAddress;
        }

        $comment = DB::transaction(fn (): BlogComment => BlogComment::create($data));

        // Yeni yorum bekleyen sayısını artırıyor; kart hemen doğru göstersin.
        $this->clearCache();

        $this->notifyOnCreated($comment);

        return $comment;
    }

    // ── Admin ──

    /**
     * @param array<string, mixed> $filters
     */
    /**
     * @param array<string, mixed> $filters
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
        return ['status', 'search', 'post_id', 'date_from', 'date_to'];
    }

    /**
     * Süzgeçler uygulanmış, sayfalanmamış sorgu.
     *
     * @param array<string, mixed> $filters
     * @return Builder<BlogComment>
     */
    public function query(array $filters = []): Builder
    {
        $query = BlogComment::with(['post'])->withTrashed()->recent();

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'trashed') {
                $query->onlyTrashed();
            } else {
                $status = CommentStatus::tryFrom($filters['status']);
                if ($status !== null) {
                    $query->whereNull('deleted_at')->where('status', $status);
                }
            }
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['post_id'])) {
            $query->where('blog_post_id', $filters['post_id']);
        }

        // Tarih aralığı gün bazında: "1 Ocak"tan başlayan bir süzgeç o günün
        // sabahından, biten süzgeç o günün gece yarısına kadar sürüyor.
        // whereDate yerine sınırlar açıkça yazılıyor: sütun üzerinde işlev
        // çağrısı indeksi kullanılamaz hâle getiriyordu.
        if (!empty($filters['date_from'])) {
            $baslangic = $this->parseDate($filters['date_from']);

            if ($baslangic !== null) {
                $query->where('created_at', '>=', $baslangic->startOfDay());
            }
        }

        if (!empty($filters['date_to'])) {
            $bitis = $this->parseDate($filters['date_to']);

            if ($bitis !== null) {
                $query->where('created_at', '<=', $bitis->endOfDay());
            }
        }

        return $query;
    }

    /**
     * Süzgeçten gelen tarih metnini güvenle çevirir.
     *
     * Değer adres çubuğundan geliyor; elle yazılmış bir metin ayrıştırma
     * hatası fırlatıp bütün listeyi düşürebilirdi.
     */
    private function parseDate(string $value): ?\Illuminate\Support\Carbon
    {
        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage);
    }

    public function findById(int $id): BlogComment
    {
        return BlogComment::with(['post', 'parent', 'replies'])->findOrFail($id);
    }

    /**
     * Süzgeç listesinde gösterilecek yazılar: yalnız yorumu olanlar.
     *
     * @return Collection<int, BlogPost>
     */
    public function commentedPosts(): Collection
    {
        return BlogPost::whereHas('comments')
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    public function pendingCount(): int
    {
        return BlogComment::pending()->count();
    }

    /**
     * @return array<string, int>
     */
    public function getAdminStats(): array
    {
        return Cache::remember('admin.blog_comments.stats', 300, function (): array {
            $counts = BlogComment::withTrashed()
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as total')
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as approved', [CommentStatus::Approved->value])
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as pending', [CommentStatus::Pending->value])
                ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND status = ? THEN 1 ELSE 0 END) as rejected', [CommentStatus::Rejected->value])
                ->first();

            return [
                'total'    => (int) $counts->total,
                'approved' => (int) $counts->approved,
                'pending'  => (int) $counts->pending,
                'rejected' => (int) $counts->rejected,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = BlogComment::withTrashed()
            ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as approved', [CommentStatus::Approved->value])
            ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as pending', [CommentStatus::Pending->value])
            ->selectRaw('sum(case when deleted_at is null and status = ? then 1 else 0 end) as rejected', [CommentStatus::Rejected->value])
            ->selectRaw('sum(case when deleted_at is not null then 1 else 0 end) as trashed')
            ->first();

        return [
            'approved' => (int) $counts->approved,
            'pending'  => (int) $counts->pending,
            'rejected' => (int) $counts->rejected,
            'trashed'  => (int) $counts->trashed,
        ];
    }

    public function approve(BlogComment $comment): void
    {
        // Zaten onaylıysa ikinci kez mail göndermenin anlamı yok: aynı kişi
        // "yorumunuz yayınlandı" mailini iki kez alırdı.
        $zatenOnayli = $comment->status === CommentStatus::Approved;

        $comment->update(['status' => CommentStatus::Approved]);
        $this->clearCache();

        if (! $zatenOnayli) {
            $this->notifyApproved($comment);
        }
    }

    public function reject(BlogComment $comment): void
    {
        $comment->update(['status' => CommentStatus::Rejected]);
        $this->clearCache();
    }

    public function delete(BlogComment $comment): void
    {
        DB::transaction(fn () => $comment->delete());
        $this->clearCache();
    }

    /**
     * Listede seçilen yorumları tek seferde onaylar.
     *
     * Yorumlar çeviri grubu taşımıyor; her satır kendi başına bir kayıt.
     * Zaten onaylı olanlar sayıya girmiyor: "5 yorum onaylandı" derken
     * hiçbiri değişmemiş olabilirdi.
     *
     * @param  list<int> $ids
     * @return int       durumu değişen yorum sayısı
     */
    public function approveMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        // Kimlerin durumu gerçekten değişecek: mail yalnız onlara gidecek.
        $degisecekler = BlogComment::with('post.category')
            ->whereIn('id', $ids)
            ->where('status', '!=', CommentStatus::Approved->value)
            ->get();

        $degisen = DB::transaction(fn (): int => BlogComment::whereIn('id', $degisecekler->pluck('id'))
            ->update(['status' => CommentStatus::Approved->value]));

        if ($degisen > 0) {
            $this->clearCache();

            foreach ($degisecekler as $comment) {
                $comment->status = CommentStatus::Approved;
                $this->notifyApproved($comment);
            }
        }

        return $degisen;
    }

    /**
     * Seçilen yorumları tek seferde siler.
     *
     * @param  list<int> $ids
     * @return int       silinen yorum sayısı
     */
    public function deleteMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $silinen = DB::transaction(fn (): int => BlogComment::whereIn('id', $ids)->delete());

        if ($silinen > 0) {
            $this->clearCache();
        }

        return $silinen;
    }

    /**
     * Seçilen yorumları çöpten tek seferde çıkarır.
     *
     * @param  list<int> $ids
     * @return int       geri yüklenen yorum sayısı
     */
    public function restoreMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $geriYuklenen = DB::transaction(fn (): int => BlogComment::onlyTrashed()->whereIn('id', $ids)->restore());

        if ($geriYuklenen > 0) {
            $this->clearCache();
        }

        return $geriYuklenen;
    }

    public function restore(BlogComment $comment): void
    {
        DB::transaction(fn () => $comment->restore());
        $this->clearCache();
    }

    /**
     * İstatistik kartlarının okuduğu sayılar beş dakika önbellekte duruyor.
     *
     * Durum değiştiren her yol bunu düşürmek zorunda: düşürülmediği için
     * kartlar bekleyen yorum sayısını olduğundan farklı gösteriyordu —
     * yorum onaylanıyor, kart hâlâ eski sayıyı yazıyordu.
     */
    public function clearCache(): void
    {
        Cache::forget('admin.blog_comments.stats');
    }

    // ── Bildirimler ──

    /**
     * Yeni yorumda iki bildirim: yöneticiye "bak, onay bekliyor", yazan kişiye
     * "aldık, değerlendiriyoruz".
     *
     * Mail gönderimi yorumun kaydedilmesini bozmamalı — SMTP kapalıysa ya da
     * adres tanımsızsa yorum yine kaydedilmiş sayılır; hata yalnız günlüğe
     * düşüyor. Gönderim MailService üzerinden gittiği için mail loglarına da
     * kendiliğinden yazılıyor.
     */
    private function notifyOnCreated(BlogComment $comment): void
    {
        $comment->loadMissing('post.category');

        $adminEmail = config('mail.admin_address', config('mail.from.address'));

        if ($adminEmail) {
            $this->trySend((string) $adminEmail, new BlogCommentAdminNotification($comment), 'yönetici bildirimi');
        }

        if ($comment->email) {
            $this->trySend($comment->email, new BlogCommentReceivedMail($comment), 'yorum alındı bildirimi');
        }
    }

    /** Onaylanan yorumun sahibine yayınlandı bildirimi. */
    private function notifyApproved(BlogComment $comment): void
    {
        if (! $comment->email) {
            return;
        }

        $comment->loadMissing('post.category');

        $this->trySend($comment->email, new BlogCommentApprovedMail($comment), 'yorum onay bildirimi');
    }

    private function trySend(string $to, \Illuminate\Contracts\Mail\Mailable $mailable, string $ne): void
    {
        try {
            $this->mailService->queue($to, $mailable);
        } catch (\Throwable $e) {
            Log::warning("Yorum {$ne} kuyruğa eklenemedi", [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
