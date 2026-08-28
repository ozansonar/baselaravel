<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileKind;
use App\Models\ContentFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * İçerik eklerinin yüklenmesi, bağlanması ve gruplanması.
 *
 * Blog yazısı da sayfa da aynı servisi kullanıyor; bağ polimorfik. İki ayrı
 * servis olsaydı ikisi zamanla birbirinden ayrışır, biri düzelen bir hatayı
 * öteki taşımaya devam ederdi.
 *
 * Ek sayısına tavan konmuyor: bir habere kırk görsel, beş tablo ve üç PDF
 * iliştirmek isteyen kullanıcı sayaç yüzünden yarıda kalmasın. Sınır dosya
 * başına boyut ve sunucunun kendi tavanı; ikisini de UploadService::limits()
 * söylüyor.
 *
 * Dosya forma binmiyor, her biri kendi küçük isteğiyle gidiyor. Hepsi tek
 * POST'ta gitseydi gövde post_max_size'ı aşar, PHP gövdeyi komple atar ve CSRF
 * alanı da onunla gittiği için istek 419 dönerdi: kullanıcı yazdığı içeriği
 * kaybederdi.
 *
 * İki bağlanma yolu var. Çevirisi zaten kayıtlı bir dilde dosya doğrudan o
 * satıra bağlanıyor — kaydet'e basılmasa bile ek yerinde. Henüz satırı olmayan
 * dilde (yeni içerik ya da hiç çevrilmemiş sekme) bağlanacak bir şey yok;
 * dosya belirteciyle bekliyor ve satır doğduğunda iliştiriliyor.
 */
final class ContentFileService
{
    /** public/uploads altındaki klasör. */
    private const string UPLOAD_FOLDER = 'blog-files';

    /**
     * Uygulamanın dosya başına tavanı. Sunucununki daha düşükse o geçerli
     * olur; paylaşımlı hosting'de ini'yi yukarı zorlamak mümkün değil.
     */
    private const int MAX_FILE_BYTES = 104_857_600; // 100 MB

    /**
     * Sayı tavanı yok. limits() yine de bir sayı istediği için pratikte
     * ulaşılmayacak bir değer veriliyor; gerçek sınır PHP'nin
     * max_file_uploads'ı ve o da tek tek yüklendiği için devreye girmiyor.
     */
    private const int MAX_FILES = PHP_INT_MAX;

    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    /**
     * @return array{per_file: int, post_max: int, max_files: int}
     */
    public function limits(): array
    {
        return $this->uploadService->limits(self::MAX_FILE_BYTES, self::MAX_FILES);
    }

    public function maxFileBytes(): int
    {
        return $this->limits()['per_file'];
    }

    /**
     * Tek dosyayı yükler.
     *
     * $attachable verildiyse ek doğrudan o içeriğe (ve o dile) bağlanır;
     * verilmediyse belirteçle bekler. Dönen kayıt iki durumda da aynı biçimde
     * çiziliyor.
     */
    public function store(UploadedFile $file, ?Model $attachable, ?int $userId): ContentFile
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mime = $file->getClientMimeType();
        $size = (int) ($file->getSize() ?: 0);

        // Diskteki ad içerikten türesin: "rapor-2026-a1b2c3d4e5.xlsx" hem
        // okunur hem çakışmaz. Kullanıcının verdiği ad kayıtta saklanıyor,
        // indirirken onunla iniyor.
        $slugSource = pathinfo($originalName, PATHINFO_FILENAME) ?: 'dosya';

        $path = $this->uploadService->uploadFile($file, self::UPLOAD_FOLDER, $slugSource);

        return ContentFile::create([
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id'   => $attachable?->getKey(),
            'token'           => $attachable === null ? (string) Str::uuid() : null,
            'user_id'         => $userId,
            'path'            => $path,
            'original_name'   => $originalName,
            'extension'       => $extension !== '' ? $extension : (string) pathinfo($path, PATHINFO_EXTENSION),
            'mime_type'       => $mime,
            'size'            => $size,
            'sort_order'      => $attachable !== null ? $this->nextSortOrder($attachable) : 0,
        ]);
    }

    /**
     * Kaydedilmeden vazgeçilen bekleyen eki diskten de siler.
     */
    public function discardPending(string $token, ?int $userId): bool
    {
        $pending = ContentFile::query()
            ->pending($userId)
            ->where('token', $token)
            ->first();

        if ($pending === null) {
            return false;
        }

        $this->purge($pending);

        return true;
    }

    /**
     * Bağlanmış eki siler.
     *
     * Yumuşak silme değil: kayıt yalnızca dosyanın adresi, dosya gittiyse
     * satırın saklanacak bir tarafı kalmıyor.
     */
    public function delete(ContentFile $file): void
    {
        $this->purge($file);
    }

    /**
     * Peşin yüklenmiş ekleri hedef içeriğin (o dilin) satırına bağlar.
     *
     * Dosya zaten diskte, satır da: burada yalnızca içerik sahipleniyor. Sıra
     * kullanıcının yükleme sırası — forma hangi sırayla eklendiyse yazıda da
     * o sırayla görünmeli. Tanınmayan belirteç sessizce atlanıyor; kullanıcı
     * iki sekmede çalışmış ya da eki kaldırmış olabilir, bu kaydı durduracak
     * bir hata değil.
     *
     * @param array<int, mixed> $tokens
     */
    public function attachPending(Model $attachable, array $tokens, ?int $userId): void
    {
        $tokens = $this->cleanTokens($tokens);

        if ($tokens === []) {
            return;
        }

        DB::transaction(function () use ($attachable, $tokens, $userId): void {
            $pending = ContentFile::query()
                ->pending($userId)
                ->whereIn('token', $tokens)
                ->get()
                ->keyBy('token');

            $sort = $this->nextSortOrder($attachable);

            foreach ($tokens as $token) {
                $file = $pending->get($token);

                if ($file === null) {
                    continue;
                }

                $file->update([
                    'attachable_type' => $attachable->getMorphClass(),
                    'attachable_id'   => $attachable->getKey(),
                    'token'           => null,
                    'sort_order'      => $sort++,
                ]);
            }
        });
    }

    /**
     * Sahiplenilemeyen bekleyenleri siler.
     *
     * Boş bırakılan bir dil sekmesine dosya atılmış olabilir: o dilde satır
     * doğmadığı için ekin bağlanacağı yer yok. Dosya diskte bırakılsaydı
     * public/uploads altında sahipsiz birikirdi.
     *
     * @param array<int, mixed> $tokens
     */
    public function discardTokens(array $tokens, ?int $userId): void
    {
        foreach ($this->cleanTokens($tokens) as $token) {
            $this->discardPending($token, $userId);
        }
    }

    /**
     * Ekleri tür ailesine göre gruplar; ön yüz ve yönetim aynı sırayı görür.
     *
     * @param  iterable<int, ContentFile> $files
     * @return Collection<string, Collection<int, ContentFile>>
     */
    public function groupByKind(iterable $files): Collection
    {
        $grouped = Collection::make($files)->groupBy(
            static fn (ContentFile $file): string => $file->kind()->value,
        );

        // Sıra ailelerin kendi sırası; gruplama sırası yükleme sırasına
        // bağlı kalsaydı aynı yazı her açılışta başka türlü dizilirdi.
        return Collection::make(FileKind::displayOrder())
            ->mapWithKeys(static fn (FileKind $kind): array => [$kind->value => $grouped->get($kind->value)])
            ->filter()
            ->map(static fn (Collection $group): Collection => $group->values());
    }

    /**
     * Kaydedilmeden bırakılmış yüklemeleri temizler; taze bekleyenlere
     * dokunulmuyor, kullanıcı hâlâ formda olabilir.
     */
    public function purgeStalePending(int $hours): int
    {
        $stale = ContentFile::query()
            ->whereNull('attachable_id')
            ->where('created_at', '<', now()->subHours(max(1, $hours)))
            ->get();

        foreach ($stale as $file) {
            $this->purge($file);
        }

        return $stale->count();
    }

    /**
     * İstemcinin satırı çizmek için ihtiyaç duyduğu her şey.
     *
     * Dosya yolu istemciye hiç verilmiyor: verilseydi kaydederken başka bir yol
     * gönderip sunucudaki herhangi bir dosyayı içeriğe iliştirmek mümkün olurdu.
     *
     * @return array<string, mixed>
     */
    public function payload(ContentFile $file): array
    {
        $kind = $file->kind();

        return [
            'id'         => $file->attachable_id !== null ? $file->id : null,
            'token'      => $file->token,
            'name'       => $file->original_name,
            'size'       => $file->humanSize(),
            'extension'  => $file->extension,
            'kind'       => $kind->value,
            'kind_label' => $kind->label(),
            'icon'       => $kind->icon(),
            'color'      => $kind->color(),
            'is_image'   => $file->isImage(),
            'url'        => $file->url(),
        ];
    }

    private function nextSortOrder(Model $attachable): int
    {
        return (int) ContentFile::query()
            ->where('attachable_type', $attachable->getMorphClass())
            ->where('attachable_id', $attachable->getKey())
            ->max('sort_order') + 1;
    }

    private function purge(ContentFile $file): void
    {
        $this->uploadService->deleteFile($file->path);
        $file->forceDelete();
    }

    /**
     * @param  array<int, mixed> $tokens
     * @return list<string>
     */
    private function cleanTokens(array $tokens): array
    {
        return array_values(array_unique(array_filter(
            $tokens,
            static fn (mixed $token): bool => is_string($token) && $token !== '',
        )));
    }
}
