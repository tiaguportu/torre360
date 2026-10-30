# Central de Avisos à Família (Onda 70) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for
> autorizada — não há código, migration ou teste desta funcionalidade no sistema ainda.

## Contexto

A família descobre o que mudou abrindo o Portal. As ondas recentes deixaram vários desfechos que **ela precisa
saber** e hoje só vê se entrar na tela certa:

- o pedido de cancelamento foi **aprovado ou recusado** (Onda 61);
- há um **documento esperando a assinatura dela** (Onda 69) ou ele foi assinado por todos;
- a **lista de material** foi publicada (Onda 59);
- a **fatura** venceu ou está para vencer (régua de cobrança);
- a rematrícula abriu, o boletim saiu, a declaração pedida ficou pronta.

O sistema já avisa em alguns casos isolados (falta do aluno, ocorrência), cada um com seu próprio e-mail e push, sem
histórico para a família rever e sem controle de quanto ela recebe. O resultado é o oposto do desejado: ora a
família não sabe, ora recebe aviso demais.

Esta onda cria **um lugar só para os avisos transacionais**: um sino com histórico no Portal e um serviço que decide,
por evento, quem avisar e por qual canal.

## O que já existe (ponto de partida)

- `CanalMensagem`, `EmailCanal`, `FcmCanal` e `CanalMensagemManager`: enviam a uma `Pessoa` pelo canal escolhido.
  WhatsApp é apoio manual à equipe, não canal de disparo.
- Notificações do Laravel com e-mail, banco e push (`FrequenciaResumoDiarioNotification`, um aviso por dia
  desde a [Onda 91](resumo_diario_faltas_roadmap.md), e `OcorrenciaRegistradaNotification`) e o
  `NotificationService`/`FcmService`.
- Sino de avisos do **painel da equipe** (notificações do Filament no banco). O **Portal da Família não mostra o sino**.
- `ComunicacaoEmMassa` com descadastro (`DescadastroController`) e a Central de Atendimento (conversa, não aviso).
- Relação responsável ↔ aluno (`Pessoa::users`, responsáveis financeiros do contrato) para saber quem avisar.

## Escopo

### Catálogo de avisos
- Um cadastro de **tipos de aviso** (`TipoAviso`): chave, título, mensagem-padrão com variáveis, quem recebe
  (responsáveis do aluno, responsável financeiro, o próprio aluno maior de idade), canais-padrão, e se é
  **essencial** (cobrança, segurança, ato do contrato: não dá para silenciar) ou **opcional**.
- Os módulos disparam por evento (`avisar(TipoAviso::CancelamentoAprovado, $cancelamento)`), sem escolher canal nem
  texto. Textos editáveis pela escola, com o mesmo editor e o mesmo catálogo de variáveis dos modelos de documento
  (Onda 69).

### Modelos novos
- `AvisoFamilia`: destinatário (usuário e pessoa), tipo, aluno (quando for o caso), título, resumo, endereço da tela,
  `lido_em`, canais usados e resultado, `chave_de_deduplicacao`.
- `PreferenciaAviso`: usuário, tipo, canais ligados e horário de silêncio. Padrão = os canais do catálogo.

### Serviço
- `AvisosFamiliaService::avisar(...)`: resolve os destinatários, **deduplica** (o mesmo evento não avisa duas vezes), aplica
  a preferência e o silêncio, grava o aviso no Portal e dispara e-mail e push. O aviso no Portal sempre existe: é o
  registro de que a escola avisou.
- Responsável com dois filhos na escola recebe **um** aviso por evento da família, não um por aluno, quando o evento é
  do contrato.

### Telas
- **Portal:** sino com contador e página **Meus Avisos** (histórico, marcar como lido, abrir a tela do assunto).
  Preferências do responsável (canais por tipo opcional).
- **Equipe:** relatório de entrega por aviso (enviado, falhou, lido) e reenvio; o aviso essencial que não foi lido
  alimenta a Mesa de Pendências (Onda 71).

### Integrações iniciais
1. Onda 61: cancelamento aprovado ou recusado.
2. Onda 69: documento para assinar (com o link do próprio responsável) e documento assinado.
3. Onda 59: lista de material publicada.
4. Régua de cobrança: fatura a vencer e vencida (passa a registrar no Portal; o texto continua o da régua).
5. Migrar aos poucos os avisos antigos (falta, ocorrência) para o serviço, sem mudar o que a família recebe.

## Regras

- **Conteúdo mínimo** em e-mail e push: o que aconteceu e um link para o Portal autenticado. Nada de valor, motivo,
  nota, dado de saúde ou nome do aluno em notificação de tela bloqueada.
- Aviso **essencial** não tem descadastro, mas respeita o silêncio de horário no push (vai ao Portal e ao e-mail).
- Opcional respeita o descadastro e as preferências.
- Limite diário por canal e por responsável, com agrupamento ("3 novos avisos") quando passar.
- Texto de e-mail passa pelo sanitizador de HTML do projeto.

## Base legal

- **LGPD:** avisos sobre o contrato e sobre o aluno têm base de **execução de contrato**; comunicação promocional ou de
  eventos opcionais pede consentimento ou legítimo interesse com opção de recusa. A separação essencial/opcional do
  catálogo existe por isso. O corpo mínimo reduz a exposição de dado de criança (art. 14).

## Dependências já satisfeitas

- Canais de e-mail e push, notificações no banco, relação responsável ↔ aluno e Portal.
- As ondas 59, 61 e 69 só precisam chamar o serviço quando ele existir.

## Risco e observação

- **Fadiga de aviso:** começar com poucos tipos, medir abertura e ajustar o limite.
- **Entregabilidade de e-mail** (SPF, DKIM, reputação do domínio): aviso que cai em spam não conta como aviso.
- **Push em tela bloqueada** expõe o texto: manter genérico.
- WhatsApp como canal exige conta de negócio, modelos aprovados pelo provedor e consentimento. Fica fora desta onda.

## Fusões e ampliações

- **Com a Onda 33 (comunicados com ciência e enquetes):** os dois precisam do mesmo destinatário, do mesmo registro
  de entrega e do mesmo sino. A recomendação é implementar como **uma só onda de "Comunicação com a família"**: aviso
  transacional (esta onda) e comunicado com ciência rastreada (Onda 33) sobre o mesmo modelo de destinatário.
- **Com as ondas 80 e 83 (requerimentos e atendimento):** o Portal da família **não tem sino** e o chamado de hoje não avisa ninguém;
  as duas respondem para ninguém sem esta onda. Ver o [índice das ondas 80 a 88](ondas_80_a_88_indice.md#fusões-sugeridas).
- **Com a Onda 88 (WhatsApp oficial):** é o canal de entrega que esta onda e a 33 devem usar, depois que os três "canais" atuais forem
  unificados ([documento da 88](whatsapp_oficial_roadmap.md)).
- **Com a Onda 47 (digest diário):** a equipe recebe o resumo; a família, os avisos. Compartilham o agendamento e as
  preferências por canal.
