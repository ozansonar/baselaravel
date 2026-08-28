<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\BlogPostFileService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * İçerik ekinin tek dosyalık yükleme isteği.
 *
 * Sayı sınırı yok; tür listesi geniş tutuldu — görsel, video, ses, PDF, Office
 * ailesi, OpenDocument, düz metin ve arşiv. Yine de beyaz liste: sunucuda
 * çalıştırılabilen bir uzantı (php, phtml, sh, exe...) public/uploads altına
 * inerse dosya yükleme kutusu uzaktan kod çalıştırmaya dönüşür. SVG de dışarıda:
 * içine script gömülebiliyor ve doğrudan adresinden açıldığında çalışıyor.
 *
 * `mimes:` yerine `extensions:` + `mimetypes:` kullanıldı: Office dosyaları ZIP
 * container olduğu için tarayıcı MIME tahmini application/zip ya da
 * octet-stream dönebiliyor ve `mimes:` xlsx/docx/pptx'te zaman zaman düşüyor.
 */
final class StoreBlogPostFileRequest extends FormRequest
{
    /** @var list<string> İzin verilen uzantılar (beyaz liste) */
    private const array ALLOWED_EXTENSIONS = [
        // Görseller (SVG hariç — XSS riski)
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif', 'heic',
        // Belgeler
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
        // Tablolar
        'xls', 'xlsx', 'ods', 'csv',
        // Sunumlar
        'ppt', 'pptx', 'odp',
        // Arşivler
        'zip', 'rar', '7z',
        // Video
        'mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv',
        // Ses
        'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac',
    ];

    /**
     * Geniş MIME seti: Office'in hem eski hem openxml tipleri, ZIP container
     * yedeği ve tarayıcıların generic octet-stream tahmini dahil. Asıl daraltma
     * uzantı listesinde.
     *
     * @var list<string>
     */
    private const array ALLOWED_MIME_TYPES = [
        // Görseller
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp',
        'image/avif', 'image/heic', 'image/heif',
        // PDF
        'application/pdf',
        // Word / OpenDocument
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/rtf', 'text/rtf',
        'text/plain',
        // Excel / OpenDocument
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv',
        // PowerPoint / OpenDocument
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.presentation',
        // Arşivler (Office dosyalarının ZIP container yedeği dahil)
        'application/zip', 'application/x-zip-compressed',
        'application/x-rar-compressed', 'application/vnd.rar',
        'application/x-7z-compressed',
        'application/octet-stream',
        // Video
        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
        'video/x-matroska',
        // Ses
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg',
        'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/flac', 'audio/x-flac',
    ];

    /**
     * Bırakma kutusunun kabul ettiği uzantılar.
     *
     * İstemci listesi sunucununkiyle aynı olsun diye buradan okunuyor: iki
     * yerde ayrı yazılsaydı kullanıcı dakikalarca yükleyip sonunda "bu uzantı
     * kabul edilmiyor" cevabını alırdı.
     *
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    /** Dropzone'un acceptedFiles biçimi: ".jpg,.png,..." */
    public static function acceptAttribute(): string
    {
        return implode(',', array_map(static fn (string $e): string => '.' . $e, self::ALLOWED_EXTENSIONS));
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Tavan sunucununki ile uygulamanınkinin küçüğü; kullanıcıya da bu
        // sayı gösteriliyor, istemci kuralı sunucudan gevşek kalmasın.
        $maxKb = (int) floor(app(BlogPostFileService::class)->maxFileBytes() / 1024);

        return [
            'file' => [
                'required',
                'file',
                'extensions:' . implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
                'max:' . $maxKb,
            ],
            // Çevirisi kayıtlı bir dile yükleniyorsa ek doğrudan o satıra bağlanır.
            'blog_post_id' => ['nullable', 'integer', 'exists:blog_posts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $extList = implode(', ', array_map(static fn (string $e): string => '.' . $e, self::ALLOWED_EXTENSIONS));

        return [
            'file.required'   => 'Dosya alınamadı.',
            'file.file'       => 'Geçerli bir dosya yüklemelisiniz.',
            'file.extensions' => "Bu uzantı kabul edilmiyor. İzin verilenler: {$extList}",
            'file.mimetypes'  => 'Bu dosya türü desteklenmiyor.',
            'file.max'        => 'Dosya sunucunun kabul ettiği boyutu aşıyor.',
            'file.uploaded'   => 'Dosya yüklenemedi; sunucu sınırını aşıyor olabilir.',
        ];
    }
}
