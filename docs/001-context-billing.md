Contexto

O pandy pro é um SAAS, ou seja, um serviço de assinatura e para tanto precisamos estruturar um fluxo de cobranças que permita:

Que o usuário possa assinar o sistema usando um conjunto de métodos de pagamento

Que o administrador do sistema possa criar e editar os preços e os limites da assinatura

Que o usuário possa editar seus métodos de pagamento e visualizar o histórico de faturas

Que o sistema faça automaticamente a cobrança e controle o acesso ao sistema em caso de inadimplência

Que o usuário possa realizar a assinatura através da landing page, no processo de signup

Para isso, precisamos inicialmente definir os termos que vamos lidar no fluxo:

Plano: Entidade que representa um conjunto de limites para o uso da aplicação. Exemplo: O plano A tem o limite de 5 contas, já o plano B tem o limite de upload de até 10 vídeos mensais.

Preço: Entidade que representa um preço, ou seja, um valor em uma determinada moeda. Exemplo: 50 doláres, 13 reais

Assinatura: Entidade que representa o vínculo da conta com o plano, a partir da assinatura teremos como calcular limites para um determinado cliente.

Pagamento: Entidade que representa um ato individual de pagamento, em um sistema de pagamento recorrente por assinatura cada assinatura terá muitos pagamentos. Todo pagamento tem um status, é feito através de um método e tem uma fatura que representa o que foi gasto para gerar a necessidade daquele pagamento

Cliente: Entidade que realiza os pagamentos com o objetivo de criar/manter a assinatura

O administrador cadastrará Planos, cada plano terá um conjunto de preços a depender da frequência (mensal, anual) e da moeda (dolár, real, euro).

Um usuário se torna cliente ao fazer signup no sistema, ele coloca seus dados e cria uma assinatura, ao criar a assinatura ele terá um período de teste e após isso será efetuado pagamentos recorrentes de acorso com a frequência escolhida.

Um cliente pode alterar seu método de pagamento e o sistema descontará no próximo ciclo no novo método de pagamento.

Um cliente também pode ver os pagamentos realizados, baixar a fatura relacionada àquele pagamento.
Usaremos o stripe como gateway de pagamentos:


Dito isso, as tarefas do backend se divirão em três frentes:

Gestão do financeiro - Administrador

Webhook e Gestão de Inadimplência

Gestão do financeiro - Cliente
