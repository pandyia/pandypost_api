@extends('emails.layout')

@section('title', 'Confirmação de Cadastro')

@section('icon')&#9993;@endsection

@section('greeting')
    Olá, <span style="color: #a78bfa;">{{ $user->name }}</span>!
@endsection

@section('description')
    Estamos felizes em tê-lo conosco! Clique no botão abaixo para confirmar seu
    e-mail e começar a usar o <strong style="color: #cbd5e1;">PandyPost</strong>.
@endsection

@section('action')
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="text-align: center;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center"
                    style="margin: 0 auto;">
                    <tr>
                        <td align="center" valign="middle"
                            style="border-radius: 10px; background: linear-gradient(135deg, #a78bfa 0%, #7c3aed 100%); text-align: center;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $verificationUrl }}" style="height:54px;v-text-anchor:middle;width:260px;" arcsize="19%" strokecolor="#7c3aed" fillcolor="#7c3aed">
                            <w:anchorlock/>
                            <center style="color:#ffffff;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;font-size:16px;font-weight:bold;">Verificar meu E-mail</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-->
                            <a href="{{ $verificationUrl }}" target="_blank" class="cta-btn"
                                style="display: inline-block; padding: 18px 52px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.3px; line-height: 1; text-align: center;">
                                Verificar meu E-mail
                            </a>
                            <!--<![endif]-->
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection

@section('infoBadge')
    &#9203; Este link expira em <strong style="color: #a78bfa;">{{ config('auth.verification_timeout') / 60 }}
        horas</strong>
@endsection

@section('fallbackUrl', $verificationUrl)

@section('footerText')
    Se você não criou esta conta, ignore este e-mail.
@endsection