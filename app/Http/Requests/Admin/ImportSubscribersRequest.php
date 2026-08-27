<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Excel/CSV içe aktarımının kaydetme adımı.
 *
 * İki yoldan gelinebiliyor:
 *   - satırlar (rows) → önizleme ekranında gözden geçirilmiş, gerekirse elle
 *     düzeltilmiş liste,
 *   - dosya (file)    → önizlemeden geçmeden doğrudan yükleme.
 *
 * Satır geldiğinde dosya beklenmiyor: kullanıcı önizlemede düzeltme yaptıysa
 * dosyayı tekrar okumak o düzeltmeleri çöpe atardı.
 */
final class ImportSubscribersRequest extends FormRequest
{
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
            'file' => [
                $this->has('rows') ? 'nullable' : 'required',
                'file', 'mimes:xlsx,xls,ods,csv,txt', 'max:10240',
            ],
            'rows'              => ['nullable', 'array', 'max:1000'],
            'rows.*.email'      => ['required', 'email', 'max:191'],
            'rows.*.first_name' => ['nullable', 'string', 'max:191'],
            'rows.*.last_name'  => ['nullable', 'string', 'max:191'],
            'locale'            => ['nullable', 'string', 'size:2'],
            'list_ids'          => ['nullable', 'array'],
            'list_ids.*'        => ['integer', 'exists:subscriber_lists,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required'    => 'Bir dosya seçin.',
            'file.mimes'       => 'Yalnızca Excel (.xlsx, .xls, .ods) veya CSV dosyası yükleyebilirsiniz.',
            'rows.max'         => 'Tek seferde en fazla 1000 satır aktarılabilir.',
            'rows.*.email.required' => 'Listede e-postası boş bir satır var.',
            'rows.*.email.email'    => 'Listede geçersiz bir e-posta adresi var.',
        ];
    }

    /**
     * Kaydedilecek satırlar; boş adresli satırlar ayıklanmış hâlde.
     *
     * @return array<int, array{email: string, first_name: ?string, last_name: ?string}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ((array) $this->input('rows', []) as $row) {
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            $rows[] = [
                'email'      => $email,
                'first_name' => trim((string) ($row['first_name'] ?? '')) ?: null,
                'last_name'  => trim((string) ($row['last_name'] ?? '')) ?: null,
            ];
        }

        return $rows;
    }
}
