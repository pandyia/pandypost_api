<!DOCTYPE html>
<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title') - PandyPost</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
        }

        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }

            .email-content {
                padding: 32px 24px !important;
            }

            .email-header {
                padding: 32px 24px 20px !important;
            }

            .cta-btn {
                padding: 16px 36px !important;
                font-size: 15px !important;
            }
        }
    </style>
</head>

<body
    style="margin: 0; padding: 0; background-color: #0a0914; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"
        style="background-color: #0a0914;">
        <tr>
            <td align="center" valign="top" style="padding: 48px 16px;">

                <!--[if mso]>
                <table align="center" role="presentation" border="0" cellspacing="0" cellpadding="0" width="560"><tr><td align="center" valign="top" width="560">
                <![endif]-->

                <table role="presentation" class="email-container" border="0" cellpadding="0" cellspacing="0"
                    width="560" style="max-width: 560px; margin: 0 auto;">

                    <!-- HEADER -->
                    <tr>
                        <td align="center" class="email-header"
                            style="padding: 44px 40px 28px; background-color: #15122a; border-radius: 16px 16px 0 0; text-align: center;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="text-align: center;">
                                        <!--[if mso]>
                                        <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="48" align="center"><tr><td align="center" valign="middle" width="48" height="48" style="background-color:#7c3aed; border-radius:12px; font-size:22px; font-weight:700; color:#ffffff; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; text-align:center;">P</td></tr></table>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0"
                                            align="center" style="margin: 0 auto;">
                                            <tr>
                                                <td align="center" valign="middle" width="48" height="48"
                                                    style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #a78bfa 0%, #7c3aed 50%, #6d28d9 100%); text-align: center; font-size: 22px; font-weight: 700; color: #ffffff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                                                    P</td>
                                            </tr>
                                        </table>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 14px; text-align: center;">
                                        <p
                                            style="margin: 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px; text-align: center;">
                                            Pandy<span style="color: #a78bfa;">Post</span>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- ACCENT LINE -->
                    <tr>
                        <td align="center" style="background-color: #15122a; padding: 0 48px; text-align: center;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td
                                        style="height: 2px; background: linear-gradient(90deg, transparent 0%, #a78bfa 50%, transparent 100%); font-size: 0; line-height: 0;">
                                        &nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td align="center" class="email-content"
                            style="padding: 40px 48px 44px; background-color: #15122a; text-align: center;">

                            <!-- Icon -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 28px; text-align: center;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0"
                                            align="center" style="margin: 0 auto;">
                                            <tr>
                                                <td align="center" valign="middle" width="68" height="68"
                                                    style="width: 68px; height: 68px; border-radius: 50%; background-color: #1e1a36; border: 2px solid #2d2754; text-align: center; font-size: 30px; line-height: 68px;">
                                                    @yield('icon')</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Greeting -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 12px; text-align: center;">
                                        <p
                                            style="margin: 0; font-size: 24px; font-weight: 700; color: #f1f5f9; text-align: center; line-height: 1.3;">
                                            @yield('greeting')
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Description -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 36px; text-align: center;">
                                        <p
                                            style="margin: 0; font-size: 16px; line-height: 1.7; color: #94a3b8; text-align: center;">
                                            @yield('description')
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Action Area (CTA button, token box, etc.) -->
                            @yield('action')

                            <!-- Info Badge -->
                            @hasSection('infoBadge')
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td align="center" style="padding-top: 32px; text-align: center;">
                                            <table role="presentation" border="0" cellpadding="0" cellspacing="0"
                                                align="center" style="margin: 0 auto;">
                                                <tr>
                                                    <td align="center" valign="middle"
                                                        style="padding: 10px 24px; background-color: #1a1730; border: 1px solid #2d2754; border-radius: 8px; text-align: center;">
                                                        <p
                                                            style="margin: 0; font-size: 13px; color: #94a3b8; text-align: center;">
                                                            @yield('infoBadge')
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                        </td>
                    </tr>

                    <!-- FALLBACK LINK (optional) -->
                    @hasSection('fallbackUrl')
                        <tr>
                            <td align="center" style="padding: 24px 48px; background-color: #110f24; text-align: center;">
                                <p style="margin: 0 0 8px; font-size: 12px; color: #64748b; text-align: center;">
                                    Se o botão não funcionar, copie e cole este link:
                                </p>
                                <p style="margin: 0; font-size: 11px; word-break: break-all; text-align: center;">
                                    <a href="@yield('fallbackUrl')"
                                        style="color: #8b5cf6; text-decoration: underline;">@yield('fallbackUrl')</a>
                                </p>
                            </td>
                        </tr>
                    @endif

                    <!-- FOOTER -->
                    <tr>
                        <td align="center"
                            style="padding: 28px 40px; background-color: #0d0b1a; border-radius: 0 0 16px 16px; border-top: 1px solid #1e1b33; text-align: center;">
                            <p
                                style="margin: 0; font-size: 12px; line-height: 1.6; color: #475569; text-align: center;">
                                @yield('footerText')<br>
                                &copy; {{ date('Y') }} PandyPost. Todos os direitos reservados.
                            </p>
                        </td>
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