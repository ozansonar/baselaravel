<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bir ekin uzantısından türeyen tür ailesi.
 *
 * Ek listesi hem yönetimde hem ön yüzde bu ailelere göre gruplanıyor: on beş
 * dosyayı tek sırada dizmek okunmuyor, "5 Görsel · 3 PDF · 2 Excel" okunuyor.
 * Uzantı → aile eşlemesi tek yerde durur; ikiye ayrılsaydı yönetimdeki rozet
 * ile ön yüzdeki başlık zamanla birbirini tutmazdı.
 */
enum FileKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Pdf = 'pdf';
    case Document = 'document';
    case Spreadsheet = 'spreadsheet';
    case Presentation = 'presentation';
    case Archive = 'archive';
    case Other = 'other';

    /**
     * Uzantıların aileye dağılımı.
     *
     * @var array<string, list<string>>
     */
    private const array EXTENSIONS = [
        'image'        => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'heic'],
        'video'        => ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'],
        'audio'        => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'],
        'pdf'          => ['pdf'],
        'document'     => ['doc', 'docx', 'odt', 'rtf', 'txt'],
        'spreadsheet'  => ['xls', 'xlsx', 'ods', 'csv'],
        'presentation' => ['ppt', 'pptx', 'odp'],
        'archive'      => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];

    public static function fromExtension(?string $extension): self
    {
        $needle = strtolower(trim((string) $extension, ". \t\n\r\0\x0B"));

        foreach (self::EXTENSIONS as $kind => $extensions) {
            if (in_array($needle, $extensions, true)) {
                return self::from($kind);
            }
        }

        return self::Other;
    }

    /**
     * Listeleme sırası: sık bakılan aileler önce.
     *
     * @return list<self>
     */
    public static function displayOrder(): array
    {
        return [
            self::Image,
            self::Video,
            self::Audio,
            self::Pdf,
            self::Document,
            self::Spreadsheet,
            self::Presentation,
            self::Archive,
            self::Other,
        ];
    }

    /** Yönetim panelinde kullanılan Türkçe ad. */
    public function label(): string
    {
        return match ($this) {
            self::Image        => 'Görsel',
            self::Video        => 'Video',
            self::Audio        => 'Ses',
            self::Pdf          => 'PDF',
            self::Document     => 'Belge',
            self::Spreadsheet  => 'Tablo',
            self::Presentation => 'Sunum',
            self::Archive      => 'Arşiv',
            self::Other        => 'Diğer',
        };
    }

    /** Bootstrap Icons sınıfı — yönetim paneli. */
    public function icon(): string
    {
        return match ($this) {
            self::Image        => 'bi-file-earmark-image',
            self::Video        => 'bi-file-earmark-play',
            self::Audio        => 'bi-file-earmark-music',
            self::Pdf          => 'bi-file-earmark-pdf',
            self::Document     => 'bi-file-earmark-word',
            self::Spreadsheet  => 'bi-file-earmark-spreadsheet',
            self::Presentation => 'bi-file-earmark-slides',
            self::Archive      => 'bi-file-earmark-zip',
            self::Other        => 'bi-file-earmark',
        };
    }

    /** Font Awesome sınıfı — ön yüz. */
    public function faIcon(): string
    {
        return match ($this) {
            self::Image        => 'fa-regular fa-file-image',
            self::Video        => 'fa-regular fa-file-video',
            self::Audio        => 'fa-regular fa-file-audio',
            self::Pdf          => 'fa-regular fa-file-pdf',
            self::Document     => 'fa-regular fa-file-word',
            self::Spreadsheet  => 'fa-regular fa-file-excel',
            self::Presentation => 'fa-regular fa-file-powerpoint',
            self::Archive      => 'fa-regular fa-file-zipper',
            self::Other        => 'fa-regular fa-file',
        };
    }

    /**
     * Rozet rengi; aileler renkle de ayrışsın diye. Değerler ön yüzde CSS
     * değişkeni adına, yönetimde sınıf ekine dönüşüyor.
     */
    public function color(): string
    {
        return match ($this) {
            self::Image        => 'teal',
            self::Video        => 'purple',
            self::Audio        => 'pink',
            self::Pdf          => 'red',
            self::Document     => 'blue',
            self::Spreadsheet  => 'green',
            self::Presentation => 'orange',
            self::Archive      => 'yellow',
            self::Other        => 'gray',
        };
    }
}
