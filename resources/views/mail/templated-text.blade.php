{{--
    The plain-text half of every mail.

    Worth the few lines: a mail with no text part is more likely to be
    treated as spam, and some people genuinely read their mail this way.
    Built from the same body rather than written twice, so the two can
    never say different things.
--}}
@php
    $plain = html_entity_decode(
        trim(strip_tags(preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n\n", str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)))),
        ENT_QUOTES | ENT_HTML5
    );
@endphp
{{ $plain }}

@if ($actionLabel && $actionUrl)
{{ $actionLabel }}: {{ $actionUrl }}

@endif
@if ($footerHtml)
{{ trim(strip_tags($footerHtml)) }}

@endif
--
{{ $siteName }} — {{ $siteUrl }}
@if ($unsubscribeUrl)
{{ __('mail.footer.unsubscribe') }}: {{ $unsubscribeUrl }}
@endif
