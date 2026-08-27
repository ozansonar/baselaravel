<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\CampaignAudience;
use App\Services\CampaignService;
use App\Services\RecipientImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;

class StoreCampaignRequest extends FormRequest
{
    /**
     * Recipients resolved from the upload or the textarea, kept here so the
     * controller does not have to parse them a second time.
     *
     * @var array<int, array{name: ?string, email: string}>
     */
    private array $resolvedRecipients = [];

    public function authorize(): bool
    {
        return true; // Policy is checked in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:191'],
            'subject'    => ['required', 'string', 'max:191'],
            'body'       => ['required', 'string'],
            'from_name'  => ['nullable', 'string', 'max:191'],
            'from_email' => ['nullable', 'email', 'max:191'],
            'reply_to'   => ['nullable', 'email', 'max:191'],
            'locale'     => ['nullable', 'string', 'size:2'],
            'audience'   => ['required', Rule::enum(CampaignAudience::class)],

            // Site members
            'active_only'   => ['nullable', 'boolean'],
            'verified_only' => ['nullable', 'boolean'],
            'role_ids'      => ['nullable', 'array'],
            'role_ids.*'    => ['integer', 'exists:roles,id'],

            // Mailing list
            'match_locale' => ['nullable', 'boolean'],
            'list_ids'     => ['nullable', 'array'],
            'list_ids.*'   => ['integer', 'exists:subscriber_lists,id'],

            // Excel / CSV
            'recipient_file' => ['nullable', 'file', 'mimes:xlsx,xls,ods,csv,txt', 'max:10240'],

            // Typed by hand — satır satır alanlar
            'manual_rows'                => ['nullable', 'array', 'max:5000'],
            'manual_rows.*.email'        => ['nullable', 'string', 'max:191'],
            'manual_rows.*.first_name'   => ['nullable', 'string', 'max:100'],
            'manual_rows.*.last_name'    => ['nullable', 'string', 'max:100'],

            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manual_rows.max'      => 'Elle en fazla 5000 alıcı girebilirsiniz, daha uzun listeler için Excel yükleyin.',
            'recipient_file.mimes' => 'Yalnızca Excel (.xlsx, .xls, .ods) veya CSV dosyası yükleyebilirsiniz.',
            'recipient_file.max'   => 'Alıcı dosyası en fazla 10 MB olabilir.',
            'attachments.max'      => 'Bir kampanyaya en fazla 10 ek ekleyebilirsiniz.',
            'attachments.*.max'    => 'Her ek en fazla 10 MB olabilir.',
        ];
    }

    /**
     * The chosen audience decides what else is required — a file upload matters
     * only for an import, a textarea only for a hand-typed list.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $audience = CampaignAudience::tryFrom((string) $this->input('audience'));

            if ($audience === CampaignAudience::Import) {
                $this->validateImport($validator);
            }

            if ($audience === CampaignAudience::Manual) {
                $this->validateManual($validator);
            }
        });
    }

    private function validateImport(Validator $validator): void
    {
        $file = $this->file('recipient_file');

        if ($file === null) {
            // Editing without re-uploading keeps the list already stored.
            if ($this->route('campaign')?->audience_filter['recipients'] ?? null) {
                return;
            }

            $validator->errors()->add('recipient_file', 'Alıcı listesi için bir Excel veya CSV dosyası yükleyin.');

            return;
        }

        try {
            $result = app(RecipientImportService::class)->parse($file);
            $this->resolvedRecipients = $result['rows'];
        } catch (RuntimeException $e) {
            $validator->errors()->add('recipient_file', $e->getMessage());
        }
    }

    private function validateManual(Validator $validator): void
    {
        $submitted = $this->input('manual_rows', []);
        $rows = app(CampaignService::class)->parseManualRows(is_array($submitted) ? $submitted : []);

        if ($rows === []) {
            // Hangi satırın bozuk olduğunu söylemek, "geçerli alıcı yok"
            // demekten daha işe yarar: kullanıcı yazdığı adrese bakabilsin.
            $firstBad = null;

            foreach (is_array($submitted) ? $submitted : [] as $index => $row) {
                $email = trim((string) ($row['email'] ?? ''));

                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $firstBad = ($index + 1) . '. satırdaki "' . $email . '" geçerli bir e-posta adresi değil.';

                    break;
                }
            }

            $validator->errors()->add(
                'manual_rows',
                $firstBad ?? 'En az bir alıcı girin: e-posta alanı zorunlu, ad ve soyad isteğe bağlı.',
            );

            return;
        }

        $this->resolvedRecipients = $rows;
    }

    /**
     * @return array<int, array{name: ?string, email: string}>
     */
    public function recipients(): array
    {
        return $this->resolvedRecipients;
    }

    /**
     * The shape CampaignService expects.
     *
     * @return array<string, mixed>
     */
    public function campaignData(): array
    {
        $audience = CampaignAudience::from((string) $this->input('audience'));

        $filter = match ($audience) {
            CampaignAudience::Users => [
                'active_only'   => $this->boolean('active_only'),
                'verified_only' => $this->boolean('verified_only'),
                'role_ids'      => array_map('intval', $this->input('role_ids', [])),
            ],
            CampaignAudience::Subscribers => [
                'match_locale' => $this->boolean('match_locale'),
                // Boş bırakılırsa liste ayrımı yapılmaz: tüm aboneler.
                'list_ids'     => array_map('intval', $this->input('list_ids', [])),
            ],
            CampaignAudience::Import, CampaignAudience::Manual => [
                'recipients' => $this->recipients(),
            ],
        };

        return [
            'name'            => $this->string('name')->toString(),
            'subject'         => $this->string('subject')->toString(),
            'body'            => (string) $this->input('body'),
            'from_name'       => $this->input('from_name'),
            'from_email'      => $this->input('from_email'),
            'reply_to'        => $this->input('reply_to'),
            'locale'          => $this->input('locale'),
            'audience'        => $audience,
            'audience_filter' => $filter,
            // Yayarak gönderim kullanıcı tercihi değil: listeyi tek seferde
            // boşaltmak gönderen hesabı kısıtlatır ya da kara listeye düşürür.
            // Hız ayarı panelin mail ayarlarında, kampanya başına değil.
            'throttled'       => true,
            'attachments'     => $this->file('attachments', []),
        ];
    }
}
