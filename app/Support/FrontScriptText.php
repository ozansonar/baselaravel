<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The strings the front-end scripts print, in the visitor's language.
 *
 * A script tag cannot call __(), so every text a .js file writes on screen had
 * to be spelled out inside the file — and stayed Turkish on /en. The layout
 * publishes this map as window.SiteText before the scripts load; the files
 * themselves now carry no wording at all.
 *
 * Deliberately a hand-picked list rather than the whole site.php: that file
 * holds ~500 lines and shipping all of them on every page would cost far more
 * than the twenty the scripts actually read.
 *
 * Keys are camelCase because JavaScript reads them; the lang files stay
 * snake_case. Several entries point at strings the Blade side already uses —
 * "Gönderiliyor…" is one text with one place to edit, not two.
 */
final class FrontScriptText
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            // Shared with the Blade forms.
            'sending'           => __('site.misc.sending'),
            'errorGeneric'      => __('site.misc.error_generic'),
            'errorRetry'        => __('site.misc.error_retry'),
            'recaptchaRequired' => __('site.forms.recaptcha'),

            // Result modal.
            'titleSuccess' => __('site.js.title_success'),
            'titleError'   => __('site.js.title_error'),
            'titleWarning' => __('site.js.title_warning'),
            'titleInfo'    => __('site.js.title_info'),
            'failed'       => __('site.js.failed'),

            // Confirm modal.
            'confirmTitle' => __('site.actions.sure'),
            'confirmYes'   => __('site.actions.yes'),

            // Validation rules the engine's own language file does not carry.
            'onlyLetters'             => __('site.js.only_letters'),
            'imageType'               => __('site.js.image_type'),
            'imageSize'               => __('site.js.image_size'),
            'currentPasswordRequired' => __('site.js.current_password_required'),

            // Password show/hide button.
            'showPassword' => __('site.actions.show_password'),
            'hidePassword' => __('site.actions.hide_password'),

            // Dark/light toggle — the label names the action, not the state.
            'themeDark'  => __('site.theme.dark'),
            'themeLight' => __('site.theme.light'),

            // Attachment lightbox.
            'attachmentClose'    => __('site.attachments.close'),
            'attachmentPrev'     => __('site.attachments.prev'),
            'attachmentNext'     => __('site.attachments.next'),
            'attachmentDownload' => __('site.attachments.download'),
        ];
    }
}
