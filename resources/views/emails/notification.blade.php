@extends('emails.layout')

@section('title', $title)

@section('icon')&#128276;@endsection

@section('greeting')
    Olá, <span style="color: #a78bfa;">{{ $notifiable->name }}</span>!
@endsection

@section('description')
    <strong style="color: #cbd5e1;">{{ $title }}</strong><br>
    {{ $body }}
@endsection

@section('action')
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="text-align: center;">
                <a href="{{ $url }}" target="_blank" class="cta-btn"
                    style="display: inline-block; padding: 18px 52px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 16px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 10px; background: linear-gradient(135deg, #a78bfa 0%, #7c3aed 100%); line-height: 1; text-align: center;">
                    Abrir no PandyPost
                </a>
            </td>
        </tr>
    </table>
@endsection

@section('infoBadge')
    &#128276; Você pode ajustar os avisos por e-mail no seu perfil
@endsection

@section('fallbackUrl', $url)

@section('footerText')
    Você recebeu este e-mail por causa da sua atividade no PandyPost.
@endsection
