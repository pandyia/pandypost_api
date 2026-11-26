# Kit de submissão: Meta App Review (Instagram API com Instagram Login)

> Rascunho de 2026-11-25. Complementa `docs/manual-producao.md` §2. Os requisitos da Meta mudam: confira cada item no App Dashboard antes de enviar. Textos de justificativa em **inglês** (revisores da Meta); roteiro e checklist em pt-BR.

## 1. Permissões a solicitar (Advanced Access)

| Permissão | Usada para | Onde no código |
|---|---|---|
| `instagram_business_basic` | Conectar a conta e exibir nome/foto/@usuário | `InstagramOAuthProvider` |
| `instagram_business_content_publish` | Agendar e publicar vídeo/Reel na conta do cliente | `InstagramApi` (`/media`, `/media_publish`) |
| `instagram_business_manage_insights` | Painel de analytics (alcance, visualizações, curtidas etc.) | `InstagramAnalyticsProvider` |
| ~~`instagram_business_manage_messages`~~ | **Sem uso: já removido do código, NÃO pedir** | `InstagramOAuthProvider` |

> Pedir permissão sem uso reprova a submissão. O nome no portal pode aparecer como `instagram_business_content_publishing`: use o que o App Dashboard mostrar.

## 2. Justificativas (colar em cada permissão)

**Descrição geral do app**
> Pandypost is a SaaS that lets teams schedule and publish short videos to YouTube, TikTok and Instagram from one dashboard. Customers connect their own Instagram professional account, schedule a video with caption and date/time, and Pandypost publishes it at the chosen time and shows performance analytics.

**instagram_business_basic**
> Used to identify the Instagram professional account the customer connects (username, name, profile picture) so they can choose which account to schedule posts to. We show this info in the "Connected accounts" screen. No other profile data is read or stored beyond what is needed to display the account.

**instagram_business_content_publish**
> Core feature. After the customer connects their account and schedules a video, Pandypost creates a media container and publishes it to the customer's account at the scheduled time (`POST /{ig-user-id}/media` and `/media_publish`). Publishing only happens after explicit user action (the user creates and confirms the schedule). Without this permission the product cannot publish.

**instagram_business_manage_insights**
> Used to show the customer analytics of their own account and published posts (reach, views, likes, comments, followers) in the Analytics screen, so they can measure the result of the videos scheduled in Pandypost. Data is shown only to members of the customer's workspace and is never shared or sold.

**Tratamento de dados (campos comuns)**
> Tokens are stored encrypted, scoped to the customer's workspace, and deleted when the customer disconnects the account or requests data deletion at <URL de exclusão de dados>.

## 3. Roteiro dos screencasts (um vídeo por permissão, ou um só cobrindo todas)

Regras: UI em inglês (ou legendas), gravação de tela contínua, mostrar a URL/domínio de produção, mostrar o **fluxo de login/consentimento da Meta** inteiro.

**Vídeo A: `basic` + `content_publish`**
1. Abrir o site de produção e fazer login com o usuário de teste.
2. Ir em *Connected accounts* → *Connect Instagram*.
3. Mostrar a tela de consentimento da Meta com as permissões e aceitar.
4. Voltar ao Pandypost: conta aparece com @usuário e foto (`basic`).
5. Ir em *New post*: enviar um vídeo, escrever legenda, escolher a conta Instagram, escolher data/hora, confirmar o agendamento.
6. Mostrar o post agendado na lista/calendário.
7. Aguardar (ou usar "publicar agora") e mostrar o status *Published*; abrir o Instagram (app/web) e mostrar o Reel publicado na conta.

**Vídeo B: `manage_insights`**
1. Logado, abrir *Analytics* com a conta Instagram selecionada.
2. Mostrar métricas da conta e de um post específico.
3. Destacar que os números vêm da conta conectada.

## 4. Passo a passo no portal

**Dono (só você consegue):**
- [ ] Verificação da empresa (Business Verification) e ligar o app ao negócio verificado.
- [ ] Dados do app: ícone 1024x1024, categoria, e-mail de contato, URL de **privacidade**, URL de **termos**, URL/callback de **exclusão de dados**.
- [ ] Em *Instagram > API setup with Instagram login*: cadastrar o **redirect URI** de produção `{APP_URL}/api/social-accounts/instagram/callback` (igual ao `META_REDIRECT_URI`).
- [ ] Criar o usuário de teste (login e senha) do Pandypost para os revisores e uma conta Instagram profissional de teste com papel de testador no app.
- [ ] Fazer ao menos **1 chamada de API bem-sucedida** por permissão (publicar 1 Reel de teste e abrir o Analytics).
- [ ] Gravar os vídeos, preencher *App Review > Requests* (descrição + vídeo por permissão + instruções de teste) e enviar.
- [ ] Após aprovado, mudar o app para **Live**.

**Instruções de teste para o revisor (colar):**
> 1. Go to <URL de produção> and log in with the test credentials: <e-mail> / <senha>.
> 2. Open "Connected accounts" and click "Connect Instagram"; log in with the Instagram test account <@conta> and accept the permissions.
> 3. Open "New post", upload a short vertical video, write a caption, select the Instagram account, choose "Publish now" (or a time 5 minutes ahead) and confirm.
> 4. Check the post status turns "Published" and the Reel appears on the Instagram account.
> 5. Open "Analytics" and select the Instagram account to see insights.

## 5. Pré-requisitos no produto (conferir antes de gravar)

- [ ] App em produção, acessível, sem erro; o S3 com endpoint público (a Meta baixa o vídeo pela URL presigned).
- [ ] Home pública + páginas de privacidade e termos com URL própria (hoje os termos abrem em modal no `SignupView.vue`).
- [ ] Tela de exclusão de dados / desconexão da conta funcionando.
- [ ] Interface em inglês (ou legendas no vídeo) para a gravação.
- [x] Removido `instagram_business_manage_messages` do código.

## 6. Causas comuns de reprova

- Permissão pedida sem aparecer no vídeo.
- Vídeo sem mostrar a tela de consentimento da Meta ou cortado antes do resultado.
- Credenciais de teste que não funcionam (2FA, conta bloqueada).
- Política de privacidade inacessível ou genérica, sem mencionar dados do Instagram.
- App em modo desenvolvimento sem negócio verificado.
