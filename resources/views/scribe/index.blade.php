<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Pandypost API</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .javascript-example code { display: none; }
                    body .content .bash-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost:9000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.11.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.11.0.js") }}"></script>

</head>

<body data-languages="[&quot;javascript&quot;,&quot;bash&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Buscar">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introducao" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introducao">
                    <a href="#introducao">Introdução</a>
                </li>
                                    <ul id="tocify-subheader-introducao" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="como-a-api-funciona">
                                <a href="#como-a-api-funciona">Como a API funciona</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="listagens">
                                <a href="#listagens">Listagens</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="erros">
                                <a href="#erros">Erros</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="datas-e-valores">
                                <a href="#datas-e-valores">Datas e valores</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="tempo-real-reverb">
                                <a href="#tempo-real-reverb">Tempo real (Reverb)</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-autenticacao-das-requisicoes" class="tocify-header">
                <li class="tocify-item level-1" data-unique="autenticacao-das-requisicoes">
                    <a href="#autenticacao-das-requisicoes">Autenticação das requisições</a>
                </li>
                            </ul>
                    <ul id="tocify-header-autenticacao" class="tocify-header">
                <li class="tocify-item level-1" data-unique="autenticacao">
                    <a href="#autenticacao">Autenticação</a>
                </li>
                                    <ul id="tocify-subheader-autenticacao" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="autenticacao-POSTapi-auth-signup">
                                <a href="#autenticacao-POSTapi-auth-signup">Cadastrar usuário</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacao-POSTapi-auth-forgot-password">
                                <a href="#autenticacao-POSTapi-auth-forgot-password">Esqueci minha senha</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacao-POSTapi-auth-password-reset">
                                <a href="#autenticacao-POSTapi-auth-password-reset">Redefinir senha</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacao-POSTapi-login">
                                <a href="#autenticacao-POSTapi-login">Login</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacao-POSTapi-auth-confirm-email">
                                <a href="#autenticacao-POSTapi-auth-confirm-email">Confirmar email</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacao-POSTapi-logout">
                                <a href="#autenticacao-POSTapi-logout">Logout</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacao-GETapi-me">
                                <a href="#autenticacao-GETapi-me">Usuário logado</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="autenticacao-POSTapi-auth-resend-email-verification">
                                <a href="#autenticacao-POSTapi-auth-resend-email-verification">Reenviar código de confirmação</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-workspaces" class="tocify-header">
                <li class="tocify-item level-1" data-unique="workspaces">
                    <a href="#workspaces">Workspaces</a>
                </li>
                                    <ul id="tocify-subheader-workspaces" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="workspaces-GETapi-workspaces">
                                <a href="#workspaces-GETapi-workspaces">Listar workspaces</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-POSTapi-workspaces">
                                <a href="#workspaces-POSTapi-workspaces">Criar workspace</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-GETapi-workspaces--id-">
                                <a href="#workspaces-GETapi-workspaces--id-">Ver workspace</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-PUTapi-workspaces--id-">
                                <a href="#workspaces-PUTapi-workspaces--id-">Renomear workspace</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-DELETEapi-workspaces--id-">
                                <a href="#workspaces-DELETEapi-workspaces--id-">Excluir workspace</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-POSTapi-workspaces--workspace--switch">
                                <a href="#workspaces-POSTapi-workspaces--workspace--switch">Trocar workspace corrente</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-POSTapi-workspaces--workspace_uuid--avatar-upload-url">
                                <a href="#workspaces-POSTapi-workspaces--workspace_uuid--avatar-upload-url">Pedir URL de upload da foto</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-PUTapi-workspaces--workspace_uuid--avatar">
                                <a href="#workspaces-PUTapi-workspaces--workspace_uuid--avatar">Confirmar a foto enviada</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="workspaces-DELETEapi-workspaces--workspace_uuid--avatar">
                                <a href="#workspaces-DELETEapi-workspaces--workspace_uuid--avatar">Remover a foto</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-usuarios" class="tocify-header">
                <li class="tocify-item level-1" data-unique="usuarios">
                    <a href="#usuarios">Usuários</a>
                </li>
                                    <ul id="tocify-subheader-usuarios" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="usuarios-GETapi-users">
                                <a href="#usuarios-GETapi-users">Listar usuários</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="usuarios-POSTapi-users">
                                <a href="#usuarios-POSTapi-users">Criar usuário</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="usuarios-GETapi-users--id-">
                                <a href="#usuarios-GETapi-users--id-">Ver usuário</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="usuarios-PUTapi-users--id-">
                                <a href="#usuarios-PUTapi-users--id-">Editar usuário</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="usuarios-DELETEapi-users--id-">
                                <a href="#usuarios-DELETEapi-users--id-">Remover usuário do workspace</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="usuarios-PATCHapi-users--user--role">
                                <a href="#usuarios-PATCHapi-users--user--role">Trocar perfil do usuário</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-perfis-e-permissoes" class="tocify-header">
                <li class="tocify-item level-1" data-unique="perfis-e-permissoes">
                    <a href="#perfis-e-permissoes">Perfis e permissões</a>
                </li>
                                    <ul id="tocify-subheader-perfis-e-permissoes" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="perfis-e-permissoes-GETapi-roles">
                                <a href="#perfis-e-permissoes-GETapi-roles">Listar perfis</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="perfis-e-permissoes-POSTapi-roles">
                                <a href="#perfis-e-permissoes-POSTapi-roles">Criar perfil</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="perfis-e-permissoes-GETapi-roles--id-">
                                <a href="#perfis-e-permissoes-GETapi-roles--id-">Ver perfil</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="perfis-e-permissoes-PUTapi-roles--id-">
                                <a href="#perfis-e-permissoes-PUTapi-roles--id-">Editar perfil</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="perfis-e-permissoes-DELETEapi-roles--id-">
                                <a href="#perfis-e-permissoes-DELETEapi-roles--id-">Excluir perfil</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="perfis-e-permissoes-GETapi-permissions">
                                <a href="#perfis-e-permissoes-GETapi-permissions">Listar permissões</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="perfis-e-permissoes-GETapi-permissions--permission-">
                                <a href="#perfis-e-permissoes-GETapi-permissions--permission-">Ver permissão</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-acessos" class="tocify-header">
                <li class="tocify-item level-1" data-unique="acessos">
                    <a href="#acessos">Acessos</a>
                </li>
                                    <ul id="tocify-subheader-acessos" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="acessos-GETapi-accesses">
                                <a href="#acessos-GETapi-accesses">Listar acessos</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="acessos-POSTapi-accesses">
                                <a href="#acessos-POSTapi-accesses">Criar acesso</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="acessos-GETapi-accesses--id-">
                                <a href="#acessos-GETapi-accesses--id-">Ver acesso</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="acessos-PUTapi-accesses--id-">
                                <a href="#acessos-PUTapi-accesses--id-">Editar acesso</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="acessos-DELETEapi-accesses--id-">
                                <a href="#acessos-DELETEapi-accesses--id-">Excluir acesso</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-convites" class="tocify-header">
                <li class="tocify-item level-1" data-unique="convites">
                    <a href="#convites">Convites</a>
                </li>
                                    <ul id="tocify-subheader-convites" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="convites-GETapi-me-invites">
                                <a href="#convites-GETapi-me-invites">Convites recebidos</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="convites-PATCHapi-me-invites--invite_uuid--accept">
                                <a href="#convites-PATCHapi-me-invites--invite_uuid--accept">Aceitar convite</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="convites-PATCHapi-me-invites--invite_uuid--decline">
                                <a href="#convites-PATCHapi-me-invites--invite_uuid--decline">Recusar convite</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="convites-GETapi-invites">
                                <a href="#convites-GETapi-invites">Convites enviados</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="convites-POSTapi-invites">
                                <a href="#convites-POSTapi-invites">Enviar convite</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-contas-sociais" class="tocify-header">
                <li class="tocify-item level-1" data-unique="contas-sociais">
                    <a href="#contas-sociais">Contas sociais</a>
                </li>
                                    <ul id="tocify-subheader-contas-sociais" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="contas-sociais-GETapi-social-accounts--platform--callback">
                                <a href="#contas-sociais-GETapi-social-accounts--platform--callback">Callback do OAuth</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="contas-sociais-GETapi-social-accounts">
                                <a href="#contas-sociais-GETapi-social-accounts">Listar contas conectadas</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="contas-sociais-GETapi-social-accounts-schedules">
                                <a href="#contas-sociais-GETapi-social-accounts-schedules">Contas com seus agendamentos</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="contas-sociais-GETapi-social-accounts--platform--auth">
                                <a href="#contas-sociais-GETapi-social-accounts--platform--auth">Iniciar conexão (URL de OAuth)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="contas-sociais-DELETEapi-social-accounts--socialAccount-">
                                <a href="#contas-sociais-DELETEapi-social-accounts--socialAccount-">Desconectar conta</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-agendamentos" class="tocify-header">
                <li class="tocify-item level-1" data-unique="agendamentos">
                    <a href="#agendamentos">Agendamentos</a>
                </li>
                                    <ul id="tocify-subheader-agendamentos" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="agendamentos-GETapi-scheduled-posts">
                                <a href="#agendamentos-GETapi-scheduled-posts">Listar agendamentos</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="agendamentos-POSTapi-scheduled-posts-upload-url">
                                <a href="#agendamentos-POSTapi-scheduled-posts-upload-url">Gerar URL de upload</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="agendamentos-POSTapi-scheduled-posts">
                                <a href="#agendamentos-POSTapi-scheduled-posts">Agendar publicação</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="agendamentos-POSTapi-scheduled-posts--scheduledPost--retry">
                                <a href="#agendamentos-POSTapi-scheduled-posts--scheduledPost--retry">Reenviar post que falhou</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="agendamentos-DELETEapi-scheduled-posts--scheduledPost-">
                                <a href="#agendamentos-DELETEapi-scheduled-posts--scheduledPost-">Cancelar agendamento</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="agendamentos-GETapi-youtube-categories">
                                <a href="#agendamentos-GETapi-youtube-categories">Categorias do YouTube</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="agendamentos-GETapi-youtube-privacy-statuses">
                                <a href="#agendamentos-GETapi-youtube-privacy-statuses">Privacidades do YouTube</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-pipeline-de-conteudo" class="tocify-header">
                <li class="tocify-item level-1" data-unique="pipeline-de-conteudo">
                    <a href="#pipeline-de-conteudo">Pipeline de conteúdo</a>
                </li>
                                    <ul id="tocify-subheader-pipeline-de-conteudo" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="pipeline-de-conteudo-GETapi-pipeline-board">
                                <a href="#pipeline-de-conteudo-GETapi-pipeline-board">Quadro</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pipeline-de-conteudo-POSTapi-pipeline">
                                <a href="#pipeline-de-conteudo-POSTapi-pipeline">Criar card</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pipeline-de-conteudo-GETapi-pipeline--contentPipeline-">
                                <a href="#pipeline-de-conteudo-GETapi-pipeline--contentPipeline-">Ver card</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline-">
                                <a href="#pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline-">Editar card</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pipeline-de-conteudo-DELETEapi-pipeline--contentPipeline-">
                                <a href="#pipeline-de-conteudo-DELETEapi-pipeline--contentPipeline-">Remover card</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline--move">
                                <a href="#pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline--move">Mover card de etapa</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline--restore">
                                <a href="#pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline--restore">Restaurar card</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-analytics" class="tocify-header">
                <li class="tocify-item level-1" data-unique="analytics">
                    <a href="#analytics">Analytics</a>
                </li>
                                    <ul id="tocify-subheader-analytics" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="analytics-GETapi-analytics--socialAccount_uuid--dashboard">
                                <a href="#analytics-GETapi-analytics--socialAccount_uuid--dashboard">Dashboard da conta</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="analytics-GETapi-analytics--socialAccount_uuid--best-times">
                                <a href="#analytics-GETapi-analytics--socialAccount_uuid--best-times">Melhores horários para postar</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-auditoria" class="tocify-header">
                <li class="tocify-item level-1" data-unique="auditoria">
                    <a href="#auditoria">Auditoria</a>
                </li>
                                    <ul id="tocify-subheader-auditoria" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="auditoria-GETapi-logs">
                                <a href="#auditoria-GETapi-logs">Listar logs</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-planos-publico" class="tocify-header">
                <li class="tocify-item level-1" data-unique="planos-publico">
                    <a href="#planos-publico">Planos (público)</a>
                </li>
                                    <ul id="tocify-subheader-planos-publico" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="planos-publico-GETapi-plans">
                                <a href="#planos-publico-GETapi-plans">Listar planos</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-assinatura-e-pagamentos" class="tocify-header">
                <li class="tocify-item level-1" data-unique="assinatura-e-pagamentos">
                    <a href="#assinatura-e-pagamentos">Assinatura e pagamentos</a>
                </li>
                                    <ul id="tocify-subheader-assinatura-e-pagamentos" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-GETapi-billing-subscription">
                                <a href="#assinatura-e-pagamentos-GETapi-billing-subscription">Assinatura atual</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-POSTapi-billing-subscription-checkout">
                                <a href="#assinatura-e-pagamentos-POSTapi-billing-subscription-checkout">Iniciar checkout</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-POSTapi-billing-subscription-checkout-confirm">
                                <a href="#assinatura-e-pagamentos-POSTapi-billing-subscription-checkout-confirm">Confirmar checkout</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-GETapi-billing-setup-intent">
                                <a href="#assinatura-e-pagamentos-GETapi-billing-setup-intent">Preparar cadastro de cartão</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-GETapi-billing-cards">
                                <a href="#assinatura-e-pagamentos-GETapi-billing-cards">Listar cartões</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-POSTapi-billing-cards">
                                <a href="#assinatura-e-pagamentos-POSTapi-billing-cards">Adicionar cartão</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-PATCHapi-billing-cards--paymentMethod-">
                                <a href="#assinatura-e-pagamentos-PATCHapi-billing-cards--paymentMethod-">Definir cartão padrão</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-DELETEapi-billing-cards--paymentMethod-">
                                <a href="#assinatura-e-pagamentos-DELETEapi-billing-cards--paymentMethod-">Remover cartão</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-GETapi-billing-payments">
                                <a href="#assinatura-e-pagamentos-GETapi-billing-payments">Listar faturas</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="assinatura-e-pagamentos-GETapi-billing-payments--payment-">
                                <a href="#assinatura-e-pagamentos-GETapi-billing-payments--payment-">Ver fatura</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-admin-planos-e-precos" class="tocify-header">
                <li class="tocify-item level-1" data-unique="admin-planos-e-precos">
                    <a href="#admin-planos-e-precos">Admin: planos e preços</a>
                </li>
                                    <ul id="tocify-subheader-admin-planos-e-precos" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="admin-planos-e-precos-GETapi-admin-plans">
                                <a href="#admin-planos-e-precos-GETapi-admin-plans">Listar planos</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-POSTapi-admin-plans">
                                <a href="#admin-planos-e-precos-POSTapi-admin-plans">Criar plano</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-GETapi-admin-plans--plan-">
                                <a href="#admin-planos-e-precos-GETapi-admin-plans--plan-">Ver plano</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-PATCHapi-admin-plans--plan-">
                                <a href="#admin-planos-e-precos-PATCHapi-admin-plans--plan-">Editar plano</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-DELETEapi-admin-plans--plan-">
                                <a href="#admin-planos-e-precos-DELETEapi-admin-plans--plan-">Remover plano</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-GETapi-admin-plans--plan--prices">
                                <a href="#admin-planos-e-precos-GETapi-admin-plans--plan--prices">Listar preços do plano</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-POSTapi-admin-plans--plan--prices">
                                <a href="#admin-planos-e-precos-POSTapi-admin-plans--plan--prices">Adicionar preço</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-GETapi-admin-plans--plan--prices--price--versions">
                                <a href="#admin-planos-e-precos-GETapi-admin-plans--plan--prices--price--versions">Histórico do preço</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-PATCHapi-admin-plans--plan--prices--price-">
                                <a href="#admin-planos-e-precos-PATCHapi-admin-plans--plan--prices--price-">Editar preço</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="admin-planos-e-precos-DELETEapi-admin-plans--plan--prices--price-">
                                <a href="#admin-planos-e-precos-DELETEapi-admin-plans--plan--prices--price-">Remover preço</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-admin-landing" class="tocify-header">
                <li class="tocify-item level-1" data-unique="admin-landing">
                    <a href="#admin-landing">Admin: landing</a>
                </li>
                                    <ul id="tocify-subheader-admin-landing" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="admin-landing-PUTapi-admin-landing-settings">
                                <a href="#admin-landing-PUTapi-admin-landing-settings">Trocar o tema da landing</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-landing-publico" class="tocify-header">
                <li class="tocify-item level-1" data-unique="landing-publico">
                    <a href="#landing-publico">Landing (público)</a>
                </li>
                                    <ul id="tocify-subheader-landing-publico" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="landing-publico-GETapi-landing-settings">
                                <a href="#landing-publico-GETapi-landing-settings">Tema da landing</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-meu-perfil" class="tocify-header">
                <li class="tocify-item level-1" data-unique="meu-perfil">
                    <a href="#meu-perfil">Meu perfil</a>
                </li>
                                    <ul id="tocify-subheader-meu-perfil" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="meu-perfil-PATCHapi-me">
                                <a href="#meu-perfil-PATCHapi-me">Atualizar dados pessoais</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="meu-perfil-POSTapi-me-avatar-upload-url">
                                <a href="#meu-perfil-POSTapi-me-avatar-upload-url">Pedir URL de upload da foto</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="meu-perfil-PUTapi-me-avatar">
                                <a href="#meu-perfil-PUTapi-me-avatar">Confirmar a foto enviada</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="meu-perfil-DELETEapi-me-avatar">
                                <a href="#meu-perfil-DELETEapi-me-avatar">Remover a foto</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="meu-perfil-PUTapi-me-password">
                                <a href="#meu-perfil-PUTapi-me-password">Trocar a senha</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-notificacoes" class="tocify-header">
                <li class="tocify-item level-1" data-unique="notificacoes">
                    <a href="#notificacoes">Notificações</a>
                </li>
                                    <ul id="tocify-subheader-notificacoes" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="notificacoes-GETapi-notifications">
                                <a href="#notificacoes-GETapi-notifications">Listar notificações</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="notificacoes-GETapi-notifications-unread">
                                <a href="#notificacoes-GETapi-notifications-unread">Listar não lidas</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="notificacoes-PATCHapi-notifications-read-all">
                                <a href="#notificacoes-PATCHapi-notifications-read-all">Marcar todas como lidas</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="notificacoes-PATCHapi-notifications--notification--read">
                                <a href="#notificacoes-PATCHapi-notifications--notification--read">Marcar como lida</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="notificacoes-DELETEapi-notifications--notification-">
                                <a href="#notificacoes-DELETEapi-notifications--notification-">Remover notificação</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="notificacoes-GETapi-notification-preferences">
                                <a href="#notificacoes-GETapi-notification-preferences">Ler preferências</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="notificacoes-PATCHapi-notification-preferences">
                                <a href="#notificacoes-PATCHapi-notification-preferences">Alterar preferências</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">Coleção do Postman</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">Especificação OpenAPI</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Atualizado em 20/10/2026 (e0b1c0a)</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introducao">Introdução</h1>
<p>Referência da API do Pandypost para o frontend.</p>
<aside>
    <strong>URL base</strong>: <code>http://localhost:9000</code>
</aside>
<p>API do Pandypost: agendamento e publicação de vídeos no YouTube, TikTok e Instagram. Esta página é o contrato entre a API e o frontend.</p>
<h2 id="como-a-api-funciona">Como a API funciona</h2>
<p><strong>Base URL:</strong> todas as rotas ficam sob <code>/api</code>. Envie e espere sempre JSON.</p>
<p><strong>Autenticação:</strong> <code>POST /api/login</code> devolve um <code>token</code>. Mande-o em todas as rotas protegidas:
<code>Authorization: Bearer {token}</code>. Rotas marcadas como públicas não precisam dele.</p>
<p><strong>Email verificado:</strong> quase toda rota exige email confirmado. Sem isso a resposta é
<code>403 {"error": "email_not_verified"}</code> → leve o usuário para a tela de confirmação. Só <code>/me</code>, <code>/logout</code> e o
reenvio do código funcionam antes da confirmação.</p>
<p><strong>Workspace corrente:</strong> o sistema é multi-tenant. Cada usuário tem um workspace corrente (o pessoal, por padrão)
e <strong>todas</strong> as leituras e escritas são feitas nele. Para trocar, use <code>POST /api/workspaces/{uuid}/switch</code>.
Recurso de outro workspace responde <code>404</code>.</p>
<p><strong>Permissões:</strong> cada endpoint informa a permissão exigida (ex.: <code>users.view</code>). A lista do usuário no workspace
corrente vem em <code>GET /api/me</code> → <code>permissions</code>; use-a para esconder botões. Sem permissão:
<code>403 {"message": "...", "required_permission": "users.view"}</code>. Super admin passa em tudo.</p>
<p><strong>Identificadores:</strong> as rotas usam <code>uuid</code>. Exceções estão indicadas no próprio endpoint.</p>
<h2 id="listagens">Listagens</h2>
<p>Listagens aceitam <code>per_page</code> e <code>page</code>. <strong>Sem <code>per_page</code>, vem tudo numa página só.</strong> Filtros de texto são
parciais e sem diferenciar maiúsculas (<code>?name=ana</code>). Formato:</p>
<pre><code class="language-json">{
  "data": [ ... ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 3 }
}</code></pre>
<aside class="notice">Algumas listagens (marcadas no endpoint) devolvem o paginador "cru" do Laravel:
os itens em <code>data</code> e os campos de paginação (<code>current_page</code>, <code>total</code>, ...) na raiz.</aside>
<h2 id="erros">Erros</h2>
<table>
<thead>
<tr>
<th>Status</th>
<th>Quando</th>
<th>Corpo</th>
</tr>
</thead>
<tbody>
<tr>
<td>401</td>
<td>Token ausente ou inválido</td>
<td><code>{"message": "Não autenticado"}</code></td>
</tr>
<tr>
<td>403</td>
<td>Sem permissão / email não verificado</td>
<td><code>{"message": "..."}</code></td>
</tr>
<tr>
<td>404</td>
<td>Não existe ou é de outro workspace</td>
<td><code>{"message": "..."}</code></td>
</tr>
<tr>
<td>422</td>
<td>Validação</td>
<td><code>{"message": "...", "errors": {"campo": ["..."]}}</code></td>
</tr>
<tr>
<td>429</td>
<td>Muitas tentativas</td>
<td><code>{"error": "TOO_MANY_ATTEMPTS", "message": "..."}</code></td>
</tr>
<tr>
<td>4xx/5xx</td>
<td>Regra de negócio</td>
<td><code>{"error": "codigo_do_erro", "message": "..."}</code></td>
</tr>
</tbody>
</table>
<p>Erros de regra de negócio sempre trazem <code>error</code> (código estável, use-o no <code>if</code>) e <code>message</code> (texto em pt-BR,
pronto para exibir). Os códigos possíveis aparecem em cada endpoint.</p>
<h2 id="datas-e-valores">Datas e valores</h2>
<ul>
<li>Datas em ISO 8601 (<code>2026-10-15T14:00:00-03:00</code>), salvo onde o exemplo mostrar outro formato.</li>
<li>Valores monetários em <strong>centavos</strong> (<code>4990</code> = R$ 49,90).</li>
</ul>
<h2 id="tempo-real-reverb">Tempo real (Reverb)</h2>
<p>Eventos chegam por WebSocket (Laravel Echo + Reverb). Autorize canais privados em <code>POST /api/broadcasting/auth</code>
com o mesmo <code>Authorization: Bearer {token}</code>.</p>
<table>
<thead>
<tr>
<th>Canal</th>
<th>Evento</th>
<th>Quando</th>
</tr>
</thead>
<tbody>
<tr>
<td><code>private-workspaces.{uuid}</code></td>
<td><code>.RoleUpdated</code></td>
<td>Um perfil do workspace foi criado, editado ou removido. Payload: <code>{"action": "created\|updated\|deleted\|restored", "role": {...}}</code>. Recarregue <code>/me</code> para atualizar as permissões.</td>
</tr>
</tbody>
</table>
<aside class="warning">Endpoints marcados com ⚠️ têm defeito conhecido registrado em
<code>specs/002-security-hardening</code>. Não dependa deles até a correção.</aside>

        <h1 id="autenticacao-das-requisicoes">Autenticação das requisições</h1>
<p>Para autenticar, envie o header <strong><code>Authorization</code></strong> com o valor <strong><code>"Bearer {TOKEN}"</code></strong>.</p>
<p>Os endpoints que exigem login têm o selo <code>requires authentication</code>. Os demais são públicos.</p>
<p>Obtenha o token em <code>POST /api/login</code> (campo <code>token</code>). Ele vale até o logout ou a expiração definida pelo <code>remember_me</code>.</p>

        <h1 id="autenticacao">Autenticação</h1>

    <p>Cadastro, login, confirmação de email e recuperação de senha.</p>
<p>Fluxo: <code>signup</code> → usuário recebe um código por email → <code>confirm-email</code> → <code>login</code>.</p>

                                <h2 id="autenticacao-POSTapi-auth-signup">Cadastrar usuário</h2>

<p>
</p>

<p>Cria o usuário e o workspace pessoal dele, e envia o código de confirmação por email.
O token devolvido já funciona, mas só para <code>/me</code>, <code>/logout</code> e reenvio do código até o email ser confirmado.</p>
<p>Limite: 3 requisições por minuto.</p>

<span id="example-requests-POSTapi-auth-signup">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/auth/signup"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Ana Souza",
    "email": "ana@exemplo.com",
    "password": "senha-forte-123",
    "password_confirmation": "senha-forte-123"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/auth/signup" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Ana Souza\",
    \"email\": \"ana@exemplo.com\",
    \"password\": \"senha-forte-123\",
    \"password_confirmation\": \"senha-forte-123\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-auth-signup">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;user&quot;: {
        &quot;uuid&quot;: &quot;2bd8fa57-724f-4cfb-ae33-875afedee0e6&quot;,
        &quot;name&quot;: &quot;Ana Souza&quot;,
        &quot;email&quot;: &quot;ana@exemplo.com&quot;,
        &quot;email_verified_at&quot;: null,
        &quot;is_active&quot;: true,
        &quot;remember_me&quot;: false,
        &quot;is_super_admin&quot;: false,
        &quot;access_id&quot;: 2,
        &quot;created_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-15T21:39:45.000000Z&quot;
    },
    &quot;token&quot;: &quot;1|Xk2...&quot;,
    &quot;status&quot;: &quot;unverified&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Este e-mail j&aacute; est&aacute; cadastrado em nossa plataforma.&quot;,
    &quot;errors&quot;: {
        &quot;email&quot;: [
            &quot;Este e-mail j&aacute; est&aacute; cadastrado em nossa plataforma.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-auth-signup" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-auth-signup"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-auth-signup"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-auth-signup" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-auth-signup">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-auth-signup" data-method="POST"
      data-path="api/auth/signup"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-auth-signup', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-auth-signup"
                    onclick="tryItOut('POSTapi-auth-signup');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-auth-signup"
                    onclick="cancelTryOut('POSTapi-auth-signup');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-auth-signup"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/auth/signup</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-auth-signup"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-auth-signup"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-auth-signup"
               value="Ana Souza"
               data-component="body">
    <br>
<p>Nome (máx. 255). Example: <code>Ana Souza</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-auth-signup"
               value="ana@exemplo.com"
               data-component="body">
    <br>
<p>Email (máx. 255), ainda não cadastrado. Example: <code>ana@exemplo.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-auth-signup"
               value="senha-forte-123"
               data-component="body">
    <br>
<p>Senha, mín. 8 caracteres. Example: <code>senha-forte-123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password_confirmation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password_confirmation"                data-endpoint="POSTapi-auth-signup"
               value="senha-forte-123"
               data-component="body">
    <br>
<p>Repetição da senha. Example: <code>senha-forte-123</code></p>
        </div>
        </form>

                    <h2 id="autenticacao-POSTapi-auth-forgot-password">Esqueci minha senha</h2>

<p>
</p>

<p>Envia o link de recuperação. A resposta é sempre a mesma, exista ou não o email.</p>
<p>Limite: 3 requisições por minuto.</p>

<span id="example-requests-POSTapi-auth-forgot-password">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/auth/forgot-password"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "ana@exemplo.com"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/auth/forgot-password" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"ana@exemplo.com\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-auth-forgot-password">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Se o email existir, um link de recupera&ccedil;&atilde;o ser&aacute; enviado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-auth-forgot-password" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-auth-forgot-password"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-auth-forgot-password"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-auth-forgot-password" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-auth-forgot-password">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-auth-forgot-password" data-method="POST"
      data-path="api/auth/forgot-password"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-auth-forgot-password', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-auth-forgot-password"
                    onclick="tryItOut('POSTapi-auth-forgot-password');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-auth-forgot-password"
                    onclick="cancelTryOut('POSTapi-auth-forgot-password');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-auth-forgot-password"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/auth/forgot-password</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-auth-forgot-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-auth-forgot-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-auth-forgot-password"
               value="ana@exemplo.com"
               data-component="body">
    <br>
<p>Email da conta. Example: <code>ana@exemplo.com</code></p>
        </div>
        </form>

                    <h2 id="autenticacao-POSTapi-auth-password-reset">Redefinir senha</h2>

<p>
</p>

<p>Troca a senha usando o token recebido no email de recuperação.</p>
<p>Limite: 3 requisições por minuto.</p>

<span id="example-requests-POSTapi-auth-password-reset">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/auth/password-reset"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "token": "Zx8Yw7Vu6T",
    "email": "ana@exemplo.com",
    "password": "nova-senha-123",
    "password_confirmation": "nova-senha-123"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/auth/password-reset" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"token\": \"Zx8Yw7Vu6T\",
    \"email\": \"ana@exemplo.com\",
    \"password\": \"nova-senha-123\",
    \"password_confirmation\": \"nova-senha-123\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-auth-password-reset">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Senha redefinida com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_token&quot;,
    &quot;message&quot;: &quot;Token inv&aacute;lido.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-auth-password-reset" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-auth-password-reset"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-auth-password-reset"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-auth-password-reset" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-auth-password-reset">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-auth-password-reset" data-method="POST"
      data-path="api/auth/password-reset"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-auth-password-reset', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-auth-password-reset"
                    onclick="tryItOut('POSTapi-auth-password-reset');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-auth-password-reset"
                    onclick="cancelTryOut('POSTapi-auth-password-reset');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-auth-password-reset"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/auth/password-reset</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-auth-password-reset"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-auth-password-reset"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-auth-password-reset"
               value="Zx8Yw7Vu6T"
               data-component="body">
    <br>
<p>Token recebido no email de recuperação. Example: <code>Zx8Yw7Vu6T</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-auth-password-reset"
               value="ana@exemplo.com"
               data-component="body">
    <br>
<p>Email da conta. Example: <code>ana@exemplo.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-auth-password-reset"
               value="nova-senha-123"
               data-component="body">
    <br>
<p>Nova senha, mín. 8 caracteres. Example: <code>nova-senha-123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password_confirmation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password_confirmation"                data-endpoint="POSTapi-auth-password-reset"
               value="nova-senha-123"
               data-component="body">
    <br>
<p>Repetição da nova senha. Example: <code>nova-senha-123</code></p>
        </div>
        </form>

                    <h2 id="autenticacao-POSTapi-login">Login</h2>

<p>
</p>

<p>Devolve o token de acesso e o workspace corrente.
Com <code>remember_me: true</code> o token dura mais.</p>
<p>Limite: 55 requisições por minuto.</p>

<span id="example-requests-POSTapi-login">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "ana@exemplo.com",
    "password": "senha-forte-123",
    "remember_me": false
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"ana@exemplo.com\",
    \"password\": \"senha-forte-123\",
    \"remember_me\": false
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-login">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;user&quot;: {
        &quot;uuid&quot;: &quot;2bd8fa57-724f-4cfb-ae33-875afedee0e6&quot;,
        &quot;name&quot;: &quot;Ana Souza&quot;,
        &quot;email&quot;: &quot;ana@exemplo.com&quot;,
        &quot;email_verified_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
        &quot;is_active&quot;: true,
        &quot;remember_me&quot;: false,
        &quot;is_super_admin&quot;: false,
        &quot;access_id&quot;: 2,
        &quot;created_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-15T21:39:45.000000Z&quot;
    },
    &quot;token&quot;: &quot;3|pZ9...&quot;,
    &quot;current_workspace&quot;: {
        &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
        &quot;name&quot;: &quot;Workspace da Ana&quot;,
        &quot;is_personal_team&quot;: true,
        &quot;stripe_id&quot;: null,
        &quot;pm_type&quot;: null,
        &quot;pm_last_four&quot;: null,
        &quot;trial_ends_at&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (401, senha errada):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_credentials&quot;,
    &quot;message&quot;: &quot;Credenciais inv&aacute;lidas.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, email não confirmado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;email_not_verified&quot;,
    &quot;message&quot;: &quot;Confirme seu email para continuar!&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (404, email não cadastrado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;user_not_found&quot;,
    &quot;message&quot;: &quot;Usu&aacute;rio n&atilde;o encontrado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-login" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-login"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-login" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-login">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-login" data-method="POST"
      data-path="api/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-login', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-login"
                    onclick="tryItOut('POSTapi-login');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-login"
                    onclick="cancelTryOut('POSTapi-login');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-login"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-login"
               value="ana@exemplo.com"
               data-component="body">
    <br>
<p>Email. Example: <code>ana@exemplo.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-login"
               value="senha-forte-123"
               data-component="body">
    <br>
<p>Senha. Example: <code>senha-forte-123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>remember_me</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-login" style="display: none">
            <input type="radio" name="remember_me"
                   value="true"
                   data-endpoint="POSTapi-login"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-login" style="display: none">
            <input type="radio" name="remember_me"
                   value="false"
                   data-endpoint="POSTapi-login"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Manter conectado (token de duração maior). Example: <code>false</code></p>
        </div>
        </form>

                    <h2 id="autenticacao-POSTapi-auth-confirm-email">Confirmar email</h2>

<p>
</p>

<p>Valida o código recebido por email. Depois disso o usuário pode fazer login.</p>
<p>Limite: 10 requisições por minuto.</p>

<span id="example-requests-POSTapi-auth-confirm-email">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/auth/confirm-email"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "token": "aB3dE5gH7j"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/auth/confirm-email" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"token\": \"aB3dE5gH7j\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-auth-confirm-email">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Email verificado com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, código inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_token&quot;,
    &quot;message&quot;: &quot;Token inv&aacute;lido.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, código expirado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;expired_token&quot;,
    &quot;message&quot;: &quot;Token expirado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-auth-confirm-email" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-auth-confirm-email"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-auth-confirm-email"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-auth-confirm-email" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-auth-confirm-email">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-auth-confirm-email" data-method="POST"
      data-path="api/auth/confirm-email"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-auth-confirm-email', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-auth-confirm-email"
                    onclick="tryItOut('POSTapi-auth-confirm-email');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-auth-confirm-email"
                    onclick="cancelTryOut('POSTapi-auth-confirm-email');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-auth-confirm-email"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/auth/confirm-email</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-auth-confirm-email"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-auth-confirm-email"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>token</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="token"                data-endpoint="POSTapi-auth-confirm-email"
               value="aB3dE5gH7j"
               data-component="body">
    <br>
<p>Código recebido por email. Example: <code>aB3dE5gH7j</code></p>
        </div>
        </form>

                    <h2 id="autenticacao-POSTapi-logout">Logout</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Revoga o token usado na requisição.</p>

<span id="example-requests-POSTapi-logout">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/logout"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/logout" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-POSTapi-logout">
            <blockquote>
            <p>Exemplo de resposta (204, sucesso):</p>
        </blockquote>
                <pre>
<code>Resposta vazia</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-logout" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-logout"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-logout" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-logout">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-logout" data-method="POST"
      data-path="api/logout"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-logout', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-logout"
                    onclick="tryItOut('POSTapi-logout');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-logout"
                    onclick="cancelTryOut('POSTapi-logout');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-logout"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-logout"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="autenticacao-GETapi-me">Usuário logado</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Dados do usuário, workspace corrente, perfil e permissões nesse workspace.
Use <code>permissions</code> para decidir o que mostrar na interface. Super admin recebe todas as permissões.
Funciona sem email verificado: confira <code>email_verified_at</code> para saber se precisa confirmar.</p>

<span id="example-requests-GETapi-me">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/me" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-me">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 2,
        &quot;name&quot;: &quot;Ana Souza&quot;,
        &quot;email&quot;: &quot;ana@exemplo.com&quot;,
        &quot;is_active&quot;: true,
        &quot;email_verified_at&quot;: &quot;10/10/2026 16:52:25&quot;,
        &quot;is_super_admin&quot;: false,
        &quot;current_workspace&quot;: {
            &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
            &quot;name&quot;: &quot;Workspace da Ana&quot;
        },
        &quot;role&quot;: &quot;Admin&quot;,
        &quot;permissions&quot;: [
            &quot;users.view&quot;,
            &quot;users.create&quot;,
            &quot;posts.view&quot;,
            &quot;posts.create&quot;,
            &quot;billing.view&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-me" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-me"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-me" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-me">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-me" data-method="GET"
      data-path="api/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-me', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-me"
                    onclick="tryItOut('GETapi-me');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-me"
                    onclick="cancelTryOut('GETapi-me');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-me"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-me"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email_verified_at</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>ISO 8601. <code>null</code> = email não confirmado.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>avatar</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>URL assinada da foto de perfil (muda a cada chamada), ou <code>null</code>.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Só dígitos (DDD + número).</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>bio</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Até 160 caracteres.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>timezone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Fuso horário IANA.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>created_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>ISO 8601.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>current_workspace</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Ausente se o usuário não tiver workspace.</p>
        </div>
                        <h2 id="autenticacao-POSTapi-auth-resend-email-verification">Reenviar código de confirmação</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Envia de novo o código de confirmação para o email do usuário logado. Funciona sem email verificado.</p>
<p>Limite: 3 requisições por minuto.</p>

<span id="example-requests-POSTapi-auth-resend-email-verification">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/auth/resend-email-verification"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/auth/resend-email-verification" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-POSTapi-auth-resend-email-verification">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;C&oacute;digo reenviado com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;already_verified&quot;,
    &quot;message&quot;: &quot;Email j&aacute; verificado.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-auth-resend-email-verification" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-auth-resend-email-verification"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-auth-resend-email-verification"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-auth-resend-email-verification" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-auth-resend-email-verification">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-auth-resend-email-verification" data-method="POST"
      data-path="api/auth/resend-email-verification"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-auth-resend-email-verification', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-auth-resend-email-verification"
                    onclick="tryItOut('POSTapi-auth-resend-email-verification');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-auth-resend-email-verification"
                    onclick="cancelTryOut('POSTapi-auth-resend-email-verification');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-auth-resend-email-verification"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/auth/resend-email-verification</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-auth-resend-email-verification"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-auth-resend-email-verification"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-auth-resend-email-verification"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="workspaces">Workspaces</h1>

    <p>Workspaces (times) dos quais o usuário participa. Todo usuário tem um workspace pessoal, criado no cadastro,
que não pode ser excluído. Quem cria um workspace vira "Admin" dele.</p>

                                <h2 id="workspaces-GETapi-workspaces">Listar workspaces</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Workspaces dos quais o usuário é membro.</p>
<p><strong>Permissão:</strong> <code>workspaces.view</code></p>
<aside class="notice">Formato: paginador "cru" do Laravel (itens em <code>data</code>, paginação na raiz).</aside>

<span id="example-requests-GETapi-workspaces">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces"
);

const params = {
    "per_page": "15",
    "page": "1",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/workspaces?per_page=15&amp;page=1" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-workspaces">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;current_page&quot;: 1,
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
            &quot;name&quot;: &quot;Ag&ecirc;ncia Pandy&quot;,
            &quot;is_personal_team&quot;: false,
            &quot;stripe_id&quot;: null,
            &quot;pm_type&quot;: null,
            &quot;pm_last_four&quot;: null,
            &quot;trial_ends_at&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;
        }
    ],
    &quot;per_page&quot;: 1,
    &quot;total&quot;: 1,
    &quot;last_page&quot;: 1
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;workspaces.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-workspaces" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-workspaces"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-workspaces"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-workspaces" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-workspaces">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-workspaces" data-method="GET"
      data-path="api/workspaces"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-workspaces', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-workspaces"
                    onclick="tryItOut('GETapi-workspaces');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-workspaces"
                    onclick="cancelTryOut('GETapi-workspaces');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-workspaces"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/workspaces</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-workspaces"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-workspaces"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-workspaces"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-workspaces"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-workspaces"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                </form>

                    <h2 id="workspaces-POSTapi-workspaces">Criar workspace</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>O usuário logado vira Admin do novo workspace. O workspace corrente <strong>não</strong> muda: use <code>switch</code> depois.</p>
<p><strong>Permissão:</strong> <code>workspaces.create</code></p>

<span id="example-requests-POSTapi-workspaces">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Agência Pandy"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/workspaces" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Agência Pandy\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-workspaces">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Registro inserido com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;workspaces.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, nome repetido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;workspace_name_already_exists&quot;,
    &quot;message&quot;: &quot;J&aacute; existe um workspace com esse nome.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-workspaces" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-workspaces"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-workspaces"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-workspaces" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-workspaces">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-workspaces" data-method="POST"
      data-path="api/workspaces"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-workspaces', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-workspaces"
                    onclick="tryItOut('POSTapi-workspaces');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-workspaces"
                    onclick="cancelTryOut('POSTapi-workspaces');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-workspaces"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/workspaces</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-workspaces"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-workspaces"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-workspaces"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-workspaces"
               value="Agência Pandy"
               data-component="body">
    <br>
<p>Nome (máx. 255). Não pode repetir o nome de outro workspace do usuário. Example: <code>Agência Pandy</code></p>
        </div>
        </form>

                    <h2 id="workspaces-GETapi-workspaces--id-">Ver workspace</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>⚠️ <strong>BUG-01:</strong> hoje este endpoint falha (500 no PostgreSQL), ver <code>specs/002-security-hardening</code>. Não use até a correção.</p>
<p><strong>Permissão:</strong> <code>workspaces.view</code></p>

<span id="example-requests-GETapi-workspaces--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-workspaces--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
    &quot;name&quot;: &quot;Ag&ecirc;ncia Pandy&quot;,
    &quot;is_personal_team&quot;: false,
    &quot;stripe_id&quot;: null,
    &quot;pm_type&quot;: null,
    &quot;pm_last_four&quot;: null,
    &quot;trial_ends_at&quot;: null,
    &quot;created_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
    &quot;updated_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-workspaces--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-workspaces--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-workspaces--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-workspaces--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-workspaces--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-workspaces--id-" data-method="GET"
      data-path="api/workspaces/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-workspaces--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-workspaces--id-"
                    onclick="tryItOut('GETapi-workspaces--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-workspaces--id-"
                    onclick="cancelTryOut('GETapi-workspaces--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-workspaces--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/workspaces/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-workspaces--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-workspaces--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-workspaces--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-workspaces--id-"
               value="58d7d732-10a4-491d-9864-b4d85dfcf833"
               data-component="url">
    <br>
<p>UUID do workspace. Example: <code>58d7d732-10a4-491d-9864-b4d85dfcf833</code></p>
            </div>
                    </form>

                    <h2 id="workspaces-PUTapi-workspaces--id-">Renomear workspace</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>workspaces.update</code></p>

<span id="example-requests-PUTapi-workspaces--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Agência Pandy 2"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Agência Pandy 2\"
}"
</code></pre></div>

</span>

<span id="example-responses-PUTapi-workspaces--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Registro atualizado com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;workspaces.update&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-workspaces--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-workspaces--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-workspaces--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-workspaces--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-workspaces--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-workspaces--id-" data-method="PUT"
      data-path="api/workspaces/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-workspaces--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-workspaces--id-"
                    onclick="tryItOut('PUTapi-workspaces--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-workspaces--id-"
                    onclick="cancelTryOut('PUTapi-workspaces--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-workspaces--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/workspaces/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/workspaces/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-workspaces--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-workspaces--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-workspaces--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-workspaces--id-"
               value="58d7d732-10a4-491d-9864-b4d85dfcf833"
               data-component="url">
    <br>
<p>UUID do workspace. Example: <code>58d7d732-10a4-491d-9864-b4d85dfcf833</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-workspaces--id-"
               value="Agência Pandy 2"
               data-component="body">
    <br>
<p>Novo nome (máx. 255). Example: <code>Agência Pandy 2</code></p>
        </div>
        </form>

                    <h2 id="workspaces-DELETEapi-workspaces--id-">Excluir workspace</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>O workspace pessoal não pode ser excluído.</p>
<p><strong>Permissão:</strong> <code>workspaces.delete</code></p>

<span id="example-requests-DELETEapi-workspaces--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-workspaces--id-">
            <blockquote>
            <p>Exemplo de resposta (204, sucesso):</p>
        </blockquote>
                <pre>
<code>Resposta vazia</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, workspace pessoal):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;workspace_cannot_deleted&quot;,
    &quot;message&quot;: &quot;Workspace pessoal n&atilde;o pode ser exclu&iacute;do.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;workspaces.delete&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-workspaces--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-workspaces--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-workspaces--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-workspaces--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-workspaces--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-workspaces--id-" data-method="DELETE"
      data-path="api/workspaces/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-workspaces--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-workspaces--id-"
                    onclick="tryItOut('DELETEapi-workspaces--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-workspaces--id-"
                    onclick="cancelTryOut('DELETEapi-workspaces--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-workspaces--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/workspaces/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-workspaces--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-workspaces--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-workspaces--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-workspaces--id-"
               value="58d7d732-10a4-491d-9864-b4d85dfcf833"
               data-component="url">
    <br>
<p>UUID do workspace. Example: <code>58d7d732-10a4-491d-9864-b4d85dfcf833</code></p>
            </div>
                    </form>

                    <h2 id="workspaces-POSTapi-workspaces--workspace--switch">Trocar workspace corrente</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Define o workspace em que o usuário vai trabalhar. A partir daí, todas as rotas leem e gravam nele.
Atualize na interface o perfil e as permissões com o que vier na resposta.</p>
<p><strong>Permissão:</strong> <code>workspaces.view</code></p>

<span id="example-requests-POSTapi-workspaces--workspace--switch">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/switch"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/switch" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-POSTapi-workspaces--workspace--switch">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;current_workspace&quot;: {
            &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
            &quot;name&quot;: &quot;Ag&ecirc;ncia Pandy&quot;
        },
        &quot;role&quot;: &quot;Editor&quot;,
        &quot;permissions&quot;: [
            &quot;posts.view&quot;,
            &quot;posts.create&quot;
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, não é membro):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;user_not_linked_to_workspace&quot;,
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o est&aacute; vinculado a este workspace.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-workspaces--workspace--switch" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-workspaces--workspace--switch"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-workspaces--workspace--switch"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-workspaces--workspace--switch" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-workspaces--workspace--switch">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-workspaces--workspace--switch" data-method="POST"
      data-path="api/workspaces/{workspace}/switch"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-workspaces--workspace--switch', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-workspaces--workspace--switch"
                    onclick="tryItOut('POSTapi-workspaces--workspace--switch');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-workspaces--workspace--switch"
                    onclick="cancelTryOut('POSTapi-workspaces--workspace--switch');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-workspaces--workspace--switch"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/workspaces/{workspace}/switch</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-workspaces--workspace--switch"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-workspaces--workspace--switch"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-workspaces--workspace--switch"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace"                data-endpoint="POSTapi-workspaces--workspace--switch"
               value="58d7d732-10a4-491d-9864-b4d85dfcf833"
               data-component="url">
    <br>
<p>UUID do workspace de destino. Example: <code>58d7d732-10a4-491d-9864-b4d85dfcf833</code></p>
            </div>
                    </form>

                    <h2 id="workspaces-POSTapi-workspaces--workspace_uuid--avatar-upload-url">Pedir URL de upload da foto</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-workspaces--workspace_uuid--avatar-upload-url">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces/45171527-92f1-4e21-ab9b-ab94a85acce4/avatar/upload-url"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "content_type": "image\/jpeg"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/workspaces/45171527-92f1-4e21-ab9b-ab94a85acce4/avatar/upload-url" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"content_type\": \"image\\/jpeg\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-workspaces--workspace_uuid--avatar-upload-url">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;url&quot;: &quot;https://storage.exemplo.com/pandypost/workspaces/7f3c.../avatar/a1b2....jpg?X-Amz-Signature=...&quot;,
    &quot;headers&quot;: {},
    &quot;path&quot;: &quot;workspaces/7f3c.../avatar/a1b2....jpg&quot;,
    &quot;content_type&quot;: &quot;image/jpeg&quot;,
    &quot;max_size&quot;: 5242880,
    &quot;expires_in&quot;: 86400
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão neste workspace):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;workspaces.update&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-workspaces--workspace_uuid--avatar-upload-url" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-workspaces--workspace_uuid--avatar-upload-url"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-workspaces--workspace_uuid--avatar-upload-url"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-workspaces--workspace_uuid--avatar-upload-url" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-workspaces--workspace_uuid--avatar-upload-url">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-workspaces--workspace_uuid--avatar-upload-url" data-method="POST"
      data-path="api/workspaces/{workspace_uuid}/avatar/upload-url"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-workspaces--workspace_uuid--avatar-upload-url', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-workspaces--workspace_uuid--avatar-upload-url"
                    onclick="tryItOut('POSTapi-workspaces--workspace_uuid--avatar-upload-url');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-workspaces--workspace_uuid--avatar-upload-url"
                    onclick="cancelTryOut('POSTapi-workspaces--workspace_uuid--avatar-upload-url');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-workspaces--workspace_uuid--avatar-upload-url"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/workspaces/{workspace_uuid}/avatar/upload-url</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-workspaces--workspace_uuid--avatar-upload-url"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-workspaces--workspace_uuid--avatar-upload-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-workspaces--workspace_uuid--avatar-upload-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace_uuid"                data-endpoint="POSTapi-workspaces--workspace_uuid--avatar-upload-url"
               value="45171527-92f1-4e21-ab9b-ab94a85acce4"
               data-component="url">
    <br>
<p>Example: <code>45171527-92f1-4e21-ab9b-ab94a85acce4</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace"                data-endpoint="POSTapi-workspaces--workspace_uuid--avatar-upload-url"
               value="7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c"
               data-component="url">
    <br>
<p>UUID do workspace. Example: <code>7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>content_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="content_type"                data-endpoint="POSTapi-workspaces--workspace_uuid--avatar-upload-url"
               value="image/jpeg"
               data-component="body">
    <br>
<p><code>image/jpeg</code>, <code>image/png</code> ou <code>image/webp</code>. Example: <code>image/jpeg</code></p>
        </div>
        </form>

                    <h2 id="workspaces-PUTapi-workspaces--workspace_uuid--avatar">Confirmar a foto enviada</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-workspaces--workspace_uuid--avatar">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces/45171527-92f1-4e21-ab9b-ab94a85acce4/avatar"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "path": "workspaces\/7f3c...\/avatar\/a1b2....jpg"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/workspaces/45171527-92f1-4e21-ab9b-ab94a85acce4/avatar" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"path\": \"workspaces\\/7f3c...\\/avatar\\/a1b2....jpg\"
}"
</code></pre></div>

</span>

<span id="example-responses-PUTapi-workspaces--workspace_uuid--avatar">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c&quot;,
        &quot;name&quot;: &quot;Ag&ecirc;ncia Aurora&quot;,
        &quot;avatar&quot;: &quot;https://storage.exemplo.com/...assinada...&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;workspace_avatar_too_large&quot;,
    &quot;message&quot;: &quot;A foto &eacute; maior que o limite de 5 MB. Escolha uma imagem menor.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-workspaces--workspace_uuid--avatar" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-workspaces--workspace_uuid--avatar"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-workspaces--workspace_uuid--avatar"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-workspaces--workspace_uuid--avatar" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-workspaces--workspace_uuid--avatar">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-workspaces--workspace_uuid--avatar" data-method="PUT"
      data-path="api/workspaces/{workspace_uuid}/avatar"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-workspaces--workspace_uuid--avatar', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-workspaces--workspace_uuid--avatar"
                    onclick="tryItOut('PUTapi-workspaces--workspace_uuid--avatar');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-workspaces--workspace_uuid--avatar"
                    onclick="cancelTryOut('PUTapi-workspaces--workspace_uuid--avatar');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-workspaces--workspace_uuid--avatar"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/workspaces/{workspace_uuid}/avatar</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-workspaces--workspace_uuid--avatar"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-workspaces--workspace_uuid--avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-workspaces--workspace_uuid--avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace_uuid"                data-endpoint="PUTapi-workspaces--workspace_uuid--avatar"
               value="45171527-92f1-4e21-ab9b-ab94a85acce4"
               data-component="url">
    <br>
<p>Example: <code>45171527-92f1-4e21-ab9b-ab94a85acce4</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace"                data-endpoint="PUTapi-workspaces--workspace_uuid--avatar"
               value="7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c"
               data-component="url">
    <br>
<p>UUID do workspace. Example: <code>7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>path</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="path"                data-endpoint="PUTapi-workspaces--workspace_uuid--avatar"
               value="workspaces/7f3c.../avatar/a1b2....jpg"
               data-component="body">
    <br>
<p><code>path</code> recebido em <code>upload-url</code>. Example: <code>workspaces/7f3c.../avatar/a1b2....jpg</code></p>
        </div>
        </form>

                    <h2 id="workspaces-DELETEapi-workspaces--workspace_uuid--avatar">Remover a foto</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Idempotente: sem foto, responde 200 do mesmo jeito.</p>

<span id="example-requests-DELETEapi-workspaces--workspace_uuid--avatar">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/workspaces/45171527-92f1-4e21-ab9b-ab94a85acce4/avatar"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/workspaces/45171527-92f1-4e21-ab9b-ab94a85acce4/avatar" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-workspaces--workspace_uuid--avatar">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c&quot;,
        &quot;name&quot;: &quot;Ag&ecirc;ncia Aurora&quot;,
        &quot;avatar&quot;: null
    }
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-workspaces--workspace_uuid--avatar" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-workspaces--workspace_uuid--avatar"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-workspaces--workspace_uuid--avatar"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-workspaces--workspace_uuid--avatar" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-workspaces--workspace_uuid--avatar">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-workspaces--workspace_uuid--avatar" data-method="DELETE"
      data-path="api/workspaces/{workspace_uuid}/avatar"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-workspaces--workspace_uuid--avatar', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-workspaces--workspace_uuid--avatar"
                    onclick="tryItOut('DELETEapi-workspaces--workspace_uuid--avatar');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-workspaces--workspace_uuid--avatar"
                    onclick="cancelTryOut('DELETEapi-workspaces--workspace_uuid--avatar');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-workspaces--workspace_uuid--avatar"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/workspaces/{workspace_uuid}/avatar</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-workspaces--workspace_uuid--avatar"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-workspaces--workspace_uuid--avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-workspaces--workspace_uuid--avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace_uuid"                data-endpoint="DELETEapi-workspaces--workspace_uuid--avatar"
               value="45171527-92f1-4e21-ab9b-ab94a85acce4"
               data-component="url">
    <br>
<p>Example: <code>45171527-92f1-4e21-ab9b-ab94a85acce4</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace"                data-endpoint="DELETEapi-workspaces--workspace_uuid--avatar"
               value="7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c"
               data-component="url">
    <br>
<p>UUID do workspace. Example: <code>7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c</code></p>
            </div>
                    </form>

                <h1 id="usuarios">Usuários</h1>

    <p>Membros do workspace corrente. Para colocar alguém num workspace, prefira <strong>convites</strong>.</p>

                                <h2 id="usuarios-GETapi-users">Listar usuários</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>users.view</code></p>

<span id="example-requests-GETapi-users">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/users"
);

const params = {
    "per_page": "15",
    "page": "1",
    "name": "ana",
    "email": "@exemplo.com",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/users?per_page=15&amp;page=1&amp;name=ana&amp;email=%40exemplo.com" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-users">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;2bd8fa57-724f-4cfb-ae33-875afedee0e6&quot;,
            &quot;name&quot;: &quot;Ana Souza&quot;,
            &quot;email&quot;: &quot;ana@exemplo.com&quot;,
            &quot;email_verified_at&quot;: &quot;2026-10-10 16:52:25&quot;,
            &quot;created_at&quot;: &quot;2026-10-10 16:52:25&quot;,
            &quot;updated_at&quot;: &quot;2026-10-15 21:39:45&quot;,
            &quot;is_active&quot;: true,
            &quot;remember_me&quot;: false,
            &quot;access_id&quot;: 2,
            &quot;current_workspace&quot;: {
                &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
                &quot;name&quot;: &quot;Ag&ecirc;ncia Pandy&quot;
            }
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;users.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-users" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-users"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-users"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-users" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-users">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-users" data-method="GET"
      data-path="api/users"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-users', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-users"
                    onclick="tryItOut('GETapi-users');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-users"
                    onclick="cancelTryOut('GETapi-users');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-users"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/users</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-users"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-users"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-users"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-users"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-users"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="GETapi-users"
               value="ana"
               data-component="query">
    <br>
<p>Filtra por nome (parcial). Example: <code>ana</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="GETapi-users"
               value="@exemplo.com"
               data-component="query">
    <br>
<p>Filtra por email (parcial). Example: <code>@exemplo.com</code></p>
            </div>
                </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>current_workspace</code></b>&nbsp;&nbsp;
<small>object|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Workspace corrente <strong>do usuário listado</strong> (não o seu).</p>
        </div>
                        <h2 id="usuarios-POSTapi-users">Criar usuário</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Cria a conta de um usuário (com senha definida por quem cria).</p>
<p><strong>Permissão:</strong> <code>users.create</code></p>

<span id="example-requests-POSTapi-users">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/users"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/users" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-POSTapi-users">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Registro inserido com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;users.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Este e-mail j&aacute; est&aacute; cadastrado em nossa plataforma.&quot;,
    &quot;errors&quot;: {
        &quot;email&quot;: [
            &quot;Este e-mail j&aacute; est&aacute; cadastrado em nossa plataforma.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-users" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-users"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-users"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-users" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-users">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-users" data-method="POST"
      data-path="api/users"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-users', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-users"
                    onclick="tryItOut('POSTapi-users');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-users"
                    onclick="cancelTryOut('POSTapi-users');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-users"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/users</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-users"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-users"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-users"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="usuarios-GETapi-users--id-">Ver usuário</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>⚠️ <strong>BUG-01:</strong> hoje este endpoint falha (500 no PostgreSQL), ver <code>specs/002-security-hardening</code>. Não use até a correção.</p>
<p><strong>Permissão:</strong> <code>users.view</code></p>

<span id="example-requests-GETapi-users--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-users--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;2bd8fa57-724f-4cfb-ae33-875afedee0e6&quot;,
        &quot;name&quot;: &quot;Ana Souza&quot;,
        &quot;email&quot;: &quot;ana@exemplo.com&quot;,
        &quot;email_verified_at&quot;: &quot;2026-10-10 16:52:25&quot;,
        &quot;created_at&quot;: &quot;2026-10-10 16:52:25&quot;,
        &quot;updated_at&quot;: &quot;2026-10-15 21:39:45&quot;,
        &quot;is_active&quot;: true,
        &quot;remember_me&quot;: false,
        &quot;access_id&quot;: 2,
        &quot;current_workspace&quot;: {
            &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
            &quot;name&quot;: &quot;Ag&ecirc;ncia Pandy&quot;
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-users--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-users--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-users--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-users--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-users--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-users--id-" data-method="GET"
      data-path="api/users/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-users--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-users--id-"
                    onclick="tryItOut('GETapi-users--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-users--id-"
                    onclick="cancelTryOut('GETapi-users--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-users--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/users/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-users--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-users--id-"
               value="2bd8fa57-724f-4cfb-ae33-875afedee0e6"
               data-component="url">
    <br>
<p>UUID do usuário. Example: <code>2bd8fa57-724f-4cfb-ae33-875afedee0e6</code></p>
            </div>
                    </form>

                    <h2 id="usuarios-PUTapi-users--id-">Editar usuário</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>⚠️ <strong>BUG-01:</strong> hoje este endpoint falha (500 no PostgreSQL), ver <code>specs/002-security-hardening</code>. Não use até a correção.</p>
<p><strong>Permissão:</strong> <code>users.update</code></p>

<span id="example-requests-PUTapi-users--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Ana Souza"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Ana Souza\"
}"
</code></pre></div>

</span>

<span id="example-responses-PUTapi-users--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Registro atualizado com sucesso.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-users--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-users--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-users--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-users--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-users--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-users--id-" data-method="PUT"
      data-path="api/users/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-users--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-users--id-"
                    onclick="tryItOut('PUTapi-users--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-users--id-"
                    onclick="cancelTryOut('PUTapi-users--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-users--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/users/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/users/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-users--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-users--id-"
               value="2bd8fa57-724f-4cfb-ae33-875afedee0e6"
               data-component="url">
    <br>
<p>UUID do usuário. Example: <code>2bd8fa57-724f-4cfb-ae33-875afedee0e6</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-users--id-"
               value="Ana Souza"
               data-component="body">
    <br>
<p>Nome. Example: <code>Ana Souza</code></p>
        </div>
        </form>

                    <h2 id="usuarios-DELETEapi-users--id-">Remover usuário do workspace</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Tira o usuário do workspace corrente (a conta dele continua existindo). Se ele estava usando esse workspace,
é deslogado e volta para o workspace pessoal.</p>
<p><strong>Permissão:</strong> <code>users.delete</code></p>

<span id="example-requests-DELETEapi-users--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-users--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Usu&aacute;rio removido com sucesso&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, remover a si mesmo):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;cannot_remove_yourself&quot;,
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o pode remover a si mesmo do workspace.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, último membro):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;workspace_must_have_at_least_one_user&quot;,
    &quot;message&quot;: &quot;O workspace deve ter pelo menos um usu&aacute;rio.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;users.delete&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-users--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-users--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-users--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-users--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-users--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-users--id-" data-method="DELETE"
      data-path="api/users/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-users--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-users--id-"
                    onclick="tryItOut('DELETEapi-users--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-users--id-"
                    onclick="cancelTryOut('DELETEapi-users--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-users--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/users/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-users--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-users--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-users--id-"
               value="2bd8fa57-724f-4cfb-ae33-875afedee0e6"
               data-component="url">
    <br>
<p>UUID do usuário. Example: <code>2bd8fa57-724f-4cfb-ae33-875afedee0e6</code></p>
            </div>
                    </form>

                    <h2 id="usuarios-PATCHapi-users--user--role">Trocar perfil do usuário</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Muda o perfil (role) do usuário no workspace corrente.</p>
<p><strong>Permissão:</strong> <code>users.change_role</code></p>

<span id="example-requests-PATCHapi-users--user--role">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6/role"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "role_uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/users/2bd8fa57-724f-4cfb-ae33-875afedee0e6/role" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"role_uuid\": \"9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d\"
}"
</code></pre></div>

</span>

<span id="example-responses-PATCHapi-users--user--role">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Perfil do usu&aacute;rio atualizado com sucesso&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;users.change_role&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (404, perfil de outro workspace):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;profile_not_found&quot;,
    &quot;message&quot;: &quot;Perfil n&atilde;o encontrado no workspace.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-users--user--role" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-users--user--role"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-users--user--role"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-users--user--role" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-users--user--role">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-users--user--role" data-method="PATCH"
      data-path="api/users/{user}/role"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-users--user--role', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-users--user--role"
                    onclick="tryItOut('PATCHapi-users--user--role');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-users--user--role"
                    onclick="cancelTryOut('PATCHapi-users--user--role');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-users--user--role"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/users/{user}/role</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-users--user--role"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-users--user--role"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-users--user--role"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>user</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user"                data-endpoint="PATCHapi-users--user--role"
               value="2bd8fa57-724f-4cfb-ae33-875afedee0e6"
               data-component="url">
    <br>
<p>UUID do usuário. Example: <code>2bd8fa57-724f-4cfb-ae33-875afedee0e6</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>role_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="role_uuid"                data-endpoint="PATCHapi-users--user--role"
               value="9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
               data-component="body">
    <br>
<p>UUID de um perfil do workspace corrente. Example: <code>9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d</code></p>
        </div>
        </form>

                <h1 id="perfis-e-permissoes">Perfis e permissões</h1>

    <p>Perfis (roles) agrupam permissões dentro do workspace corrente. Cada membro tem um perfil por workspace.
Mudanças em perfis são transmitidas em tempo real (ver "Tempo real" na introdução).</p>

                                <h2 id="perfis-e-permissoes-GETapi-roles">Listar perfis</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Só nome e uuid. Para ver permissões e membros, use o detalhe.</p>
<p><strong>Permissão:</strong> <code>roles.view</code></p>

<span id="example-requests-GETapi-roles">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/roles"
);

const params = {
    "per_page": "15",
    "page": "1",
    "name": "edit",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/roles?per_page=15&amp;page=1&amp;name=edit" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-roles">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&quot;,
            &quot;name&quot;: &quot;Editor&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;roles.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-roles" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-roles"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-roles"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-roles" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-roles">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-roles" data-method="GET"
      data-path="api/roles"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-roles', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-roles"
                    onclick="tryItOut('GETapi-roles');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-roles"
                    onclick="cancelTryOut('GETapi-roles');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-roles"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/roles</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-roles"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-roles"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-roles"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="GETapi-roles"
               value="edit"
               data-component="query">
    <br>
<p>Filtra por nome (parcial). Example: <code>edit</code></p>
            </div>
                </form>

                    <h2 id="perfis-e-permissoes-POSTapi-roles">Criar perfil</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>roles.create</code></p>

<span id="example-requests-POSTapi-roles">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/roles"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Editor",
    "permissions": [
        "posts.view",
        "posts.create"
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/roles" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Editor\",
    \"permissions\": [
        \"posts.view\",
        \"posts.create\"
    ]
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-roles">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Perfil criado com sucesso&quot;,
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&quot;,
        &quot;name&quot;: &quot;Editor&quot;,
        &quot;permissions&quot;: [
            {
                &quot;name&quot;: &quot;posts.view&quot;,
                &quot;description&quot;: &quot;Visualizar posts&quot;,
                &quot;group&quot;: &quot;posts&quot;
            },
            {
                &quot;name&quot;: &quot;posts.create&quot;,
                &quot;description&quot;: &quot;Criar posts&quot;,
                &quot;group&quot;: &quot;posts&quot;
            }
        ],
        &quot;user_count&quot;: 0,
        &quot;users&quot;: []
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;roles.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, nome repetido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;role_name_already_exists&quot;,
    &quot;message&quot;: &quot;J&aacute; existe um perfil com esse nome neste workspace.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-roles" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-roles"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-roles"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-roles" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-roles">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-roles" data-method="POST"
      data-path="api/roles"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-roles', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-roles"
                    onclick="tryItOut('POSTapi-roles');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-roles"
                    onclick="cancelTryOut('POSTapi-roles');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-roles"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/roles</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-roles"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-roles"
               value="Editor"
               data-component="body">
    <br>
<p>Nome do perfil, único no workspace. Example: <code>Editor</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>permissions</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="permissions[0]"                data-endpoint="POSTapi-roles"
               data-component="body">
        <input type="text" style="display: none"
               name="permissions[1]"                data-endpoint="POSTapi-roles"
               data-component="body">
    <br>
<p>Nomes das permissões (ver <code>GET /permissions</code>). Substitui a lista inteira.</p>
        </div>
        </form>

                    <h2 id="perfis-e-permissoes-GETapi-roles--id-">Ver perfil</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Com permissões e membros.</p>
<p><strong>Permissão:</strong> <code>roles.view</code></p>

<span id="example-requests-GETapi-roles--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/roles/9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/roles/9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-roles--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&quot;,
        &quot;name&quot;: &quot;Editor&quot;,
        &quot;permissions&quot;: [
            {
                &quot;name&quot;: &quot;posts.view&quot;,
                &quot;description&quot;: &quot;Visualizar posts&quot;,
                &quot;group&quot;: &quot;posts&quot;
            },
            {
                &quot;name&quot;: &quot;posts.create&quot;,
                &quot;description&quot;: &quot;Criar posts&quot;,
                &quot;group&quot;: &quot;posts&quot;
            }
        ],
        &quot;user_count&quot;: 1,
        &quot;users&quot;: [
            {
                &quot;uuid&quot;: &quot;2bd8fa57-724f-4cfb-ae33-875afedee0e6&quot;,
                &quot;name&quot;: &quot;Ana Souza&quot;,
                &quot;email&quot;: &quot;ana@exemplo.com&quot;
            }
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-roles--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-roles--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-roles--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-roles--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-roles--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-roles--id-" data-method="GET"
      data-path="api/roles/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-roles--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-roles--id-"
                    onclick="tryItOut('GETapi-roles--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-roles--id-"
                    onclick="cancelTryOut('GETapi-roles--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-roles--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/roles/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-roles--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-roles--id-"
               value="9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
               data-component="url">
    <br>
<p>UUID do perfil. Example: <code>9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d</code></p>
            </div>
                    </form>

                    <h2 id="perfis-e-permissoes-PUTapi-roles--id-">Editar perfil</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Envie só o que mudar. Se mandar <code>permissions</code>, a lista é substituída.</p>
<p><strong>Permissão:</strong> <code>roles.update</code></p>

<span id="example-requests-PUTapi-roles--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/roles/9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Editor sênior",
    "permissions": [
        "posts.view"
    ]
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/roles/9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Editor sênior\",
    \"permissions\": [
        \"posts.view\"
    ]
}"
</code></pre></div>

</span>

<span id="example-responses-PUTapi-roles--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Perfil editado com sucesso&quot;,
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&quot;,
        &quot;name&quot;: &quot;Editor&quot;,
        &quot;permissions&quot;: [
            {
                &quot;name&quot;: &quot;posts.view&quot;,
                &quot;description&quot;: &quot;Visualizar posts&quot;,
                &quot;group&quot;: &quot;posts&quot;
            },
            {
                &quot;name&quot;: &quot;posts.create&quot;,
                &quot;description&quot;: &quot;Criar posts&quot;,
                &quot;group&quot;: &quot;posts&quot;
            }
        ],
        &quot;user_count&quot;: 1,
        &quot;users&quot;: [
            {
                &quot;uuid&quot;: &quot;2bd8fa57-724f-4cfb-ae33-875afedee0e6&quot;,
                &quot;name&quot;: &quot;Ana Souza&quot;,
                &quot;email&quot;: &quot;ana@exemplo.com&quot;
            }
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;roles.update&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, nome repetido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;role_name_already_exists&quot;,
    &quot;message&quot;: &quot;J&aacute; existe um perfil com esse nome neste workspace.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-roles--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-roles--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-roles--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-roles--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-roles--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-roles--id-" data-method="PUT"
      data-path="api/roles/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-roles--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-roles--id-"
                    onclick="tryItOut('PUTapi-roles--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-roles--id-"
                    onclick="cancelTryOut('PUTapi-roles--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-roles--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/roles/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/roles/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-roles--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-roles--id-"
               value="9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
               data-component="url">
    <br>
<p>UUID do perfil. Example: <code>9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-roles--id-"
               value="Editor sênior"
               data-component="body">
    <br>
<p>Novo nome, único no workspace. Example: <code>Editor sênior</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>permissions</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="permissions[0]"                data-endpoint="PUTapi-roles--id-"
               data-component="body">
        <input type="text" style="display: none"
               name="permissions[1]"                data-endpoint="PUTapi-roles--id-"
               data-component="body">
    <br>
<p>Nomes das permissões.</p>
        </div>
        </form>

                    <h2 id="perfis-e-permissoes-DELETEapi-roles--id-">Excluir perfil</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Não é possível excluir perfil com membros.</p>
<p><strong>Permissão:</strong> <code>roles.delete</code></p>

<span id="example-requests-DELETEapi-roles--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/roles/9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/roles/9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-roles--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Registro deletado com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;roles.delete&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, perfil com membros):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;profile_has_linked_users&quot;,
    &quot;message&quot;: &quot;N&atilde;o &eacute; poss&iacute;vel excluir um perfil com usu&aacute;rios vinculados.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-roles--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-roles--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-roles--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-roles--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-roles--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-roles--id-" data-method="DELETE"
      data-path="api/roles/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-roles--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-roles--id-"
                    onclick="tryItOut('DELETEapi-roles--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-roles--id-"
                    onclick="cancelTryOut('DELETEapi-roles--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-roles--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/roles/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-roles--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-roles--id-"
               value="9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
               data-component="url">
    <br>
<p>UUID do perfil. Example: <code>9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d</code></p>
            </div>
                    </form>

                    <h2 id="perfis-e-permissoes-GETapi-permissions">Listar permissões</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Catálogo de todas as permissões do sistema. Use <code>group</code> para montar a tela de edição de perfil.</p>
<p><strong>Permissão:</strong> <code>permissions.view</code></p>

<span id="example-requests-GETapi-permissions">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/permissions"
);

const params = {
    "per_page": "15",
    "page": "1",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/permissions?per_page=15&amp;page=1" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-permissions">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;name&quot;: &quot;posts.view&quot;,
            &quot;description&quot;: &quot;Visualizar posts&quot;,
            &quot;group&quot;: &quot;posts&quot;
        },
        {
            &quot;name&quot;: &quot;posts.create&quot;,
            &quot;description&quot;: &quot;Criar posts&quot;,
            &quot;group&quot;: &quot;posts&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 2
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;permissions.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-permissions" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-permissions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-permissions"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-permissions" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-permissions">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-permissions" data-method="GET"
      data-path="api/permissions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-permissions', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-permissions"
                    onclick="tryItOut('GETapi-permissions');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-permissions"
                    onclick="cancelTryOut('GETapi-permissions');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-permissions"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/permissions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-permissions"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-permissions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-permissions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-permissions"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-permissions"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                </form>

                    <h2 id="perfis-e-permissoes-GETapi-permissions--permission-">Ver permissão</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>permissions.view</code></p>

<span id="example-requests-GETapi-permissions--permission-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/permissions/1"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/permissions/1" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-permissions--permission-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;name&quot;: &quot;posts.view&quot;,
        &quot;description&quot;: &quot;Visualizar posts&quot;,
        &quot;group&quot;: &quot;posts&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-permissions--permission-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-permissions--permission-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-permissions--permission-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-permissions--permission-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-permissions--permission-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-permissions--permission-" data-method="GET"
      data-path="api/permissions/{permission}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-permissions--permission-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-permissions--permission-"
                    onclick="tryItOut('GETapi-permissions--permission-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-permissions--permission-"
                    onclick="cancelTryOut('GETapi-permissions--permission-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-permissions--permission-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/permissions/{permission}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-permissions--permission-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-permissions--permission-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-permissions--permission-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>permission</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="permission"                data-endpoint="GETapi-permissions--permission-"
               value="1"
               data-component="url">
    <br>
<p><strong>ID numérico</strong> da permissão (exceção à regra do uuid). Example: <code>1</code></p>
            </div>
                    </form>

                <h1 id="acessos">Acessos</h1>

    <p>Vínculo bruto usuário ↔ perfil no workspace corrente. <strong>Usa IDs numéricos</strong>, não uuid.</p>
<aside class="notice">Para o fluxo normal, use convites (<code>/invites</code>) e troca de perfil
(<code>PATCH /users/{user}/role</code>). A permanência destas rotas está em discussão em <code>specs/002</code> (Q2).</aside>

                                <h2 id="acessos-GETapi-accesses">Listar acessos</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>accesses.view</code></p>
<aside class="notice">Formato: paginador "cru" do Laravel (itens em <code>data</code>, paginação na raiz).</aside>

<span id="example-requests-GETapi-accesses">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/accesses"
);

const params = {
    "per_page": "15",
    "page": "1",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/accesses?per_page=15&amp;page=1" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-accesses">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;current_page&quot;: 1,
    &quot;data&quot;: [
        {
            &quot;id&quot;: 2,
            &quot;user_id&quot;: 2,
            &quot;role_id&quot;: 2,
            &quot;workspace_id&quot;: 2,
            &quot;created_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;
        }
    ],
    &quot;per_page&quot;: 1,
    &quot;total&quot;: 1,
    &quot;last_page&quot;: 1
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;accesses.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-accesses" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-accesses"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-accesses"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-accesses" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-accesses">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-accesses" data-method="GET"
      data-path="api/accesses"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-accesses', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-accesses"
                    onclick="tryItOut('GETapi-accesses');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-accesses"
                    onclick="cancelTryOut('GETapi-accesses');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-accesses"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/accesses</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-accesses"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-accesses"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-accesses"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-accesses"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-accesses"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                </form>

                    <h2 id="acessos-POSTapi-accesses">Criar acesso</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Coloca um usuário no workspace corrente com um perfil.</p>
<p><strong>Permissão:</strong> <code>accesses.create</code></p>

<span id="example-requests-POSTapi-accesses">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/accesses"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "user_id": 5,
    "role_id": 2
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/accesses" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"user_id\": 5,
    \"role_id\": 2
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-accesses">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Registro inserido com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;accesses.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O perfil informado n&atilde;o existe neste workspace.&quot;,
    &quot;errors&quot;: {
        &quot;role_id&quot;: [
            &quot;O perfil informado n&atilde;o existe neste workspace.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-accesses" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-accesses"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-accesses"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-accesses" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-accesses">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-accesses" data-method="POST"
      data-path="api/accesses"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-accesses', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-accesses"
                    onclick="tryItOut('POSTapi-accesses');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-accesses"
                    onclick="cancelTryOut('POSTapi-accesses');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-accesses"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/accesses</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-accesses"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-accesses"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-accesses"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="user_id"                data-endpoint="POSTapi-accesses"
               value="5"
               data-component="body">
    <br>
<p>ID do usuário. Example: <code>5</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>role_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="role_id"                data-endpoint="POSTapi-accesses"
               value="2"
               data-component="body">
    <br>
<p>ID de um perfil do workspace corrente. Example: <code>2</code></p>
        </div>
        </form>

                    <h2 id="acessos-GETapi-accesses--id-">Ver acesso</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>accesses.view</code></p>

<span id="example-requests-GETapi-accesses--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/accesses/2"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/accesses/2" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-accesses--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 2,
    &quot;user_id&quot;: 2,
    &quot;role_id&quot;: 2,
    &quot;workspace_id&quot;: 2,
    &quot;created_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;,
    &quot;updated_at&quot;: &quot;2026-10-10T16:52:25.000000Z&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-accesses--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-accesses--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-accesses--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-accesses--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-accesses--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-accesses--id-" data-method="GET"
      data-path="api/accesses/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-accesses--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-accesses--id-"
                    onclick="tryItOut('GETapi-accesses--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-accesses--id-"
                    onclick="cancelTryOut('GETapi-accesses--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-accesses--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/accesses/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-accesses--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-accesses--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-accesses--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="GETapi-accesses--id-"
               value="2"
               data-component="url">
    <br>
<p>ID do acesso. Example: <code>2</code></p>
            </div>
                    </form>

                    <h2 id="acessos-PUTapi-accesses--id-">Editar acesso</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>⚠️ <strong>BUG-01:</strong> hoje este endpoint falha (500 no PostgreSQL), ver <code>specs/002-security-hardening</code>. Não use até a correção.</p>
<p><strong>Permissão:</strong> <code>accesses.update</code></p>

<span id="example-requests-PUTapi-accesses--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/accesses/2"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "PUT",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/accesses/2" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-PUTapi-accesses--id-">
</span>
<span id="execution-results-PUTapi-accesses--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-accesses--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-accesses--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-accesses--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-accesses--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-accesses--id-" data-method="PUT"
      data-path="api/accesses/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-accesses--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-accesses--id-"
                    onclick="tryItOut('PUTapi-accesses--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-accesses--id-"
                    onclick="cancelTryOut('PUTapi-accesses--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-accesses--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/accesses/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/accesses/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-accesses--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-accesses--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-accesses--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="PUTapi-accesses--id-"
               value="2"
               data-component="url">
    <br>
<p>ID do acesso. Example: <code>2</code></p>
            </div>
                    </form>

                    <h2 id="acessos-DELETEapi-accesses--id-">Excluir acesso</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>accesses.delete</code></p>

<span id="example-requests-DELETEapi-accesses--id-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/accesses/2"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/accesses/2" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-accesses--id-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Registro deletado com sucesso.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-accesses--id-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-accesses--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-accesses--id-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-accesses--id-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-accesses--id-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-accesses--id-" data-method="DELETE"
      data-path="api/accesses/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-accesses--id-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-accesses--id-"
                    onclick="tryItOut('DELETEapi-accesses--id-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-accesses--id-"
                    onclick="cancelTryOut('DELETEapi-accesses--id-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-accesses--id-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/accesses/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-accesses--id-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-accesses--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-accesses--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="id"                data-endpoint="DELETEapi-accesses--id-"
               value="2"
               data-component="url">
    <br>
<p>ID do acesso. Example: <code>2</code></p>
            </div>
                    </form>

                <h1 id="convites">Convites</h1>

    <p>Convidar pessoas para o workspace corrente e responder convites recebidos.
O convidado recebe um email; ao aceitar, entra no workspace com o perfil escolhido.</p>
<p><code>status</code>: <code>pending</code>, <code>accepted</code>, <code>declined</code> ou <code>expired</code>.</p>

                                <h2 id="convites-GETapi-me-invites">Convites recebidos</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Convites para o usuário logado, de qualquer workspace. Não exige permissão.</p>

<span id="example-requests-GETapi-me-invites">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me/invites"
);

const params = {
    "per_page": "15",
    "page": "1",
    "sender": "ana@",
    "workspace_uuid": "58d7d732-10a4-491d-9864-b4d85dfcf833",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/me/invites?per_page=15&amp;page=1&amp;sender=ana%40&amp;workspace_uuid=58d7d732-10a4-491d-9864-b4d85dfcf833" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-me-invites">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;c7e9...&quot;,
            &quot;invitedBy&quot;: {
                &quot;uuid&quot;: &quot;2bd8...&quot;,
                &quot;name&quot;: &quot;Ana Souza&quot;,
                &quot;email&quot;: &quot;ana@exemplo.com&quot;
            },
            &quot;workspace&quot;: {
                &quot;uuid&quot;: &quot;58d7d732-10a4-491d-9864-b4d85dfcf833&quot;,
                &quot;name&quot;: &quot;Ag&ecirc;ncia Pandy&quot;
            },
            &quot;role&quot;: {
                &quot;uuid&quot;: &quot;9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&quot;,
                &quot;name&quot;: &quot;Editor&quot;
            },
            &quot;status&quot;: &quot;pending&quot;,
            &quot;created_at&quot;: &quot;2026-10-15 10:00:00&quot;,
            &quot;expires_at&quot;: &quot;2026-10-22 10:00:00&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-me-invites" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-me-invites"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-me-invites"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-me-invites" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-me-invites">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-me-invites" data-method="GET"
      data-path="api/me/invites"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-me-invites', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-me-invites"
                    onclick="tryItOut('GETapi-me-invites');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-me-invites"
                    onclick="cancelTryOut('GETapi-me-invites');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-me-invites"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/me/invites</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-me-invites"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-me-invites"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-me-invites"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-me-invites"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-me-invites"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>sender</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="sender"                data-endpoint="GETapi-me-invites"
               value="ana@"
               data-component="query">
    <br>
<p>Filtra pelo email de quem convidou (parcial). Example: <code>ana@</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>workspace_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="workspace_uuid"                data-endpoint="GETapi-me-invites"
               value="58d7d732-10a4-491d-9864-b4d85dfcf833"
               data-component="query">
    <br>
<p>Filtra pelo workspace. Example: <code>58d7d732-10a4-491d-9864-b4d85dfcf833</code></p>
            </div>
                </form>

                    <h2 id="convites-PATCHapi-me-invites--invite_uuid--accept">Aceitar convite</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>O usuário passa a ser membro do workspace. O workspace corrente não muda.</p>

<span id="example-requests-PATCHapi-me-invites--invite_uuid--accept">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me/invites/c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b/accept"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "PATCH",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/me/invites/c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b/accept" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-PATCHapi-me-invites--invite_uuid--accept">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Convite aceito com sucesso&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, já respondido ou expirado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invite_not_pending&quot;,
    &quot;message&quot;: &quot;Este convite j&aacute; foi processado ou expirou.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, convite de outra pessoa):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;not_your_invite&quot;,
    &quot;message&quot;: &quot;Este convite pertence a outro usu&aacute;rio.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-me-invites--invite_uuid--accept" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-me-invites--invite_uuid--accept"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-me-invites--invite_uuid--accept"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-me-invites--invite_uuid--accept" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-me-invites--invite_uuid--accept">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-me-invites--invite_uuid--accept" data-method="PATCH"
      data-path="api/me/invites/{invite_uuid}/accept"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-me-invites--invite_uuid--accept', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-me-invites--invite_uuid--accept"
                    onclick="tryItOut('PATCHapi-me-invites--invite_uuid--accept');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-me-invites--invite_uuid--accept"
                    onclick="cancelTryOut('PATCHapi-me-invites--invite_uuid--accept');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-me-invites--invite_uuid--accept"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/me/invites/{invite_uuid}/accept</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-me-invites--invite_uuid--accept"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-me-invites--invite_uuid--accept"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-me-invites--invite_uuid--accept"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>invite_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="invite_uuid"                data-endpoint="PATCHapi-me-invites--invite_uuid--accept"
               value="c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b"
               data-component="url">
    <br>
<p>UUID do convite. Example: <code>c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b</code></p>
            </div>
                    </form>

                    <h2 id="convites-PATCHapi-me-invites--invite_uuid--decline">Recusar convite</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-me-invites--invite_uuid--decline">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me/invites/c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b/decline"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "PATCH",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/me/invites/c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b/decline" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-PATCHapi-me-invites--invite_uuid--decline">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Convite rejeitado com sucesso&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, já respondido ou expirado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invite_not_pending&quot;,
    &quot;message&quot;: &quot;Este convite j&aacute; foi processado ou expirou.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, convite de outra pessoa):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;not_your_invite&quot;,
    &quot;message&quot;: &quot;Este convite pertence a outro usu&aacute;rio.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-me-invites--invite_uuid--decline" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-me-invites--invite_uuid--decline"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-me-invites--invite_uuid--decline"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-me-invites--invite_uuid--decline" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-me-invites--invite_uuid--decline">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-me-invites--invite_uuid--decline" data-method="PATCH"
      data-path="api/me/invites/{invite_uuid}/decline"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-me-invites--invite_uuid--decline', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-me-invites--invite_uuid--decline"
                    onclick="tryItOut('PATCHapi-me-invites--invite_uuid--decline');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-me-invites--invite_uuid--decline"
                    onclick="cancelTryOut('PATCHapi-me-invites--invite_uuid--decline');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-me-invites--invite_uuid--decline"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/me/invites/{invite_uuid}/decline</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-me-invites--invite_uuid--decline"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-me-invites--invite_uuid--decline"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-me-invites--invite_uuid--decline"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>invite_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="invite_uuid"                data-endpoint="PATCHapi-me-invites--invite_uuid--decline"
               value="c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b"
               data-component="url">
    <br>
<p>UUID do convite. Example: <code>c7e9a0b1-2c3d-4e5f-8a9b-0c1d2e3f4a5b</code></p>
            </div>
                    </form>

                    <h2 id="convites-GETapi-invites">Convites enviados</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Convites do workspace corrente.</p>
<p><strong>Permissão:</strong> <code>invites.view</code></p>

<span id="example-requests-GETapi-invites">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/invites"
);

const params = {
    "per_page": "15",
    "page": "1",
    "role_uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d",
    "recipient": "joao@",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/invites?per_page=15&amp;page=1&amp;role_uuid=9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&amp;recipient=joao%40" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-invites">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;c7e9...&quot;,
            &quot;recipient&quot;: {
                &quot;uuid&quot;: &quot;a1b2...&quot;,
                &quot;name&quot;: &quot;Jo&atilde;o Lima&quot;,
                &quot;email&quot;: &quot;joao@exemplo.com&quot;
            },
            &quot;role&quot;: {
                &quot;uuid&quot;: &quot;9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&quot;,
                &quot;name&quot;: &quot;Editor&quot;
            },
            &quot;status&quot;: &quot;pending&quot;,
            &quot;created_at&quot;: &quot;2026-10-15 10:00:00&quot;,
            &quot;updated_at&quot;: &quot;2026-10-15 10:00:00&quot;,
            &quot;expires_at&quot;: &quot;2026-10-22 10:00:00&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;invites.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-invites" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-invites"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-invites"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-invites" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-invites">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-invites" data-method="GET"
      data-path="api/invites"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-invites', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-invites"
                    onclick="tryItOut('GETapi-invites');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-invites"
                    onclick="cancelTryOut('GETapi-invites');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-invites"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/invites</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-invites"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-invites"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-invites"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-invites"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-invites"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>role_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="role_uuid"                data-endpoint="GETapi-invites"
               value="9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
               data-component="query">
    <br>
<p>Filtra pelo perfil. Example: <code>9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>recipient</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="recipient"                data-endpoint="GETapi-invites"
               value="joao@"
               data-component="query">
    <br>
<p>Filtra pelo email do convidado (parcial). Example: <code>joao@</code></p>
            </div>
                </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>recipient</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Se o convidado ainda não tem conta, vem só <code>{"email": "..."}</code>.</p>
        </div>
                        <h2 id="convites-POSTapi-invites">Enviar convite</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Convida um email (com ou sem conta) para o workspace corrente.</p>
<p><strong>Permissão:</strong> <code>invites.create</code></p>

<span id="example-requests-POSTapi-invites">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/invites"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "joao@exemplo.com",
    "role_uuid": "9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/invites" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"joao@exemplo.com\",
    \"role_uuid\": \"9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-invites">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Convite enviado com sucesso&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;invites.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, já convidado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;already_invited&quot;,
    &quot;message&quot;: &quot;Usu&aacute;rio j&aacute; foi convidado para este workspace.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, já é membro):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;already_member&quot;,
    &quot;message&quot;: &quot;Este usu&aacute;rio j&aacute; &eacute; membro deste workspace.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-invites" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-invites"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-invites"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-invites" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-invites">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-invites" data-method="POST"
      data-path="api/invites"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-invites', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-invites"
                    onclick="tryItOut('POSTapi-invites');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-invites"
                    onclick="cancelTryOut('POSTapi-invites');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-invites"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/invites</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-invites"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-invites"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-invites"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-invites"
               value="joao@exemplo.com"
               data-component="body">
    <br>
<p>Email do convidado. Example: <code>joao@exemplo.com</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>role_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="role_uuid"                data-endpoint="POSTapi-invites"
               value="9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d"
               data-component="body">
    <br>
<p>UUID do perfil que ele terá. Example: <code>9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d</code></p>
        </div>
        </form>

                <h1 id="contas-sociais">Contas sociais</h1>

    <p>Contas de YouTube, Instagram e TikTok conectadas ao workspace corrente.</p>
<p><strong>Como conectar uma conta (OAuth em popup):</strong></p>
<ol>
<li><code>GET /social-accounts/{platform}/auth</code> → recebe <code>url</code>.</li>
<li>Abra <code>url</code> num popup. O usuário autoriza na plataforma.</li>
<li>A plataforma chama o callback da API, que redireciona o popup para
<code>{FRONTEND_URL}/social-accounts/callback?success=1</code> ou <code>?error=mensagem</code>.</li>
<li>Nessa página, avise a janela principal (ex.: <code>window.opener.postMessage</code>) e recarregue a lista.</li>
</ol>

                                <h2 id="contas-sociais-GETapi-social-accounts--platform--callback">Callback do OAuth</h2>

<p>
</p>

<p><strong>Não chame pelo frontend.</strong> É a URL de retorno cadastrada na plataforma. A API conecta a conta e redireciona
o popup para <code>{FRONTEND_URL}/social-accounts/callback</code> com:</p>
<ul>
<li><code>?success=1</code> → conta conectada;</li>
<li><code>?error=&lt;mensagem&gt;</code> → falha (mensagem pronta para exibir).</li>
</ul>

<span id="example-requests-GETapi-social-accounts--platform--callback">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/social-accounts/youtube/callback"
);

const params = {
    "code": "",
    "state": "",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/social-accounts/youtube/callback?code=&amp;state=" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-social-accounts--platform--callback">
            <blockquote>
            <p>Exemplo de resposta (302, redireciona para o frontend):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">&quot;&quot;</code>
 </pre>
    </span>
<span id="execution-results-GETapi-social-accounts--platform--callback" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-social-accounts--platform--callback"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-social-accounts--platform--callback"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-social-accounts--platform--callback" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-social-accounts--platform--callback">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-social-accounts--platform--callback" data-method="GET"
      data-path="api/social-accounts/{platform}/callback"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-social-accounts--platform--callback', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-social-accounts--platform--callback"
                    onclick="tryItOut('GETapi-social-accounts--platform--callback');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-social-accounts--platform--callback"
                    onclick="cancelTryOut('GETapi-social-accounts--platform--callback');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-social-accounts--platform--callback"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/social-accounts/{platform}/callback</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-social-accounts--platform--callback"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-social-accounts--platform--callback"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="GETapi-social-accounts--platform--callback"
               value="youtube"
               data-component="url">
    <br>
<p>Plataforma. Example: <code>youtube</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>youtube</code></li> <li><code>instagram</code></li> <li><code>tiktok</code></li></ul>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="GETapi-social-accounts--platform--callback"
               value=""
               data-component="query">
    <br>
<p>Código de autorização enviado pela plataforma.</p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>state</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="state"                data-endpoint="GETapi-social-accounts--platform--callback"
               value=""
               data-component="query">
    <br>
<p>Estado gerado no passo 1.</p>
            </div>
                </form>

                    <h2 id="contas-sociais-GETapi-social-accounts">Listar contas conectadas</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>social_accounts.view</code></p>

<span id="example-requests-GETapi-social-accounts">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/social-accounts"
);

const params = {
    "per_page": "15",
    "page": "1",
    "platform": "youtube",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/social-accounts?per_page=15&amp;page=1&amp;platform=youtube" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-social-accounts">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;5f0c2d1e-...&quot;,
            &quot;platform&quot;: &quot;youtube&quot;,
            &quot;platform_id&quot;: &quot;UCx9...&quot;,
            &quot;nickname&quot;: &quot;Canal da Ana&quot;,
            &quot;avatar&quot;: &quot;https://yt3.ggpht.com/...&quot;,
            &quot;expires_at&quot;: &quot;2026-10-15T15:00:00-03:00&quot;,
            &quot;connected_at&quot;: &quot;2026-10-01T10:00:00-03:00&quot;,
            &quot;is_stale&quot;: false,
            &quot;days_since_last_activity&quot;: 1
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;social_accounts.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-social-accounts" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-social-accounts"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-social-accounts"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-social-accounts" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-social-accounts">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-social-accounts" data-method="GET"
      data-path="api/social-accounts"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-social-accounts', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-social-accounts"
                    onclick="tryItOut('GETapi-social-accounts');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-social-accounts"
                    onclick="cancelTryOut('GETapi-social-accounts');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-social-accounts"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/social-accounts</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-social-accounts"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-social-accounts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-social-accounts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-social-accounts"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-social-accounts"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="GETapi-social-accounts"
               value="youtube"
               data-component="query">
    <br>
<p>Filtra pela plataforma. Example: <code>youtube</code></p>
            </div>
                </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_stale</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p><code>true</code> se a conta está 2+ dias sem postar ou nunca postou.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>days_since_last_activity</code></b>&nbsp;&nbsp;
<small>integer|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Dias desde o último post (0 se há post agendado no futuro). <code>null</code> = nunca postou.</p>
        </div>
                        <h2 id="contas-sociais-GETapi-social-accounts-schedules">Contas com seus agendamentos</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Mesma lista de contas, cada uma com <code>scheduled_posts</code> (formato de <a href="#agendamentos">Agendamentos</a>). Útil para o calendário.</p>

<span id="example-requests-GETapi-social-accounts-schedules">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/social-accounts/schedules"
);

const params = {
    "per_page": "15",
    "page": "1",
    "platform": "instagram",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/social-accounts/schedules?per_page=15&amp;page=1&amp;platform=instagram" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-social-accounts-schedules">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;5f0c2d1e-...&quot;,
            &quot;platform&quot;: &quot;youtube&quot;,
            &quot;platform_id&quot;: &quot;UCx9...&quot;,
            &quot;nickname&quot;: &quot;Canal da Ana&quot;,
            &quot;avatar&quot;: &quot;https://yt3.ggpht.com/...&quot;,
            &quot;expires_at&quot;: &quot;2026-10-15T15:00:00-03:00&quot;,
            &quot;connected_at&quot;: &quot;2026-10-01T10:00:00-03:00&quot;,
            &quot;is_stale&quot;: false,
            &quot;days_since_last_activity&quot;: 1,
            &quot;scheduled_posts&quot;: [
                {
                    &quot;uuid&quot;: &quot;e1d2...&quot;,
                    &quot;platform&quot;: &quot;youtube&quot;,
                    &quot;title&quot;: &quot;Meu v&iacute;deo&quot;,
                    &quot;status&quot;: &quot;pending&quot;,
                    &quot;scheduled_at&quot;: &quot;2026-10-20T18:00:00-03:00&quot;
                }
            ]
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-social-accounts-schedules" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-social-accounts-schedules"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-social-accounts-schedules"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-social-accounts-schedules" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-social-accounts-schedules">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-social-accounts-schedules" data-method="GET"
      data-path="api/social-accounts/schedules"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-social-accounts-schedules', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-social-accounts-schedules"
                    onclick="tryItOut('GETapi-social-accounts-schedules');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-social-accounts-schedules"
                    onclick="cancelTryOut('GETapi-social-accounts-schedules');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-social-accounts-schedules"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/social-accounts/schedules</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-social-accounts-schedules"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-social-accounts-schedules"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-social-accounts-schedules"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-social-accounts-schedules"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-social-accounts-schedules"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="GETapi-social-accounts-schedules"
               value="instagram"
               data-component="query">
    <br>
<p>Filtra pela plataforma. Example: <code>instagram</code></p>
            </div>
                </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>data</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
 &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style=" margin-left: 14px; clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>scheduled_posts</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
 &nbsp;
 &nbsp;
<br>

            </summary>
                                                <div style="margin-left: 28px; clear: unset;">
                        <b style="line-height: 2;"><code>can_retry</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p><code>true</code> quando o post falhou e o arquivo ainda está guardado: mostre o botão Reenviar (<code>POST /scheduled-posts/{uuid}/retry</code>).</p>
                    </div>
                                                                <div style="margin-left: 28px; clear: unset;">
                        <b style="line-height: 2;"><code>retry_until</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Até quando o arquivo fica guardado para reenvio (5 dias após a falha).</p>
                    </div>
                                                                <div style="margin-left: 28px; clear: unset;">
                        <b style="line-height: 2;"><code>platform_post_url</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Link do post publicado. No Instagram vem do permalink da rede; pode ser <code>null</code> se não foi possível obtê-lo.</p>
                    </div>
                                    </details>
        </div>
                                        </details>
        </div>
                        <h2 id="contas-sociais-GETapi-social-accounts--platform--auth">Iniciar conexão (URL de OAuth)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Devolve a URL de autorização da plataforma. <strong>Não redireciona</strong>: abra a URL num popup (passo 1 do fluxo acima).</p>
<p><strong>Permissão:</strong> <code>social_accounts.connect</code></p>

<span id="example-requests-GETapi-social-accounts--platform--auth">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/social-accounts/youtube/auth"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/social-accounts/youtube/auth" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-social-accounts--platform--auth">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;url&quot;: &quot;https://accounts.google.com/o/oauth2/v2/auth?client_id=...&amp;state=...&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, plataforma inválida):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;platform_not_supported&quot;,
    &quot;message&quot;: &quot;Plataforma &#039;x&#039; n&atilde;o &eacute; suportada.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;social_accounts.connect&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (500, credenciais da plataforma ausentes):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;oauth_initialization_failed&quot;,
    &quot;message&quot;: &quot;Falha ao iniciar o fluxo OAuth.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-social-accounts--platform--auth" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-social-accounts--platform--auth"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-social-accounts--platform--auth"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-social-accounts--platform--auth" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-social-accounts--platform--auth">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-social-accounts--platform--auth" data-method="GET"
      data-path="api/social-accounts/{platform}/auth"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-social-accounts--platform--auth', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-social-accounts--platform--auth"
                    onclick="tryItOut('GETapi-social-accounts--platform--auth');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-social-accounts--platform--auth"
                    onclick="cancelTryOut('GETapi-social-accounts--platform--auth');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-social-accounts--platform--auth"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/social-accounts/{platform}/auth</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-social-accounts--platform--auth"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-social-accounts--platform--auth"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-social-accounts--platform--auth"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="GETapi-social-accounts--platform--auth"
               value="youtube"
               data-component="url">
    <br>
<p>Plataforma. Example: <code>youtube</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>youtube</code></li> <li><code>instagram</code></li> <li><code>tiktok</code></li></ul>
            </div>
                    </form>

                    <h2 id="contas-sociais-DELETEapi-social-accounts--socialAccount-">Desconectar conta</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Remove a conta e <strong>cancela os agendamentos pendentes</strong> dela.</p>
<p><strong>Permissão:</strong> <code>social_accounts.disconnect</code></p>

<span id="example-requests-DELETEapi-social-accounts--socialAccount-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/social-accounts/5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/social-accounts/5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-social-accounts--socialAccount-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Conta social desconectada com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;social_accounts.disconnect&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-social-accounts--socialAccount-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-social-accounts--socialAccount-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-social-accounts--socialAccount-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-social-accounts--socialAccount-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-social-accounts--socialAccount-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-social-accounts--socialAccount-" data-method="DELETE"
      data-path="api/social-accounts/{socialAccount}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-social-accounts--socialAccount-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-social-accounts--socialAccount-"
                    onclick="tryItOut('DELETEapi-social-accounts--socialAccount-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-social-accounts--socialAccount-"
                    onclick="cancelTryOut('DELETEapi-social-accounts--socialAccount-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-social-accounts--socialAccount-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/social-accounts/{socialAccount}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-social-accounts--socialAccount-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-social-accounts--socialAccount-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-social-accounts--socialAccount-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>socialAccount</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="socialAccount"                data-endpoint="DELETEapi-social-accounts--socialAccount-"
               value="5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID da conta. Example: <code>5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                    </form>

                <h1 id="agendamentos">Agendamentos</h1>

    <p>Publicação e agendamento de vídeos/imagens nas contas conectadas.</p>
<p><strong>Fluxo de publicação:</strong></p>
<ol>
<li><code>POST /scheduled-posts/upload-url</code> → recebe <code>url</code> e <code>path</code>.</li>
<li>Envie o arquivo com <code>PUT {url}</code> direto para o S3, com o header <code>Content-Type</code> igual ao <code>content_type</code> pedido
(e os <code>headers</code> devolvidos). Não passa pela API.</li>
<li><code>POST /scheduled-posts</code> com o <code>path</code> em <code>media_storage_path</code> e as contas escolhidas.
É criado <strong>um post por conta</strong>. Sem <code>scheduled_at</code>, publica na hora.</li>
</ol>
<p><code>status</code>: <code>pending</code> → <code>processing</code> → <code>published</code> ou <code>failed</code>; <code>cancelled</code> quando cancelado.
Post <code>failed</code> guarda o arquivo por 5 dias e pode ser reenviado em <code>POST /scheduled-posts/{uuid}/retry</code>.</p>

                                <h2 id="agendamentos-GETapi-scheduled-posts">Listar agendamentos</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Posts do workspace corrente, mais recentes primeiro.</p>
<p><strong>Permissão:</strong> <code>posts.view</code></p>

<span id="example-requests-GETapi-scheduled-posts">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/scheduled-posts"
);

const params = {
    "per_page": "15",
    "page": "1",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/scheduled-posts?per_page=15&amp;page=1" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-scheduled-posts">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;e1d2c3b4-...&quot;,
            &quot;responsible&quot;: &quot;Ana Souza&quot;,
            &quot;media_url&quot;: &quot;https://bucket.s3.amazonaws.com/workspaces/.../videos/abc.mp4?X-Amz-Signature=...&quot;,
            &quot;total_posts_for_account&quot;: 12,
            &quot;failed_posts_for_account&quot;: 1,
            &quot;platform&quot;: &quot;youtube&quot;,
            &quot;title&quot;: &quot;Meu v&iacute;deo&quot;,
            &quot;caption&quot;: &quot;Descri&ccedil;&atilde;o do v&iacute;deo&quot;,
            &quot;status&quot;: &quot;pending&quot;,
            &quot;scheduled_at&quot;: &quot;2026-10-20T18:00:00-03:00&quot;,
            &quot;published_at&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;,
            &quot;platform_post_id&quot;: null,
            &quot;platform_post_url&quot;: null,
            &quot;payload&quot;: {
                &quot;youtube_privacy_status&quot;: &quot;public&quot;,
                &quot;youtube_tags&quot;: [
                    &quot;vlog&quot;
                ],
                &quot;is_short&quot;: false
            }
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-scheduled-posts" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-scheduled-posts"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-scheduled-posts"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-scheduled-posts" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-scheduled-posts">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-scheduled-posts" data-method="GET"
      data-path="api/scheduled-posts"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-scheduled-posts', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-scheduled-posts"
                    onclick="tryItOut('GETapi-scheduled-posts');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-scheduled-posts"
                    onclick="cancelTryOut('GETapi-scheduled-posts');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-scheduled-posts"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/scheduled-posts</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-scheduled-posts"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-scheduled-posts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-scheduled-posts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-scheduled-posts"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-scheduled-posts"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_url</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>URL temporária (assinada) para exibir a mídia.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform_post_url</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Link do post publicado (YouTube e Instagram).</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>error_message</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Só aparece quando <code>status</code> = <code>failed</code>.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>can_retry</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p><code>true</code> quando o post falhou e o arquivo ainda está guardado: mostre o botão Reenviar.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>retry_until</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Até quando o arquivo fica guardado para reenvio (5 dias após a falha). <code>null</code> se não dá para reenviar.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>payload</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Opções específicas da plataforma enviadas no agendamento.</p>
        </div>
                        <h2 id="agendamentos-POSTapi-scheduled-posts-upload-url">Gerar URL de upload</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Passo 1 do fluxo. A URL é temporária (<code>expires_in</code> segundos) e serve para <strong>um</strong> arquivo.</p>
<p><strong>Permissão:</strong> <code>posts.create</code></p>

<span id="example-requests-POSTapi-scheduled-posts-upload-url">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/scheduled-posts/upload-url"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "content_type": "video\/mp4",
    "directory": "videos"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/scheduled-posts/upload-url" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"content_type\": \"video\\/mp4\",
    \"directory\": \"videos\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-scheduled-posts-upload-url">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;url&quot;: &quot;https://bucket.s3.amazonaws.com/workspaces/58d7.../videos/9f8e....mp4?X-Amz-Signature=...&quot;,
    &quot;headers&quot;: {
        &quot;Content-Type&quot;: &quot;video/mp4&quot;
    },
    &quot;path&quot;: &quot;workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/videos/9f8e7d6c-....mp4&quot;,
    &quot;content_type&quot;: &quot;video/mp4&quot;,
    &quot;max_size&quot;: 32212254720,
    &quot;expires_in&quot;: 3600
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.create&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-scheduled-posts-upload-url" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-scheduled-posts-upload-url"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-scheduled-posts-upload-url"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-scheduled-posts-upload-url" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-scheduled-posts-upload-url">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-scheduled-posts-upload-url" data-method="POST"
      data-path="api/scheduled-posts/upload-url"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-scheduled-posts-upload-url', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-scheduled-posts-upload-url"
                    onclick="tryItOut('POSTapi-scheduled-posts-upload-url');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-scheduled-posts-upload-url"
                    onclick="cancelTryOut('POSTapi-scheduled-posts-upload-url');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-scheduled-posts-upload-url"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/scheduled-posts/upload-url</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-scheduled-posts-upload-url"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-scheduled-posts-upload-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-scheduled-posts-upload-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>content_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="content_type"                data-endpoint="POSTapi-scheduled-posts-upload-url"
               value="video/mp4"
               data-component="body">
    <br>
<p>Tipo do arquivo. Example: <code>video/mp4</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>video/mp4</code></li> <li><code>video/quicktime</code></li> <li><code>image/jpeg</code></li> <li><code>image/png</code></li> <li><code>image/jpg</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>directory</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="directory"                data-endpoint="POSTapi-scheduled-posts-upload-url"
               value="videos"
               data-component="body">
    <br>
<p>Onde guardar: <code>videos</code> ou <code>images</code> para a mídia, <code>thumbnails</code> para a capa. Example: <code>videos</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>videos</code></li> <li><code>thumbnails</code></li> <li><code>images</code></li></ul>
        </div>
        </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>path</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Guarde: vai em <code>media_storage_path</code> ou <code>thumbnail_storage_path</code> no agendamento.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>max_size</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Tamanho máximo em bytes.</p>
        </div>
                        <h2 id="agendamentos-POSTapi-scheduled-posts">Agendar publicação</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Passo 3 do fluxo. Cria um post para cada conta em <code>social_account_uuids</code>.</p>
<p><strong>Permissão:</strong> <code>posts.create</code></p>

<span id="example-requests-POSTapi-scheduled-posts">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/scheduled-posts"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "media_storage_path": "workspaces\/58d7d732-10a4-491d-9864-b4d85dfcf833\/videos\/9f8e7d6c.mp4",
    "thumbnail_storage_path": "workspaces\/58d7d732-10a4-491d-9864-b4d85dfcf833\/thumbnails\/1a2b3c.jpg",
    "social_account_uuids": [
        "5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
    ],
    "title": "Meu vídeo",
    "caption": "Descrição do vídeo",
    "scheduled_at": "2026-10-20T18:00:00-03:00",
    "is_short": false,
    "youtube_privacy_status": "public",
    "youtube_category_id": "22",
    "youtube_tags": [
        "vlog",
        "viagem"
    ],
    "youtube_made_for_kids": false,
    "pipeline_card_uuid": "3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/scheduled-posts" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"media_storage_path\": \"workspaces\\/58d7d732-10a4-491d-9864-b4d85dfcf833\\/videos\\/9f8e7d6c.mp4\",
    \"thumbnail_storage_path\": \"workspaces\\/58d7d732-10a4-491d-9864-b4d85dfcf833\\/thumbnails\\/1a2b3c.jpg\",
    \"social_account_uuids\": [
        \"5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f\"
    ],
    \"title\": \"Meu vídeo\",
    \"caption\": \"Descrição do vídeo\",
    \"scheduled_at\": \"2026-10-20T18:00:00-03:00\",
    \"is_short\": false,
    \"youtube_privacy_status\": \"public\",
    \"youtube_category_id\": \"22\",
    \"youtube_tags\": [
        \"vlog\",
        \"viagem\"
    ],
    \"youtube_made_for_kids\": false,
    \"pipeline_card_uuid\": \"3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-scheduled-posts">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;V&iacute;deo(s) agendado(s) com sucesso!&quot;,
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;e1d2c3b4-...&quot;,
            &quot;responsible&quot;: &quot;Ana Souza&quot;,
            &quot;media_url&quot;: &quot;https://bucket.s3.amazonaws.com/workspaces/.../videos/abc.mp4?X-Amz-Signature=...&quot;,
            &quot;total_posts_for_account&quot;: 12,
            &quot;failed_posts_for_account&quot;: 1,
            &quot;platform&quot;: &quot;youtube&quot;,
            &quot;title&quot;: &quot;Meu v&iacute;deo&quot;,
            &quot;caption&quot;: &quot;Descri&ccedil;&atilde;o do v&iacute;deo&quot;,
            &quot;status&quot;: &quot;pending&quot;,
            &quot;scheduled_at&quot;: &quot;2026-10-20T18:00:00-03:00&quot;,
            &quot;published_at&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;,
            &quot;platform_post_id&quot;: null,
            &quot;platform_post_url&quot;: null,
            &quot;payload&quot;: {
                &quot;youtube_privacy_status&quot;: &quot;public&quot;,
                &quot;youtube_tags&quot;: [
                    &quot;vlog&quot;
                ],
                &quot;is_short&quot;: false
            }
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (400, conta não é do usuário):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;no_account_linked&quot;,
    &quot;message&quot;: &quot;...&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, validação):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;O agendamento precisa ser de pelo menos 5 minutos no futuro.&quot;,
    &quot;errors&quot;: {
        &quot;scheduled_at&quot;: [
            &quot;O agendamento precisa ser de pelo menos 5 minutos no futuro.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-scheduled-posts" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-scheduled-posts"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-scheduled-posts"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-scheduled-posts" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-scheduled-posts">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-scheduled-posts" data-method="POST"
      data-path="api/scheduled-posts"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-scheduled-posts', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-scheduled-posts"
                    onclick="tryItOut('POSTapi-scheduled-posts');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-scheduled-posts"
                    onclick="cancelTryOut('POSTapi-scheduled-posts');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-scheduled-posts"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/scheduled-posts</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-scheduled-posts"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-scheduled-posts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-scheduled-posts"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>media_storage_path</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="media_storage_path"                data-endpoint="POSTapi-scheduled-posts"
               value="workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/videos/9f8e7d6c.mp4"
               data-component="body">
    <br>
<p><code>path</code> devolvido pelo upload (pasta <code>videos</code> ou <code>images</code>). Example: <code>workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/videos/9f8e7d6c.mp4</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>thumbnail_storage_path</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="thumbnail_storage_path"                data-endpoint="POSTapi-scheduled-posts"
               value="workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/thumbnails/1a2b3c.jpg"
               data-component="body">
    <br>
<p><code>path</code> de uma capa enviada na pasta <code>thumbnails</code>. Example: <code>workspaces/58d7d732-10a4-491d-9864-b4d85dfcf833/thumbnails/1a2b3c.jpg</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>social_account_uuids</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="social_account_uuids[0]"                data-endpoint="POSTapi-scheduled-posts"
               data-component="body">
        <input type="text" style="display: none"
               name="social_account_uuids[1]"                data-endpoint="POSTapi-scheduled-posts"
               data-component="body">
    <br>
<p>UUIDs das contas (mín. 1). Só contas conectadas pelo próprio usuário.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="POSTapi-scheduled-posts"
               value="Meu vídeo"
               data-component="body">
    <br>
<p>Título (máx. 100). Example: <code>Meu vídeo</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>caption</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="caption"                data-endpoint="POSTapi-scheduled-posts"
               value="Descrição do vídeo"
               data-component="body">
    <br>
<p>Legenda/descrição. Example: <code>Descrição do vídeo</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>scheduled_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="scheduled_at"                data-endpoint="POSTapi-scheduled-posts"
               value="2026-10-20T18:00:00-03:00"
               data-component="body">
    <br>
<p>Data/hora da publicação, pelo menos 5 minutos no futuro. Vazio = publica agora. Example: <code>2026-10-20T18:00:00-03:00</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_short</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-scheduled-posts" style="display: none">
            <input type="radio" name="is_short"
                   value="true"
                   data-endpoint="POSTapi-scheduled-posts"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-scheduled-posts" style="display: none">
            <input type="radio" name="is_short"
                   value="false"
                   data-endpoint="POSTapi-scheduled-posts"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>YouTube: publicar como Short. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>youtube_privacy_status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="youtube_privacy_status"                data-endpoint="POSTapi-scheduled-posts"
               value="public"
               data-component="body">
    <br>
<p>YouTube: privacidade (ver <code>GET /youtube-privacy-statuses</code>). Example: <code>public</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>public</code></li> <li><code>private</code></li> <li><code>unlisted</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>youtube_category_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="youtube_category_id"                data-endpoint="POSTapi-scheduled-posts"
               value="22"
               data-component="body">
    <br>
<p>YouTube: ID da categoria (ver <code>GET /youtube-categories</code>). Example: <code>22</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>youtube_tags</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="youtube_tags[0]"                data-endpoint="POSTapi-scheduled-posts"
               data-component="body">
        <input type="text" style="display: none"
               name="youtube_tags[1]"                data-endpoint="POSTapi-scheduled-posts"
               data-component="body">
    <br>
<p>YouTube: tags (máx. 50, cada uma até 50 caracteres).</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>youtube_made_for_kids</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-scheduled-posts" style="display: none">
            <input type="radio" name="youtube_made_for_kids"
                   value="true"
                   data-endpoint="POSTapi-scheduled-posts"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-scheduled-posts" style="display: none">
            <input type="radio" name="youtube_made_for_kids"
                   value="false"
                   data-endpoint="POSTapi-scheduled-posts"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>YouTube: conteúdo infantil. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>pipeline_card_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="pipeline_card_uuid"                data-endpoint="POSTapi-scheduled-posts"
               value="3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="body">
    <br>
<p>Card do pipeline a vincular; ele vai para a etapa <code>scheduled</code>. Example: <code>3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
        </div>
        </form>

                    <h2 id="agendamentos-POSTapi-scheduled-posts--scheduledPost--retry">Reenviar post que falhou</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Só posts <code>failed</code> do workspace corrente cuja mídia ainda está guardada (<code>can_retry</code> na listagem).
Sem <code>scheduled_at</code> o post sai na hora; com ele, é reagendado. Título, legenda e opções não mudam.</p>
<p><strong>Permissão:</strong> <code>posts.create</code></p>

<span id="example-requests-POSTapi-scheduled-posts--scheduledPost--retry">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/scheduled-posts/e1d2c3b4-a5f6-4789-8abc-def012345678/retry"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "scheduled_at": "2026-10-25T18:00:00-03:00"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/scheduled-posts/e1d2c3b4-a5f6-4789-8abc-def012345678/retry" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"scheduled_at\": \"2026-10-25T18:00:00-03:00\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-scheduled-posts--scheduledPost--retry">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Post reenviado para a fila.&quot;,
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;e1d2c3b4-...&quot;,
        &quot;status&quot;: &quot;pending&quot;,
        &quot;can_retry&quot;: false
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, post não falhou):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;retry_not_allowed&quot;,
    &quot;message&quot;: &quot;S&oacute; &eacute; poss&iacute;vel reenviar posts que falharam.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, arquivo já removido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;media_expired&quot;,
    &quot;message&quot;: &quot;O arquivo deste post n&atilde;o est&aacute; mais dispon&iacute;vel. Adicione o post novamente.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-scheduled-posts--scheduledPost--retry" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-scheduled-posts--scheduledPost--retry"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-scheduled-posts--scheduledPost--retry"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-scheduled-posts--scheduledPost--retry" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-scheduled-posts--scheduledPost--retry">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-scheduled-posts--scheduledPost--retry" data-method="POST"
      data-path="api/scheduled-posts/{scheduledPost}/retry"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-scheduled-posts--scheduledPost--retry', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-scheduled-posts--scheduledPost--retry"
                    onclick="tryItOut('POSTapi-scheduled-posts--scheduledPost--retry');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-scheduled-posts--scheduledPost--retry"
                    onclick="cancelTryOut('POSTapi-scheduled-posts--scheduledPost--retry');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-scheduled-posts--scheduledPost--retry"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/scheduled-posts/{scheduledPost}/retry</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-scheduled-posts--scheduledPost--retry"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-scheduled-posts--scheduledPost--retry"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-scheduled-posts--scheduledPost--retry"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>scheduledPost</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="scheduledPost"                data-endpoint="POSTapi-scheduled-posts--scheduledPost--retry"
               value="e1d2c3b4-a5f6-4789-8abc-def012345678"
               data-component="url">
    <br>
<p>UUID do post. Example: <code>e1d2c3b4-a5f6-4789-8abc-def012345678</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>scheduled_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="scheduled_at"                data-endpoint="POSTapi-scheduled-posts--scheduledPost--retry"
               value="2026-10-25T18:00:00-03:00"
               data-component="body">
    <br>
<p>Nova data/hora (ISO 8601, pelo menos 5 minutos no futuro). Sem ela, reenvia agora. Example: <code>2026-10-25T18:00:00-03:00</code></p>
        </div>
        </form>

                    <h2 id="agendamentos-DELETEapi-scheduled-posts--scheduledPost-">Cancelar agendamento</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Só posts <code>pending</code> criados pelo próprio usuário. A mídia é apagada do storage.</p>
<p><strong>Permissão:</strong> <code>posts.delete</code></p>

<span id="example-requests-DELETEapi-scheduled-posts--scheduledPost-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/scheduled-posts/e1d2c3b4-a5f6-4789-8abc-def012345678"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/scheduled-posts/e1d2c3b4-a5f6-4789-8abc-def012345678" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-scheduled-posts--scheduledPost-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Agendamento cancelado com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.delete&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, já publicado ou em processamento):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;cancel_not_allowed&quot;,
    &quot;message&quot;: &quot;Este post n&atilde;o pode ser cancelado pois j&aacute; foi publicado ou est&aacute; em processamento.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-scheduled-posts--scheduledPost-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-scheduled-posts--scheduledPost-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-scheduled-posts--scheduledPost-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-scheduled-posts--scheduledPost-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-scheduled-posts--scheduledPost-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-scheduled-posts--scheduledPost-" data-method="DELETE"
      data-path="api/scheduled-posts/{scheduledPost}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-scheduled-posts--scheduledPost-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-scheduled-posts--scheduledPost-"
                    onclick="tryItOut('DELETEapi-scheduled-posts--scheduledPost-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-scheduled-posts--scheduledPost-"
                    onclick="cancelTryOut('DELETEapi-scheduled-posts--scheduledPost-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-scheduled-posts--scheduledPost-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/scheduled-posts/{scheduledPost}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-scheduled-posts--scheduledPost-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-scheduled-posts--scheduledPost-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-scheduled-posts--scheduledPost-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>scheduledPost</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="scheduledPost"                data-endpoint="DELETEapi-scheduled-posts--scheduledPost-"
               value="e1d2c3b4-a5f6-4789-8abc-def012345678"
               data-component="url">
    <br>
<p>UUID do post. Example: <code>e1d2c3b4-a5f6-4789-8abc-def012345678</code></p>
            </div>
                    </form>

                    <h2 id="agendamentos-GETapi-youtube-categories">Categorias do YouTube</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Opções para <code>youtube_category_id</code>. Use <code>id</code> como valor e <code>name</code> como rótulo.</p>

<span id="example-requests-GETapi-youtube-categories">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/youtube-categories"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/youtube-categories" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-youtube-categories">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Film &amp; Animation&quot;,
        &quot;created_at&quot;: &quot;2026-10-10T16:52:26.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-10T16:52:26.000000Z&quot;
    },
    {
        &quot;id&quot;: 22,
        &quot;name&quot;: &quot;People &amp; Blogs&quot;,
        &quot;created_at&quot;: &quot;2026-10-10T16:52:26.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-10T16:52:26.000000Z&quot;
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-youtube-categories" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-youtube-categories"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-youtube-categories"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-youtube-categories" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-youtube-categories">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-youtube-categories" data-method="GET"
      data-path="api/youtube-categories"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-youtube-categories', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-youtube-categories"
                    onclick="tryItOut('GETapi-youtube-categories');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-youtube-categories"
                    onclick="cancelTryOut('GETapi-youtube-categories');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-youtube-categories"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/youtube-categories</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-youtube-categories"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-youtube-categories"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-youtube-categories"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="agendamentos-GETapi-youtube-privacy-statuses">Privacidades do YouTube</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Opções para <code>youtube_privacy_status</code>.</p>

<span id="example-requests-GETapi-youtube-privacy-statuses">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/youtube-privacy-statuses"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/youtube-privacy-statuses" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-youtube-privacy-statuses">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;value&quot;: &quot;public&quot;,
        &quot;label&quot;: &quot;P&uacute;blico&quot;
    },
    {
        &quot;value&quot;: &quot;private&quot;,
        &quot;label&quot;: &quot;Privado&quot;
    },
    {
        &quot;value&quot;: &quot;unlisted&quot;,
        &quot;label&quot;: &quot;N&atilde;o listado&quot;
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-youtube-privacy-statuses" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-youtube-privacy-statuses"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-youtube-privacy-statuses"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-youtube-privacy-statuses" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-youtube-privacy-statuses">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-youtube-privacy-statuses" data-method="GET"
      data-path="api/youtube-privacy-statuses"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-youtube-privacy-statuses', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-youtube-privacy-statuses"
                    onclick="tryItOut('GETapi-youtube-privacy-statuses');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-youtube-privacy-statuses"
                    onclick="cancelTryOut('GETapi-youtube-privacy-statuses');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-youtube-privacy-statuses"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/youtube-privacy-statuses</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-youtube-privacy-statuses"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-youtube-privacy-statuses"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-youtube-privacy-statuses"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="pipeline-de-conteudo">Pipeline de conteúdo</h1>

    <p>Kanban de produção de conteúdo do workspace corrente.</p>
<p>Etapas, em ordem: <code>idea</code> → <code>script</code> → <code>recorded</code> → <code>editing</code> → <code>ready</code> → <code>scheduled</code>.
O card nasce em <code>idea</code> e anda <strong>uma etapa por vez</strong> (para frente ou para trás).
<code>scheduled</code> é automática: o card chega lá quando um agendamento é criado com <code>pipeline_card_uuid</code>.</p>

                                <h2 id="pipeline-de-conteudo-GETapi-pipeline-board">Quadro</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Todos os cards agrupados por etapa. Toda etapa aparece, mesmo vazia. Em cada coluna: data-alvo mais próxima primeiro.</p>
<p><strong>Permissão:</strong> <code>posts.view</code></p>

<span id="example-requests-GETapi-pipeline-board">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/pipeline/board"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/pipeline/board" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-pipeline-board">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;idea&quot;: [
        {
            &quot;uuid&quot;: &quot;3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f&quot;,
            &quot;title&quot;: &quot;Vlog da viagem&quot;,
            &quot;description&quot;: &quot;Roteiro em anexo&quot;,
            &quot;stage&quot;: &quot;idea&quot;,
            &quot;platform&quot;: &quot;youtube&quot;,
            &quot;due_date&quot;: &quot;2026-10-30&quot;,
            &quot;created_by&quot;: &quot;Ana Souza&quot;,
            &quot;scheduled_post_uuid&quot;: null,
            &quot;created_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;,
            &quot;updated_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;
        }
    ],
    &quot;script&quot;: [],
    &quot;recorded&quot;: [],
    &quot;editing&quot;: [],
    &quot;ready&quot;: [],
    &quot;scheduled&quot;: []
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-pipeline-board" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-pipeline-board"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-pipeline-board"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-pipeline-board" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-pipeline-board">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-pipeline-board" data-method="GET"
      data-path="api/pipeline/board"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-pipeline-board', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-pipeline-board"
                    onclick="tryItOut('GETapi-pipeline-board');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-pipeline-board"
                    onclick="cancelTryOut('GETapi-pipeline-board');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-pipeline-board"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/pipeline/board</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-pipeline-board"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-pipeline-board"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-pipeline-board"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="pipeline-de-conteudo-POSTapi-pipeline">Criar card</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Sempre começa em <code>idea</code>.</p>
<p><strong>Permissão:</strong> <code>posts.create</code></p>

<span id="example-requests-POSTapi-pipeline">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/pipeline"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "Vlog da viagem",
    "description": "Roteiro em anexo",
    "platform": "youtube",
    "due_date": "2026-10-30"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/pipeline" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"title\": \"Vlog da viagem\",
    \"description\": \"Roteiro em anexo\",
    \"platform\": \"youtube\",
    \"due_date\": \"2026-10-30\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-pipeline">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Card criado com sucesso.&quot;,
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f&quot;,
        &quot;title&quot;: &quot;Vlog da viagem&quot;,
        &quot;description&quot;: &quot;Roteiro em anexo&quot;,
        &quot;stage&quot;: &quot;idea&quot;,
        &quot;platform&quot;: &quot;youtube&quot;,
        &quot;due_date&quot;: &quot;2026-10-30&quot;,
        &quot;created_by&quot;: &quot;Ana Souza&quot;,
        &quot;scheduled_post_uuid&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.create&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-pipeline" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-pipeline"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-pipeline"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-pipeline" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-pipeline">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-pipeline" data-method="POST"
      data-path="api/pipeline"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-pipeline', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-pipeline"
                    onclick="tryItOut('POSTapi-pipeline');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-pipeline"
                    onclick="cancelTryOut('POSTapi-pipeline');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-pipeline"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/pipeline</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-pipeline"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-pipeline"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-pipeline"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="POSTapi-pipeline"
               value="Vlog da viagem"
               data-component="body">
    <br>
<p>Título (máx. 255). Example: <code>Vlog da viagem</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-pipeline"
               value="Roteiro em anexo"
               data-component="body">
    <br>
<p>Descrição livre. Example: <code>Roteiro em anexo</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="POSTapi-pipeline"
               value="youtube"
               data-component="body">
    <br>
<p>Plataforma alvo. Example: <code>youtube</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>youtube</code></li> <li><code>instagram</code></li> <li><code>tiktok</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>due_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="due_date"                data-endpoint="POSTapi-pipeline"
               value="2026-10-30"
               data-component="body">
    <br>
<p>Data-alvo (<code>Y-m-d</code>), hoje ou futura. Example: <code>2026-10-30</code></p>
        </div>
        </form>

                    <h2 id="pipeline-de-conteudo-GETapi-pipeline--contentPipeline-">Ver card</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>⚠️ <strong>BUG-01:</strong> hoje este endpoint falha (500 no PostgreSQL), ver <code>specs/002-security-hardening</code>. Não use até a correção.</p>
<p><strong>Permissão:</strong> <code>posts.view</code></p>

<span id="example-requests-GETapi-pipeline--contentPipeline-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-pipeline--contentPipeline-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;uuid&quot;: &quot;3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f&quot;,
        &quot;title&quot;: &quot;Vlog da viagem&quot;,
        &quot;description&quot;: &quot;Roteiro em anexo&quot;,
        &quot;stage&quot;: &quot;idea&quot;,
        &quot;platform&quot;: &quot;youtube&quot;,
        &quot;due_date&quot;: &quot;2026-10-30&quot;,
        &quot;created_by&quot;: &quot;Ana Souza&quot;,
        &quot;scheduled_post_uuid&quot;: null,
        &quot;created_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;,
        &quot;updated_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-pipeline--contentPipeline-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-pipeline--contentPipeline-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-pipeline--contentPipeline-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-pipeline--contentPipeline-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-pipeline--contentPipeline-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-pipeline--contentPipeline-" data-method="GET"
      data-path="api/pipeline/{contentPipeline}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-pipeline--contentPipeline-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-pipeline--contentPipeline-"
                    onclick="tryItOut('GETapi-pipeline--contentPipeline-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-pipeline--contentPipeline-"
                    onclick="cancelTryOut('GETapi-pipeline--contentPipeline-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-pipeline--contentPipeline-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/pipeline/{contentPipeline}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-pipeline--contentPipeline-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-pipeline--contentPipeline-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-pipeline--contentPipeline-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>contentPipeline</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="contentPipeline"                data-endpoint="GETapi-pipeline--contentPipeline-"
               value="3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID do card. Example: <code>3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                    </form>

                    <h2 id="pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline-">Editar card</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Envie só os campos que mudam. Para mudar de etapa use <code>move</code>.</p>
<p><strong>Permissão:</strong> nenhuma além de ser membro. ⚠️ VULN-04 (<code>specs/002</code>): a permissão ainda será definida.</p>

<span id="example-requests-PATCHapi-pipeline--contentPipeline-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "title": "Vlog da viagem (parte 1)",
    "description": "Roteiro revisado",
    "platform": "instagram",
    "due_date": "2026-11-05"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"title\": \"Vlog da viagem (parte 1)\",
    \"description\": \"Roteiro revisado\",
    \"platform\": \"instagram\",
    \"due_date\": \"2026-11-05\"
}"
</code></pre></div>

</span>

<span id="example-responses-PATCHapi-pipeline--contentPipeline-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Card atualizado com sucesso.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-pipeline--contentPipeline-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-pipeline--contentPipeline-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-pipeline--contentPipeline-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-pipeline--contentPipeline-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-pipeline--contentPipeline-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-pipeline--contentPipeline-" data-method="PATCH"
      data-path="api/pipeline/{contentPipeline}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-pipeline--contentPipeline-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-pipeline--contentPipeline-"
                    onclick="tryItOut('PATCHapi-pipeline--contentPipeline-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-pipeline--contentPipeline-"
                    onclick="cancelTryOut('PATCHapi-pipeline--contentPipeline-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-pipeline--contentPipeline-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/pipeline/{contentPipeline}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>contentPipeline</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="contentPipeline"                data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID do card. Example: <code>3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="Vlog da viagem (parte 1)"
               data-component="body">
    <br>
<p>Título (máx. 255). Example: <code>Vlog da viagem (parte 1)</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="Roteiro revisado"
               data-component="body">
    <br>
<p>Descrição. Example: <code>Roteiro revisado</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>platform</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="platform"                data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="instagram"
               data-component="body">
    <br>
<p>Plataforma alvo. Example: <code>instagram</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>youtube</code></li> <li><code>instagram</code></li> <li><code>tiktok</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>due_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="due_date"                data-endpoint="PATCHapi-pipeline--contentPipeline-"
               value="2026-11-05"
               data-component="body">
    <br>
<p>Data-alvo (<code>Y-m-d</code>), hoje ou futura. Example: <code>2026-11-05</code></p>
        </div>
        </form>

                    <h2 id="pipeline-de-conteudo-DELETEapi-pipeline--contentPipeline-">Remover card</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Exclusão reversível: dá para desfazer com <code>restore</code>.</p>
<p><strong>Permissão:</strong> nenhuma além de ser membro. ⚠️ VULN-04 (<code>specs/002</code>): a permissão ainda será definida.</p>

<span id="example-requests-DELETEapi-pipeline--contentPipeline-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-pipeline--contentPipeline-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Card removido com sucesso.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-pipeline--contentPipeline-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-pipeline--contentPipeline-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-pipeline--contentPipeline-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-pipeline--contentPipeline-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-pipeline--contentPipeline-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-pipeline--contentPipeline-" data-method="DELETE"
      data-path="api/pipeline/{contentPipeline}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-pipeline--contentPipeline-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-pipeline--contentPipeline-"
                    onclick="tryItOut('DELETEapi-pipeline--contentPipeline-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-pipeline--contentPipeline-"
                    onclick="cancelTryOut('DELETEapi-pipeline--contentPipeline-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-pipeline--contentPipeline-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/pipeline/{contentPipeline}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-pipeline--contentPipeline-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-pipeline--contentPipeline-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-pipeline--contentPipeline-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>contentPipeline</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="contentPipeline"                data-endpoint="DELETEapi-pipeline--contentPipeline-"
               value="3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID do card. Example: <code>3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                    </form>

                    <h2 id="pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline--move">Mover card de etapa</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Só para a etapa vizinha (anterior ou seguinte). <code>scheduled</code> não é aceita aqui.</p>
<p><strong>Permissão:</strong> <code>posts.create</code></p>

<span id="example-requests-PATCHapi-pipeline--contentPipeline--move">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f/move"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "stage": "script"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f/move" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"stage\": \"script\"
}"
</code></pre></div>

</span>

<span id="example-responses-PATCHapi-pipeline--contentPipeline--move">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Card movido com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;posts.create&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, pulou etapa):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_stage_transition&quot;,
    &quot;message&quot;: &quot;Transi&ccedil;&atilde;o de etapa inv&aacute;lida: idea &rarr; editing.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, destino scheduled):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;scheduled_stage_forbidden&quot;,
    &quot;message&quot;: &quot;A etapa &#039;scheduled&#039; n&atilde;o pode ser definida manualmente. Ela &eacute; atribu&iacute;da automaticamente ao agendar um post.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-pipeline--contentPipeline--move" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-pipeline--contentPipeline--move"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-pipeline--contentPipeline--move"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-pipeline--contentPipeline--move" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-pipeline--contentPipeline--move">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-pipeline--contentPipeline--move" data-method="PATCH"
      data-path="api/pipeline/{contentPipeline}/move"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-pipeline--contentPipeline--move', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-pipeline--contentPipeline--move"
                    onclick="tryItOut('PATCHapi-pipeline--contentPipeline--move');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-pipeline--contentPipeline--move"
                    onclick="cancelTryOut('PATCHapi-pipeline--contentPipeline--move');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-pipeline--contentPipeline--move"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/pipeline/{contentPipeline}/move</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-pipeline--contentPipeline--move"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-pipeline--contentPipeline--move"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-pipeline--contentPipeline--move"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>contentPipeline</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="contentPipeline"                data-endpoint="PATCHapi-pipeline--contentPipeline--move"
               value="3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID do card. Example: <code>3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>stage</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="stage"                data-endpoint="PATCHapi-pipeline--contentPipeline--move"
               value="script"
               data-component="body">
    <br>
<p>Etapa de destino. Example: <code>script</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>idea</code></li> <li><code>script</code></li> <li><code>recorded</code></li> <li><code>editing</code></li> <li><code>ready</code></li></ul>
        </div>
        </form>

                    <h2 id="pipeline-de-conteudo-PATCHapi-pipeline--contentPipeline--restore">Restaurar card</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Desfaz a remoção.</p>
<p><strong>Permissão:</strong> nenhuma além de ser membro. ⚠️ VULN-04 (<code>specs/002</code>): a permissão ainda será definida.</p>

<span id="example-requests-PATCHapi-pipeline--contentPipeline--restore">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f/restore"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "PATCH",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/pipeline/3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f/restore" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-PATCHapi-pipeline--contentPipeline--restore">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Card restaurado com sucesso.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-pipeline--contentPipeline--restore" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-pipeline--contentPipeline--restore"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-pipeline--contentPipeline--restore"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-pipeline--contentPipeline--restore" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-pipeline--contentPipeline--restore">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-pipeline--contentPipeline--restore" data-method="PATCH"
      data-path="api/pipeline/{contentPipeline}/restore"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-pipeline--contentPipeline--restore', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-pipeline--contentPipeline--restore"
                    onclick="tryItOut('PATCHapi-pipeline--contentPipeline--restore');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-pipeline--contentPipeline--restore"
                    onclick="cancelTryOut('PATCHapi-pipeline--contentPipeline--restore');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-pipeline--contentPipeline--restore"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/pipeline/{contentPipeline}/restore</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-pipeline--contentPipeline--restore"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-pipeline--contentPipeline--restore"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-pipeline--contentPipeline--restore"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>contentPipeline</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="contentPipeline"                data-endpoint="PATCHapi-pipeline--contentPipeline--restore"
               value="3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID do card. Example: <code>3c4d5e6f-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                    </form>

                <h1 id="analytics">Analytics</h1>

    <p>Métricas das contas conectadas, no <strong>mesmo formato</strong> para YouTube, Instagram e TikTok.
Métrica que a plataforma não oferece simplesmente não aparece.</p>
<p><strong>Permissão:</strong> nenhuma além de ser membro do workspace (VULN-06 em <code>specs/002</code>, em aberto).</p>

                                <h2 id="analytics-GETapi-analytics--socialAccount_uuid--dashboard">Dashboard da conta</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Monte a interface a partir da resposta:</p>
<ul>
<li><strong>Filtros de período:</strong> <code>capabilities.date_ranges</code> (o que a plataforma aceita) e <code>capabilities.max_range_days</code>.</li>
<li><strong>Cards:</strong> <code>overview</code>. <code>previous</code> e <code>trend</code> (variação %) podem ser <code>null</code> quando não há base de comparação.</li>
<li><strong>Gráfico:</strong> <code>time_series.dates</code> + <code>series[].current</code> / <code>series[].previous</code>.</li>
<li><strong>Formato dos números:</strong> campo <code>format</code> = <code>number</code> | <code>percent</code> | <code>duration</code> (segundos) | <code>hours</code> | <code>currency</code>.</li>
<li><strong>Avisos:</strong> <code>alerts</code> (<code>type</code>: <code>success</code>, <code>warning</code>, <code>danger</code>, <code>info</code>) e <code>notes</code> (textos fixos da plataforma).</li>
</ul>
<p>Períodos por plataforma: YouTube <code>last_7_days</code>, <code>last_28_days</code>, <code>last_90_days</code>, <code>this_month</code>, <code>last_month</code>,
<code>last_365_days</code>, <code>lifetime</code>, <code>custom</code>; Instagram <code>last_7_days</code>, <code>last_28_days</code>, <code>custom</code> (máx. 30 dias);
TikTok <code>last_7_days</code>, <code>last_28_days</code>, <code>last_90_days</code>, <code>custom</code> (máx. 365 dias).</p>
<p>Resultado em cache (YouTube 6h, Instagram 3h, TikTok 1h).</p>

<span id="example-requests-GETapi-analytics--socialAccount_uuid--dashboard">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/analytics/5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f/dashboard"
);

const params = {
    "date_range": "last_28_days",
    "start_date": "2026-09-01",
    "end_date": "2026-09-28",
    "refresh": "0",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/analytics/5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f/dashboard?date_range=last_28_days&amp;start_date=2026-09-01&amp;end_date=2026-09-28&amp;refresh=" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-analytics--socialAccount_uuid--dashboard">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;platform&quot;: &quot;instagram&quot;,
        &quot;period&quot;: {
            &quot;start&quot;: &quot;2026-09-16&quot;,
            &quot;end&quot;: &quot;2026-10-13&quot;,
            &quot;previous_start&quot;: &quot;2026-08-19&quot;,
            &quot;previous_end&quot;: &quot;2026-09-15&quot;,
            &quot;granularity&quot;: &quot;day&quot;,
            &quot;date_range&quot;: &quot;last_28_days&quot;
        },
        &quot;capabilities&quot;: {
            &quot;date_ranges&quot;: [
                &quot;last_7_days&quot;,
                &quot;last_28_days&quot;,
                &quot;custom&quot;
            ],
            &quot;max_range_days&quot;: 30,
            &quot;has_daily_series&quot;: true,
            &quot;has_revenue&quot;: false,
            &quot;has_watch_time&quot;: false
        },
        &quot;overview&quot;: [
            {
                &quot;key&quot;: &quot;views&quot;,
                &quot;label&quot;: &quot;Visualiza&ccedil;&otilde;es&quot;,
                &quot;format&quot;: &quot;number&quot;,
                &quot;value&quot;: 12034,
                &quot;previous&quot;: 9800,
                &quot;trend&quot;: 22.8,
                &quot;description&quot;: &quot;Total de visualiza&ccedil;&otilde;es no per&iacute;odo.&quot;
            }
        ],
        &quot;time_series&quot;: {
            &quot;dates&quot;: [
                &quot;2026-09-16&quot;,
                &quot;2026-09-17&quot;
            ],
            &quot;series&quot;: [
                {
                    &quot;key&quot;: &quot;reach&quot;,
                    &quot;label&quot;: &quot;Alcance&quot;,
                    &quot;format&quot;: &quot;number&quot;,
                    &quot;type&quot;: &quot;area&quot;,
                    &quot;current&quot;: [
                        120,
                        98
                    ],
                    &quot;previous&quot;: [
                        80,
                        101
                    ]
                }
            ]
        },
        &quot;breakdowns&quot;: [
            {
                &quot;key&quot;: &quot;content_type&quot;,
                &quot;label&quot;: &quot;Visualiza&ccedil;&otilde;es por formato&quot;,
                &quot;items&quot;: [
                    {
                        &quot;key&quot;: &quot;REEL&quot;,
                        &quot;label&quot;: &quot;Reels&quot;,
                        &quot;value&quot;: 900
                    }
                ]
            }
        ],
        &quot;top_content&quot;: {
            &quot;columns&quot;: [
                {
                    &quot;key&quot;: &quot;views&quot;,
                    &quot;label&quot;: &quot;Visualiza&ccedil;&otilde;es&quot;,
                    &quot;format&quot;: &quot;number&quot;
                }
            ],
            &quot;items&quot;: [
                {
                    &quot;id&quot;: &quot;1789...&quot;,
                    &quot;title&quot;: &quot;Bastidores&quot;,
                    &quot;thumbnail_url&quot;: &quot;https://...&quot;,
                    &quot;permalink&quot;: &quot;https://www.instagram.com/p/...&quot;,
                    &quot;published_at&quot;: &quot;2026-09-20T18:00:00Z&quot;,
                    &quot;format&quot;: &quot;reel&quot;,
                    &quot;metrics&quot;: {
                        &quot;views&quot;: 1000,
                        &quot;engagement_rate&quot;: 4.2
                    }
                }
            ]
        },
        &quot;score&quot;: {
            &quot;value&quot;: 72,
            &quot;engagement&quot;: 40,
            &quot;growth&quot;: 20,
            &quot;frequency&quot;: 12
        },
        &quot;alerts&quot;: [
            {
                &quot;type&quot;: &quot;success&quot;,
                &quot;title&quot;: &quot;Visualiza&ccedil;&otilde;es em alta&quot;,
                &quot;message&quot;: &quot;Suas visualiza&ccedil;&otilde;es subiram 22,8% em rela&ccedil;&atilde;o ao per&iacute;odo anterior.&quot;
            }
        ],
        &quot;notes&quot;: [
            &quot;Os dados do Instagram podem ter atraso de at&eacute; 48 horas.&quot;
        ],
        &quot;generated_at&quot;: &quot;2026-10-14T18:00:00Z&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, reconectar conta):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;analytics_reauth_required&quot;,
    &quot;message&quot;: &quot;Reconecte a conta do Instagram para liberar a leitura das m&eacute;tricas.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, período inválido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{&quot;message&quot;: &quot;Per&iacute;odo n&atilde;o dispon&iacute;vel para esta plataforma.&quot;, &quot;errors&quot;: {&quot;date_range&quot;: [&quot;Per&iacute;odo n&atilde;o dispon&iacute;vel para esta plataforma.&quot;]}}

No 409, mostre o bot&atilde;o &quot;Reconectar conta&quot; (fluxo de [Contas sociais](#contas-sociais)).</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (429, refresh repetido):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;analytics_refresh_too_soon&quot;,
    &quot;message&quot;: &quot;Voc&ecirc; j&aacute; atualizou os dados recentemente. Tente novamente em 7 minutos.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (502, plataforma fora do ar):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;analytics_provider_unavailable&quot;,
    &quot;message&quot;: &quot;N&atilde;o foi poss&iacute;vel carregar as m&eacute;tricas do Instagram agora. Tente novamente em alguns minutos.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-analytics--socialAccount_uuid--dashboard" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-analytics--socialAccount_uuid--dashboard"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-analytics--socialAccount_uuid--dashboard"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-analytics--socialAccount_uuid--dashboard" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-analytics--socialAccount_uuid--dashboard">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-analytics--socialAccount_uuid--dashboard" data-method="GET"
      data-path="api/analytics/{socialAccount_uuid}/dashboard"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-analytics--socialAccount_uuid--dashboard', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-analytics--socialAccount_uuid--dashboard"
                    onclick="tryItOut('GETapi-analytics--socialAccount_uuid--dashboard');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-analytics--socialAccount_uuid--dashboard"
                    onclick="cancelTryOut('GETapi-analytics--socialAccount_uuid--dashboard');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-analytics--socialAccount_uuid--dashboard"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/analytics/{socialAccount_uuid}/dashboard</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>socialAccount_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="socialAccount_uuid"                data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
               value="5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID da conta social. Example: <code>5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>date_range</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="date_range"                data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
               value="last_28_days"
               data-component="query">
    <br>
<p>Período. Padrão <code>last_28_days</code>. Example: <code>last_28_days</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>start_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="start_date"                data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
               value="2026-09-01"
               data-component="query">
    <br>
<p>Obrigatório com <code>date_range=custom</code> (<code>Y-m-d</code>). Example: <code>2026-09-01</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>end_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="end_date"                data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
               value="2026-09-28"
               data-component="query">
    <br>
<p>Obrigatório com <code>date_range=custom</code> (<code>Y-m-d</code>), até hoje. Example: <code>2026-09-28</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>refresh</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard" style="display: none">
            <input type="radio" name="refresh"
                   value="1"
                   data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
                   data-component="query"             >
            <code>true</code>
        </label>
        <label data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard" style="display: none">
            <input type="radio" name="refresh"
                   value="0"
                   data-endpoint="GETapi-analytics--socialAccount_uuid--dashboard"
                   data-component="query"             >
            <code>false</code>
        </label>
    <br>
<p><code>1</code> ignora o cache. No máximo 1 vez a cada 10 minutos por conta. Example: <code>false</code></p>
            </div>
                </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>score</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Nota da conta (0–100) = <code>engagement</code> (até 50) + <code>growth</code> (até 30) + <code>frequency</code> (até 20).</p>
        </div>
                        <h2 id="analytics-GETapi-analytics--socialAccount_uuid--best-times">Melhores horários para postar</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Média de visualizações por hora de publicação dos últimos conteúdos (até 50), no fuso <code>America/Sao_Paulo</code>.
Com menos de 5 conteúdos, <code>best_hours</code> é uma sugestão padrão e <code>is_fallback</code> vem <code>true</code>.</p>

<span id="example-requests-GETapi-analytics--socialAccount_uuid--best-times">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/analytics/5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f/best-times"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/analytics/5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f/best-times" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-analytics--socialAccount_uuid--best-times">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;best_hours&quot;: [
            18,
            20,
            12
        ],
        &quot;by_hour&quot;: [
            {
                &quot;hour&quot;: 0,
                &quot;average&quot;: 0,
                &quot;posts&quot;: 0
            },
            {
                &quot;hour&quot;: 18,
                &quot;average&quot;: 1520.5,
                &quot;posts&quot;: 6
            }
        ],
        &quot;metric&quot;: &quot;views&quot;,
        &quot;sample_size&quot;: 37,
        &quot;is_fallback&quot;: false,
        &quot;timezone&quot;: &quot;America/Sao_Paulo&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-analytics--socialAccount_uuid--best-times" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-analytics--socialAccount_uuid--best-times"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-analytics--socialAccount_uuid--best-times"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-analytics--socialAccount_uuid--best-times" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-analytics--socialAccount_uuid--best-times">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-analytics--socialAccount_uuid--best-times" data-method="GET"
      data-path="api/analytics/{socialAccount_uuid}/best-times"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-analytics--socialAccount_uuid--best-times', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-analytics--socialAccount_uuid--best-times"
                    onclick="tryItOut('GETapi-analytics--socialAccount_uuid--best-times');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-analytics--socialAccount_uuid--best-times"
                    onclick="cancelTryOut('GETapi-analytics--socialAccount_uuid--best-times');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-analytics--socialAccount_uuid--best-times"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/analytics/{socialAccount_uuid}/best-times</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-analytics--socialAccount_uuid--best-times"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-analytics--socialAccount_uuid--best-times"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-analytics--socialAccount_uuid--best-times"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>socialAccount_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="socialAccount_uuid"                data-endpoint="GETapi-analytics--socialAccount_uuid--best-times"
               value="5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f"
               data-component="url">
    <br>
<p>UUID da conta social. Example: <code>5f0c2d1e-7a8b-4c9d-8e0f-1a2b3c4d5e6f</code></p>
            </div>
                    </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>by_hour</code></b>&nbsp;&nbsp;
<small>object[]</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Sempre 24 posições (horas 0 a 23).</p>
        </div>
                    <h1 id="auditoria">Auditoria</h1>

    <p>Histórico de alterações (quem mudou o quê) no workspace corrente.</p>

                                <h2 id="auditoria-GETapi-logs">Listar logs</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Mais recentes primeiro. <code>old_values</code>/<code>new_values</code> trazem só os campos alterados.</p>
<p><strong>Permissão:</strong> <code>logs.view</code></p>

<span id="example-requests-GETapi-logs">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/logs"
);

const params = {
    "per_page": "15",
    "page": "1",
    "event": "updated",
    "actor_uuid": "2bd8fa57-724f-4cfb-ae33-875afedee0e6",
    "entity_type": "role",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/logs?per_page=15&amp;page=1&amp;event=updated&amp;actor_uuid=2bd8fa57-724f-4cfb-ae33-875afedee0e6&amp;entity_type=role" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-logs">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;uuid&quot;: &quot;aa11bb22-...&quot;,
            &quot;action&quot;: &quot;updated&quot;,
            &quot;entity_type&quot;: &quot;role&quot;,
            &quot;entity_uuid&quot;: &quot;9b1c3e2a-6f7d-4a8b-9c0d-1e2f3a4b5c6d&quot;,
            &quot;entity_name&quot;: &quot;Editor&quot;,
            &quot;actor_uuid&quot;: &quot;2bd8fa57-724f-4cfb-ae33-875afedee0e6&quot;,
            &quot;actor_name&quot;: &quot;Ana Souza&quot;,
            &quot;actor_email&quot;: &quot;ana@exemplo.com&quot;,
            &quot;old_values&quot;: {
                &quot;name&quot;: &quot;Redator&quot;
            },
            &quot;new_values&quot;: {
                &quot;name&quot;: &quot;Editor&quot;
            },
            &quot;tags&quot;: [],
            &quot;created_at&quot;: &quot;2026-10-15T10:00:00-03:00&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;logs.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-logs" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-logs"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-logs"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-logs" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-logs">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-logs" data-method="GET"
      data-path="api/logs"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-logs', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-logs"
                    onclick="tryItOut('GETapi-logs');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-logs"
                    onclick="cancelTryOut('GETapi-logs');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-logs"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/logs</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-logs"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-logs"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-logs"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-logs"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-logs"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>event</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="event"                data-endpoint="GETapi-logs"
               value="updated"
               data-component="query">
    <br>
<p>Filtra pela ação. Example: <code>updated</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>created</code></li> <li><code>updated</code></li> <li><code>deleted</code></li> <li><code>restored</code></li></ul>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>actor_uuid</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="actor_uuid"                data-endpoint="GETapi-logs"
               value="2bd8fa57-724f-4cfb-ae33-875afedee0e6"
               data-component="query">
    <br>
<p>Filtra por quem fez a ação. Example: <code>2bd8fa57-724f-4cfb-ae33-875afedee0e6</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>entity_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="entity_type"                data-endpoint="GETapi-logs"
               value="role"
               data-component="query">
    <br>
<p>Filtra pelo tipo de registro (parcial, ex.: <code>role</code>, <code>workspace</code>). Example: <code>role</code></p>
            </div>
                </form>

                <h1 id="planos-publico">Planos (público)</h1>

    <p>Vitrine de planos para a landing page e a tela de assinatura.</p>

                                <h2 id="planos-publico-GETapi-plans">Listar planos</h2>

<p>
</p>

<p>Planos ativos e visíveis, com os preços ativos (do menor para o maior). Não precisa de login.
Para assinar, envie o <code>id</code> do <strong>preço</strong> em <code>POST /billing/subscription/checkout</code>.</p>
<p>Limite: 45 requisições por minuto.</p>

<span id="example-requests-GETapi-plans">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/plans"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/plans" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-plans">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;1a2b3c4d-...&quot;,
            &quot;name&quot;: &quot;Pro&quot;,
            &quot;description&quot;: &quot;Para criadores que postam toda semana&quot;,
            &quot;prices&quot;: [
                {
                    &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
                    &quot;amount&quot;: 4990,
                    &quot;currency&quot;: &quot;brl&quot;,
                    &quot;frequency&quot;: &quot;monthly&quot;,
                    &quot;trial_period_days&quot;: 7
                },
                {
                    &quot;id&quot;: &quot;8e7d6c5b-...&quot;,
                    &quot;amount&quot;: 49900,
                    &quot;currency&quot;: &quot;brl&quot;,
                    &quot;frequency&quot;: &quot;yearly&quot;,
                    &quot;trial_period_days&quot;: 7
                }
            ]
        }
    ],
    &quot;meta&quot;: {
        &quot;total&quot;: 1
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-plans" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-plans"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-plans"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-plans" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-plans">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-plans" data-method="GET"
      data-path="api/plans"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-plans', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-plans"
                    onclick="tryItOut('GETapi-plans');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-plans"
                    onclick="cancelTryOut('GETapi-plans');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-plans"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/plans</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>amount</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Valor em centavos.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>frequency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p><code>monthly</code> ou <code>yearly</code>.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>currency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p><code>brl</code>, <code>usd</code>, <code>eur</code> ou <code>gbp</code>.</p>
        </div>
                    <h1 id="assinatura-e-pagamentos">Assinatura e pagamentos</h1>

    <p>Assinatura, cartões e faturas <strong>do workspace corrente</strong> (a cobrança é por workspace, via Stripe).</p>
<p><strong>Fluxo de assinatura:</strong> <code>POST /billing/subscription/checkout</code> → redirecione para <code>checkout_url</code> (página do Stripe)
→ o Stripe volta para o frontend → consulte <code>GET /billing/subscription</code>.</p>

                                <h2 id="assinatura-e-pagamentos-GETapi-billing-subscription">Assinatura atual</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>billing.view</code></p>

<span id="example-requests-GETapi-billing-subscription">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/subscription"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/billing/subscription" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-billing-subscription">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;billing_status&quot;: &quot;active&quot;,
        &quot;stripe_status&quot;: &quot;active&quot;,
        &quot;on_trial&quot;: false,
        &quot;trial_ends_at&quot;: null,
        &quot;ends_at&quot;: null,
        &quot;plan&quot;: {
            &quot;id&quot;: &quot;1a2b3c4d-...&quot;,
            &quot;name&quot;: &quot;Pro&quot;
        },
        &quot;price&quot;: {
            &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
            &quot;amount&quot;: 4990,
            &quot;currency&quot;: &quot;brl&quot;,
            &quot;frequency&quot;: &quot;monthly&quot;
        }
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.view&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (404, sem assinatura):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;no_active_subscription&quot;,
    &quot;message&quot;: &quot;Nenhuma assinatura ativa para este workspace.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-billing-subscription" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-billing-subscription"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-billing-subscription"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-billing-subscription" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-billing-subscription">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-billing-subscription" data-method="GET"
      data-path="api/billing/subscription"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-billing-subscription', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-billing-subscription"
                    onclick="tryItOut('GETapi-billing-subscription');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-billing-subscription"
                    onclick="cancelTryOut('GETapi-billing-subscription');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-billing-subscription"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/billing/subscription</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-billing-subscription"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-billing-subscription"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-billing-subscription"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>billing_status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Use este para a interface: <code>trialing</code> (em teste), <code>active</code> (em dia), <code>past_due</code> (pagamento pendente, acesso mantido) ou <code>blocked</code> (sem acesso).</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>ends_at</code></b>&nbsp;&nbsp;
<small>string|null</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Preenchido quando a assinatura foi cancelada e termina nessa data.</p>
        </div>
                        <h2 id="assinatura-e-pagamentos-POSTapi-billing-subscription-checkout">Iniciar checkout</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Cria a sessão do Stripe Checkout. Redirecione o usuário para <code>checkout_url</code>.</p>
<p><strong>Permissão:</strong> <code>billing.manage</code></p>

<span id="example-requests-POSTapi-billing-subscription-checkout">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/subscription/checkout"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "price": "9f8e7d6c-5b4a-4321-8fed-cba987654321"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/billing/subscription/checkout" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"price\": \"9f8e7d6c-5b4a-4321-8fed-cba987654321\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-billing-subscription-checkout">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;checkout_url&quot;: &quot;https://checkout.stripe.com/c/pay/cs_test_...&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.manage&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, já assinante):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;already_subscribed&quot;,
    &quot;message&quot;: &quot;Este workspace j&aacute; possui uma assinatura ativa.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, preço não sincronizado):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;price_not_synced&quot;,
    &quot;message&quot;: &quot;Este pre&ccedil;o ainda n&atilde;o foi sincronizado com o gateway de pagamento.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (502, falha no Stripe):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;gateway_error&quot;,
    &quot;message&quot;: &quot;Falha ao comunicar com o gateway de pagamento. Tente novamente.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-billing-subscription-checkout" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-billing-subscription-checkout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-billing-subscription-checkout"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-billing-subscription-checkout" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-billing-subscription-checkout">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-billing-subscription-checkout" data-method="POST"
      data-path="api/billing/subscription/checkout"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-billing-subscription-checkout', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-billing-subscription-checkout"
                    onclick="tryItOut('POSTapi-billing-subscription-checkout');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-billing-subscription-checkout"
                    onclick="cancelTryOut('POSTapi-billing-subscription-checkout');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-billing-subscription-checkout"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/billing/subscription/checkout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-billing-subscription-checkout"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-billing-subscription-checkout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-billing-subscription-checkout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>price</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="price"                data-endpoint="POSTapi-billing-subscription-checkout"
               value="9f8e7d6c-5b4a-4321-8fed-cba987654321"
               data-component="body">
    <br>
<p><code>id</code> de um preço ativo (de <code>GET /plans</code>). Example: <code>9f8e7d6c-5b4a-4321-8fed-cba987654321</code></p>
        </div>
        </form>

                    <h2 id="assinatura-e-pagamentos-POSTapi-billing-subscription-checkout-confirm">Confirmar checkout</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Chame ao voltar do Stripe, com o <code>session_id</code> recebido na URL de sucesso. A API confirma a sessão direto no Stripe
e cria a assinatura sem esperar o webhook. É idempotente.</p>
<p><strong>Permissão:</strong> <code>billing.manage</code></p>

<span id="example-requests-POSTapi-billing-subscription-checkout-confirm">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/subscription/checkout/confirm"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "session_id": "cs_test_a1b2c3"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/billing/subscription/checkout/confirm" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"session_id\": \"cs_test_a1b2c3\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-billing-subscription-checkout-confirm">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;billing_status&quot;: &quot;active&quot;,
        &quot;stripe_status&quot;: &quot;active&quot;,
        &quot;on_trial&quot;: false,
        &quot;trial_ends_at&quot;: null,
        &quot;ends_at&quot;: null,
        &quot;plan&quot;: {
            &quot;id&quot;: &quot;1a2b3c4d-...&quot;,
            &quot;name&quot;: &quot;Pro&quot;
        },
        &quot;price&quot;: {
            &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
            &quot;amount&quot;: 4990,
            &quot;currency&quot;: &quot;brl&quot;,
            &quot;frequency&quot;: &quot;monthly&quot;
        }
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (404, sessão de outro workspace):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;checkout_session_not_found&quot;,
    &quot;message&quot;: &quot;Sess&atilde;o de checkout n&atilde;o encontrada para este workspace.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (404, checkout não concluído):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;no_active_subscription&quot;,
    &quot;message&quot;: &quot;Nenhuma assinatura ativa para este workspace.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (502, falha no Stripe):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;gateway_error&quot;,
    &quot;message&quot;: &quot;Falha ao comunicar com o gateway de pagamento. Tente novamente.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-billing-subscription-checkout-confirm" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-billing-subscription-checkout-confirm"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-billing-subscription-checkout-confirm"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-billing-subscription-checkout-confirm" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-billing-subscription-checkout-confirm">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-billing-subscription-checkout-confirm" data-method="POST"
      data-path="api/billing/subscription/checkout/confirm"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-billing-subscription-checkout-confirm', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-billing-subscription-checkout-confirm"
                    onclick="tryItOut('POSTapi-billing-subscription-checkout-confirm');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-billing-subscription-checkout-confirm"
                    onclick="cancelTryOut('POSTapi-billing-subscription-checkout-confirm');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-billing-subscription-checkout-confirm"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/billing/subscription/checkout/confirm</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-billing-subscription-checkout-confirm"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-billing-subscription-checkout-confirm"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-billing-subscription-checkout-confirm"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>session_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="session_id"                data-endpoint="POSTapi-billing-subscription-checkout-confirm"
               value="cs_test_a1b2c3"
               data-component="body">
    <br>
<p><code>session_id</code> devolvido pelo Stripe na URL de sucesso. Example: <code>cs_test_a1b2c3</code></p>
        </div>
        </form>

                    <h2 id="assinatura-e-pagamentos-GETapi-billing-setup-intent">Preparar cadastro de cartão</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Devolve o <code>client_secret</code> para o Stripe.js. Fluxo: chame este endpoint → <code>stripe.confirmCardSetup(client_secret, ...)</code>
→ envie o <code>payment_method</code> resultante para <code>POST /billing/cards</code>.</p>
<p><strong>Permissão:</strong> <code>billing.view</code></p>

<span id="example-requests-GETapi-billing-setup-intent">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/setup-intent"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/billing/setup-intent" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-billing-setup-intent">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;client_secret&quot;: &quot;seti_1Q2w3E_secret_abc&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-billing-setup-intent" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-billing-setup-intent"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-billing-setup-intent"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-billing-setup-intent" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-billing-setup-intent">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-billing-setup-intent" data-method="GET"
      data-path="api/billing/setup-intent"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-billing-setup-intent', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-billing-setup-intent"
                    onclick="tryItOut('GETapi-billing-setup-intent');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-billing-setup-intent"
                    onclick="cancelTryOut('GETapi-billing-setup-intent');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-billing-setup-intent"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/billing/setup-intent</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-billing-setup-intent"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-billing-setup-intent"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-billing-setup-intent"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="assinatura-e-pagamentos-GETapi-billing-cards">Listar cartões</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>billing.view</code></p>

<span id="example-requests-GETapi-billing-cards">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/cards"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/billing/cards" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-billing-cards">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;pm_1Q2w3E4r5T6y&quot;,
            &quot;brand&quot;: &quot;visa&quot;,
            &quot;last_digits&quot;: &quot;4242&quot;,
            &quot;exp_month&quot;: 12,
            &quot;exp_year&quot;: 2028,
            &quot;is_default&quot;: true
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-billing-cards" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-billing-cards"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-billing-cards"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-billing-cards" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-billing-cards">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-billing-cards" data-method="GET"
      data-path="api/billing/cards"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-billing-cards', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-billing-cards"
                    onclick="tryItOut('GETapi-billing-cards');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-billing-cards"
                    onclick="cancelTryOut('GETapi-billing-cards');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-billing-cards"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/billing/cards</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-billing-cards"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-billing-cards"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-billing-cards"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="assinatura-e-pagamentos-POSTapi-billing-cards">Adicionar cartão</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>billing.manage</code></p>

<span id="example-requests-POSTapi-billing-cards">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/cards"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "payment_method": "pm_1Q2w3E4r5T6y",
    "is_default": true
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/billing/cards" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"payment_method\": \"pm_1Q2w3E4r5T6y\",
    \"is_default\": true
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-billing-cards">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Cart&atilde;o adicionado com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.manage&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-billing-cards" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-billing-cards"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-billing-cards"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-billing-cards" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-billing-cards">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-billing-cards" data-method="POST"
      data-path="api/billing/cards"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-billing-cards', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-billing-cards"
                    onclick="tryItOut('POSTapi-billing-cards');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-billing-cards"
                    onclick="cancelTryOut('POSTapi-billing-cards');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-billing-cards"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/billing/cards</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-billing-cards"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-billing-cards"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-billing-cards"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>payment_method</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="payment_method"                data-endpoint="POSTapi-billing-cards"
               value="pm_1Q2w3E4r5T6y"
               data-component="body">
    <br>
<p>ID do PaymentMethod criado pelo Stripe.js. Example: <code>pm_1Q2w3E4r5T6y</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_default</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-billing-cards" style="display: none">
            <input type="radio" name="is_default"
                   value="true"
                   data-endpoint="POSTapi-billing-cards"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-billing-cards" style="display: none">
            <input type="radio" name="is_default"
                   value="false"
                   data-endpoint="POSTapi-billing-cards"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Tornar o cartão padrão. Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="assinatura-e-pagamentos-PATCHapi-billing-cards--paymentMethod-">Definir cartão padrão</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>billing.manage</code></p>

<span id="example-requests-PATCHapi-billing-cards--paymentMethod-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/cards/pm_1Q2w3E4r5T6y"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "PATCH",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/billing/cards/pm_1Q2w3E4r5T6y" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-PATCHapi-billing-cards--paymentMethod-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Cart&atilde;o padr&atilde;o atualizado com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.manage&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-billing-cards--paymentMethod-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-billing-cards--paymentMethod-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-billing-cards--paymentMethod-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-billing-cards--paymentMethod-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-billing-cards--paymentMethod-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-billing-cards--paymentMethod-" data-method="PATCH"
      data-path="api/billing/cards/{paymentMethod}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-billing-cards--paymentMethod-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-billing-cards--paymentMethod-"
                    onclick="tryItOut('PATCHapi-billing-cards--paymentMethod-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-billing-cards--paymentMethod-"
                    onclick="cancelTryOut('PATCHapi-billing-cards--paymentMethod-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-billing-cards--paymentMethod-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/billing/cards/{paymentMethod}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-billing-cards--paymentMethod-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-billing-cards--paymentMethod-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-billing-cards--paymentMethod-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>paymentMethod</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="paymentMethod"                data-endpoint="PATCHapi-billing-cards--paymentMethod-"
               value="pm_1Q2w3E4r5T6y"
               data-component="url">
    <br>
<p>ID do cartão no Stripe (<code>id</code> da listagem). Example: <code>pm_1Q2w3E4r5T6y</code></p>
            </div>
                    </form>

                    <h2 id="assinatura-e-pagamentos-DELETEapi-billing-cards--paymentMethod-">Remover cartão</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>O cartão padrão não pode ser removido: defina outro como padrão antes.</p>
<p><strong>Permissão:</strong> <code>billing.manage</code></p>

<span id="example-requests-DELETEapi-billing-cards--paymentMethod-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/cards/pm_1Q2w3E4r5T6y"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/billing/cards/pm_1Q2w3E4r5T6y" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-billing-cards--paymentMethod-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Cart&atilde;o removido com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.manage&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422, cartão padrão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;default_card_required&quot;,
    &quot;message&quot;: &quot;N&atilde;o &eacute; poss&iacute;vel remover o cart&atilde;o padr&atilde;o; defina outro como padr&atilde;o antes.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-billing-cards--paymentMethod-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-billing-cards--paymentMethod-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-billing-cards--paymentMethod-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-billing-cards--paymentMethod-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-billing-cards--paymentMethod-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-billing-cards--paymentMethod-" data-method="DELETE"
      data-path="api/billing/cards/{paymentMethod}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-billing-cards--paymentMethod-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-billing-cards--paymentMethod-"
                    onclick="tryItOut('DELETEapi-billing-cards--paymentMethod-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-billing-cards--paymentMethod-"
                    onclick="cancelTryOut('DELETEapi-billing-cards--paymentMethod-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-billing-cards--paymentMethod-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/billing/cards/{paymentMethod}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-billing-cards--paymentMethod-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-billing-cards--paymentMethod-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-billing-cards--paymentMethod-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>paymentMethod</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="paymentMethod"                data-endpoint="DELETEapi-billing-cards--paymentMethod-"
               value="pm_1Q2w3E4r5T6y"
               data-component="url">
    <br>
<p>ID do cartão no Stripe (<code>id</code> da listagem). Example: <code>pm_1Q2w3E4r5T6y</code></p>
            </div>
                    </form>

                    <h2 id="assinatura-e-pagamentos-GETapi-billing-payments">Listar faturas</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Histórico de cobranças do workspace, mais recentes primeiro. Os links levam para a fatura/recibo no Stripe.</p>
<p><strong>Permissão:</strong> <code>billing.view</code></p>

<span id="example-requests-GETapi-billing-payments">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/payments"
);

const params = {
    "per_page": "15",
    "page": "1",
    "status": "paid",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/billing/payments?per_page=15&amp;page=1&amp;status=paid" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-billing-payments">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;4d5e6f7a-...&quot;,
            &quot;status&quot;: {
                &quot;value&quot;: &quot;paid&quot;,
                &quot;label&quot;: &quot;Pago&quot;
            },
            &quot;method&quot;: &quot;card&quot;,
            &quot;amount&quot;: 4990,
            &quot;amount_formatted&quot;: &quot;R$ 49,90&quot;,
            &quot;currency&quot;: &quot;brl&quot;,
            &quot;paid_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
            &quot;due_date&quot;: &quot;2026-10-01T00:00:00.000000Z&quot;,
            &quot;period_start&quot;: &quot;2026-10-01T00:00:00.000000Z&quot;,
            &quot;period_end&quot;: &quot;2026-11-01T00:00:00.000000Z&quot;,
            &quot;hosted_invoice_url&quot;: &quot;https://invoice.stripe.com/i/...&quot;,
            &quot;invoice_pdf&quot;: &quot;https://pay.stripe.com/invoice/.../pdf&quot;,
            &quot;receipt_url&quot;: &quot;https://pay.stripe.com/receipts/...&quot;,
            &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;billing.view&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-billing-payments" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-billing-payments"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-billing-payments"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-billing-payments" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-billing-payments">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-billing-payments" data-method="GET"
      data-path="api/billing/payments"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-billing-payments', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-billing-payments"
                    onclick="tryItOut('GETapi-billing-payments');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-billing-payments"
                    onclick="cancelTryOut('GETapi-billing-payments');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-billing-payments"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/billing/payments</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-billing-payments"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-billing-payments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-billing-payments"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-billing-payments"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-billing-payments"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-billing-payments"
               value="paid"
               data-component="query">
    <br>
<p>Filtra pelo status. Example: <code>paid</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>pending</code></li> <li><code>processing</code></li> <li><code>paid</code></li> <li><code>failed</code></li> <li><code>expired</code></li> <li><code>void</code></li></ul>
            </div>
                </form>

    <h3>Resposta</h3>
    <h4 class="fancy-heading-panel"><b>Campos da resposta</b></h4>
    <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>amount_formatted</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
<br>
<p>Valor pronto para exibir.</p>
        </div>
                        <h2 id="assinatura-e-pagamentos-GETapi-billing-payments--payment-">Ver fatura</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><strong>Permissão:</strong> <code>billing.view</code></p>

<span id="example-requests-GETapi-billing-payments--payment-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/billing/payments/4d5e6f7a-8b9c-4d0e-9f1a-2b3c4d5e6f7a"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/billing/payments/4d5e6f7a-8b9c-4d0e-9f1a-2b3c4d5e6f7a" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-billing-payments--payment-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: &quot;4d5e6f7a-...&quot;,
        &quot;status&quot;: {
            &quot;value&quot;: &quot;paid&quot;,
            &quot;label&quot;: &quot;Pago&quot;
        },
        &quot;method&quot;: &quot;card&quot;,
        &quot;amount&quot;: 4990,
        &quot;amount_formatted&quot;: &quot;R$ 49,90&quot;,
        &quot;currency&quot;: &quot;brl&quot;,
        &quot;paid_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;due_date&quot;: &quot;2026-10-01T00:00:00.000000Z&quot;,
        &quot;period_start&quot;: &quot;2026-10-01T00:00:00.000000Z&quot;,
        &quot;period_end&quot;: &quot;2026-11-01T00:00:00.000000Z&quot;,
        &quot;hosted_invoice_url&quot;: &quot;https://invoice.stripe.com/i/...&quot;,
        &quot;invoice_pdf&quot;: &quot;https://pay.stripe.com/invoice/.../pdf&quot;,
        &quot;receipt_url&quot;: &quot;https://pay.stripe.com/receipts/...&quot;,
        &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-billing-payments--payment-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-billing-payments--payment-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-billing-payments--payment-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-billing-payments--payment-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-billing-payments--payment-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-billing-payments--payment-" data-method="GET"
      data-path="api/billing/payments/{payment}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-billing-payments--payment-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-billing-payments--payment-"
                    onclick="tryItOut('GETapi-billing-payments--payment-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-billing-payments--payment-"
                    onclick="cancelTryOut('GETapi-billing-payments--payment-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-billing-payments--payment-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/billing/payments/{payment}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-billing-payments--payment-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-billing-payments--payment-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-billing-payments--payment-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>payment</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="payment"                data-endpoint="GETapi-billing-payments--payment-"
               value="4d5e6f7a-8b9c-4d0e-9f1a-2b3c4d5e6f7a"
               data-component="url">
    <br>
<p>UUID da fatura (<code>id</code> da listagem). Example: <code>4d5e6f7a-8b9c-4d0e-9f1a-2b3c4d5e6f7a</code></p>
            </div>
                    </form>

                <h1 id="admin-planos-e-precos">Admin: planos e preços</h1>

    <p>Catálogo global de planos e preços, <strong>só para o super admin</strong> (dono do SaaS). Tudo é sincronizado com o Stripe.
Para qualquer outro usuário a resposta é 403.</p>

                                <h2 id="admin-planos-e-precos-GETapi-admin-plans">Listar planos</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Todos os planos, inclusive inativos e ocultos (sem <code>prices</code>).</p>

<span id="example-requests-GETapi-admin-plans">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans"
);

const params = {
    "per_page": "15",
    "page": "1",
    "name": "pro",
    "is_active": "1",
    "is_visible": "1",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/admin/plans?per_page=15&amp;page=1&amp;name=pro&amp;is_active=1&amp;is_visible=1" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-admin-plans">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;1a2b3c4d-...&quot;,
            &quot;name&quot;: &quot;Pro&quot;,
            &quot;description&quot;: &quot;Para criadores que postam toda semana&quot;,
            &quot;is_visible&quot;: true,
            &quot;is_active&quot;: true,
            &quot;gateway_product_id&quot;: &quot;prod_Q1w2e3&quot;,
            &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
        }
    ],
    &quot;links&quot;: {
        &quot;first&quot;: &quot;...&quot;,
        &quot;last&quot;: &quot;...&quot;,
        &quot;prev&quot;: null,
        &quot;next&quot;: null
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;last_page&quot;: 1,
        &quot;per_page&quot;: 15,
        &quot;total&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, não é super admin):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-admin-plans" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-admin-plans"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-admin-plans"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-admin-plans" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-admin-plans">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-admin-plans" data-method="GET"
      data-path="api/admin/plans"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-admin-plans', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-admin-plans"
                    onclick="tryItOut('GETapi-admin-plans');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-admin-plans"
                    onclick="cancelTryOut('GETapi-admin-plans');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-admin-plans"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/admin/plans</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-admin-plans"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-admin-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-admin-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-admin-plans"
               value="15"
               data-component="query">
    <br>
<p>Itens por página. Sem ele, vem tudo. Example: <code>15</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="page"                data-endpoint="GETapi-admin-plans"
               value="1"
               data-component="query">
    <br>
<p>Página. Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="GETapi-admin-plans"
               value="pro"
               data-component="query">
    <br>
<p>Filtra por nome (parcial). Example: <code>pro</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="GETapi-admin-plans" style="display: none">
            <input type="radio" name="is_active"
                   value="1"
                   data-endpoint="GETapi-admin-plans"
                   data-component="query"             >
            <code>true</code>
        </label>
        <label data-endpoint="GETapi-admin-plans" style="display: none">
            <input type="radio" name="is_active"
                   value="0"
                   data-endpoint="GETapi-admin-plans"
                   data-component="query"             >
            <code>false</code>
        </label>
    <br>
<p>Filtra por ativo. Example: <code>true</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>is_visible</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="GETapi-admin-plans" style="display: none">
            <input type="radio" name="is_visible"
                   value="1"
                   data-endpoint="GETapi-admin-plans"
                   data-component="query"             >
            <code>true</code>
        </label>
        <label data-endpoint="GETapi-admin-plans" style="display: none">
            <input type="radio" name="is_visible"
                   value="0"
                   data-endpoint="GETapi-admin-plans"
                   data-component="query"             >
            <code>false</code>
        </label>
    <br>
<p>Filtra por visível na vitrine. Example: <code>true</code></p>
            </div>
                </form>

                    <h2 id="admin-planos-e-precos-POSTapi-admin-plans">Criar plano</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Cria o produto no Stripe. Os preços são cadastrados depois.</p>

<span id="example-requests-POSTapi-admin-plans">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Pro",
    "description": "Para criadores que postam toda semana",
    "is_visible": true,
    "is_active": true
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/admin/plans" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Pro\",
    \"description\": \"Para criadores que postam toda semana\",
    \"is_visible\": true,
    \"is_active\": true
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-admin-plans">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Plano criado com sucesso.&quot;,
    &quot;data&quot;: {
        &quot;id&quot;: &quot;1a2b3c4d-...&quot;,
        &quot;name&quot;: &quot;Pro&quot;,
        &quot;description&quot;: &quot;Para criadores que postam toda semana&quot;,
        &quot;is_visible&quot;: true,
        &quot;is_active&quot;: true,
        &quot;gateway_product_id&quot;: &quot;prod_Q1w2e3&quot;,
        &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;prices&quot;: []
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403, não é super admin):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (502, falha no Stripe):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;gateway_error&quot;,
    &quot;message&quot;: &quot;Falha ao comunicar com o gateway de pagamento. Tente novamente.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-admin-plans" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-admin-plans"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-admin-plans"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-admin-plans" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-admin-plans">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-admin-plans" data-method="POST"
      data-path="api/admin/plans"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-admin-plans', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-admin-plans"
                    onclick="tryItOut('POSTapi-admin-plans');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-admin-plans"
                    onclick="cancelTryOut('POSTapi-admin-plans');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-admin-plans"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/admin/plans</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-admin-plans"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-admin-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-admin-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-admin-plans"
               value="Pro"
               data-component="body">
    <br>
<p>Nome (máx. 255). Example: <code>Pro</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-admin-plans"
               value="Para criadores que postam toda semana"
               data-component="body">
    <br>
<p>Descrição (máx. 1000). Example: <code>Para criadores que postam toda semana</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_visible</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-admin-plans" style="display: none">
            <input type="radio" name="is_visible"
                   value="true"
                   data-endpoint="POSTapi-admin-plans"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-admin-plans" style="display: none">
            <input type="radio" name="is_visible"
                   value="false"
                   data-endpoint="POSTapi-admin-plans"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Aparece na vitrine pública. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-admin-plans" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="POSTapi-admin-plans"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-admin-plans" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="POSTapi-admin-plans"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Disponível para assinatura. Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="admin-planos-e-precos-GETapi-admin-plans--plan-">Ver plano</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Com todos os preços.</p>

<span id="example-requests-GETapi-admin-plans--plan-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-admin-plans--plan-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: &quot;1a2b3c4d-...&quot;,
        &quot;name&quot;: &quot;Pro&quot;,
        &quot;description&quot;: &quot;Para criadores que postam toda semana&quot;,
        &quot;is_visible&quot;: true,
        &quot;is_active&quot;: true,
        &quot;gateway_product_id&quot;: &quot;prod_Q1w2e3&quot;,
        &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;prices&quot;: [
            {
                &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
                &quot;amount&quot;: 4990,
                &quot;currency&quot;: &quot;brl&quot;,
                &quot;frequency&quot;: &quot;monthly&quot;,
                &quot;trial_period_days&quot;: 7,
                &quot;is_active&quot;: true,
                &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
            }
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-admin-plans--plan-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-admin-plans--plan-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-admin-plans--plan-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-admin-plans--plan-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-admin-plans--plan-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-admin-plans--plan-" data-method="GET"
      data-path="api/admin/plans/{plan}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-admin-plans--plan-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-admin-plans--plan-"
                    onclick="tryItOut('GETapi-admin-plans--plan-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-admin-plans--plan-"
                    onclick="cancelTryOut('GETapi-admin-plans--plan-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-admin-plans--plan-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/admin/plans/{plan}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-admin-plans--plan-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-admin-plans--plan-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-admin-plans--plan-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="GETapi-admin-plans--plan-"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                    </form>

                    <h2 id="admin-planos-e-precos-PATCHapi-admin-plans--plan-">Editar plano</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Envie só o que mudar.</p>

<span id="example-requests-PATCHapi-admin-plans--plan-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Pro+",
    "description": "Nova descrição",
    "is_visible": false,
    "is_active": true
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Pro+\",
    \"description\": \"Nova descrição\",
    \"is_visible\": false,
    \"is_active\": true
}"
</code></pre></div>

</span>

<span id="example-responses-PATCHapi-admin-plans--plan-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Plano atualizado com sucesso.&quot;,
    &quot;data&quot;: {
        &quot;id&quot;: &quot;1a2b3c4d-...&quot;,
        &quot;name&quot;: &quot;Pro&quot;,
        &quot;description&quot;: &quot;Para criadores que postam toda semana&quot;,
        &quot;is_visible&quot;: true,
        &quot;is_active&quot;: true,
        &quot;gateway_product_id&quot;: &quot;prod_Q1w2e3&quot;,
        &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;prices&quot;: [
            {
                &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
                &quot;amount&quot;: 4990,
                &quot;currency&quot;: &quot;brl&quot;,
                &quot;frequency&quot;: &quot;monthly&quot;,
                &quot;trial_period_days&quot;: 7,
                &quot;is_active&quot;: true,
                &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
            }
        ]
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, desativar plano com assinantes):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;plan_has_active_subscriptions&quot;,
    &quot;message&quot;: &quot;N&atilde;o &eacute; poss&iacute;vel concluir a opera&ccedil;&atilde;o: o plano possui assinaturas ativas.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-admin-plans--plan-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-admin-plans--plan-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-admin-plans--plan-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-admin-plans--plan-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-admin-plans--plan-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-admin-plans--plan-" data-method="PATCH"
      data-path="api/admin/plans/{plan}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-admin-plans--plan-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-admin-plans--plan-"
                    onclick="tryItOut('PATCHapi-admin-plans--plan-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-admin-plans--plan-"
                    onclick="cancelTryOut('PATCHapi-admin-plans--plan-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-admin-plans--plan-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/admin/plans/{plan}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-admin-plans--plan-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-admin-plans--plan-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-admin-plans--plan-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="PATCHapi-admin-plans--plan-"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PATCHapi-admin-plans--plan-"
               value="Pro+"
               data-component="body">
    <br>
<p>Nome (máx. 255). Example: <code>Pro+</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="PATCHapi-admin-plans--plan-"
               value="Nova descrição"
               data-component="body">
    <br>
<p>Descrição (máx. 1000). Example: <code>Nova descrição</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_visible</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-admin-plans--plan-" style="display: none">
            <input type="radio" name="is_visible"
                   value="true"
                   data-endpoint="PATCHapi-admin-plans--plan-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-admin-plans--plan-" style="display: none">
            <input type="radio" name="is_visible"
                   value="false"
                   data-endpoint="PATCHapi-admin-plans--plan-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Aparece na vitrine pública. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-admin-plans--plan-" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="PATCHapi-admin-plans--plan-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-admin-plans--plan-" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="PATCHapi-admin-plans--plan-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Disponível para assinatura. Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="admin-planos-e-precos-DELETEapi-admin-plans--plan-">Remover plano</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Plano que já teve assinaturas é só desativado (some da vitrine, mas fica no histórico).</p>

<span id="example-requests-DELETEapi-admin-plans--plan-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-admin-plans--plan-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Plano removido com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, assinaturas ativas):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;plan_has_active_subscriptions&quot;,
    &quot;message&quot;: &quot;N&atilde;o &eacute; poss&iacute;vel concluir a opera&ccedil;&atilde;o: o plano possui assinaturas ativas.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-admin-plans--plan-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-admin-plans--plan-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-admin-plans--plan-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-admin-plans--plan-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-admin-plans--plan-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-admin-plans--plan-" data-method="DELETE"
      data-path="api/admin/plans/{plan}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-admin-plans--plan-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-admin-plans--plan-"
                    onclick="tryItOut('DELETEapi-admin-plans--plan-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-admin-plans--plan-"
                    onclick="cancelTryOut('DELETEapi-admin-plans--plan-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-admin-plans--plan-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/admin/plans/{plan}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-admin-plans--plan-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-admin-plans--plan-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-admin-plans--plan-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="DELETEapi-admin-plans--plan-"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                    </form>

                    <h2 id="admin-planos-e-precos-GETapi-admin-plans--plan--prices">Listar preços do plano</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-admin-plans--plan--prices">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-admin-plans--plan--prices">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
            &quot;amount&quot;: 4990,
            &quot;currency&quot;: &quot;brl&quot;,
            &quot;frequency&quot;: &quot;monthly&quot;,
            &quot;trial_period_days&quot;: 7,
            &quot;is_active&quot;: true,
            &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-admin-plans--plan--prices" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-admin-plans--plan--prices"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-admin-plans--plan--prices"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-admin-plans--plan--prices" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-admin-plans--plan--prices">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-admin-plans--plan--prices" data-method="GET"
      data-path="api/admin/plans/{plan}/prices"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-admin-plans--plan--prices', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-admin-plans--plan--prices"
                    onclick="tryItOut('GETapi-admin-plans--plan--prices');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-admin-plans--plan--prices"
                    onclick="cancelTryOut('GETapi-admin-plans--plan--prices');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-admin-plans--plan--prices"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/admin/plans/{plan}/prices</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-admin-plans--plan--prices"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-admin-plans--plan--prices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-admin-plans--plan--prices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="GETapi-admin-plans--plan--prices"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                    </form>

                    <h2 id="admin-planos-e-precos-POSTapi-admin-plans--plan--prices">Adicionar preço</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Cria o preço no Stripe.</p>

<span id="example-requests-POSTapi-admin-plans--plan--prices">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "amount": 4990,
    "currency": "brl",
    "frequency": "monthly",
    "trial_period_days": 7
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"amount\": 4990,
    \"currency\": \"brl\",
    \"frequency\": \"monthly\",
    \"trial_period_days\": 7
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-admin-plans--plan--prices">
            <blockquote>
            <p>Exemplo de resposta (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Pre&ccedil;o adicionado com sucesso.&quot;,
    &quot;data&quot;: {
        &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
        &quot;amount&quot;: 4990,
        &quot;currency&quot;: &quot;brl&quot;,
        &quot;frequency&quot;: &quot;monthly&quot;,
        &quot;trial_period_days&quot;: 7,
        &quot;is_active&quot;: true,
        &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (502, falha no Stripe):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;gateway_error&quot;,
    &quot;message&quot;: &quot;Falha ao comunicar com o gateway de pagamento. Tente novamente.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-admin-plans--plan--prices" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-admin-plans--plan--prices"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-admin-plans--plan--prices"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-admin-plans--plan--prices" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-admin-plans--plan--prices">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-admin-plans--plan--prices" data-method="POST"
      data-path="api/admin/plans/{plan}/prices"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-admin-plans--plan--prices', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-admin-plans--plan--prices"
                    onclick="tryItOut('POSTapi-admin-plans--plan--prices');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-admin-plans--plan--prices"
                    onclick="cancelTryOut('POSTapi-admin-plans--plan--prices');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-admin-plans--plan--prices"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/admin/plans/{plan}/prices</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-admin-plans--plan--prices"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-admin-plans--plan--prices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-admin-plans--plan--prices"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="POSTapi-admin-plans--plan--prices"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>amount</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="amount"                data-endpoint="POSTapi-admin-plans--plan--prices"
               value="4990"
               data-component="body">
    <br>
<p>Valor em centavos. Example: <code>4990</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>currency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="currency"                data-endpoint="POSTapi-admin-plans--plan--prices"
               value="brl"
               data-component="body">
    <br>
<p>Moeda. Example: <code>brl</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>brl</code></li> <li><code>usd</code></li> <li><code>eur</code></li> <li><code>gbp</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>frequency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="frequency"                data-endpoint="POSTapi-admin-plans--plan--prices"
               value="monthly"
               data-component="body">
    <br>
<p>Recorrência. Example: <code>monthly</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>monthly</code></li> <li><code>yearly</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>trial_period_days</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="trial_period_days"                data-endpoint="POSTapi-admin-plans--plan--prices"
               value="7"
               data-component="body">
    <br>
<p>Dias de teste grátis (0 a 365). Example: <code>7</code></p>
        </div>
        </form>

                    <h2 id="admin-planos-e-precos-GETapi-admin-plans--plan--prices--price--versions">Histórico do preço</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Versões anteriores, gravadas a cada edição, mais recentes primeiro.</p>

<span id="example-requests-GETapi-admin-plans--plan--prices--price--versions">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices/9f8e7d6c-5b4a-4321-8fed-cba987654321/versions"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices/9f8e7d6c-5b4a-4321-8fed-cba987654321/versions" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-admin-plans--plan--prices--price--versions">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;7c6b5a4d-...&quot;,
            &quot;amount&quot;: 3990,
            &quot;currency&quot;: &quot;brl&quot;,
            &quot;frequency&quot;: &quot;monthly&quot;,
            &quot;trial_period_days&quot;: 7,
            &quot;reason&quot;: &quot;Reajuste anual&quot;,
            &quot;archived_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
            &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-admin-plans--plan--prices--price--versions" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-admin-plans--plan--prices--price--versions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-admin-plans--plan--prices--price--versions"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-admin-plans--plan--prices--price--versions" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-admin-plans--plan--prices--price--versions">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-admin-plans--plan--prices--price--versions" data-method="GET"
      data-path="api/admin/plans/{plan}/prices/{price}/versions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-admin-plans--plan--prices--price--versions', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-admin-plans--plan--prices--price--versions"
                    onclick="tryItOut('GETapi-admin-plans--plan--prices--price--versions');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-admin-plans--plan--prices--price--versions"
                    onclick="cancelTryOut('GETapi-admin-plans--plan--prices--price--versions');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-admin-plans--plan--prices--price--versions"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/admin/plans/{plan}/prices/{price}/versions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-admin-plans--plan--prices--price--versions"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-admin-plans--plan--prices--price--versions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-admin-plans--plan--prices--price--versions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="GETapi-admin-plans--plan--prices--price--versions"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>price</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="price"                data-endpoint="GETapi-admin-plans--plan--prices--price--versions"
               value="9f8e7d6c-5b4a-4321-8fed-cba987654321"
               data-component="url">
    <br>
<p>UUID do preço. Example: <code>9f8e7d6c-5b4a-4321-8fed-cba987654321</code></p>
            </div>
                    </form>

                    <h2 id="admin-planos-e-precos-PATCHapi-admin-plans--plan--prices--price-">Editar preço</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>No Stripe preço não se edita: é criado um novo e o anterior vai para o histórico. Quem já assina segue no valor antigo.</p>

<span id="example-requests-PATCHapi-admin-plans--plan--prices--price-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices/9f8e7d6c-5b4a-4321-8fed-cba987654321"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "amount": 5990,
    "currency": "brl",
    "frequency": "monthly",
    "trial_period_days": 7,
    "reason": "Reajuste anual"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices/9f8e7d6c-5b4a-4321-8fed-cba987654321" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"amount\": 5990,
    \"currency\": \"brl\",
    \"frequency\": \"monthly\",
    \"trial_period_days\": 7,
    \"reason\": \"Reajuste anual\"
}"
</code></pre></div>

</span>

<span id="example-responses-PATCHapi-admin-plans--plan--prices--price-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Pre&ccedil;o atualizado com sucesso.&quot;,
    &quot;data&quot;: {
        &quot;id&quot;: &quot;9f8e7d6c-...&quot;,
        &quot;amount&quot;: 4990,
        &quot;currency&quot;: &quot;brl&quot;,
        &quot;frequency&quot;: &quot;monthly&quot;,
        &quot;trial_period_days&quot;: 7,
        &quot;is_active&quot;: true,
        &quot;created_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2026-10-01T10:00:00.000000Z&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (404, preço de outro plano):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;price_plan_mismatch&quot;,
    &quot;message&quot;: &quot;O pre&ccedil;o informado n&atilde;o pertence a este plano.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-admin-plans--plan--prices--price-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-admin-plans--plan--prices--price-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-admin-plans--plan--prices--price-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-admin-plans--plan--prices--price-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-admin-plans--plan--prices--price-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-admin-plans--plan--prices--price-" data-method="PATCH"
      data-path="api/admin/plans/{plan}/prices/{price}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-admin-plans--plan--prices--price-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-admin-plans--plan--prices--price-"
                    onclick="tryItOut('PATCHapi-admin-plans--plan--prices--price-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-admin-plans--plan--prices--price-"
                    onclick="cancelTryOut('PATCHapi-admin-plans--plan--prices--price-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-admin-plans--plan--prices--price-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/admin/plans/{plan}/prices/{price}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>price</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="price"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="9f8e7d6c-5b4a-4321-8fed-cba987654321"
               data-component="url">
    <br>
<p>UUID do preço. Example: <code>9f8e7d6c-5b4a-4321-8fed-cba987654321</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>amount</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="amount"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="5990"
               data-component="body">
    <br>
<p>Valor em centavos. Example: <code>5990</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>currency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="currency"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="brl"
               data-component="body">
    <br>
<p>Moeda. Example: <code>brl</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>brl</code></li> <li><code>usd</code></li> <li><code>eur</code></li> <li><code>gbp</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>frequency</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="frequency"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="monthly"
               data-component="body">
    <br>
<p>Recorrência. Example: <code>monthly</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>monthly</code></li> <li><code>yearly</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>trial_period_days</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="trial_period_days"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="7"
               data-component="body">
    <br>
<p>Dias de teste grátis (0 a 365). Example: <code>7</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>reason</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="reason"                data-endpoint="PATCHapi-admin-plans--plan--prices--price-"
               value="Reajuste anual"
               data-component="body">
    <br>
<p>Motivo da mudança, fica no histórico (máx. 255). Example: <code>Reajuste anual</code></p>
        </div>
        </form>

                    <h2 id="admin-planos-e-precos-DELETEapi-admin-plans--plan--prices--price-">Remover preço</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Preço já usado em assinatura é só desativado.</p>

<span id="example-requests-DELETEapi-admin-plans--plan--prices--price-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices/9f8e7d6c-5b4a-4321-8fed-cba987654321"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/admin/plans/1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d/prices/9f8e7d6c-5b4a-4321-8fed-cba987654321" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-admin-plans--plan--prices--price-">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Pre&ccedil;o removido com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (409, único preço ativo da combinação):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;price_last_active_in_use&quot;,
    &quot;message&quot;: &quot;Este &eacute; o &uacute;nico pre&ccedil;o ativo para a combina&ccedil;&atilde;o de moeda e frequ&ecirc;ncia; cadastre outro antes de remov&ecirc;-lo.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-admin-plans--plan--prices--price-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-admin-plans--plan--prices--price-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-admin-plans--plan--prices--price-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-admin-plans--plan--prices--price-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-admin-plans--plan--prices--price-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-admin-plans--plan--prices--price-" data-method="DELETE"
      data-path="api/admin/plans/{plan}/prices/{price}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-admin-plans--plan--prices--price-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-admin-plans--plan--prices--price-"
                    onclick="tryItOut('DELETEapi-admin-plans--plan--prices--price-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-admin-plans--plan--prices--price-"
                    onclick="cancelTryOut('DELETEapi-admin-plans--plan--prices--price-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-admin-plans--plan--prices--price-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/admin/plans/{plan}/prices/{price}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-admin-plans--plan--prices--price-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-admin-plans--plan--prices--price-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-admin-plans--plan--prices--price-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>plan</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="plan"                data-endpoint="DELETEapi-admin-plans--plan--prices--price-"
               value="1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d"
               data-component="url">
    <br>
<p>UUID do plano. Example: <code>1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>price</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="price"                data-endpoint="DELETEapi-admin-plans--plan--prices--price-"
               value="9f8e7d6c-5b4a-4321-8fed-cba987654321"
               data-component="url">
    <br>
<p>UUID do preço. Example: <code>9f8e7d6c-5b4a-4321-8fed-cba987654321</code></p>
            </div>
                    </form>

                <h1 id="admin-landing">Admin: landing</h1>

    <p>Tema da landing page, <strong>só para o super admin</strong> (dono do SaaS). Para qualquer outro usuário a resposta é 403.</p>

                                <h2 id="admin-landing-PUTapi-admin-landing-settings">Trocar o tema da landing</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p><code>brand</code> aceita <code>pop</code>, <code>violeta</code>, <code>coral</code>, <code>bambu</code> ou <code>rose</code> (os valores de <code>App\Enums\LandingBrand</code>). Repetir o mesmo valor não muda nada.</p>

<span id="example-requests-PUTapi-admin-landing-settings">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/admin/landing-settings"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "brand": "coral"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/admin/landing-settings" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"brand\": \"coral\"
}"
</code></pre></div>

</span>

<span id="example-responses-PUTapi-admin-landing-settings">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Tema da landing atualizado.&quot;,
    &quot;data&quot;: {
        &quot;brand&quot;: &quot;coral&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (403):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Esse tema n&atilde;o est&aacute; dispon&iacute;vel para a landing.&quot;,
    &quot;errors&quot;: {
        &quot;brand&quot;: [
            &quot;Esse tema n&atilde;o est&aacute; dispon&iacute;vel para a landing.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-admin-landing-settings" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-admin-landing-settings"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-admin-landing-settings"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-admin-landing-settings" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-admin-landing-settings">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-admin-landing-settings" data-method="PUT"
      data-path="api/admin/landing-settings"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-admin-landing-settings', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-admin-landing-settings"
                    onclick="tryItOut('PUTapi-admin-landing-settings');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-admin-landing-settings"
                    onclick="cancelTryOut('PUTapi-admin-landing-settings');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-admin-landing-settings"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/admin/landing-settings</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-admin-landing-settings"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-admin-landing-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-admin-landing-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>brand</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="brand"                data-endpoint="PUTapi-admin-landing-settings"
               value="coral"
               data-component="body">
    <br>
<p>Tema da landing. Example: <code>coral</code></p>
        </div>
        </form>

                <h1 id="landing-publico">Landing (público)</h1>

    <p>Configuração visual da landing page, definida pelo super admin.</p>

                                <h2 id="landing-publico-GETapi-landing-settings">Tema da landing</h2>

<p>
</p>

<p>Tema que a landing deve usar. Não precisa de login. Sem configuração salva, devolve <code>pop</code>.</p>
<p>Limite: 45 requisições por minuto.</p>

<span id="example-requests-GETapi-landing-settings">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/landing-settings"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/landing-settings" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-landing-settings">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;brand&quot;: &quot;pop&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-landing-settings" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-landing-settings"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-landing-settings"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-landing-settings" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-landing-settings">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-landing-settings" data-method="GET"
      data-path="api/landing-settings"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-landing-settings', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-landing-settings"
                    onclick="tryItOut('GETapi-landing-settings');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-landing-settings"
                    onclick="cancelTryOut('GETapi-landing-settings');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-landing-settings"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/landing-settings</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-landing-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-landing-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="meu-perfil">Meu perfil</h1>

    <p>Dados, foto e senha do <strong>próprio</strong> usuário autenticado. Não exigem e-mail verificado.</p>
<p><strong>Foto de perfil (3 passos, mesmo padrão dos uploads de post):</strong></p>
<ol>
<li><code>POST /me/avatar/upload-url</code> com o <code>content_type</code> → recebe <code>url</code>, <code>headers</code> e <code>path</code>.</li>
<li><code>PUT</code> do arquivo direto na <code>url</code> (envie os <code>headers</code> e o mesmo <code>Content-Type</code>; sem token da API).</li>
<li><code>PUT /me/avatar</code> com o <code>path</code> → a API confere tipo e tamanho (até 5 MB), troca a foto e apaga a antiga.</li>
</ol>
<p>O campo <code>avatar</code> do usuário é uma URL assinada que <strong>muda a cada <code>GET /me</code></strong> (válida por ~6 dias).</p>

                                <h2 id="meu-perfil-PATCHapi-me">Atualizar dados pessoais</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>O e-mail não é alterável por aqui. Envie <code>phone</code> e <code>bio</code> como <code>null</code> para apagá-los.
O telefone aceita máscara; é gravado só com dígitos (DDD + número).</p>

<span id="example-requests-PATCHapi-me">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "Maria Souza",
    "phone": "11912345678",
    "bio": "Crio conteúdo sobre receitas.",
    "timezone": "America\/Sao_Paulo"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/me" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"Maria Souza\",
    \"phone\": \"11912345678\",
    \"bio\": \"Crio conteúdo sobre receitas.\",
    \"timezone\": \"America\\/Sao_Paulo\"
}"
</code></pre></div>

</span>

<span id="example-responses-PATCHapi-me">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 12,
        &quot;name&quot;: &quot;Maria Souza&quot;,
        &quot;email&quot;: &quot;maria@exemplo.com&quot;,
        &quot;avatar&quot;: null,
        &quot;phone&quot;: &quot;11912345678&quot;,
        &quot;bio&quot;: &quot;Crio conte&uacute;do sobre receitas.&quot;,
        &quot;timezone&quot;: &quot;America/Sao_Paulo&quot;,
        &quot;created_at&quot;: &quot;2026-03-02T14:20:00-03:00&quot;,
        &quot;email_verified_at&quot;: &quot;2026-03-02T14:25:10-03:00&quot;,
        &quot;is_active&quot;: true,
        &quot;is_super_admin&quot;: false,
        &quot;permissions&quot;: []
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Esse fuso hor&aacute;rio n&atilde;o &eacute; v&aacute;lido.&quot;,
    &quot;errors&quot;: {
        &quot;timezone&quot;: [
            &quot;Esse fuso hor&aacute;rio n&atilde;o &eacute; v&aacute;lido.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-me" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-me"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-me" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-me">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-me" data-method="PATCH"
      data-path="api/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-me', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-me"
                    onclick="tryItOut('PATCHapi-me');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-me"
                    onclick="cancelTryOut('PATCHapi-me');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-me"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-me"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PATCHapi-me"
               value="Maria Souza"
               data-component="body">
    <br>
<p>Nome completo (3 a 80 caracteres). Example: <code>Maria Souza</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>phone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="phone"                data-endpoint="PATCHapi-me"
               value="11912345678"
               data-component="body">
    <br>
<p>Telefone com DDD, 10 ou 11 dígitos, ou <code>null</code>. Example: <code>11912345678</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>bio</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="bio"                data-endpoint="PATCHapi-me"
               value="Crio conteúdo sobre receitas."
               data-component="body">
    <br>
<p>Até 160 caracteres, ou <code>null</code>. Example: <code>Crio conteúdo sobre receitas.</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>timezone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="timezone"                data-endpoint="PATCHapi-me"
               value="America/Sao_Paulo"
               data-component="body">
    <br>
<p>Fuso horário IANA. Example: <code>America/Sao_Paulo</code></p>
        </div>
        </form>

                    <h2 id="meu-perfil-POSTapi-me-avatar-upload-url">Pedir URL de upload da foto</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Devolve uma URL pré-assinada de PUT. Depois do PUT, confirme com <code>PUT /me/avatar</code>.</p>

<span id="example-requests-POSTapi-me-avatar-upload-url">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me/avatar/upload-url"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "content_type": "image\/jpeg"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:9000/api/me/avatar/upload-url" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"content_type\": \"image\\/jpeg\"
}"
</code></pre></div>

</span>

<span id="example-responses-POSTapi-me-avatar-upload-url">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;url&quot;: &quot;https://storage.exemplo.com/pandypost/users/5f0c.../avatars/a1b2....jpg?X-Amz-Signature=...&quot;,
    &quot;headers&quot;: {},
    &quot;path&quot;: &quot;users/5f0c.../avatars/a1b2....jpg&quot;,
    &quot;content_type&quot;: &quot;image/jpeg&quot;,
    &quot;max_size&quot;: 5242880,
    &quot;expires_in&quot;: 86400
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Use uma imagem JPG, PNG ou WebP.&quot;,
    &quot;errors&quot;: {
        &quot;content_type&quot;: [
            &quot;Use uma imagem JPG, PNG ou WebP.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-me-avatar-upload-url" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-POSTapi-me-avatar-upload-url"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-me-avatar-upload-url"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-me-avatar-upload-url" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-POSTapi-me-avatar-upload-url">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-POSTapi-me-avatar-upload-url" data-method="POST"
      data-path="api/me/avatar/upload-url"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-me-avatar-upload-url', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-me-avatar-upload-url"
                    onclick="tryItOut('POSTapi-me-avatar-upload-url');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-me-avatar-upload-url"
                    onclick="cancelTryOut('POSTapi-me-avatar-upload-url');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-me-avatar-upload-url"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/me/avatar/upload-url</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-me-avatar-upload-url"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-me-avatar-upload-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-me-avatar-upload-url"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>content_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="content_type"                data-endpoint="POSTapi-me-avatar-upload-url"
               value="image/jpeg"
               data-component="body">
    <br>
<p><code>image/jpeg</code>, <code>image/png</code> ou <code>image/webp</code>. Example: <code>image/jpeg</code></p>
        </div>
        </form>

                    <h2 id="meu-perfil-PUTapi-me-avatar">Confirmar a foto enviada</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Confere que o arquivo é do próprio usuário, existe, é imagem JPG/PNG/WebP de até 5 MB, troca a foto
e apaga a anterior. Arquivo inválido é apagado do storage.</p>

<span id="example-requests-PUTapi-me-avatar">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me/avatar"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "path": "users\/5f0c...\/avatars\/a1b2....jpg"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/me/avatar" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"path\": \"users\\/5f0c...\\/avatars\\/a1b2....jpg\"
}"
</code></pre></div>

</span>

<span id="example-responses-PUTapi-me-avatar">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 12,
        &quot;name&quot;: &quot;Maria Souza&quot;,
        &quot;avatar&quot;: &quot;https://storage.exemplo.com/...assinada...&quot;,
        &quot;email&quot;: &quot;maria@exemplo.com&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;profile_avatar_too_large&quot;,
    &quot;message&quot;: &quot;A foto &eacute; maior que o limite de 5 MB. Escolha uma imagem menor.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-me-avatar" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-me-avatar"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-me-avatar"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-me-avatar" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-me-avatar">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-me-avatar" data-method="PUT"
      data-path="api/me/avatar"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-me-avatar', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-me-avatar"
                    onclick="tryItOut('PUTapi-me-avatar');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-me-avatar"
                    onclick="cancelTryOut('PUTapi-me-avatar');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-me-avatar"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/me/avatar</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-me-avatar"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-me-avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-me-avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>path</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="path"                data-endpoint="PUTapi-me-avatar"
               value="users/5f0c.../avatars/a1b2....jpg"
               data-component="body">
    <br>
<p><code>path</code> recebido em <code>POST /me/avatar/upload-url</code>. Example: <code>users/5f0c.../avatars/a1b2....jpg</code></p>
        </div>
        </form>

                    <h2 id="meu-perfil-DELETEapi-me-avatar">Remover a foto</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Apaga o arquivo e limpa o campo. Sem foto, responde 200 do mesmo jeito.</p>

<span id="example-requests-DELETEapi-me-avatar">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me/avatar"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/me/avatar" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-me-avatar">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 12,
        &quot;name&quot;: &quot;Maria Souza&quot;,
        &quot;avatar&quot;: null,
        &quot;email&quot;: &quot;maria@exemplo.com&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-me-avatar" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-me-avatar"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-me-avatar"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-me-avatar" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-me-avatar">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-me-avatar" data-method="DELETE"
      data-path="api/me/avatar"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-me-avatar', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-me-avatar"
                    onclick="tryItOut('DELETEapi-me-avatar');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-me-avatar"
                    onclick="cancelTryOut('DELETEapi-me-avatar');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-me-avatar"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/me/avatar</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-me-avatar"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-me-avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-me-avatar"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="meu-perfil-PUTapi-me-password">Trocar a senha</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Exige a senha atual. A nova precisa de 8+ caracteres, 1 maiúscula e 1 número, e ser diferente da atual.
As <strong>outras</strong> sessões (tokens) são encerradas; a sessão atual continua válida.</p>
<p>Limite: 5 requisições por minuto.</p>

<span id="example-requests-PUTapi-me-password">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/me/password"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "current_password": "SenhaAntiga1",
    "password": "NovaSenha123",
    "password_confirmation": "NovaSenha123"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:9000/api/me/password" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"current_password\": \"SenhaAntiga1\",
    \"password\": \"NovaSenha123\",
    \"password_confirmation\": \"NovaSenha123\"
}"
</code></pre></div>

</span>

<span id="example-responses-PUTapi-me-password">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Senha atualizada com sucesso.&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;A senha atual est&aacute; incorreta.&quot;,
    &quot;errors&quot;: {
        &quot;current_password&quot;: [
            &quot;A senha atual est&aacute; incorreta.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-me-password" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PUTapi-me-password"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-me-password"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-me-password" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PUTapi-me-password">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PUTapi-me-password" data-method="PUT"
      data-path="api/me/password"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-me-password', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-me-password"
                    onclick="tryItOut('PUTapi-me-password');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-me-password"
                    onclick="cancelTryOut('PUTapi-me-password');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-me-password"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/me/password</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-me-password"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-me-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-me-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>current_password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="current_password"                data-endpoint="PUTapi-me-password"
               value="SenhaAntiga1"
               data-component="body">
    <br>
<p>Senha atual. Example: <code>SenhaAntiga1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="PUTapi-me-password"
               value="NovaSenha123"
               data-component="body">
    <br>
<p>Nova senha. Example: <code>NovaSenha123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password_confirmation</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password_confirmation"                data-endpoint="PUTapi-me-password"
               value="NovaSenha123"
               data-component="body">
    <br>
<p>Repetir a nova senha. Example: <code>NovaSenha123</code></p>
        </div>
        </form>

                <h1 id="notificacoes">Notificações</h1>

    <p>Central de notificações do usuário logado, por workspace: lista as do workspace corrente
mais os convites recebidos (<code>workspace_invite</code>), que aparecem em qualquer workspace.
Não exige permissão de grupo. Notificação de outro usuário responde 404.</p>
<p>Tipos: <code>workspace_invite</code>, <code>post_failed</code>, <code>payment_failed</code>, <code>subscription_canceled</code>,
<code>social_account_disconnected</code>. Em tempo real: canal privado <code>App.Models.User.{id}</code>,
evento <code>Illuminate\Notifications\Events\BroadcastNotificationCreated</code>.</p>

                                <h2 id="notificacoes-GETapi-notifications">Listar notificações</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-notifications">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/notifications"
);

const params = {
    "read": "unread",
    "type": "post_failed",
    "per_page": "15",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/notifications?read=unread&amp;type=post_failed&amp;per_page=15" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-notifications">
    </span>
<span id="execution-results-GETapi-notifications" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-notifications"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-notifications"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-notifications" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-notifications">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-notifications" data-method="GET"
      data-path="api/notifications"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-notifications', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-notifications"
                    onclick="tryItOut('GETapi-notifications');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-notifications"
                    onclick="cancelTryOut('GETapi-notifications');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-notifications"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/notifications</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-notifications"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-notifications"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-notifications"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Parâmetros de query</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>read</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="read"                data-endpoint="GETapi-notifications"
               value="unread"
               data-component="query">
    <br>
<p><code>all</code> (padrão), <code>unread</code> ou <code>read</code>. Example: <code>unread</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="GETapi-notifications"
               value="post_failed"
               data-component="query">
    <br>
<p>Tipo da notificação. Example: <code>post_failed</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-notifications"
               value="15"
               data-component="query">
    <br>
<p>De 1 a 100 (padrão 15). Example: <code>15</code></p>
            </div>
                </form>

                    <h2 id="notificacoes-GETapi-notifications-unread">Listar não lidas</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Até 50 mais recentes; <code>total_unread</code> traz o total real.</p>

<span id="example-requests-GETapi-notifications-unread">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/notifications/unread"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/notifications/unread" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-notifications-unread">
    </span>
<span id="execution-results-GETapi-notifications-unread" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-notifications-unread"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-notifications-unread"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-notifications-unread" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-notifications-unread">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-notifications-unread" data-method="GET"
      data-path="api/notifications/unread"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-notifications-unread', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-notifications-unread"
                    onclick="tryItOut('GETapi-notifications-unread');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-notifications-unread"
                    onclick="cancelTryOut('GETapi-notifications-unread');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-notifications-unread"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/notifications/unread</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-notifications-unread"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-notifications-unread"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-notifications-unread"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="notificacoes-PATCHapi-notifications-read-all">Marcar todas como lidas</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Vale para as notificações visíveis no workspace corrente.</p>

<span id="example-requests-PATCHapi-notifications-read-all">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/notifications/read-all"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "PATCH",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/notifications/read-all" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-PATCHapi-notifications-read-all">
</span>
<span id="execution-results-PATCHapi-notifications-read-all" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-notifications-read-all"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-notifications-read-all"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-notifications-read-all" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-notifications-read-all">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-notifications-read-all" data-method="PATCH"
      data-path="api/notifications/read-all"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-notifications-read-all', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-notifications-read-all"
                    onclick="tryItOut('PATCHapi-notifications-read-all');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-notifications-read-all"
                    onclick="cancelTryOut('PATCHapi-notifications-read-all');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-notifications-read-all"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/notifications/read-all</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-notifications-read-all"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-notifications-read-all"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-notifications-read-all"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="notificacoes-PATCHapi-notifications--notification--read">Marcar como lida</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-notifications--notification--read">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/notifications/architecto/read"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "PATCH",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/notifications/architecto/read" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-PATCHapi-notifications--notification--read">
</span>
<span id="execution-results-PATCHapi-notifications--notification--read" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-notifications--notification--read"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-notifications--notification--read"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-notifications--notification--read" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-notifications--notification--read">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-notifications--notification--read" data-method="PATCH"
      data-path="api/notifications/{notification}/read"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-notifications--notification--read', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-notifications--notification--read"
                    onclick="tryItOut('PATCHapi-notifications--notification--read');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-notifications--notification--read"
                    onclick="cancelTryOut('PATCHapi-notifications--notification--read');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-notifications--notification--read"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/notifications/{notification}/read</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-notifications--notification--read"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-notifications--notification--read"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-notifications--notification--read"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>notification</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="notification"                data-endpoint="PATCHapi-notifications--notification--read"
               value="architecto"
               data-component="url">
    <br>
<p>The notification. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="notificacoes-DELETEapi-notifications--notification-">Remover notificação</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-notifications--notification-">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/notifications/architecto"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:9000/api/notifications/architecto" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-DELETEapi-notifications--notification-">
</span>
<span id="execution-results-DELETEapi-notifications--notification-" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-DELETEapi-notifications--notification-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-notifications--notification-"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-notifications--notification-" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-notifications--notification-">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-DELETEapi-notifications--notification-" data-method="DELETE"
      data-path="api/notifications/{notification}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-notifications--notification-', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-notifications--notification-"
                    onclick="tryItOut('DELETEapi-notifications--notification-');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-notifications--notification-"
                    onclick="cancelTryOut('DELETEapi-notifications--notification-');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-notifications--notification-"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/notifications/{notification}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-notifications--notification-"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-notifications--notification-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-notifications--notification-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Parâmetros de URL</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>notification</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="notification"                data-endpoint="DELETEapi-notifications--notification-"
               value="architecto"
               data-component="url">
    <br>
<p>The notification. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="notificacoes-GETapi-notification-preferences">Ler preferências</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retorna as preferências de e-mail e de aviso no app do usuário logado.</p>
<p><strong>Permissão:</strong> <code>notifications.view</code></p>

<span id="example-requests-GETapi-notification-preferences">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/notification-preferences"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:9000/api/notification-preferences" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>

</span>

<span id="example-responses-GETapi-notification-preferences">
            <blockquote>
            <p>Exemplo de resposta (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;invite&quot;: true,
        &quot;posts&quot;: true,
        &quot;social_accounts&quot;: true,
        &quot;post_published&quot;: true,
        &quot;in_app&quot;: {
            &quot;invite&quot;: true,
            &quot;posts&quot;: true,
            &quot;social_accounts&quot;: true,
            &quot;post_published&quot;: false
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-notification-preferences" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-GETapi-notification-preferences"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-notification-preferences"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-notification-preferences" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-GETapi-notification-preferences">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-GETapi-notification-preferences" data-method="GET"
      data-path="api/notification-preferences"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-notification-preferences', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-notification-preferences"
                    onclick="tryItOut('GETapi-notification-preferences');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-notification-preferences"
                    onclick="cancelTryOut('GETapi-notification-preferences');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-notification-preferences"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/notification-preferences</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-notification-preferences"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-notification-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-notification-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="notificacoes-PATCHapi-notification-preferences">Alterar preferências</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Envie só as famílias que quer mudar.</p>
<p><strong>Permissão:</strong> <code>notifications.update</code></p>

<span id="example-requests-PATCHapi-notification-preferences">
<blockquote>Exemplo de requisição:</blockquote>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:9000/api/notification-preferences"
);

const headers = {
    "Authorization": "Bearer {TOKEN}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "invite": true,
    "posts": false,
    "social_accounts": true,
    "in_app": {
        "posts": false,
        "invite": true,
        "social_accounts": true,
        "post_published": true
    },
    "post_published": true
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://localhost:9000/api/notification-preferences" \
    --header "Authorization: Bearer {TOKEN}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"invite\": true,
    \"posts\": false,
    \"social_accounts\": true,
    \"in_app\": {
        \"posts\": false,
        \"invite\": true,
        \"social_accounts\": true,
        \"post_published\": true
    },
    \"post_published\": true
}"
</code></pre></div>

</span>

<span id="example-responses-PATCHapi-notification-preferences">
            <blockquote>
            <p>Exemplo de resposta (403, sem permissão):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Voc&ecirc; n&atilde;o tem permiss&atilde;o para acessar este recurso.&quot;,
    &quot;required_permission&quot;: &quot;notifications.update&quot;
}</code>
 </pre>
            <blockquote>
            <p>Exemplo de resposta (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Os e-mails de cobran&ccedil;a n&atilde;o podem ser desativados.&quot;,
    &quot;errors&quot;: {
        &quot;billing&quot;: [
            &quot;Os e-mails de cobran&ccedil;a n&atilde;o podem ser desativados.&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-PATCHapi-notification-preferences" hidden>
    <blockquote>Resposta recebida<span
                id="execution-response-status-PATCHapi-notification-preferences"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-notification-preferences"
      data-empty-response-text="<Resposta vazia>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-notification-preferences" hidden>
    <blockquote>A requisição falhou com o erro:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-notification-preferences">

Dica: confira sua conexão. Se você mantém esta API, verifique se ela está no ar e com CORS liberado.
Os detalhes aparecem no console do navegador.</code></pre>
</span>
<form id="form-PATCHapi-notification-preferences" data-method="PATCH"
      data-path="api/notification-preferences"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-notification-preferences', this);">
    <h3>
        Requisição&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-notification-preferences"
                    onclick="tryItOut('PATCHapi-notification-preferences');">Testar ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-notification-preferences"
                    onclick="cancelTryOut('PATCHapi-notification-preferences');" hidden>Cancelar
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-notification-preferences"
                    data-initial-text="Enviar"
                    data-loading-text="⏱ Enviando..."
                    hidden>Enviar
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/notification-preferences</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PATCHapi-notification-preferences"
               value="Bearer {TOKEN}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {TOKEN}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-notification-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-notification-preferences"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Corpo da requisição</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>invite</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="invite"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="invite"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>E-mail de convites. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>posts</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="posts"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="posts"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>E-mail de falha de posts. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>social_accounts</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="social_accounts"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="social_accounts"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>E-mail de contas sociais. Example: <code>true</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
        <details>
            <summary style="padding-bottom: 10px;">
                <b style="line-height: 2;"><code>in_app</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
<br>
<p>Aviso no app (sino + tempo real) por família; envie só o que muda.</p>
            </summary>
                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>invite</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.invite"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.invite"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Aviso no app de convites. Example: <code>true</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>posts</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.posts"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.posts"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Aviso no app de falha de posts. Example: <code>false</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>social_accounts</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.social_accounts"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.social_accounts"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Aviso no app de contas sociais. Example: <code>true</code></p>
                    </div>
                                                                <div style="margin-left: 14px; clear: unset;">
                        <b style="line-height: 2;"><code>post_published</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.post_published"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="in_app.post_published"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Aviso no app quando um post seu é publicado; padrão desligado (opt-in). Example: <code>true</code></p>
                    </div>
                                    </details>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>post_published</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="post_published"
                   value="true"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PATCHapi-notification-preferences" style="display: none">
            <input type="radio" name="post_published"
                   value="false"
                   data-endpoint="PATCHapi-notification-preferences"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>E-mail quando um post seu é publicado; padrão ligado. Example: <code>true</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                            </div>
            </div>
</div>
</body>
</html>
