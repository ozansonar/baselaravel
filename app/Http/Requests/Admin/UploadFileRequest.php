<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FileManager dosya yükleme — beyaz liste extension + mimetype + 50 MB tavan.
 *
 * Dropzone paralel olarak her dosya için ayrı POST atar; bu request
 * tek dosyalıdır. SVG kabul edilmez (XSS riski — content içinde script).
 *
 * `mimes:` rule yerine `extensions:` + `mimetypes:` kombinasyonu kullanıldı.
 * Sebep: Laravel'in `mimes:` rule'u xlsx/docx/pptx için zaman zaman fail
 * eder çünkü Office dosyaları ZIP container'dır ve browser MIME tahmini
 * `application/zip` veya `application/octet-stream` döndürebilir.
 *
 * `extensions:` uzantı kontrolü (sıkı), `mimetypes:` her dosya için kabul
 * edilen MIME setleri (geniş — tüm Office variant'larını kapsar).
 */
final class UploadFileRequest extends FormRequest
{
    /** @var list<string> İzin verilen uzantılar (beyaz liste) */
    private const array ALLOWED_EXTENSIONS = [
        // Görseller (SVG hariç — XSS riski)
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        // Belgeler
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt',
        // Arşiv
        'zip',
        // Video / audio
        'mp4', 'mp3',
    ];

    /**
     * Beyaz liste MIME setleri.
     * Office dosyaları için hem eski (msword/ms-excel) hem yeni (openxmlformats)
     * MIME tipleri kabul edilir; ZIP container varyantı da listeye eklenir.
     *
     * @var list<string>
     */
    private const array ALLOWED_MIME_TYPES = [
        // Görseller
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        // PDF
        'application/pdf',
        // Word
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // Excel
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        // PowerPoint
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // ZIP (xlsx/docx/pptx ZIP container fallback dahil)
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',          // Bazı browser'larda generic — extensions ile sınırlandık
        // Video / audio
        'video/mp4',
        'audio/mpeg',
        'audio/mp3',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file'     => [
                'required',
                'file',
                // Uzantı kontrolü (sıkı) — Laravel 11+ rule
                'extensions:' . implode(',', self::ALLOWED_EXTENSIONS),
                // MIME kontrolü (geniş — Office varyantları + ZIP fallback)
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
                'max:51200', // 50 MB (KB cinsinden)
            ],
            'title'    => ['nullable', 'string', 'max:191'],
            'alt_text' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $extList = implode(', ', array_map(static fn (string $e): string => '.' . $e, self::ALLOWED_EXTENSIONS));

        return [
            'file.required'   => 'Dosya yüklemelisiniz.',
            'file.file'       => 'Geçerli bir dosya yüklemelisiniz.',
            'file.extensions' => "İzin verilen dosya uzantıları: {$extList}. (SVG kabul edilmez.)",
            'file.mimetypes'  => 'Bu dosya türü desteklenmiyor (MIME). İzin verilen: görsel, PDF, Word, Excel, PowerPoint, CSV, ZIP, MP4, MP3.',
            'file.max'        => 'Dosya en fazla 50 MB olabilir.',
            'file.uploaded'   => 'Dosya sunucuya yüklenemedi. PHP upload limiti aşılmış olabilir (upload_max_filesize ve post_max_size en az 64M olmalı).',
            'title.max'       => 'Başlık en fazla 191 karakter olabilir.',
            'alt_text.max'    => 'Alt metin en fazla 500 karakter olabilir.',
        ];
    }
}
