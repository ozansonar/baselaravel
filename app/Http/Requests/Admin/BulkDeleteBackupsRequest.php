<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Yedek listesinden seçilen dosyaların toplu silinmesi.
 *
 * Dosya adları istekten geldiği için biçim burada sabitlenir: yalnızca yedek
 * servisinin ürettiği ada benzeyenler geçer, böylece klasör dışına çıkma
 * denemeleri servise hiç ulaşmaz.
 */
final class BulkDeleteBackupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Yetki kontrolü controller'daki policy çağrısında (delete-backups).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files'   => ['required', 'array', 'min:1', 'max:200'],
            'files.*' => ['required', 'string', 'max:255', 'regex:/^backup-[A-Za-z0-9\-_]+\.zip$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Silinecek yedek seçilmedi.',
            'files.*.regex'  => 'Geçersiz yedek dosyası adı.',
        ];
    }

    /**
     * @return list<string>
     */
    public function filenames(): array
    {
        /** @var list<string> $files */
        $files = array_values($this->validated()['files']);

        return $files;
    }
}
