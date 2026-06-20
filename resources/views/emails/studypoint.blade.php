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
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .stack { display: block !important; width: 100% !important; max-width: 100% !important; }
            .stack-pad { padding-left: 0 !important; padding-right: 0 !important; padding-top: 16px !important; }
            .center-mobile { text-align: center !important; }
            .hide-mobile { display: none !important; }
            .cta-btn { display: block !important; width: 100% !important; text-align: center !important; box-sizing: border-box !important; }
            .hero-title { font-size: 26px !important; line-height: 1.3 !important; }
            .signature-photo { margin-bottom: 12px !important; }
        }
    </style>
    @if(!empty($preheader))
        <div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">{{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
    @endif
</head>
<body style="margin:0;padding:0;width:100%;background-color:{{ $backgroundColor }};font-family:'Segoe UI',Roboto,Arial,Helvetica,sans-serif;color:#1e293b;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $backgroundColor }};margin:0;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" class="email-container" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.18);">
                {{-- Accent top bar --}}
                <tr>
                    <td height="6" style="background:{{ $primaryColor }};font-size:0;line-height:0;">&nbsp;</td>
                </tr>

                {{-- Header --}}
                <tr>
                    <td style="padding:28px 32px 10px 32px;background-color:#ffffff;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td class="stack center-mobile" align="left" valign="middle" style="width:65%;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                        <tr>
                                            @if(!empty($logoUrl))
                                                <td valign="middle" style="padding-right:14px;">
                                                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="56" style="display:block;height:56px;max-height:56px;width:auto;max-width:220px;border:0;">
                                                </td>
                                            @else
                                                <td valign="middle" style="padding-right:12px;">
                                                    <div style="width:52px;height:52px;border-radius:12px;background:{{ $primaryColor }};color:#ffffff;font-size:18px;font-weight:700;line-height:52px;text-align:center;">{{ $initials }}</div>
                                                </td>
                                            @endif
                                            <td valign="middle">
                                                <div style="font-size:22px;font-weight:800;color:#0f172a;line-height:1.2;letter-spacing:-0.02em;">{{ $siteName }}</div>
                                                <div style="font-size:12px;color:#64748b;margin-top:4px;letter-spacing:0.06em;text-transform:uppercase;">{{ $siteTagline }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td class="stack stack-pad center-mobile" align="right" valign="middle" style="width:35%;">
                                    <a href="{{ $viewOnlineUrl }}" style="font-size:12px;color:#64748b;text-decoration:none;border-bottom:1px solid #cbd5e1;padding-bottom:2px;">View online &rarr;</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Hero icon --}}
                <tr>
                    <td align="center" style="padding:8px 32px 0 32px;">
                        <div style="width:64px;height:64px;border-radius:50%;background:{{ $badgeBackground }};border:1px solid #e2e8f0;line-height:64px;text-align:center;font-size:28px;">&#127758;</div>
                    </td>
                </tr>

                {{-- Eyebrow + Title --}}
                <tr>
                    <td style="padding:18px 32px 8px 32px;">
                        @if(!empty($eyebrow))
                            <div style="display:inline-block;margin:0 0 12px 0;padding:6px 14px;border-radius:999px;background-color:{{ $badgeBackground }};color:{{ $primaryColor }};font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">{{ $eyebrow }}</div>
                        @endif
                        <h1 class="hero-title" style="margin:0;font-size:32px;line-height:1.2;font-weight:800;color:#0f172a;letter-spacing:-0.03em;">{{ $title }}</h1>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:12px 32px 8px 32px;">
                        @foreach($paragraphs as $paragraph)
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.75;color:#475569;">{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach

                        @if(!empty($details))
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:12px 0 8px 0;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#f8fafc;">
                                @foreach($details as $index => $row)
                                    <tr>
                                        <td style="padding:0;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                                <tr>
                                                    <td width="6" style="background-color:{{ $accentColor }};font-size:0;line-height:0;">&nbsp;</td>
                                                    <td style="padding:14px 18px;@if($index < count($details) - 1) border-bottom:1px solid #e2e8f0; @endif">
                                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                                            <tr>
                                                                <td style="font-size:12px;color:#64748b;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;padding-bottom:4px;">{{ $row['label'] ?? '' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="font-size:15px;color:#0f172a;font-weight:700;line-height:1.4;">{{ $row['value'] ?? '' }}</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </td>
                </tr>

                {{-- Signature --}}
                <tr>
                    <td style="padding:20px 32px 24px 32px;border-top:1px solid #eef2f7;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td class="stack center-mobile" valign="top" style="width:33%;padding-right:8px;">
                                    <div style="font-size:16px;font-weight:800;color:#0f172a;letter-spacing:-0.02em;">{{ $signatureName }}</div>
                                    <div style="font-size:12px;color:#64748b;margin-top:6px;line-height:1.5;">{{ $signatureRole }}</div>
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:14px;">
                                        <tr>
                                            @if(!empty($company['social_facebook']))
                                                <td style="padding-right:8px;">
                                                    <a href="{{ $company['social_facebook'] }}" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#1877f2;color:#ffffff;font-size:11px;font-weight:700;line-height:28px;text-align:center;text-decoration:none;">f</a>
                                                </td>
                                            @endif
                                            @if(!empty($company['social_twitter']))
                                                <td style="padding-right:8px;">
                                                    <a href="{{ $company['social_twitter'] }}" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#1da1f2;color:#ffffff;font-size:11px;font-weight:700;line-height:28px;text-align:center;text-decoration:none;">t</a>
                                                </td>
                                            @endif
                                            @if(!empty($company['social_instagram']))
                                                <td style="padding-right:8px;">
                                                    <a href="{{ $company['social_instagram'] }}" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:#ffffff;font-size:11px;font-weight:700;line-height:28px;text-align:center;text-decoration:none;">i</a>
                                                </td>
                                            @endif
                                            @if(!empty($company['social_youtube']))
                                                <td>
                                                    <a href="{{ $company['social_youtube'] }}" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#ff0000;color:#ffffff;font-size:11px;font-weight:700;line-height:28px;text-align:center;text-decoration:none;">y</a>
                                                </td>
                                            @endif
                                        </tr>
                                    </table>
                                </td>
                                <td class="stack stack-pad center-mobile signature-photo" valign="top" align="center" style="width:34%;">
                                    @if(!empty($logoUrl))
                                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}" width="84" height="84" style="display:block;width:84px;height:84px;border-radius:50%;object-fit:cover;border:4px solid #ffffff;box-shadow:0 8px 24px rgba(15,23,42,0.12);margin:0 auto;">
                                    @else
                                        <div style="width:84px;height:84px;border-radius:50%;background:linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $accentColor }} 100%);color:#ffffff;font-size:28px;font-weight:800;line-height:84px;text-align:center;margin:0 auto;box-shadow:0 8px 24px rgba(15,23,42,0.12);">{{ $initials }}</div>
                                    @endif
                                </td>
                                <td class="stack stack-pad center-mobile" valign="top" align="right" style="width:33%;padding-left:8px;">
                                    @if(!empty($supportPhone))
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="right" class="center-mobile" style="margin:0 auto;">
                                            <tr>
                                                <td style="padding-bottom:10px;border-bottom:1px solid #eef2f7;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                        <tr>
                                                            <td valign="top" style="padding-right:8px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $accentColor }};margin-top:6px;"></span></td>
                                                            <td style="font-size:13px;color:#334155;line-height:1.5;">{{ $supportPhone }}</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    @endif
                                    @if(!empty($company['email']))
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="right" class="center-mobile" style="margin:6px auto 0 auto;">
                                            <tr>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f7;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                        <tr>
                                                            <td valign="top" style="padding-right:8px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $accentColor }};margin-top:6px;"></span></td>
                                                            <td style="font-size:13px;color:#334155;line-height:1.5;"><a href="mailto:{{ $company['email'] }}" style="color:#334155;text-decoration:none;">{{ $company['email'] }}</a></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    @endif
                                    @if(!empty($company['website']))
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="right" class="center-mobile" style="margin:6px auto 0 auto;">
                                            <tr>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f7;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                        <tr>
                                                            <td valign="top" style="padding-right:8px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $accentColor }};margin-top:6px;"></span></td>
                                                            <td style="font-size:13px;color:#334155;line-height:1.5;"><a href="{{ $company['website'] }}" style="color:#334155;text-decoration:none;">{{ preg_replace('#^https?://#', '', $company['website']) }}</a></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    @endif
                                    @if(!empty($company['address']))
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="right" class="center-mobile" style="margin:6px auto 0 auto;">
                                            <tr>
                                                <td style="padding-top:10px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                        <tr>
                                                            <td valign="top" style="padding-right:8px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $accentColor }};margin-top:6px;"></span></td>
                                                            <td style="font-size:13px;color:#334155;line-height:1.5;">{{ $company['address'] }}</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- CTA + Share --}}
                @if(!empty($ctaLabel) && !empty($ctaUrl))
                    <tr>
                        <td style="padding:0 32px 32px 32px;background-color:#fafbfc;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#ffffff;border:1px solid #e8edf3;border-radius:14px;padding:20px;">
                                <tr>
                                    <td class="stack center-mobile" align="left" valign="middle" style="width:50%;">
                                        <a href="{{ $ctaUrl }}" class="cta-btn" style="display:inline-block;background-color:{{ $primaryColor }};color:#ffffff;text-decoration:none;font-size:14px;font-weight:800;padding:14px 32px;border-radius:999px;letter-spacing:0.02em;">{{ $ctaLabel }}</a>
                                    </td>
                                    <td class="stack stack-pad center-mobile" align="right" valign="middle" style="width:50%;">
                                        <div style="font-size:12px;color:#64748b;margin-bottom:8px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Share our message</div>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="right" class="center-mobile" style="margin:0 auto;">
                                            <tr>
                                                @if(!empty($company['social_facebook']))
                                                    <td style="padding-right:6px;"><a href="{{ $company['social_facebook'] }}" style="display:inline-block;width:30px;height:30px;border-radius:50%;background:#eef2ff;color:{{ $primaryColor }};font-size:12px;font-weight:700;line-height:30px;text-align:center;text-decoration:none;">f</a></td>
                                                @endif
                                                @if(!empty($company['social_twitter']))
                                                    <td style="padding-right:6px;"><a href="{{ $company['social_twitter'] }}" style="display:inline-block;width:30px;height:30px;border-radius:50%;background:#eef2ff;color:{{ $primaryColor }};font-size:12px;font-weight:700;line-height:30px;text-align:center;text-decoration:none;">t</a></td>
                                                @endif
                                                @if(!empty($company['social_instagram']))
                                                    <td style="padding-right:6px;"><a href="{{ $company['social_instagram'] }}" style="display:inline-block;width:30px;height:30px;border-radius:50%;background:#eef2ff;color:{{ $primaryColor }};font-size:12px;font-weight:700;line-height:30px;text-align:center;text-decoration:none;">i</a></td>
                                                @endif
                                                @if(!empty($company['social_youtube']))
                                                    <td><a href="{{ $company['social_youtube'] }}" style="display:inline-block;width:30px;height:30px;border-radius:50%;background:#eef2ff;color:{{ $primaryColor }};font-size:12px;font-weight:700;line-height:30px;text-align:center;text-decoration:none;">y</a></td>
                                                @endif
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif
            </table>

            {{-- Footer --}}
            <table role="presentation" class="email-container" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;margin-top:20px;">
                <tr>
                    <td align="center" style="padding:8px 20px 24px 20px;font-size:13px;color:#64748b;line-height:1.7;">
                        <p style="margin:0 0 10px 0;">Not interested? No problem. <a href="mailto:{{ $supportEmail }}" style="color:{{ $primaryColor }};text-decoration:underline;font-weight:600;">Contact us here</a></p>
                        <p style="margin:0;font-size:12px;color:#94a3b8;">&copy; {{ $year }} {{ $siteName }}. All rights reserved.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
