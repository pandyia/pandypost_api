Visão Geral

Implementar o módulo de Gestão do Financeiro do Administrador no Pandy Pro, responsável por permitir que administradores do sistema cadastrem, editem e gerenciem os Planos e seus respectivos Preços (por frequência e moeda), bem como os limites de uso associados a cada plano. Este módulo é a base do fluxo de cobrança recorrente do SaaS e fornece os dados que serão consumidos pelos demais módulos (Cliente e Webhook/Inadimplência).

O que deve ser feito?

Criar as entidades no banco de dados:

Plano (Plan) com os atributos:

name (text)

description (text)

is_visible (boolean)

is_active (boolean)

gateway_product_id

created_at

updated_at

Limites de Plano (PlanLimit) com os atributos:

plan_id

Preço (Price) com os atributos:

plan_id

amount (integer) - armazenar em centavos

currency (enum)

frequency (enum)

trial_period_days (integer)

gateway_price_id

created_at

updated_at

Histórico de Preço (PriceHistory) com os atributos:

price_id

gateway_price_id

amount (integer) - armazenar em centavos

currency (enum)

frequency (enum)

trial_period_days (integer)

archived_at

reason (text)

Os limites dos planos ainda serão definidos posteriormente

Integrar com o Stripe

Criação

Ao criar um Plano no Pandy → criar Product correspondente no Stripe e armazenar stripe_product_id

Ao criar um Preço → criar Price correspondente no Stripe e armazenar stripe_price_id

Edição de Plano

Campos editáveis no Plano (name, description) são sincronizados via stripe.products.update() — operação simples.

Edição de limites (PlanLimit) é local, não reflete no Stripe.

Edição de Preço (ponto crítico)

Preços no Stripe são imutáveis. Não existe stripe.prices.update() para amount, currency ou recurring.interval. Para "editar" um Preço, o fluxo correto é:

Arquivar o Price antigo no Stripe: stripe.prices.update(oldId, { active: false })

Criar um novo Price no Stripe com os novos valores

Persistir o novo stripe_price_id no banco local, sem apagar o antigo (mover o antigo para o history

Desativação de Plano

Marcar Plan.is_active = false localmente

Arquivar todos os Prices ativos do plano no Stripe (active: false)

Arquivar o Product no Stripe (active: false)

Não permitir desativar se houver assinaturas ativas no plano (ver seção 4)

Não migrar automaticamente assinaturas existentes — elas continuam no preço antigo até decisão explícita do admin ou renovação manual do cliente

Deleção de Plano

Cenário

Comportamento

Plano nunca teve assinaturas

Permite hard delete. Antes de deletar: arquivar Product e todos os Prices no Stripe; remover registros locais.

Plano teve ou tem assinaturas (ativas ou históricas)

Bloquear hard delete. Apenas soft delete: is_active = false, archived_at = now(). Stripe: Product.active = false.

Plano tem assinaturas ativas

Bloquear até todas as assinaturas serem canceladas ou migradas. Retornar 409 Conflict com lista de assinaturas afetadas.

Deleção de Preço

Cenário

Comportamento

Preço nunca foi usado em assinatura/pagamento

Permite hard delete. Arquivar no Stripe e remover registro local + entradas de PriceHistory.

Preço foi usado em pelo menos um pagamento ou assinatura (mesmo cancelada)

Bloquear hard delete. Apenas soft delete: is_active = false, archived_at = now(). Stripe: Price.active = false.

Preço é o único ativo do plano para uma combinação (currency, frequency) em uso no signup

Bloquear até ser substituído por outro preço ativo. Retornar 409 Conflict.

Endpoints

Planos

POST /admin/plans — criar plano

GET /admin/plans — listar planos (com filtros: ativo/inativo)

GET /admin/plans/:id — detalhar plano

PATCH /admin/plans/:id — atualizar dados do plano / ativar e desativar

DELETE /admin/plans/:id — remover plano

Limites do Plano

POST /admin/plans/:id/limits — adicionar limite

PATCH /admin/plans/:id/limits/:limitId — editar limite

DELETE /admin/plans/:id/limits/:limitId — remover limite

Preços

GET /admin/plans/:id/prices - Listar Preços

GET /admin/plans/:id/prices/:priceId/versions - Listar histórico de preços

POST /admin/plans/:id/prices — adicionar preço ao plano

PATCH /admin/plans/:id/prices/:priceId — atualizar preço (arquiva e recria no Stripe)

DELETE /admin/plans/:id/prices/:priceId — desativar preço

