<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dışarıdan getirilen yedek dosyasının yüklenmesi.
 *
 * Diski gitmiş bir sunucuda kurtarmanın tek yolu bu: dosya listeye giriyor,
 * geri yükleme sonra aynı doğrulanmış yoldan geçiyor.
 *
 * Buradaki doğrulama yalnızca ilk kapı — dosyanın gerçekten bir yedek olduğu
 * `BackupService::store()` içinde arşiv açılarak, geri yüklemeden önce de
 * `BackupRestoreService::inspect()` içinde bir kez daha doğrulanıyor. Uzantı
 * ve MIME tek başına hiçbir şey kanıtlamaz.
 *
 * Tavan büyük tutuldu: yedek veritabanı ve tüm görselleri taşıyor, orta
 * ölçekli bir sitede yüz megabaytları bulur. PHP'nin `upload_max_filesize` ve
 * `post_max_size` ayarları bundan küçükse gerçek sınır onlardır.
 */
final class UploadBackupRequest extends FormRequest
{
    /** Kabul edilen en büyük dosya — KB cinsinden (512 MB). */
    private const MAX_KILOBYTES = 524288;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'backup' => [
                'required',
                'file',
                'extensions:zip',
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
                'max:' . self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'backup.required'   => 'Yedek dosyası seçmelisiniz.',
            'backup.file'       => 'Geçerli bir dosya yüklemelisiniz.',
            'backup.extensions' => 'Yedek dosyası .zip uzantılı olmalıdır.',
            'backup.mimetypes'  => 'Yüklenen dosya bir ZIP arşivi değil.',
            'backup.max'        => 'Yedek dosyası en fazla 512 MB olabilir.',
        ];
    }
}
