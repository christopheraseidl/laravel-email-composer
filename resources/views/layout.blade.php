<!DOCTYPE html>
<html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="color-scheme" content="light dark">
        <meta name="supported-color-schemes" content="light dark">
        <style>{!! $css !!}</style>
    </head>
    <body class="email-body">
        <table role="presentation" class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center">
                    <!--[if mso]>
                    <table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td>
                    <![endif]-->
                    <table role="presentation" class="email-container" width="100%" cellpadding="0" cellspacing="0" border="0">
                        @php($logoSrc = $theme->logoSrc())
                        @if ($logoSrc)
                            <tr>
                                <td class="email-header" align="center">
                                    <img src="{{ $logoSrc }}" alt="{{ $theme->logoAlt() }}" width="160">
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="email-content">
                                {!! $body !!}
                            </td>
                        </tr>
                        <tr>
                            <td class="email-footer">{!! \CSeidl\EmailComposer\Mail\ComposedEmail::FOOTER_MARKER !!}</td>
                        </tr>
                    </table>
                    <!--[if mso]>
                    </td></tr></table>
                    <![endif]-->
                </td>
            </tr>
        </table>
    </body>
</html>
