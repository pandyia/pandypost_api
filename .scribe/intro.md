# Introdução

Referência da API do Pandypost para o frontend.

<aside>
    <strong>URL base</strong>: <code>http://localhost:9000</code>
</aside>

API do Pandypost: agendamento e publicação de vídeos no YouTube, TikTok e Instagram. Esta página é o contrato entre a API e o frontend.

## Como a API funciona

**Base URL:** todas as rotas ficam sob `/api`. Envie e espere sempre JSON.

**Autenticação:** `POST /api/login` devolve um `token`. Mande-o em todas as rotas protegidas:
`Authorization: Bearer {token}`. Rotas marcadas como públicas não precisam dele.

**Email verificado:** quase toda rota exige email confirmado. Sem isso a resposta é
`403 {"error": "email_not_verified"}` → leve o usuário para a tela de confirmação. Só `/me`, `/logout` e o
reenvio do código funcionam antes da confirmação.

**Workspace corrente:** o sistema é multi-tenant. Cada usuário tem um workspace corrente (o pessoal, por padrão)
e **todas** as leituras e escritas são feitas nele. Para trocar, use `POST /api/workspaces/{uuid}/switch`.
Recurso de outro workspace responde `404`.

**Permissões:** cada endpoint informa a permissão exigida (ex.: `users.view`). A lista do usuário no workspace
corrente vem em `GET /api/me` → `permissions`; use-a para esconder botões. Sem permissão:
`403 {"message": "...", "required_permission": "users.view"}`. Super admin passa em tudo.

**Identificadores:** as rotas usam `uuid`. Exceções estão indicadas no próprio endpoint.

## Listagens

Listagens aceitam `per_page` e `page`. **Sem `per_page`, vem tudo numa página só.** Filtros de texto são
parciais e sem diferenciar maiúsculas (`?name=ana`). Formato:

```json
{
  "data": [ ... ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 3 }
}
```

<aside class="notice">Algumas listagens (marcadas no endpoint) devolvem o paginador "cru" do Laravel:
os itens em <code>data</code> e os campos de paginação (<code>current_page</code>, <code>total</code>, ...) na raiz.</aside>

## Erros

| Status | Quando | Corpo |
|---|---|---|
| 401 | Token ausente ou inválido | `{"message": "Não autenticado"}` |
| 403 | Sem permissão / email não verificado | `{"message": "..."}` |
| 404 | Não existe ou é de outro workspace | `{"message": "..."}` |
| 422 | Validação | `{"message": "...", "errors": {"campo": ["..."]}}` |
| 429 | Muitas tentativas | `{"error": "TOO_MANY_ATTEMPTS", "message": "..."}` |
| 4xx/5xx | Regra de negócio | `{"error": "codigo_do_erro", "message": "..."}` |

Erros de regra de negócio sempre trazem `error` (código estável, use-o no `if`) e `message` (texto em pt-BR,
pronto para exibir). Os códigos possíveis aparecem em cada endpoint.

## Datas e valores

- Datas em ISO 8601 (`2026-10-15T14:00:00-03:00`), salvo onde o exemplo mostrar outro formato.
- Valores monetários em **centavos** (`4990` = R$ 49,90).

## Tempo real (Reverb)

Eventos chegam por WebSocket (Laravel Echo + Reverb). Autorize canais privados em `POST /api/broadcasting/auth`
com o mesmo `Authorization: Bearer {token}`.

| Canal | Evento | Quando |
|---|---|---|
| `private-workspaces.{uuid}` | `.RoleUpdated` | Um perfil do workspace foi criado, editado ou removido. Payload: `{"action": "created\|updated\|deleted\|restored", "role": {...}}`. Recarregue `/me` para atualizar as permissões. |

<aside class="warning">Endpoints marcados com ⚠️ têm defeito conhecido registrado em
<code>specs/002-security-hardening</code>. Não dependa deles até a correção.</aside>

