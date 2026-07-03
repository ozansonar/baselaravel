<!DOCTYPE html>
<html lang="tr" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* === RESET === */
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0; mso-table-rspace: 0; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }

        /* === BASE === */
        .em-body {
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: {{ $themeBg }};
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .em-wrapper { width: 100%; background-color: {{ $themeBg }}; }
        .em-wrapper-td { padding: 40px 16px; }
        .em-container { width: 100%; max-width: 600px; margin: 0 auto; }

        /* === HEADER === */
        .em-header {
            background: linear-gradient(135deg, {{ $themePrimaryDark }} 0%, {{ $themePrimary }} 50%, {{ $themePrimaryDark }} 100%);
            border-radius: 16px 16px 0 0;
        }
        .em-header-td { padding: 32px 40px; text-align: center; }
        .em-logo-img { max-height: 120px; max-width: 200px; width: auto; height: auto; }
        .em-logo-text {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .em-logo-sub {
            font-size: 12px;
            color: #c5e1a5;
            letter-spacing: 2px;
            text-transform: uppercase;
            display: block;
            margin-top: 4px;
        }

        /* === ACCENT BAR === */
        .em-accent { height: 4px; background: linear-gradient(90deg, #d4a84b, {{ $themePrimary }}, #d4a84b); }

        /* === CONTENT === */
        .em-content { background-color: {{ $themeCardBg }}; }
        .em-content-td { padding: 40px; }

        /* === GREETING === */
        .em-greeting {
            font-size: 14px;
            font-weight: 600;
            color: {{ $themePrimary }};
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0 0 8px 0;
        }

        /* === TYPOGRAPHY === */
        .em-heading {
            font-size: 24px;
            font-weight: 800;
            color: {{ $themeText }};
            margin: 0 0 20px 0;
            line-height: 1.3;
        }
        .em-heading-sm {
            font-size: 18px;
            font-weight: 700;
            color: {{ $themePrimary }};
            margin: 24px 0 12px 0;
            line-height: 1.3;
        }
        .em-text {
            font-size: 15px;
            line-height: 1.7;
            color: {{ $themeText }};
            margin: 0 0 16px 0;
        }
        .em-text-sm {
            font-size: 13px;
            line-height: 1.6;
            color: {{ $themeMuted }};
            margin: 0 0 8px 0;
        }
        .em-text-bold { font-weight: 700; color: {{ $themeText }}; }

        /* === BUTTON === */
        .em-btn-wrap { text-align: center; padding: 24px 0; }
        .em-btn {
            display: inline-block;
            padding: 14px 36px;
            background: linear-gradient(135deg, {{ $themePrimary }}, {{ $themePrimaryDark }});
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.3px;
            mso-padding-alt: 14px 36px;
        }
        .em-btn-secondary {
            display: inline-block;
            padding: 12px 28px;
            background-color: #f5f5f5;
            color: {{ $themePrimaryDark }} !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: 2px solid #e0e0e0;
        }

        /* === INFO BOX === */
        .em-info-box { border-radius: 12px; background-color: #f7faf5; border: 1px solid #e0edda; }
        .em-info-box-td { padding: 20px 24px; }
        .em-info-row { margin: 6px 0; font-size: 14px; color: #333; line-height: 1.6; }
        .em-info-label { font-weight: 600; color: {{ $themePrimaryDark }}; }

        /* === HIGHLIGHT BOX === */
        .em-highlight { border-radius: 12px; background-color: #fff8e1; border: 1px solid #ffe082; }
        .em-highlight-td { padding: 20px 24px; text-align: center; }

        /* === TABLE === */
        .em-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .em-table th {
            background-color: #f7faf5;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: {{ $themePrimaryDark }};
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid {{ $themePrimary }};
        }
        .em-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: {{ $themeText }};
            border-bottom: 1px solid #f0f0f0;
        }
        .em-table-total td {
            font-weight: 700;
            color: {{ $themeText }};
            font-size: 15px;
            border-top: 2px solid {{ $themePrimary }};
            border-bottom: none;
            padding-top: 16px;
        }

        /* === BADGE === */
        .em-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .em-badge-warning { background-color: #fff3cd; color: #856404; }
        .em-badge-info { background-color: #cff4fc; color: #055160; }
        .em-badge-primary { background-color: #cfe2ff; color: #084298; }
        .em-badge-success { background-color: #d1e7dd; color: #0f5132; }
        .em-badge-danger { background-color: #f8d7da; color: #842029; }

        /* === DIVIDER === */
        .em-divider { border: none; border-top: 1px solid #e8e8e8; margin: 28px 0; }

        /* === FEATURE LIST === */
        .em-feature-td { padding: 10px 0; }
        .em-feature-icon-td {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background-color: #f7faf5;
            text-align: center;
            font-size: 20px;
            vertical-align: middle;
        }
        .em-feature-text-td {
            padding-left: 16px;
            font-size: 14px;
            color: {{ $themeText }};
            line-height: 1.5;
            vertical-align: middle;
        }

        /* === FOOTER === */
        .em-footer {
            background-color: {{ $themePrimaryDark }};
            border-radius: 0 0 16px 16px;
        }
        .em-footer-td { padding: 32px 40px; text-align: center; }
        .em-footer-text { font-size: 13px; color: #a5d6a7; margin: 4px 0; line-height: 1.6; }
        .em-footer-link { color: #c5e1a5; text-decoration: underline; }
        .em-footer-copy { font-size: 11px; color: #81c784; margin: 16px 0 0 0; }
        .em-footer-divider { border: none; border-top: 1px solid rgba(255,255,255,0.15); margin: 16px 0; }
        .em-social-icons { text-align: center; margin: 12px 0; }
        .em-social-icon {
            display: inline-block;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.15);
            text-align: center;
            line-height: 32px;
            margin: 0 4px;
            font-size: 14px;
            color: #ffffff;
            text-decoration: none;
        }

        /* === RESPONSIVE === */
        @media only screen and (max-width: 620px) {
            .em-wrapper-td { padding: 16px 8px !important; }
            .em-header-td { padding: 24px 20px !important; }
            .em-content-td { padding: 28px 20px !important; }
            .em-footer-td { padding: 24px 20px !important; }
            .em-heading { font-size: 20px !important; }
            .em-heading-sm { font-size: 16px !important; }
            .em-btn { padding: 12px 28px !important; font-size: 14px !important; }
            .em-table th, .em-table td { padding: 8px 10px !important; font-size: 13px !important; }
            .em-info-box-td { padding: 16px !important; }
        }
    </style>
</head>
<body class="em-body">
    <table class="em-wrapper" role="presentation" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="em-wrapper-td" align="center">

                {{-- Header --}}
                <table class="em-container em-header" role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-header-td">
                            @if(!empty($mailLogoUrl))
                                <img src="{{ $mailLogoUrl }}" alt="{{ $siteName }}" class="em-logo-img" style="max-height:120px;max-width:200px;width:auto;height:auto;">
                            @else
                                <span class="em-logo-text">&#127807; {{ $siteName }}</span>
                            @endif
                            <span class="em-logo-sub">Do&#287;al&#305;n En Tazesi</span>
                        </td>
                    </tr>
                </table>

                {{-- Accent Bar --}}
                <table class="em-container" role="presentation" cellpadding="0" cellspacing="0">
                    <tr><td class="em-accent"></td></tr>
                </table>

                {{-- Content --}}
                <table class="em-container em-content" role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-content-td">
                            @yield('content')
                        </td>
                    </tr>
                </table>

                {{-- Footer --}}
                <table class="em-container em-footer" role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="em-footer-td">
                            <p style="font-size:15px;font-weight:700;color:#ffffff;margin:0 0 4px;line-height:1.6;">{{ $siteName }}</p>
                            <p class="em-footer-text">{{ $themeFooterText }}</p>

                            @if($themeSocialLinks)
                            @php
                                $socialLinks = [
                                    'social_facebook'  => ['url' => \App\Models\Setting::getValue('social_facebook'), 'label' => 'Facebook',  'emoji' => '&#x1F1EB;'],
                                    'social_instagram' => ['url' => \App\Models\Setting::getValue('social_instagram'), 'label' => 'Instagram', 'emoji' => '&#x1F4F7;'],
                                    'social_twitter'   => ['url' => \App\Models\Setting::getValue('social_twitter'), 'label' => 'X',           'emoji' => '&#x2715;'],
                                    'social_youtube'   => ['url' => \App\Models\Setting::getValue('social_youtube'), 'label' => 'YouTube',     'emoji' => '&#x25B6;'],
                                ];
                            @endphp
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:16px auto 0;">
                                <tr>
                                    @foreach($socialLinks as $social)
                                        @if(!empty($social['url']))
                                        <td style="padding:0 4px;" align="center" valign="middle">
                                            <a href="{{ $social['url'] }}" target="_blank" title="{{ $social['label'] }}" style="display:block;width:36px;height:36px;border-radius:50%;background-color:rgba(255,255,255,0.18);text-align:center;line-height:36px;text-decoration:none;color:#ffffff;font-size:13px;font-weight:700;font-family:Arial,sans-serif;">{{ mb_substr($social['label'], 0, 1) }}</a>
                                        </td>
                                        @endif
                                    @endforeach
                                </tr>
                            </table>
                            @endif

                            <hr class="em-footer-divider">
                            <p class="em-footer-copy">&copy; {{ $currentYear }} {{ $siteName }}. T&uuml;m haklar&#305; sakl&#305;d&#305;r.</p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
