# Manual: colocar o Pandypost API em produção (redes sociais + Stripe)

> Escrito em 2026-11-17. Cada requisito cita a página oficial **como foi lida nessa data**. Itens marcados **[CONFERIR]** têm fonte conflitante ou ausente: confirme direto na página/portal antes de decidir. Requisitos das redes mudam; releia a fonte antes de submeter.
> Legenda de responsável: **Dono** = só você consegue (portais, empresa, domínio, contas). **Claude** = dá para fazer no repositório, com a sua autorização.

## 0. Situação hoje (código)

| Rede | Escopos pedidos hoje | Onde |
|---|---|---|
| TikTok | `user.info.basic, user.info.profile, user.info.stats, video.list, video.upload, video.publish` | `TikTokOAuthProvider::SCOPES` |
| Instagram (Instagram Login) | `instagram_business_basic, instagram_business_content_publish, instagram_business_manage_insights` | `InstagramOAuthProvider` (linha 28) |
| YouTube/Google | `youtube.upload, youtube.readonly, yt-analytics.readonly` | `GoogleOAuthProvider` |
| Stripe | chaves/webhook em `.env` (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`) | `config/services.php` |

Sem aprovação: TikTok só publica `SELF_ONLY` (e no máximo 5 usuários/24h); Instagram só para contas com papel no app; YouTube com cota padrão de 100 uploads/dia (ver §3).

## 1. Pré-requisitos comuns (Dono)

- [ ] Domínio de produção do **site** com **home pública** (não só a tela de login), **política de privacidade** e **termos** em URL própria, visíveis sem abrir menu. Hoje o `SignupView.vue` abre termos em modal (`href="#"`), então falta a página com URL. (Claude pode criar as páginas no front/landing, se pedir.)
- [ ] URL de **exclusão de dados** (a Meta pede no Business Login; ver §2).
- [ ] Ícone do app 1024x1024, nome do app sem citar redes sociais (TikTok), e-mail de contato.
- [ ] Ambiente de produção **no ar e testável** pelos revisores (logins de teste, se necessário). O app não pode estar "em desenvolvimento/teste".
- [ ] `*_REDIRECT_URI` de produção iguais aos cadastrados em cada portal: `{APP_URL}/api/social-accounts/<provider>/callback` (`tiktok`, `instagram`, `google`). Conferir `META_REDIRECT_URI`, `GOOGLE_REDIRECT_URI`, `TIKTOK_REDIRECT_URI`.
- [ ] **S3 de produção com endpoint público**: o Instagram depende de a Meta baixar a URL presigned (container ERROR se inalcançável). Ver `memória spec-023`.

## 2. Instagram (Meta)

**Fontes oficiais:**
- App Review (Instagram API): https://developers.facebook.com/documentation/instagram-platform/app-review
- Criar app Instagram: https://developers.facebook.com/documentation/development/create-an-app/other-app-types/instagram-apis
- Screen recordings: https://developers.facebook.com/docs/app-review/submission-guide/screen-recordings
- Passo a passo da submissão: https://developers.facebook.com/docs/resp-plat-initiatives/individual-processes/app-review/submission-guide
- Permissões: https://developers.facebook.com/docs/permissions

**O que a documentação diz:**
- Serve só contas que você possui/gerencia → **Standard Access**, sem review. Serve contas de **outros** negócios (nosso caso: clientes) → **Advanced Access**, e "Your app must complete App Review before it can request permissions with Advanced Access from any app user."
- Não se pode usar Facebook Login e Instagram Login no mesmo app. Nós usamos Instagram Login.
- A página de criação do app diz: "You're required to connect your app to a business that has completed Business Verification before you can publish your app." e "Instagram requires successful completion of the App Review process before your app can access live data."
- Para pedir Advanced Access é preciso **ao menos 1 chamada de API bem-sucedida**.
- Itens da submissão: ícone 1024x1024, URL de política de privacidade, categoria do app, e-mail, instruções passo a passo de login e teste (com credenciais de teste), botão de login visível, descrição do caso de uso.
- Screencast por permissão: fluxo ponta a ponta mostrando o uso daquela permissão; UI em inglês se possível, com legendas/tooltips se não for.
- Por permissão: descrever o uso, enviar o screencast e aceitar o uso permitido. "Requesting permissions your app doesn't use ... your submission will not be approved." (parafraseado do resumo da página).

**[CONFERIR] antes de submeter:**
1. Nome exato das permissões: o resumo da página de App Review listou `instagram_business_content_publishing`, e o código usa `instagram_business_content_publish`. Confirme no App Dashboard o nome que o portal mostra.
2. Se Business Verification é exigida para o Advanced Access em si (a página de criação a liga à **publicação do app**; a de App Review não menciona). Um resultado de busca afirmou a exigência, mas não consegui confirmar na página oficial.
3. Prazo de análise: nenhuma das páginas lidas informa.
4. "Access Verification" está **descontinuada** ("no longer required", https://developers.facebook.com/docs/development/release/access-verification/): ignore guias antigos que a citam.

**Passos:**
- [ ] (Dono) Verificação da empresa na Meta e ligar o app ao negócio verificado.
- [ ] (Dono) Preencher dados do app (ícone, privacidade, categoria, exclusão de dados).
- [x] (Claude) Removido `instagram_business_manage_messages`: não há uso no código e permissão sem uso reprova a submissão.
- [ ] (Claude) Escrever justificativa e roteiro de screencast por permissão: `basic` (conectar/ler perfil), `content_publish` (agendar e publicar), `manage_insights` (analytics).
- [ ] (Dono) Gravar os vídeos, fazer 1 chamada de API bem-sucedida e submeter em *App Review > Requests*.
- [ ] (Dono) Publicar o app (Live) após aprovação.

## 3. YouTube (Google)

**Fontes oficiais:**
- Cota e auditorias: https://developers.google.com/youtube/data/guides/quota_and_compliance_audits
- Formulário de auditoria/extensão de cota: https://support.google.com/youtube/contact/yt_api_form
- `videos.insert`: https://developers.google.com/youtube/v3/docs/videos/insert
- Políticas para desenvolvedores: https://developers.google.com/youtube/terms/developer-policies e guia https://developers.google.com/youtube/terms/developer-policies-guide
- Termos da API: https://developers.google.com/youtube/terms/api-services-terms-of-service
- Verificação de escopo sensível: https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification
- Verificação restrita: https://developers.google.com/identity/protocols/oauth2/production-readiness/restricted-scope-verification
- Requisitos/submissão/FAQ: https://support.google.com/cloud/answer/13464321 · https://support.google.com/cloud/answer/13461325 · https://support.google.com/cloud/answer/13463817

**O que a documentação diz:**
- Cota padrão por projeto: "100 `search.list` calls, 100 `videos.insert` calls, and 10,000 units per day combined". Um upload custa "1 unit in the Video Uploads quota bucket", limite de 100 chamadas/dia. **Isso é um teto de 100 vídeos/dia para todos os clientes juntos.** Para mais é preciso passar na **auditoria de conformidade** (formulário *Audit and Quota Extension*). A página não dá prazo: "A member of YouTube's API Services team will contact you as soon as possible."
- O formulário, segundo resumo da busca, exige para projetos de upload um **script completo ou screencast ponta a ponta** do upload até o vídeo no ar (imagens estáticas não bastam).
- **Verificação OAuth (escopos sensíveis):** domínio verificado no Search Console; home pública; privacidade no mesmo domínio e linkada na tela de consentimento; escopos declarados no *Data Access* (o mais estreito possível); justificativa por escopo (inclusive "por que um escopo menor não basta"); vídeo **não listado** no YouTube mostrando o fluxo de consentimento em inglês, o nome do app e o **client ID na barra de endereço**, e a funcionalidade de cada escopo sensível. Revisão "can take up to 10 days". Branding verificado precisa ser publicado em até 7 dias.

**[CONFERIR] (conflito de fontes; importante):**
1. Eu havia afirmado que projetos não auditados só enviam vídeo **privado**. A página oficial de `videos.insert`, como lida hoje, diz o contrário: "Videos uploaded from unverified API projects are not restricted to private viewing mode." Versões antigas/cópias dessa página e blogs dizem o contrário (projetos criados após 28/07/2020). **Abra a página no navegador e releia a nota sobre o modo privado.** Teste também em produção: envie um vídeo com um projeto sem auditoria e veja se fica privado.
2. Número do limite de usuários de app não verificado: a página oficial não informa; o "100" veio de blog.
3. Resumo da busca citou mudança de maio/2026: a partir de 01/06/2026 `videos.insert` tem bucket de cota próprio. Leia a página de cota e o histórico de revisão.
4. `yt-analytics.readonly` e `youtube.readonly` também precisam de justificativa; confirme se são classificados como sensíveis/restritos.

**Passos:**
- [ ] (Dono) Search Console: verificar o domínio de produção.
- [ ] (Dono) Tela de consentimento: branding, privacidade, home; **Publicar** o app (sair do modo Teste).
- [ ] (Claude) Justificativa por escopo e roteiro do vídeo de demonstração.
- [ ] (Dono) Enviar para verificação OAuth; responder e-mails do Trust & Safety.
- [ ] (Dono) Preencher o formulário de auditoria/extensão de cota (estimar uso diário real de uploads).

## 4. TikTok

**Fontes oficiais:**
- App Review Guidelines: https://developers.tiktok.com/docs/en/app-review-guidelines
- App Review FAQ: https://developers.tiktok.com/docs/en/getting-started-faq
- Content Sharing Guidelines (UX): https://developers.tiktok.com/docs/en/content-sharing-guidelines
- Direct Post: https://developers.tiktok.com/docs/en/content-posting-api-reference-direct-post
- Guia do Content Posting API: https://developers.tiktok.com/docs/en/content-posting-api-get-started
- Registrar o app: https://developers.tiktok.com/docs/en/getting-started-create-an-app

**O que a documentação diz:**
- **Duas etapas:** (1) *App Review* (libera produtos/escopos; "several days to two weeks"); (2) **auditoria do Content Posting API**, que tira a restrição de privado. Sem auditoria: "all content uploaded via this endpoint will be restricted to private viewing mode", só `SELF_ONLY`, e "up to 5 users to post in a 24 hour window". O FAQ **não explica como pedir a auditoria**: manda usar o *Support form*.
- Cap de criadores em 24h do cliente definido pelas estimativas enviadas na auditoria; cap por conta de criador de "typically around 15 posts per day".
- App Review: ao menos 1 vídeo de demo ponta a ponta (até 5 vídeos, 50 MB cada); app nunca aprovado usa o **sandbox** do portal para a demo; **todos os produtos e escopos selecionados precisam aparecer no vídeo** (remova os não usados); domínio do vídeo igual ao site informado. Site oficial "fully developed", política de privacidade e termos **visíveis no site sem abrir menu**. A página diz que a URL "cannot be a landing page or login page" **[CONFERIR]** como interpretar isso com a landing do produto. "Apps that are still in development or testing will not be approved." Não podem ser de uso privado/pessoal. "Only request permissions and features that your app needs."
- Depois de **Live**, mudanças precisam de nova revisão.
- **UX obrigatória do Direct Post** (Content Sharing Guidelines): buscar creator info a cada render da tela; mostrar o nickname; só as opções de `privacy_level_options`, em dropdown **sem valor padrão**; comentário/dueto/stitch **desmarcados** por padrão e cinza se o criador os desativou; toggle de conteúdo comercial desligado por padrão, com "Your brand"/"Branded content" (ao menos um; branded **não** pode ser privado); frase "By posting, you agree to TikTok's Music Usage Confirmation" (com Branded Content Policy quando aplicável); **prévia** do conteúdo; nada enviado antes do consentimento explícito; avisar que o processamento leva minutos; acompanhar status; validar duração com `max_video_post_duration_sec`; sem marca d'água promocional.

**Passos:**
- [ ] (Claude) Auditar o wizard do front (`StepCustomizations.vue`, `scheduleWizardStore.ts`) contra a lista de UX acima; reportar o que falta (não alterar UX sem aprovação).
- [ ] (Claude) Confirmar se `video.upload`, `video.list`, `user.info.profile`, `user.info.stats` têm uso no código; propor remover os sem uso.
- [ ] (Claude) Justificativa por escopo e roteiro do vídeo (sandbox).
- [ ] (Dono) Submeter o App Review; depois abrir o pedido de **auditoria** pelo Support form.
- [ ] (Dono, pós-auditoria) Revisar `TIKTOK_DEFAULT_PRIVACY=SELF_ONLY` do `.env.example` (só com sua autorização; hoje padrão não pode existir na UX, conferir o uso no backend).

## 5. Stripe

**Fontes oficiais:**
- Go-live checklist: https://docs.stripe.com/get-started/checklist/go-live
- Ativar a conta: https://docs.stripe.com/get-started/account/activate
- Chaves: https://docs.stripe.com/keys e boas práticas https://docs.stripe.com/keys-best-practices
- Webhooks: https://docs.stripe.com/webhooks
- Cashier (Laravel): https://laravel.com/docs/billing **(não lido; conferir)**

**Checklist oficial (resumo):** fixar a versão da API; testar casos extremos (dados incompletos, inválidos, duplicados; idealmente outra pessoa testa); tratar todos os tipos de erro; revisar logs sem dados sensíveis; "Não dependa de objetos de teste" (planos, cupons e produtos de sandbox **não valem em produção**; recrie em produção); **registrar webhooks de produção** e garantir que lidam com notificações **atrasadas, duplicadas e fora de ordem**; rotacionar/proteger chaves e não deixar chave no código.

**Passos:**
- [ ] (Dono) Ativar a conta Stripe (dados da empresa e bancários).
- [ ] (Dono) Chaves **live** em `STRIPE_KEY`/`STRIPE_SECRET` nos secrets de produção.
- [ ] (Dono) Criar o endpoint de webhook **live** `https://<api>/api/stripe/webhook` e guardar o novo `STRIPE_WEBHOOK_SECRET` (diferente do de teste).
- [ ] (Dono) Recadastrar planos e preços pelo painel admin do app já com chave live (`StripeCatalogService` cria os objetos no Stripe). Preços de teste não migram.
- [ ] (Dono) No webhook live, assinar também `subscription_schedule.completed`, `subscription_schedule.released` e `subscription_schedule.canceled` (spec 024, mudança de assinatura) e conferir que `STRIPE_WEBHOOK_SECRET` está preenchido (VULN-SUB-03).
- [ ] (Dono) Smart Retries no dashboard (o dunning do projeto depende disso; `specs/001-billing/plan.md`).
- [ ] (Claude) Conferir idempotência do `HandleStripeWebhook`/`ProcessStripeWebhookJob` para evento duplicado, atrasado e fora de ordem (item do checklist) e reportar.
- [ ] (Dono + Claude) Cobrança real de baixo valor: conferir `Payment`, assinatura, notificações e estorno.

## 6. Infra de produção (conferir antes de abrir ao público)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL` adequado (hoje o exemplo é `local`/`debug`).
- [ ] `CACHE_STORE=redis` **compartilhado** (o `state` OAuth de uso único depende disso) e `QUEUE_CONNECTION=redis`; `REDIS_QUEUE_RETRY_AFTER` (360) acima dos timeouts dos jobs (Instagram 180s, demais 300s).
- [ ] Horizon com os 4 supervisores (`default`, `instagram`, `youtube`, `tiktok`), scheduler rodando (`routes/console.php`) e Reverb acessível pelo front (`VITE_REVERB_*`).
- [ ] S3 de produção (Backblaze B2, segundo o histórico do projeto), CORS do bucket para o PUT presigned do front, endpoint público.
- [ ] `SCHEDULE_MIN_LEAD_MINUTES=15` (decisão de deploy registrada).
- [ ] Segredos só nos secrets de produção; `.env.example` sem valores reais (alterar só com autorização do dono).
- [ ] E-mail de produção (Resend, domínio verificado) para verificação de conta, convites e alertas.

## 7. Ordem sugerida

1. §1 (páginas públicas, domínio, S3 público) + verificação da empresa na Meta (é a mais lenta).
2. §6 infra de produção no ar (os revisores precisam testar o app real).
3. Claude: limpeza de escopos + auditoria de UX do TikTok + justificativas e roteiros (§2–§4).
4. Submissões: Meta (App Review), TikTok (App Review → auditoria), Google (OAuth + cota).
5. Stripe em paralelo (§5).

## 8. Fontes por página (para você verificar)

Todas as URLs acima. Fontes de terceiros usadas só como indício (não confiáveis para decidir): bundle.social/blog/tiktok-api-approval, adaptlypost.com/blog/why-does-my-app-need-approval-post-tiktok, ayrshare.com (Google 403), singhamandeep.com (consultoria), fórum communityforums.atmeta.com (verificação da empresa >10 dias, relato individual).
