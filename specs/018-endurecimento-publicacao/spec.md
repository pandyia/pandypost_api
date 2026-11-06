# 018 — Endurecimento da publicação (YouTube, Instagram, TikTok)

> Status: **aprovada** (pedido direto do Gabriel, 2026-11-06). Área crítica (esteira de publicação). Depende das specs 011, 014, 015, 016 e 017.

## Contexto
Antes do teste manual de agendamento nas três redes, a leitura ponta a ponta (request, service, job, *Service/*Api, job de status, notificações) achou comportamentos que fazem o post falhar, repetir à toa ou terminar errado.

## Escopo
Corrigir, com teste, o que impede agendar/publicar ou publica errado. Contrato de API inalterado (só validações novas, que devolvem 422 antes de criar o post).

## Requisitos
- RF-1 `PublishPostJob` não repete erro permanente: marca `failed` uma vez (sem `failed_jobs` duplicado) e não tenta de novo. Permanentes: YouTube (cota, limite de upload, acesso negado/`invalid_grant`, sem canal, falha de renovação, sem `refresh_token`), Instagram (`ACCESS_DENIED`), TikTok (escopo, token, privacidade indisponível), `accountNotFound`, `subscriptionInactive`. Erro transitório continua com retry.
- RF-2 O job não age sobre post que não está `pending`/`processing` (cancelado, já publicado, já falho) e `failed()` nunca sobrescreve `published`/`cancelled`. Cancelar um post agendado precisa impedir a publicação.
- RF-3 `InstagramService::publishContainer` publica o `container_id` do job, nunca o do post. O job de verificação não age sobre post fora de `pending`/`processing`. Erro ou `ERROR` num job de warmup (`shouldPublish=false`) não derruba o post: só limpa o container; o job de publicação cria outro na hora.
- RF-4 YouTube: refresh recusado (`invalid_grant`) dispara `social_account_disconnected` (mesmo mecanismo do caso sem `refresh_token`).
- RF-5 `social_account_disconnected` avisa quem conectou por último (se ainda é membro) e os membros do workspace com `social_accounts.view|connect|disconnect` ou `posts.create`, sem duplicar a mesma pessoa. Sem tipo novo.
- RF-6 Instagram: container com `status_code` `PUBLISHED` (a Meta publicou, mas a resposta do `media_publish` se perdeu) fecha o post como `published`, sem id/link; `EXPIRED` é tratado como container inválido. Falha do `media_publish` que não é de conexão confere o status do container antes de dar o post como falho.
- RF-7 Validações antes de agendar (422): YouTube exige `title` e aceita só vídeo; TikTok aceita só vídeo; Instagram aceita vídeo ou imagem JPEG e legenda até 2.200 caracteres; descrição do YouTube até 5.000 bytes.
- RF-8 `scheduled_at` com fuso (ex.: ISO com `Z` ou `-03:00`) é convertido para o fuso da aplicação antes de gravar (hoje o relógio do offset era gravado como se fosse local, atrasando/adiantando o post). Vale também para o reenvio.
- RF-9 Falha ao apagar a mídia do S3 depois do upload do YouTube não faz o job repetir (evita vídeo duplicado).
- RF-10 Timeout do `PublishPostJob` alinhado ao supervisor da rede (YouTube/TikTok 300 s; Instagram 180 s), abaixo do `retry_after` do Redis (360 s).

## Critérios de aceite
Cada RF tem teste Pest; suíte completa verde; nenhum teste existente enfraquecido.

## Fora de escopo
Mensagens novas de notificação; permissão nova; ativar `publishScope` do YouTube; renovação de token do YouTube pelo caminho de analytics; recuperar o id/permalink de um post do Instagram publicado "às cegas".

## Questões em aberto
Nenhuma (decisões delegadas ao Gabriel e registradas em Divergências).
