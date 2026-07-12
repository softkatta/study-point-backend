<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title }}</title>
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
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse !important; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; display: block; }
        a { color: inherit; text-decoration: none; }
        .email-container { width: 100%; max-width: 600px; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .stack { display: block !important; width: 100% !important; max-width: 100% !important; }
            .center-mobile { text-align: center !important; }
            .pad-mobile { padding-left: 16px !important; padding-right: 16px !important; }
            .cta-btn { display: block !important; width: 100% !important; }
            .footer-table td { display: block !important; width: 100% !important; text-align: center !important; }
            .footer-table td:last-child { padding-top: 12px !important; }
        }
    </style>
    @if(!empty($preheader))
        <div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{ $preheader }}&zwnj;&zwnj;&zwnj;</div>
    @endif
</head>
<body style="margin:0;padding:0;width:100%;background-color:{{ $backgroundColor }};font-family:'Segoe UI',Roboto,Arial,Helvetica,sans-serif;color:#1e293b;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:{{ $backgroundColor }};min-width:100%;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" class="email-container" style="background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 20px 50px rgba(15,23,42,0.12);">
                <tr>
                    <td style="background:{{ $primaryColor }};height:6px;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="padding:28px 32px 20px 32px;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td valign="middle" style="padding-bottom:16px;">
                                    <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            @if(!empty($logoUrl))
                                                <td style="padding-right:14px;vertical-align:middle;"><img src="{{ $logoUrl }}" alt="{{ $siteName }}" width="56" height="56" style="display:block;width:56px;height:56px;border-radius:14px;object-fit:cover;"></td>
                                            @else
                                                <td style="padding-right:14px;vertical-align:middle;"><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="56" height="56" style="border-radius:16px;background:{{ $accentColor }};"><tr><td align="center" valign="middle" style="font-size:20px;font-weight:800;color:#ffffff;">{{ $initials }}</td></tr></table></td>
                                            @endif
                                            <td valign="middle">
                                                <div style="font-size:22px;font-weight:800;color:#0f172a;line-height:1.2;">{{ $siteName }}</div>
                                                <div style="font-size:12px;color:#64748b;margin-top:4px;text-transform:uppercase;letter-spacing:0.08em;">{{ $siteTagline }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td valign="middle" align="right" style="font-size:12px;color:#64748b;">
                                    @if(!empty($viewOnlineUrl))
                                        <a href="{{ $viewOnlineUrl }}" style="color:#64748b;">View online &rarr;</a>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 24px 32px;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td align="center" style="padding-bottom:22px;">
                                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="68" height="68" style="border-radius:50%;background:{{ $badgeBackground }};">
                                        <tr><td align="center" valign="middle" style="font-size:28px;">&#127891;</td></tr>
                                    </table>
                                </td>
                            </tr>
                            @if(!empty($eyebrow))
                                <tr>
                                    <td align="center" style="padding-bottom:12px;">
                                        <span style="display:inline-block;padding:8px 16px;border-radius:999px;background:#f1f5f9;color:{{ $primaryColor }};font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.12em;">{{ $eyebrow }}</span>
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td align="center">
                                    <h1 style="margin:0 0 18px 0;font-size:32px;line-height:1.1;font-weight:900;color:#0f172a;">{{ $title }}</h1>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 28px 32px;">
                        @foreach($paragraphs as $paragraph)
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.75;color:#475569;">{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                        @if(!empty($details))
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc;overflow:hidden;margin-top:20px;">
                                @foreach($details as $row)
                                    <tr>
                                        <td style="padding:16px 18px;border-bottom:1px solid #e2e8f0;">
                                            <div style="font-size:12px;color:#64748b;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:6px;">{{ $row['label'] ?? '' }}</div>
                                            <div style="font-size:15px;color:#0f172a;font-weight:700;line-height:1.5;">{{ $row['value'] ?? '' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </td>
                </tr>
                @if(!empty($ctaLabel) && !empty($ctaUrl))
                    <tr>
                        <td style="padding:0 32px 32px 32px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-radius:16px;background:#f8fafc;">
                                <tr>
                                    <td align="center" style="padding:22px 22px 12px 22px;">
                                        <a href="{{ $ctaUrl }}" class="cta-btn" style="display:inline-block;background:{{ $primaryColor }};color:#ffffff;font-size:15px;font-weight:800;padding:15px 32px;border-radius:999px;">{{ $ctaLabel }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:0 22px 18px 22px;font-size:12px;color:#64748b;letter-spacing:0.04em;text-transform:uppercase;font-weight:700;">Share our message</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:0 32px 28px 32px;border-top:1px solid #eef2f7;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="footer-table">
                            <tr>
                                <td valign="top" style="width:50%;padding-right:12px;">
                                    <div style="font-size:15px;font-weight:800;color:#0f172a;margin-bottom:6px;">{{ $signatureName }}</div>
                                    <div style="font-size:13px;color:#64748b;line-height:1.6;">{{ $signatureRole }}</div>
                                </td>
                                <td valign="top" style="width:50%;text-align:right;">
                                    @if(!empty($company['email']))
                                        <div style="font-size:13px;color:#475569;line-height:1.6;margin-bottom:8px;"><a href="mailto:{{ $company['email'] }}" style="color:#475569;">{{ $company['email'] }}</a></div>
                                    @endif
                                    @if(!empty($company['website']))
                                        <div style="font-size:13px;color:#475569;line-height:1.6;margin-bottom:8px;"><a href="{{ $company['website'] }}" style="color:#475569;">{{ preg_replace('#^https?://#', '', $company['website']) }}</a></div>
                                    @endif
                                    @if(!empty($company['address']))
                                        <div style="font-size:13px;color:#475569;line-height:1.6;">{{ $company['address'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" class="email-container" style="width:100%;max-width:600px;margin-top:18px;">
                <tr>
                    <td align="center" style="padding:20px 20px 32px 20px;font-size:13px;color:#64748b;line-height:1.7;">
                        <p style="margin:0 0 10px 0;">Need help? <a href="mailto:{{ $supportEmail }}" style="color:{{ $primaryColor }};text-decoration:underline;font-weight:600;">Contact us</a> anytime.</p>
                        <p style="margin:0;font-size:12px;color:#94a3b8;">&copy; {{ $year }} {{ $siteName }}. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
