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
            'throttled'  => ['nullable', 'boolean'],

            // Site members
            'active_only'   => ['nullable', 'boolean'],
            'verified_only' => ['nullable', 'boolean'],
            'role_ids'      => ['nullable', 'array'],
            'role_ids.*'    => ['integer', 'exists:roles,id'],

            // Mailing list
            'match_locale' => ['nullable', 'boolean'],

            // Excel / CSV
            'recipient_file' => ['nullable', 'file', 'mimes:xlsx,xls,ods,csv,txt', 'max:10240'],

            // Typed by hand
            'manual_recipients' => ['nullable', 'string', 'max:200000'],

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
        $rows = app(CampaignService::class)->parseManualList($this->input('manual_recipients'));

        if ($rows === []) {
            $validator->errors()->add(
                'manual_recipients',
                'Geçerli bir alıcı bulunamadı. Her satıra "Ad Soyad <mail@ornek.com>" yazın.',
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
            'throttled'       => $this->boolean('throttled', true),
            'attachments'     => $this->file('attachments', []),
        ];
    }
}
