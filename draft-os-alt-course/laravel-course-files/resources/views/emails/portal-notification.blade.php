<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $headline ?? 'Уведомление' }}</title>
</head>
@php
    $portalName = $portalName ?? 'practice.croc.ru';
    $headline = $headline ?? 'Уведомление портала';
    $eyebrow = $eyebrow ?? 'Учебный портал КРОК';
    $ctaLabel = $ctaLabel ?? 'Открыть';
    $brand = '#00A84D';
    $dark = '#0B1210';
    $page = '#EEF1F3';
    /** @var array<string, string> $img */
    $img = $img ?? \App\Services\Mail\PortalMailAssets::cidMap();
    $i = static fn (string $file) => $img[$file] ?? ('cid:'.pathinfo($file, PATHINFO_FILENAME));
@endphp
<body style="margin:0;padding:0;background:{{ $page }};font-family:Arial,Helvetica,sans-serif;color:#111827;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:{{ $page }};width:100%;font-family:Arial,Helvetica,sans-serif;">
    <tr>
        <td align="center" style="padding:32px 12px;font-family:Arial,Helvetica,sans-serif;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">

                <tr>
                    <td style="padding:0;font-size:0;line-height:0;">
                        <img src="{{ $i('cap-top.png') }}" width="600" height="28" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;outline:none;">
                    </td>
                </tr>
                <tr>
                    <td bgcolor="{{ $dark }}" style="background-color:{{ $dark }};padding:10px 32px 8px;font-family:Arial,Helvetica,sans-serif;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="vertical-align:middle;font-family:Arial,Helvetica,sans-serif;">
                                    <p style="margin:0 0 10px;font-size:11px;line-height:14px;letter-spacing:0.14em;text-transform:uppercase;color:#7DFFB0;font-weight:700;font-family:Arial,Helvetica,sans-serif;">{{ $eyebrow }}</p>
                                    <p style="margin:0 0 10px;font-size:28px;line-height:34px;color:#FFFFFF;font-weight:700;font-family:Arial,Helvetica,sans-serif;">{{ $headline }}</p>
                                    <p style="margin:0;font-size:14px;line-height:20px;color:#B7CFC2;font-family:Arial,Helvetica,sans-serif;">{{ $portalName }}</p>
                                </td>
                                <td width="60" align="right" valign="middle" style="padding-left:16px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                        <tr>
                                            <td width="52" height="52" align="center" bgcolor="#123524" style="width:52px;height:52px;background-color:#123524;border:1px solid #4ADE80;color:#7DFFB0;font-size:22px;font-weight:700;line-height:52px;">✓</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0;font-size:0;line-height:0;">
                        <img src="{{ $i('cap-bottom.png') }}" width="600" height="28" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                    </td>
                </tr>

                <tr><td style="height:18px;line-height:18px;font-size:18px;">&nbsp;</td></tr>

                <tr>
                    <td style="padding:0;font-size:0;line-height:0;">
                        <img src="{{ $i('card-cap-top.png') }}" width="600" height="20" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#FFFFFF" style="background-color:#FFFFFF;padding:12px 32px 20px;font-family:Arial,Helvetica,sans-serif;">
                        <p style="margin:0 0 10px;font-size:18px;line-height:26px;color:#111827;font-weight:700;font-family:Arial,Helvetica,sans-serif;">{{ $greeting }}</p>
                        <p style="margin:0 0 22px;font-size:15px;line-height:24px;color:#4B5563;font-family:Arial,Helvetica,sans-serif;">{{ $lead }}</p>

                        @if (!empty($details))
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td width="4" bgcolor="{{ $brand }}" style="background-color:{{ $brand }};width:4px;font-size:0;line-height:0;">&nbsp;</td>
                                    <td bgcolor="#F3FAF5" style="background-color:#F3FAF5;padding:4px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="font-family:Arial,Helvetica,sans-serif;">
                                            @foreach ($details as $label => $value)
                                                @php $isLast = $loop->last; @endphp
                                                <tr>
                                                    <td width="28%" style="padding:12px 16px;font-size:13px;line-height:18px;color:#6B7F72;vertical-align:top;font-family:Arial,Helvetica,sans-serif;{{ $isLast ? '' : 'border-bottom:1px solid #E3F0E8;' }}">{{ $label }}</td>
                                                    <td style="padding:12px 16px;font-size:15px;line-height:22px;font-weight:700;color:{{ $brand }};vertical-align:top;font-family:Arial,Helvetica,sans-serif;{{ $isLast ? '' : 'border-bottom:1px solid #E3F0E8;' }}">{{ $value }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        {{-- Outlook often ignores CSS margins: explicit spacer before CTA --}}
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td height="28" style="height:28px;line-height:28px;font-size:28px;">&nbsp;</td>
                            </tr>
                        </table>

                        @if (!empty($ctaUrl))
                            @php
                                $ctaButton = $ctaButton ?? null;
                                $ctaFile = is_array($ctaButton) ? (string) ($ctaButton['file'] ?? '') : '';
                                $ctaSrc = $ctaFile !== ''
                                    ? ($img[$ctaFile] ?? ('cid:'.pathinfo($ctaFile, PATHINFO_FILENAME)))
                                    : (is_array($ctaButton) ? ('cid:'.($ctaButton['cid'] ?? 'cta-open')) : null);
                                $ctaW = is_array($ctaButton) ? (int) ($ctaButton['width'] ?? 220) : 220;
                                $ctaH = is_array($ctaButton) ? (int) ($ctaButton['height'] ?? 48) : 48;
                            @endphp
                            @if ($ctaSrc)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:0;font-size:0;line-height:0;">
                                            <a href="{{ $ctaUrl }}" style="text-decoration:none;border:0;outline:none;">
                                                <img src="{{ $ctaSrc }}" width="{{ $ctaW }}" height="{{ $ctaH }}" alt="{{ $ctaLabel }}" style="display:block;border:0;outline:none;text-decoration:none;">
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td height="14" style="height:14px;line-height:14px;font-size:14px;">&nbsp;</td>
                                </tr>
                            </table>
                            <p style="margin:0;font-size:12px;line-height:18px;color:#94A3B8;word-break:break-all;font-family:Arial,Helvetica,sans-serif;">
                                Если кнопка не открывается:
                                <a href="{{ $ctaUrl }}" style="color:{{ $brand }};text-decoration:underline;">{{ $ctaUrl }}</a>
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:0;font-size:0;line-height:0;">
                        <img src="{{ $i('card-cap-bottom.png') }}" width="600" height="20" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                    </td>
                </tr>

                <tr><td style="height:18px;line-height:18px;font-size:18px;">&nbsp;</td></tr>

                <tr>
                    <td style="padding:0;font-size:0;line-height:0;">
                        <img src="{{ $i('foot-cap-top.png') }}" width="600" height="20" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                    </td>
                </tr>
                <tr>
                    <td bgcolor="{{ $dark }}" style="background-color:{{ $dark }};padding:6px 28px 8px;font-family:Arial,Helvetica,sans-serif;">
                        <p style="margin:0 0 6px;font-size:15px;line-height:22px;color:#FFFFFF;font-weight:700;font-family:Arial,Helvetica,sans-serif;">
                            practice.croc.ru, с заботой о тебе ♥
                        </p>
                        <p style="margin:0;font-size:12px;line-height:18px;color:#9BB5A6;font-family:Arial,Helvetica,sans-serif;">
                            Письмо отправлено автоматически. Отвечать на него не нужно.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0;font-size:0;line-height:0;">
                        <img src="{{ $i('foot-cap-bottom.png') }}" width="600" height="20" alt="" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
