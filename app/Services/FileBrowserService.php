<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UploadedFile as UploadedFileRecord;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * public/uploads dizinini gezilebilir hâle getirir.
 *
 * Dosya yöneticisi (/admin/files) uploaded_files tablosunu listeliyor; oysa
 * editörden yüklenen görseller o tabloya yazılmıyor ve diskte content/ altında
 * duruyor. Editörün seçicisi veritabanını okusaydı kullanıcı az önce eklediği
 * görseli listede bulamazdı. Bu yüzden kaynak doğrudan disk: ekranda ne varsa
 * diskte de o var.
 *
 * Silme ise veritabanını biliyor: dosyanın uploaded_files karşılığı varsa kayıt
 * da temizleniyor, yoksa geride sahibi olmayan bir satır kalırdı.
 */
final class FileBrowserService
{
    /**
     * Bir sayfada gösterilecek en fazla dosya. Dizin binlerce dosya taşıyabilir;
     * hepsini tek seferde çizmek tarayıcıyı kilitler.
     */
    private const PAGE_SIZE = 60;

    /**
     * @var array<int, string>
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif'];

    /**
     * Uzantı → kategori. Seçicideki süzgeç düğmeleri ve ikonlar buradan
     * besleniyor; etiketler dosya yöneticisiyle (UploadedFile) aynı kalsın
     * diye kategori adları da o modeldeki sabitlerle birebir.
     *
     * @var array<string, array<int, string>>
     */
    private const CATEGORY_EXTENSIONS = [
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'rtf', 'odt', 'ods'],
        'video'    => ['mp4', 'mov', 'avi', 'webm', 'mkv', 'm4v'],
        'audio'    => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'],
        'archive'  => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];

    /**
     * Kategori süzgecinde kabul edilen değerler. Boş dize ve "all" süzgeçsiz.
     *
     * @var array<int, string>
     */
    public const CATEGORIES = ['image', 'document', 'video', 'audio', 'archive', 'other'];

    public function __construct(
        private readonly UploadService $uploads,
        private readonly FileManagerService $fileManager,
    ) {}

    /**
     * Bir klasörün içeriğini döndürür.
     *
     * @param  string $folder Uploads köküne göre klasör ("" = kök)
     * @param  string $type   Kategori süzgeci; boş ya da "all" → hepsi
     * @param  string $search Ada göre süzme
     * @return array{folder: string, parent: ?string, folders: array<int, array{name: string, path: string, count: int}>, files: array<int, array<string, mixed>>, total: int, shown: int, truncated: bool}
     */
    public function browse(string $folder = '', string $type = '', string $search = '', int $page = 1): array
    {
        $directory = $this->resolve($folder);

        $folders = [];
        $files = [];
        $search = mb_strtolower(trim($search));

        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with((string) $entry, '.')) {
                continue;
            }

            $full = $directory . '/' . $entry;

            if (is_dir($full)) {
                $folders[] = [
                    'name'  => (string) $entry,
                    'path'  => $folder === '' ? (string) $entry : $folder . '/' . $entry,
                    'count' => $this->countFiles($full),
                ];

                continue;
            }

            if (! is_file($full) || $this->isVariant($directory, (string) $entry)) {
                continue;
            }

            $extension = mb_strtolower((string) pathinfo((string) $entry, PATHINFO_EXTENSION));
            $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);
            $category = $this->category($extension, $isImage);

            // Süzgeç isteğe bağlı: boş ya da "all" gelince hiçbir tür elenmiyor.
            // Eskiden editör her zaman "image" gönderdiği için PDF, video, zip
            // gibi dosyalar seçicide hiç görünmüyordu.
            if ($type !== '' && $type !== 'all' && $type !== $category) {
                continue;
            }

            if ($search !== '' && ! str_contains(mb_strtolower((string) $entry), $search)) {
                continue;
            }

            $relative = $folder === '' ? (string) $entry : $folder . '/' . $entry;

            $files[] = [
                'name'      => (string) $entry,
                'path'      => $relative,
                'url'       => UploadService::url($relative),
                'thumb'     => $isImage ? $this->thumbUrl($directory, $relative, $extension) : null,
                'extension' => $extension,
                'size'      => (int) filesize($full),
                'is_image'  => $isImage,
                'category'  => $category,
                'icon'      => $this->icon($extension),
                'modified'  => (int) filemtime($full),
            ];
        }

        usort($folders, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        // En yeni önce: kullanıcı çoğunlukla az önce yüklediğini arıyor.
        usort($files, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        $total = count($files);
        $page = max(1, $page);
        $sayfalik = array_slice($files, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        return [
            'folder'    => $folder,
            'parent'    => $folder === '' ? null : (string) implode('/', array_slice(explode('/', $folder), 0, -1)),
            'folders'   => $page === 1 ? $folders : [],
            'files'     => array_values($sayfalik),
            'total'     => $total,
            'shown'     => ($page - 1) * self::PAGE_SIZE + count($sayfalik),
            'truncated' => $total > $page * self::PAGE_SIZE,
        ];
    }

    /**
     * Seçiciden yüklenen dosya. Görsel ise UploadService'e gidiyor: WebP'ye
     * çevrilsin ve varyantları oluşsun, elle konan bir dosyadan farkı kalmasın.
     *
     * @return array{path: string, url: string, name: string}
     */
    public function upload(UploadedFile $file, string $folder = ''): array
    {
        // Klasörün var olması şart değil: UploadService gerekirse kendisi
        // oluşturuyor ve temiz bir kurulumda content/ henüz yok olabilir.
        // Doğrulanan şey yolun güvenli olması.
        $hedef = $this->safeFolder($folder) ?: 'content';

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'dosya';
        $extension = mb_strtolower($file->getClientOriginalExtension());

        $path = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            // thumb da isteniyor: ızgarada tam boy görsel indirmek yavaş
            // bağlantıda onlarca megabayt demek.
            ? $this->uploads->uploadImage($file, $hedef, $name, ['thumb', 'sm', 'md', 'lg'])
            : $this->uploads->uploadFile($file, $hedef, $name);

        return ['path' => $path, 'url' => UploadService::url($path), 'name' => basename($path)];
    }

    /**
     * Dosyayı diskten siler; uploaded_files karşılığı varsa kaydı da temizler.
     */
    public function delete(string $path): void
    {
        $full = $this->resolveFile($path);
        $record = UploadedFileRecord::query()->where('stored_path', $path)->first();

        if ($record !== null) {
            // Servis varyantları ve kaydı birlikte temizliyor.
            $this->fileManager->delete($record);

            return;
        }

        $extension = mb_strtolower((string) pathinfo($full, PATHINFO_EXTENSION));

        in_array($extension, self::IMAGE_EXTENSIONS, true)
            ? $this->uploads->deleteImage($path)
            : $this->uploads->deleteFile($path);
    }

    /**
     * Yükleme hedefi olarak kabul edilebilir bir klasör adı mı?
     *
     * Dizin dışına çıkma, mutlak yol ve sürücü öneki reddediliyor; kalan yol
     * uploads kökünün altında bir alt klasör olmak zorunda.
     */
    private function safeFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');

        if ($folder === '') {
            return '';
        }

        foreach (explode('/', $folder) as $parca) {
            if ($parca === '' || $parca === '.' || $parca === '..' || ! preg_match('/^[\w.-]+$/u', $parca)) {
                throw new RuntimeException('Geçersiz klasör adı.');
            }
        }

        return $folder;
    }

    /**
     * Klasör yolunu doğrular ve gerçek dizini döndürür.
     *
     * Yol istemciden geliyor; realpath ile çözülüp uploads kökünün içinde
     * kaldığı doğrulanmadan hiçbir şey okunmuyor.
     */
    private function resolve(string $folder): string
    {
        $root = $this->rootPath();

        if ($folder === '') {
            return $root;
        }

        $real = realpath(UploadService::basePath($folder));

        if ($real === false || ! is_dir($real) || ! $this->inside($real, $root)) {
            throw new RuntimeException('Klasör bulunamadı.');
        }

        return $real;
    }

    private function resolveFile(string $path): string
    {
        $root = $this->rootPath();
        $real = realpath(UploadService::basePath($path));

        if ($real === false || ! is_file($real) || ! $this->inside($real, $root)) {
            throw new RuntimeException('Dosya bulunamadı.');
        }

        return $real;
    }

    /**
     * Yükleme kökünün gerçek yolu; yoksa oluşturulur.
     *
     * Kök dizin, henüz hiçbir şey yüklenmemiş taze bir kurulumda var olmayabilir
     * (git boş dizin taşımıyor, yol .env'den başka bir yere de bakabiliyor).
     * Eskiden bu durum "Yükleme dizini bulunamadı" hatasına ve editörün dosya
     * seçicisinde 404'e dönüyordu: kullanıcı bir arıza sanıyordu, oysa ortada
     * yalnızca boş bir kurulum vardı. Yükleme de aynı köke yazacağı için dizini
     * burada açmak, sonraki ilk yüklemenin yapacağı işi öne almaktan ibaret.
     */
    private function rootPath(): string
    {
        $base = UploadService::basePath();

        if (! is_dir($base) && ! @mkdir($base, 0755, true) && ! is_dir($base)) {
            throw new RuntimeException('Yükleme dizini oluşturulamadı.');
        }

        $root = realpath($base);

        if ($root === false) {
            throw new RuntimeException('Yükleme dizini bulunamadı.');
        }

        return $root;
    }

    private function inside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * Duyarlı varyant mı? ("gorsel-ab12-md.webp" gibi)
     *
     * Yalnızca sonek bakmak yetmez: "toplanti-sm.jpg" diye yüklenmiş gerçek bir
     * dosya olabilir. Aynı klasörde soneksiz aslı da duruyorsa varyant sayılıyor.
     */
    private function isVariant(string $directory, string $entry): bool
    {
        $info = pathinfo($entry);
        $filename = $info['filename'] ?? '';
        $extension = $info['extension'] ?? '';

        foreach (['thumb', 'sm', 'md', 'lg'] as $size) {
            $sonek = '-' . $size;

            if (! str_ends_with($filename, $sonek)) {
                continue;
            }

            $asil = substr($filename, 0, -strlen($sonek)) . '.' . $extension;

            if (is_file($directory . '/' . $asil)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Küçük görsel varsa onun adresi: ızgarada tam boy görselleri indirmek
     * yavaş bağlantıda sayfayı dakikalarca bekletir.
     */
    private function thumbUrl(string $directory, string $relative, string $extension): string
    {
        $info = pathinfo($relative);
        $aday = ($info['filename'] ?? '') . '-thumb.' . $extension;

        return is_file($directory . '/' . $aday)
            ? UploadService::url(($info['dirname'] === '.' ? '' : $info['dirname'] . '/') . $aday)
            : UploadService::url($relative);
    }

    /**
     * Uzantının kategorisi. Eşleşme yoksa "other".
     */
    private function category(string $extension, bool $isImage): string
    {
        if ($isImage) {
            return 'image';
        }

        foreach (self::CATEGORY_EXTENSIONS as $category => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Bootstrap Icons sınıfı. Dosya yöneticisindeki UploadedFile::iconClass()
     * ile aynı eşleme: aynı dosya iki ekranda farklı ikonla görünmesin.
     */
    private function icon(string $extension): string
    {
        return match ($extension) {
            'pdf'                           => 'bi-file-earmark-pdf',
            'doc', 'docx', 'rtf', 'odt'     => 'bi-file-earmark-word',
            'xls', 'xlsx', 'csv', 'ods'     => 'bi-file-earmark-excel',
            'ppt', 'pptx'                   => 'bi-file-earmark-slides',
            'zip', 'rar', '7z', 'tar', 'gz' => 'bi-file-earmark-zip',
            'mp4', 'mov', 'avi', 'webm', 'mkv', 'm4v' => 'bi-camera-reels',
            'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac' => 'bi-music-note-beamed',
            'txt'                           => 'bi-file-earmark-text',
            default                         => 'bi-file-earmark',
        };
    }

    private function countFiles(string $directory): int
    {
        $entries = (array) scandir($directory);

        return count(array_filter(
            $entries,
            static fn ($e): bool => $e !== '.' && $e !== '..' && ! str_starts_with((string) $e, '.'),
        ));
    }
}
