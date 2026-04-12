@extends('emails.layout')

@section('title', 'Convite para Workspace')

@section('icon')&#128140;@endsection

@section('greeting')
    Olá, <span style="color: #a78bfa;">{{ $notifiable->name }}</span>!
@endsection

@section('description')
    <strong style="color: #cbd5e1;">{{ $invitedByName }}</strong> convidou você
    para participar do workspace
    <strong style="color: #a78bfa;">{{ $workspaceName }}</strong> no
    <strong style="color: #cbd5e1;">PandyPost</strong>.
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
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $acceptUrl }}" style="height:54px;v-text-anchor:middle;width:260px;" arcsize="19%" strokecolor="#7c3aed" fillcolor="#7c3aed">
                            <w:anchorlock/>
                            <center style="color:#ffffff;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;font-size:16px;font-weight:bold;">Acessar Workspace</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-->
                            <a href="{{ $acceptUrl }}" target="_blank" class="cta-btn"
                                style="display: inline-block; padding: 18px 52px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.3px; line-height: 1; text-align: center;">
                                Acessar Workspace
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
    &#9203; Este convite expira em <strong style="color: #a78bfa;">{{ config('app.invite_expiration_days', 3) }}
        dias</strong>
@endsection

@section('fallbackUrl', $acceptUrl)

@section('footerText')
    Se você não reconhece este convite, ignore este e-mail.
@endsection