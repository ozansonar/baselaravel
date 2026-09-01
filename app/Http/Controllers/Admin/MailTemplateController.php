<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailTemplateUpdateRequest;
use App\Models\MailTemplate;
use App\Services\LanguageService;
use App\Services\MailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MailTemplateController extends Controller
{
    public function __construct(
        private readonly MailTemplateService $mailTemplateService,
        private readonly LanguageService $languages,
    ) {}

    /**
     * Kart listesinin sıralama seçenekleri; istekten gelen değer bu kümeyle
     * sınırlı, tanınmayan bir değer ada göre sıralamaya düşer.
     */
    private const SORT_OPTIONS = [
        'name'   => 'Ada göre (A-Z)',
        'key'    => 'Anahtara göre',
        'recent' => 'Son güncellenen',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MailTemplate::class);

        $sort = $request->string('sort')->value();
        $sort = array_key_exists($sort, self::SORT_OPTIONS) ? $sort : '';

        // Her şablonun her dilde bir satırı var. Süzgeç boş bırakılırsa aynı
        // ad beş kez listelenir; tanınmayan bir kod da boş liste demek. Bu
        // yüzden ekran her zaman bir dile bakıyor, varsayılan olarak sitenin
        // kendi diline.
        $languages = $this->languages->all();
        $locale = $request->string('locale')->value();
        $locale = $languages->contains('code', $locale) ? $locale : $this->languages->defaultCode();

        $filters = [
            'locale'   => $locale,
            'status'   => $request->string('status')->value(),
            'search'   => $request->string('search')->trim()->value(),
            'variable' => $request->string('variable')->value(),
            'origin'   => $request->string('origin')->value(),
            'sort'     => $sort,
        ];

        // Sekme sayıları durum dışındaki süzgeçlere göre: "Pasif 1" yazıyorsa
        // o sekmeye basınca gerçekten 1 kart gelmeli.
        $scoped = $filters;
        unset($scoped['status']);
        $scopedTemplates = $this->mailTemplateService->filter($scoped);

        $templates = $this->mailTemplateService->filter($filters);

        return view('admin.mail-templates.index', [
            'templates'       => $templates,
            // İçeriğin varsayılandan farklı olup olmadığı karta yazılıyor;
            // görünüm bunu hesaplamasın diye burada tek seferde çıkarılıyor.
            'origins'         => $templates->mapWithKeys(fn (MailTemplate $template): array => [
                $template->id => [
                    'has_default' => $this->mailTemplateService->hasDefault($template),
                    'customized'  => $this->mailTemplateService->isCustomized($template),
                ],
            ])->all(),
            'stats'           => $this->mailTemplateService->stats($locale),
            'statusCounts'    => [
                'all'      => $scopedTemplates->count(),
                'active'   => $scopedTemplates->where('is_active', true)->count(),
                'inactive' => $scopedTemplates->where('is_active', false)->count(),
            ],
            'variableOptions' => $this->mailTemplateService->variableOptions($locale),
            'sortOptions'     => self::SORT_OPTIONS,
            'languages'       => $languages,
            'filters'         => $filters,
        ]);
    }

    public function edit(MailTemplate $mailTemplate): View
    {
        $this->authorize('update', MailTemplate::class);

        return view('admin.mail-templates.edit', [
            'template' => $mailTemplate,
            // Aynı şablonun öteki dillerdeki satırları: yönetici bir metni
            // çevirirken sekmeyle karşılığına geçebilsin.
            'siblings' => $this->mailTemplateService->siblings($mailTemplate),
            'language' => $this->languages->all()->firstWhere('code', $mailTemplate->locale),
        ]);
    }

    public function update(MailTemplateUpdateRequest $request, MailTemplate $mailTemplate): RedirectResponse
    {
        $this->authorize('update', MailTemplate::class);

        $this->mailTemplateService->update($mailTemplate, [
            'subject'   => $request->validated('subject'),
            'body'      => $request->validated('body'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.mail-templates.edit', $mailTemplate)
            ->with('success', 'Mail şablonu başarıyla güncellendi.');
    }

    public function reset(MailTemplate $mailTemplate): RedirectResponse
    {
        $this->authorize('update', MailTemplate::class);

        $this->mailTemplateService->resetToDefault($mailTemplate);

        return redirect()
            ->route('admin.mail-templates.edit', $mailTemplate)
            ->with('success', 'Mail şablonu varsayılan içeriğe sıfırlandı.');
    }

    public function preview(Request $request, MailTemplate $mailTemplate): JsonResponse
    {
        $this->authorize('update', MailTemplate::class);

        $subject = $request->input('subject', $mailTemplate->subject);
        $body = $request->input('body', $mailTemplate->body);

        $variables = $mailTemplate->variables ?? [];
        $replacements = [];
        foreach ($variables as $variable) {
            $replacements['{' . $variable['key'] . '}'] = $variable['example'] ?? $variable['key'];
        }

        $renderedSubject = strtr($subject, $replacements);
        $renderedBody = strtr($body, $replacements);

        $html = view('emails.layout-preview', [
            'emailBody'        => $renderedBody,
            'siteName'         => $replacements['{site_name}'] ?? config('app.name'),
            'siteUrl'          => config('app.url'),
            'siteTagline'      => \App\Models\Setting::getValue('site_description', __('site.misc.site_description')),
            'currentYear'      => date('Y'),
            'themePrimary'     => \App\Models\Setting::getValue('mail_theme_primary_color', '#4f46e5'),
            'themePrimaryDark' => \App\Models\Setting::getValue('mail_theme_primary_dark_color', '#4338ca'),
            'themeBg'          => \App\Models\Setting::getValue('mail_theme_bg_color', '#f8fafc'),
            'themeCardBg'      => \App\Models\Setting::getValue('mail_theme_card_bg_color', '#ffffff'),
            'themeText'        => \App\Models\Setting::getValue('mail_theme_text_color', '#334155'),
            'themeMuted'       => \App\Models\Setting::getValue('mail_theme_muted_color', '#64748b'),
            'themeFooterText'  => \App\Models\Setting::getValue('mail_theme_footer_text', __('mail.footer_text')),
            'themeSocialLinks' => false,
            'mailLogoUrl'      => null,
        ])->render();

        return response()->json([
            'subject' => $renderedSubject,
            'html'    => $html,
        ]);
    }
}
