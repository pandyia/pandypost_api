# 023 — Instagram: vídeo enviado pelo nosso servidor (upload resumable)

## Contexto

### Comportamento atual (spec reversa — esteira do Instagram)

1. `InstagramService::upload` marca o post `processing` e chama `processContainer`.
2. Se o post tem `container_id` válido (`hasValidContainer`), consulta o `status_code`:
   `PUBLISHED` → fecha como publicado sem id; `FINISHED` → publica; `ERROR`/`EXPIRED`/`null` → limpa e cria outro;
   qualquer outro → despacha `CheckInstagramContainerJob`.
3. Sem container: gera uma **presigned GET URL do S3** (`StorageService::generateDownloadUrl`) e cria o container
   com `video_url` (vídeo, `media_type=REELS`) ou `image_url` (imagem). **A Meta baixa o arquivo por essa URL.**
4. Grava `container_id`/`container_created_at` e despacha `CheckInstagramContainerJob` (fila `instagram`).
5. O job consulta `status_code` com backoff (trait `ChecksPlatformPostStatus`, 15 tentativas):
   `FINISHED` → `media_publish` + permalink + `deleteIfUnused` no S3; `PUBLISHED` → fecha publicado;
   `ERROR`/`EXPIRED` → limpa container e marca `failed` com `InstagramError` fixo; erro permanente da API → `failed`
   sem repetir; esgotou → `publishTimeout`.
6. Warmup (`prepare`) faz o mesmo sem publicar; erro dele não derruba o post.

### Problema

O TikTok (`FILE_UPLOAD` em chunks) e o YouTube enviam os **bytes a partir do nosso servidor**: funcionam com
qualquer storage. Só o Instagram depende de a Meta conseguir baixar a URL do S3. Consequências:

- **Dev:** com o MinIO local (`minio.localtest.me` = `127.0.0.1`, adotado no commit `15ca973`), a Meta não alcança a
  URL e o container vai a `ERROR` em segundos (log de 2026-11-11, post 1). Voltar ao play.min.io quebra o resto do
  ambiente local.
- **Produção:** a publicação depende de a Meta baixar a tempo (URL assinada que expira, fetch da Meta que falha
  sem motivo exposto). Enviar os bytes nós mesmos tira essa dependência — alinhado ao diferencial de confiabilidade.

## Escopo

Vídeo (Reels) do Instagram passa a usar o **upload resumable** da Graph API: o container é criado com
`upload_type=resumable` e o nosso servidor envia o arquivo (lido do S3 em stream) ao endpoint de upload da Meta.
O resto da esteira (job de conferência, publicação, permalink, limpeza do S3, warmup, reenvio) não muda.

## Requisitos

- **RF-1** Post de vídeo no Instagram cria o container com `media_type=REELS` + `upload_type=resumable`, sem `video_url`.
- **RF-2** Em seguida, o servidor envia o arquivo do S3 ao endpoint de upload da Meta, **em stream** (nunca o arquivo
  inteiro em memória — Octane/Swoole e vídeos grandes).
- **RF-3** Só depois do envio concluído o `CheckInstagramContainerJob` é despachado; o fluxo de status/publicação
  segue idêntico ao atual.
- **RF-4** O `container_id` é gravado **antes** do envio dos bytes (como no TikTok): se o job morrer no meio, a
  próxima tentativa confere o container existente em vez de criar duplicado às cegas.
- **RF-5** Falha no envio dos bytes vira `InstagramException` com mensagem fixa pt-BR do `InstagramError`
  (erro novo se necessário); falha de rede/5xx é transitória (retry da fila); 4xx é permanente.
  Nunca body da Meta, token ou URL no `error_message`/log.
- **RF-6** Container criado mas com envio incompleto não pode ser publicado: na retentativa, container que não está
  `FINISHED`/`PUBLISHED`/em processamento após o envio é descartado e recriado.
- **RF-7** Imagem continua com `image_url` (a Meta não tem upload resumable para imagem).
- **RF-8** Só `InstagramApi` faz `Http::`; o token vai no header `Authorization`, nunca logado.
- **RF-9** Diagnóstico: a consulta de status passa a pedir também o campo `status` da Meta e o registra **só no log**
  (subcódigo do erro), nunca no `error_message`.

## Contrato de API

Nenhuma mudança para o frontend. Agendamento, validação (MP4/MOV/JPEG, legenda ≤ 2200) e respostas ficam iguais.

## Critérios de aceite

1. Vídeo publicado no Instagram com o storage local (MinIO) — fluxo feliz com `Http::fake`: cria container
   resumable, envia os bytes, job vê `FINISHED`, publica, grava permalink, apaga do S3.
2. O corpo da criação do container não contém `video_url`.
3. O envio dos bytes não carrega o arquivo inteiro em memória (lê de `readStream`).
4. Falha 5xx/rede no envio → exceção transitória, post não vira `failed` na hora; 4xx → `failed` com mensagem fixa.
5. Retentativa com container já existente não cria container duplicado nem publica duas vezes.
6. Imagem segue com `image_url` (teste de caracterização mantido).
7. Nenhum token/body/URL da Meta em `error_message` ou log (`tests/Feature/Security/`).
8. Warmup de vídeo funciona no novo fluxo e continua sem derrubar o post em erro.
9. Suíte inteira verde.

## Fora de escopo

- Imagem do Instagram no ambiente local (continua dependendo de URL pública; só vídeo é resolvido aqui).
- Carrossel, Stories, mudanças de validação, troca de storage, túnel público para o MinIO.
- TikTok e YouTube (já enviam bytes).

## Questões em aberto

1. **Envio em uma requisição só ou em partes?** A Meta aceita o arquivo inteiro em um POST com `offset`/`file_size`
   e permite retomar pelo `offset`. Proposta: uma requisição em stream (simples); retomada por offset fica de fora.
   Ok?
2. **Limite de tamanho do Reels:** confirmar na documentação atual da Meta (no plano) e decidir se validamos no
   agendamento (422) — hoje não validamos tamanho para o Instagram.
3. **Imagem em dev:** aceitável que post de imagem no Instagram continue falhando localmente (só vídeo resolvido)?
