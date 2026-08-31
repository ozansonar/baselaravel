<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\MailLogStatus;
use App\Enums\ReportType;
use App\Enums\SubscriberStatus;
use App\Models\BlogPost;
use App\Models\Campaign;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\MailLog;
use App\Models\Page;
use App\Models\PageView;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rapor merkezi: aynı soruyu bütün modüller için aynı biçimde yanıtlar —
 * "seçilen tarih aralığında ne oldu?"
 *
 * Veri panelde zaten toplanıyordu ama beş ayrı ekrana dağılmıştı; yönetici
 * "geçen ay ne yayınladık, kaç kişi geldi, kaç mail düştü" sorusunu ancak
 * ekranları gezerek yanıtlayabiliyordu.
 *
 * Her rapor üç parça döndürüyor ve üçü de aynı yapıda:
 *
 *   metrics → ekranın üstündeki sayılar (KPI)
 *   series  → grafiğin günlük eğrisi
 *   rows    → tablo ve dışa aktarma satırları
 *
 * Aynı yapı sayesinde ekran, Excel/PDF çıktısı ve zamanlanmış gönderim tek
 * kod yolunu paylaşıyor: rapor türü eklemek üç yerde değil bir yerde iş.
 */
final class ReportService
{
    /** Ekranın sunduğu hazır aralıklar. */
    public const RANGES = [
        '7'    => 'Son 7 gün',
        '30'   => 'Son 30 gün',
        '90'   => 'Son 90 gün',
        'this' => 'Bu ay',
        'last' => 'Geçen ay',
        'year' => 'Bu yıl',
    ];

    /**
     * Adres satırındaki aralık anahtarını gerçek tarihlere çevirir.
     *
     * Tanınmayan değer 30 güne düşüyor: rapor ekranı, elle yazılmış bir
     * parametre yüzünden boş dönmemeli.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(?string $range, ?string $from = null, ?string $to = null): array
    {
        if ($from !== null && $to !== null && $from !== '' && $to !== '') {
            try {
                return [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ];
            } catch (\Throwable) {
                // Bozuk tarih hazır aralığa düşüyor.
            }
        }

        return match ($range) {
            '7'    => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            '90'   => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
            'this' => [now()->startOfMonth(), now()->endOfDay()],
            'last' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfDay()],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * Bir raporun tamamı.
     *
     * @return array{type: ReportType, from: Carbon, to: Carbon, metrics: list<array{label: string, value: string, hint?: string}>, series: array{labels: list<string>, values: list<int>, label: string}, columns: list<string>, rows: list<array<int, string>>}
     */
    public function build(ReportType $type, Carbon $from, Carbon $to): array
    {
        $report = match ($type) {
            ReportType::Traffic     => $this->traffic($from, $to),
            ReportType::Content     => $this->content($from, $to),
            ReportType::Users       => $this->users($from, $to),
            ReportType::Mail        => $this->mail($from, $to),
            ReportType::Campaigns   => $this->campaigns($from, $to),
            ReportType::Subscribers => $this->subscribers($from, $to),
        };

        return array_merge(['type' => $type, 'from' => $from, 'to' => $to], $report);
    }

    /**
     * Ekranın üstündeki dört özet kutusu — bütün raporların toplamı.
     *
     * @return array<string, int>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        return [
            'views' => PageView::whereBetween('viewed_at', [$from, $to])->where('is_bot', false)->count(),
            'content' => BlogPost::whereBetween('created_at', [$from, $to])->count()
                + Page::whereBetween('created_at', [$from, $to])->count()
                + GalleryItem::whereBetween('created_at', [$from, $to])->count(),
            'users' => User::whereBetween('created_at', [$from, $to])->count(),
            'mails' => MailLog::whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    // ── Raporlar ──

    /**
     * @return array<string, mixed>
     */
    private function traffic(Carbon $from, Carbon $to): array
    {
        $base = PageView::whereBetween('viewed_at', [$from, $to])->where('is_bot', false);

        $views = (clone $base)->count();
        $visitors = (clone $base)->distinct()->count('session_id');
        $bots = PageView::whereBetween('viewed_at', [$from, $to])->where('is_bot', true)->count();

        $rows = (clone $base)
            ->select('url_path', DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT session_id) as visitors'))
            ->groupBy('url_path')
            ->orderByDesc('total')
            ->limit(100)
            ->get()
            ->map(fn (object $row): array => [
                (string) $row->url_path,
                (string) $row->total,
                (string) $row->visitors,
            ])
            ->all();

        return [
            'metrics' => [
                ['label' => 'Görüntülenme', 'value' => number_format($views, 0, ',', '.')],
                ['label' => 'Tekil ziyaretçi', 'value' => number_format($visitors, 0, ',', '.')],
                ['label' => 'Sayfa/ziyaretçi', 'value' => $visitors > 0 ? number_format($views / $visitors, 1, ',', '.') : '0'],
                ['label' => 'Bot isteği', 'value' => number_format($bots, 0, ',', '.'), 'hint' => 'Sayımların dışında'],
            ],
            'series'  => $this->dailySeries(PageView::query()->where('is_bot', false), 'viewed_at', $from, $to, 'Görüntülenme'),
            'columns' => ['Sayfa', 'Görüntülenme', 'Tekil ziyaretçi'],
            'rows'    => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function content(Carbon $from, Carbon $to): array
    {
        $posts = BlogPost::whereBetween('created_at', [$from, $to]);
        $pages = Page::whereBetween('created_at', [$from, $to]);
        $gallery = GalleryItem::whereBetween('created_at', [$from, $to]);
        $faqs = Faq::whereBetween('created_at', [$from, $to]);

        $rows = BlogPost::with('author')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (BlogPost $post): array => [
                (string) $post->title,
                (string) ($post->locale ?? ''),
                $post->status instanceof \BackedEnum ? (string) $post->status->value : (string) $post->status,
                (string) ($post->author?->full_name ?? '—'),
                (string) $post->created_at?->format('d.m.Y'),
            ])
            ->all();

        return [
            'metrics' => [
                ['label' => 'Blog yazısı', 'value' => (string) (clone $posts)->count()],
                ['label' => 'Sayfa', 'value' => (string) (clone $pages)->count()],
                ['label' => 'Galeri öğesi', 'value' => (string) (clone $gallery)->count()],
                ['label' => 'Sık sorulan soru', 'value' => (string) (clone $faqs)->count()],
            ],
            'series'  => $this->dailySeries(BlogPost::query(), 'created_at', $from, $to, 'Yayınlanan yazı'),
            'columns' => ['Başlık', 'Dil', 'Durum', 'Yazar', 'Tarih'],
            'rows'    => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function users(Carbon $from, Carbon $to): array
    {
        $new = User::whereBetween('created_at', [$from, $to]);

        $rows = User::with('roles')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (User $user): array => [
                $user->full_name,
                (string) $user->email,
                $user->roles->pluck('name')->implode(', ') ?: 'Kullanıcı',
                $user->email_verified_at !== null ? 'Doğrulandı' : 'Bekliyor',
                $user->is_active ? 'Aktif' : 'Pasif',
                (string) $user->created_at?->format('d.m.Y'),
            ])
            ->all();

        return [
            'metrics' => [
                ['label' => 'Yeni kayıt', 'value' => (string) (clone $new)->count()],
                ['label' => 'Doğrulanmış', 'value' => (string) (clone $new)->whereNotNull('email_verified_at')->count()],
                ['label' => 'Pasif', 'value' => (string) (clone $new)->where('is_active', false)->count()],
                ['label' => 'Toplam kullanıcı', 'value' => (string) User::count(), 'hint' => 'Tarih aralığından bağımsız'],
            ],
            'series'  => $this->dailySeries(User::query(), 'created_at', $from, $to, 'Yeni kayıt'),
            'columns' => ['Ad Soyad', 'E-posta', 'Rol', 'Doğrulama', 'Durum', 'Kayıt'],
            'rows'    => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mail(Carbon $from, Carbon $to): array
    {
        $base = MailLog::whereBetween('created_at', [$from, $to]);

        $rows = (clone $base)
            ->select('mailable_class', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('mailable_class', 'status')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                class_basename((string) $row->mailable_class),
                // Sütun ham değer olarak geliyor (select ile alındı, model
                // cast'i devrede değil) ama enum'a çevrilmiş de olabilir.
                $row->status instanceof \BackedEnum ? (string) $row->status->value : (string) $row->status,
                (string) $row->total,
            ])
            ->all();

        $sent = (clone $base)->where('status', MailLogStatus::Sent)->count();
        $failed = (clone $base)->where('status', MailLogStatus::Failed)->count();
        $total = (clone $base)->count();

        return [
            'metrics' => [
                ['label' => 'Toplam', 'value' => number_format($total, 0, ',', '.')],
                ['label' => 'Gönderilen', 'value' => number_format($sent, 0, ',', '.')],
                ['label' => 'Başarısız', 'value' => number_format($failed, 0, ',', '.')],
                ['label' => 'Başarı oranı', 'value' => $total > 0 ? number_format($sent / $total * 100, 1, ',', '.') . '%' : '—'],
            ],
            'series'  => $this->dailySeries(MailLog::query(), 'created_at', $from, $to, 'Gönderim'),
            'columns' => ['Tür', 'Durum', 'Adet'],
            'rows'    => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaigns(Carbon $from, Carbon $to): array
    {
        $campaigns = Campaign::whereBetween('created_at', [$from, $to])->orderByDesc('created_at')->get();

        $rows = $campaigns
            ->map(fn (Campaign $campaign): array => [
                (string) $campaign->name,
                $campaign->status instanceof \BackedEnum ? (string) $campaign->status->value : (string) $campaign->status,
                (string) $campaign->total_recipients,
                (string) $campaign->sent_count,
                (string) $campaign->failed_count,
                (string) $campaign->created_at?->format('d.m.Y'),
            ])
            ->all();

        $sent = (int) $campaigns->sum('sent_count');
        $failed = (int) $campaigns->sum('failed_count');

        return [
            'metrics' => [
                ['label' => 'Kampanya', 'value' => (string) $campaigns->count()],
                ['label' => 'Ulaşan gönderi', 'value' => number_format($sent, 0, ',', '.')],
                ['label' => 'Düşen gönderi', 'value' => number_format($failed, 0, ',', '.')],
                ['label' => 'Gönderilen kampanya', 'value' => (string) $campaigns->where('status', CampaignStatus::Sent)->count()],
            ],
            'series'  => $this->dailySeries(Campaign::query(), 'created_at', $from, $to, 'Kampanya'),
            'columns' => ['Kampanya', 'Durum', 'Alıcı', 'Ulaşan', 'Düşen', 'Tarih'],
            'rows'    => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscribers(Carbon $from, Carbon $to): array
    {
        $new = Subscriber::whereBetween('created_at', [$from, $to]);
        $left = Subscriber::whereBetween('unsubscribed_at', [$from, $to]);

        $rows = (clone $new)
            ->select('source', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('source', 'status')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                $row->source instanceof \BackedEnum ? (string) $row->source->value : (string) ($row->source ?? '—'),
                $row->status instanceof \BackedEnum ? (string) $row->status->value : (string) $row->status,
                (string) $row->total,
            ])
            ->all();

        return [
            'metrics' => [
                ['label' => 'Yeni abone', 'value' => (string) (clone $new)->count()],
                ['label' => 'Abonelikten çıkan', 'value' => (string) (clone $left)->count()],
                ['label' => 'Aktif abone', 'value' => (string) Subscriber::where('status', SubscriberStatus::Subscribed)->count(), 'hint' => 'Tarih aralığından bağımsız'],
                ['label' => 'Net değişim', 'value' => (string) ((clone $new)->count() - (clone $left)->count())],
            ],
            'series'  => $this->dailySeries(Subscriber::query(), 'created_at', $from, $to, 'Yeni abone'),
            'columns' => ['Kaynak', 'Durum', 'Adet'],
            'rows'    => $rows,
        ];
    }

    // ── Ortak ──

    /**
     * Günlük eğri.
     *
     * Boş günler sıfırla dolduruluyor: yalnız veri olan günleri çizmek,
     * grafikte hiç yaşanmamış bir süreklilik gösterirdi.
     *
     * @param \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model> $query
     * @return array{labels: list<string>, values: list<int>, label: string}
     */
    private function dailySeries($query, string $column, Carbon $from, Carbon $to, string $label): array
    {
        // Gün sayısı sınırlanıyor: bir yıllık aralıkta 365 nokta zaten okunmaz
        // hâle geliyor ve sorgu da gereksiz büyüyor.
        $counts = $query
            ->whereBetween($column, [$from, $to])
            ->select(DB::raw('DATE(' . $column . ') as gun'), DB::raw('COUNT(*) as adet'))
            ->groupBy('gun')
            ->pluck('adet', 'gun');

        $labels = [];
        $values = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d.m');
            $values[] = (int) ($counts[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values, 'label' => $label];
    }

    /**
     * Rapor satırlarında arama — dışa aktarmanın süzgeci.
     *
     * @param list<array<int, string>> $rows
     * @return list<array<int, string>>
     */
    public function filterRows(array $rows, ?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return $rows;
        }

        // Türkçe'de mb_strtolower tek başına yetmiyor: "İ" küçültüldüğünde
        // birleşik noktalı bir "i̇" çıkıyor ve klavyeden yazılan "i" ile
        // eşleşmiyor. Harfler önce eşleniyor, sonra küçültülüyor.
        $normalize = static fn (string $value): string => mb_strtolower(
            str_replace(['İ', 'I', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'], ['i', 'ı', 'ş', 'ğ', 'ü', 'ö', 'ç'], $value),
        );

        $needle = $normalize(trim($search));

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => str_contains($normalize(implode(' ', $row)), $needle),
        ));
    }
}
