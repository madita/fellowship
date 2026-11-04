{{--
    The HTML of every mail this site writes to a member.

    Tables and inline styles on purpose: mail clients are not browsers,
    and a flex layout with a stylesheet is exactly what Outlook drops.
    Nothing here is clever, and that is the point - it should look the
    same in fifteen-year-old software as in a phone.

    $bodyHtml, $headerHtml and $footerHtml are printed unescaped. The body
    is HTML an admin wrote, cleaned by App\Support\RichText with the same
    rules as a forum post; the header and footer are the
    email_template_header / email_template_footer settings, which only an
    admin can set. The values filled into the body were escaped on the way
    in - see App\Support\MailVariables.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f4f5f7; -webkit-font-smoothing:antialiased;">
    {{-- What a client shows in the message list, instead of the first words of the letterhead --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">{{ \Illuminate\Support\Str::limit(strip_tags($bodyHtml), 120) }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px;">

                    <tr>
                        <td align="center" style="padding:0 0 20px 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                            <a href="{{ $siteUrl }}" style="color:#1867c0; font-size:19px; font-weight:600; text-decoration:none;">{{ $siteName }}</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; border-radius:10px; padding:32px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:16px; line-height:1.6; color:#2b2b2b;">

                            @if ($headerHtml)
                                <div style="margin:0 0 24px 0;">{!! $headerHtml !!}</div>
                            @endif

                            <div class="mail-body">{!! $bodyHtml !!}</div>

                            @if ($actionLabel && $actionUrl)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px 0;">
                                    <tr>
                                        <td align="center" bgcolor="#1867c0" style="border-radius:6px;">
                                            <a href="{{ $actionUrl }}"
                                               style="display:inline-block; padding:12px 26px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:6px;">
                                                {{ $actionLabel }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if ($footerHtml)
                                <div style="margin:28px 0 0 0; padding-top:20px; border-top:1px solid #ececec; font-size:14px; color:#6b6b6b;">{!! $footerHtml !!}</div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 8px 0 8px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:1.6; color:#8a8a8a;">
                            @if ($unsubscribeUrl)
                                <p style="margin:0 0 6px 0;">
                                    @if ($categoryName)
                                        {{ __('mail.footer.category', ['category' => $categoryName]) }}
                                    @endif
                                    <a href="{{ $unsubscribeUrl }}" style="color:#8a8a8a; text-decoration:underline;">{{ __('mail.footer.unsubscribe') }}</a>
                                </p>
                            @endif
                            <p style="margin:0;">
                                <a href="{{ $siteUrl }}" style="color:#8a8a8a; text-decoration:underline;">{{ $siteName }}</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
