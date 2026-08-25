<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailTemplateUpdateRequest;
use App\Models\MailTemplate;
use App\Services\MailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MailTemplateController extends Controller
{
    public function __construct(
        private readonly MailTemplateService $mailTemplateService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MailTemplate::class);

        $templates = $this->mailTemplateService->getAll();

        return view('admin.mail-templates.index', [
            'templates' => $templates,
        ]);
    }

    public function edit(MailTemplate $mailTemplate): View
    {
        $this->authorize('update', MailTemplate::class);

        return view('admin.mail-templates.edit', [
            'template' => $mailTemplate,
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
            'currentYear'      => date('Y'),
            'themePrimary'     => \App\Models\Setting::getValue('mail_theme_primary_color', '#4f46e5'),
            'themePrimaryDark' => \App\Models\Setting::getValue('mail_theme_primary_dark_color', '#4338ca'),
            'themeBg'          => \App\Models\Setting::getValue('mail_theme_bg_color', '#f8fafc'),
            'themeCardBg'      => \App\Models\Setting::getValue('mail_theme_card_bg_color', '#ffffff'),
            'themeText'        => \App\Models\Setting::getValue('mail_theme_text_color', '#334155'),
            'themeMuted'       => \App\Models\Setting::getValue('mail_theme_muted_color', '#64748b'),
            'themeFooterText'  => \App\Models\Setting::getValue('mail_theme_footer_text', 'Sizinle çalışmaktan mutluluk duyuyoruz.'),
            'themeSocialLinks' => false,
            'mailLogoUrl'      => null,
        ])->render();

        return response()->json([
            'subject' => $renderedSubject,
            'html'    => $html,
        ]);
    }
}
