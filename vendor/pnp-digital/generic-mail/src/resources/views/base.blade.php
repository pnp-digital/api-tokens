<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <title>
        {{ $subject }}
    </title>
    @include('generic-mailable::bits.email_inline_style')
</head>
<body>

<table role="presentation" cellspacing="0" cellpadding="0" width="100%" class="wrapper">
    <tbody>
    <tr>
        <td align="center">
            <a href="{{ env("APP_URL") }}" class="wrapper logo">
                <img src="{{ env("APP_URL") . '/img/' . env('MAIL_FOOTER_IMAGE', 'logo.png') }}" height="100" width="300" />
            </a>
        </td>
    </tr>
    </tbody>
</table>
<table role="presentation" cellspacing="0" cellpadding="0" width="100%" class="wrapper">
    <tbody>
    <tr>
        <td align="center">
            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" class="content">
                <tbody>
                <tr>
                    <td cellspacing="0" cellpadding="0" width="100%" class="body">
                        <table role="presentation" cellspacing="0" cellpadding="0" width="700" class="inner-body">
                            <tbody>
                            <tr>
                                <td class="content-cell">
                                    <h1>{{ $subject }}</h1>
                                    @yield('content')
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
                @include('generic-mailable::bits.email_copyright')
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>
</body>
</html>
