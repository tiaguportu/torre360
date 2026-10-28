# Distribuição de Leads e Carteira (Onda 85) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 88](ondas_80_a_88_indice.md) e continua a
> [Onda 84](mesa_consultor_proxima_acao_roadmap.md) (Mesa do consultor).

## Contexto

Um lead novo chega **sem consultor**. Quem o pega é o primeiro que olhar a lista e apertar *Assumir lead* ou a gestão que o
atribui à mão. Funciona com dois consultores e uma escola pequena; com mais gente, o lead que ninguém assume fica parado até o
alerta de 24 horas, e o consultor mais rápido fica com os melhores leads. O próprio documento do funil já deixou esse item de fora:
*"distribuição automática de leads, WhatsApp como canal da régua (Lote D)"* ([crm_funil_e_captacao.md](crm_funil_e_captacao.md), seção 13).

## O que já existe (conferido no código)

- **Dono do lead:** `interessado.usuario_id` (o consultor). Quem é consultor: `User::consultoresCrm()` (contas ativas com
  `Update:Interessado`, mais admin e super admin).
- **Carteira:** o consultor comum vê **a própria carteira e os leads sem dono**; `ViewAll:Interessado` vê tudo (Lote D7).
  `Interessado::scopeNaoAtribuidos()`.
- **Assumir lead** (ação atômica, na lista e na ficha) e **atribuir consultor em lote** (avisa o consultor pelo sino).
- **Alerta de lead sem consultor** depois de `crm.alertas.sem_consultor_apos_horas` (24 h) e, na Onda 84, a fila *Leads sem consultor*
  na Minha Mesa.
- **Não existe mais SLA de primeira resposta.** O `LeadSlaService` (que avisava o consultor e a gestão) foi **retirado em 2026-10-28**
  ([registro](crm_sla_primeira_resposta_retirado.md)). Hoje o lead novo sem atendimento só aparece em *Sem próxima ação* (depois da
  carência) e, sem dono, no alerta de 24 h e na fila *Leads sem consultor*.
- **O que o lead diz sobre o que quer:** o dependente tem série, unidade e turno de preferência (`InteressadoDependente`); o lead tem
  origem, campanha e UTM (`CampanhaMarketing`, `UtmTracker`).
- **Entradas hoje**, todas sem dono: formulário público `/quero-matricular` (`CaptacaoInteressadoService`), matrícula online
  (converte o lead), cadastro manual, importação (`ImportacaoLeadIaService`, conversa de WhatsApp em ZIP) e indicação.
  **Não existe entrada por webhook:** `routes/api.php` só tem os webhooks do Assinafy e do pagamento. A *Landing Page* do menu
  (`LandingLead`) é do **produto** (escolas pedindo demonstração do Torre360), não das famílias da escola.

## Escopo proposto

### Regras de distribuição
- `RegraDistribuicaoLead`: ordem de avaliação, **filtros** (unidade, série de interesse, turno, origem ou campanha), **consultores
  elegíveis** e **método**: rodízio, menor carteira ativa ou rodízio ponderado.
- **Capacidade:** máximo de leads em andamento por consultor; quem atingiu fica fora da vez.
- **Disponibilidade:** o consultor pode estar *fora da distribuição* (férias, folga, treinamento) sem perder a carteira.
- **Sem regra que sirva**, o lead continua sem dono e cai na fila *Leads sem consultor* (a rede de segurança da Onda 84).
- A distribuição roda **na criação do lead** e num comando que redistribui os que ficaram sem dono.

### Reatribuição quando o prazo de primeira resposta estoura
Esta regra **precisa de um prazo de primeira resposta**, e o SLA do CRM que a sustentava foi retirado em 2026-10-28: ao implementar,
o prazo (em horário comercial) volta como parte desta onda, com parâmetro próprio. Passado o tempo configurado sem primeiro contato
(`interessado.data_primeiro_contato` vazia), o lead passa ao **próximo elegível** (com limite de trocas, e a gestão avisada
na última). Cada troca vira um registro automático no histórico do lead, que **não conta como contato** (como as demais interações
automáticas do CRM).

### Entrada automática por webhook
Endpoint autenticado (segredo e assinatura do corpo) que cria o lead a partir de **anúncios de formulário** (Meta, Google) e de
formulários de terceiros: mapeia os campos para o mesmo serviço do formulário público, **evita duplicata** (`LeadDuplicadoDetectorService`),
é **idempotente** pelo identificador externo e grava a origem e a campanha. Tudo entra pela mesma regra de distribuição.

### Carga e justiça
Painel com leads em andamento, tempo médio de primeira resposta e conversão **por consultor**, para a gestão ver se a regra está justa (o desempenho por consultor — leads, primeira resposta, contatos, visitas e
conversão — já sai de `CrmRelatoriosService::desempenhoConsultores()`). *Transferir carteira* em lote quando o consultor sai.

## Base legal

LGPD, art. 6º, I e III (finalidade e necessidade) e art. 7º, I (consentimento): o lead de anúncio traz o consentimento dado **no
formulário do provedor**, e a escola precisa **guardar a prova** (texto, data e versão), como já faz no formulário próprio
(`crm.lgpd.versao_consentimento`). Distribuir internamente não é compartilhar com terceiros, mas o endpoint recebe dado pessoal de fora:
segredo, limite de requisições e registro de cada chamada.

## Dependências e riscos

- **Privacidade da carteira:** mudar o dono do lead muda **quem o vê**. Reatribuir não pode deixar um consultor com acesso a um lead
  que já não é dele; o escopo de visibilidade existente já cobre, mas é o ponto a testar primeiro.
- **A régua dispara na criação.** Lead de webhook sem `aceita_comunicacao` não pode receber e-mail da régua; conferir antes de ligar a
  entrada automática.
- **Justiça e "cherry-picking":** consultor em meio período e o que mais fecha pedem pesos diferentes. Começar pelo rodízio simples.
- **Idempotência do webhook** e proteção contra lead em massa falso (limite, validação de campo).
- Lead que muda de consultor perde a continuidade se o histórico não estiver à mão: a ficha já tem a linha do tempo, e a troca entra nela.

## Fusão sugerida

**Sequência do CRM (84 → 85 → 86 → 87).** A 84 dá a fila do dia; esta decide quem atende; o [agendamento online](agendamento_online_visitas_roadmap.md)
(86) é a nova entrada de "visita marcada" e deve usar a mesma regra para escolher o consultor da visita; a
[proposta](proposta_comercial_aceite_roadmap.md) (87) fecha. As três usam a carteira e a Minha Mesa já existentes.
