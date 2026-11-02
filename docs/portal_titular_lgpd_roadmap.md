# Portal do Titular LGPD (Onda 34) — Implementada (parcial)

> **Status: implementada em 2026-11-02, só a parte do pedido** (registro com protocolo, prazo legal, restrição de acesso e resposta), como tipo restrito de
> requerimento no pacote P2. **Não foram feitos** a exportação dos dados da pessoa, o registro das operações de tratamento (ROPA) nem a página do encarregado
> (ver *Implementação*, no fim). O texto abaixo é o escopo original. Veja o [índice dos pacotes P2 e P10](pacotes_p2_p10_indice.md).

## Contexto

A LGPD (Lei nº 13.709/2018, art. 18) garante ao titular, e aqui ao responsável legal do aluno
menor, o direito de pedir confirmação de tratamento, acesso, correção, anonimização, portabilidade,
eliminação, informação sobre compartilhamento e revogação do consentimento. O controlador precisa
atender no prazo da lei (art. 19: formato simplificado de imediato, ou declaração completa em até
15 dias). Hoje esses pedidos chegam por e-mail ou balcão, sem protocolo, prazo nem trilha.

## O que já existe (ponto de partida)

- `ConsentimentoMatricula`/`TipoConsentimento` e a página **Consentimentos** do portal:
  consentimentos concedidos e revogáveis pelo responsável.
- `docs/lgpd_saude_policies.md`, `docs/seguranca_hardening.md` e `docs/seguranca_pendencias.md`:
  controle de acesso a dados de saúde e backlog de segurança.
- `activitylog` e `AuditLog`: trilha de acessos e alterações.
- `Customer360TimelineService`: visão consolidada do relacionamento com a pessoa.
- `DescadastroController`: revogação de comunicação.
- Não existe pedido formal do titular, prazo, registro das operações de tratamento nem exportação
  dos dados da pessoa.

## Escopo

### Modelos novos
- `PedidoTitular`: solicitante (user/pessoa), titular (responsável ou aluno), tipo (acesso,
  correção, eliminação, portabilidade, informação de compartilhamento, revogação), descrição,
  protocolo, status (recebido, em análise, atendido, negado com justificativa), prazo legal,
  responsável interno.
- `PedidoTitularAnexo`: documentos de comprovação de identidade (disco privado).
- `RegistroTratamento` (ROPA, art. 37): finalidade, base legal, categorias de dados, retenção e
  compartilhamento por processo (matrícula, financeiro, saúde, CRM...).

### Telas
- Portal: **Meus dados e direitos** — abrir pedido, acompanhar protocolo, baixar o pacote.
- Fila interna do encarregado/DPO, com prazo em destaque e justificativa obrigatória para negar.
- **Exportação dos dados** da pessoa (JSON/PDF) montada a partir dos modelos que a referenciam.
- Cadastro do registro de operações de tratamento e do encarregado (art. 41) exibido no site.

### Regras
- Dado do menor: o pedido só é aceito do responsável legal vinculado ao aluno.
- Eliminação respeita exceções legais (obrigação legal, guarda de documentos escolares e fiscais):
  o sistema explica o que **não** pode ser apagado e por quê.
- Todo atendimento fica registrado sem repetir o conteúdo dos dados.

## Dependências já satisfeitas

- Portal, consentimentos, log de atividades e armazenamento privado.

## Risco e observação

- É a onda de maior risco jurídico por erro de omissão (exportação incompleta). Começar mapeando
  as tabelas que referenciam `pessoa` e revisar com o encarregado.
- Definir quem é o encarregado e o canal oficial antes de abrir o portal.

## Fusão sugerida

Compartilha com a [Onda 35](ouvidoria_canal_denuncias_roadmap.md) (ouvidoria e convivência escolar) o mesmo desenho: um caso com protocolo, responsável, prazo legal, histórico e resposta. Em vez de três cadastros, vale um motor de **"casos com prazo legal"** e três tipos: pedido do titular (a LGPD, art. 19, II, fixa 15 dias para a declaração completa), denúncia da ouvidoria e caso de convivência (Lei nº 13.185/2015). O prazo vencendo vira fila da Mesa de Pendências (Onda 71).

Veja o [índice das ondas 68 a 71](ondas_68_a_71_indice.md#fusões-sugeridas-para-dar-completude).

## Implementação (2026-11-02)

**Entregue** (tipo `pedido_titular` de `CatalogoRequerimentos`, página *Requerimentos* do Portal):

- O responsável escolhe o direito (confirmação, acesso, correção, anonimização, portabilidade, eliminação, compartilhamento ou revogação de consentimento), descreve o pedido
  e recebe um **protocolo**. O pedido vira um chamado `restrito` com prazo de **15 dias corridos** (LGPD, art. 19, II), configurável em `ATENDIMENTO_PRAZO_PEDIDO_TITULAR_DIAS`.
- **Acesso restrito:** só quem tem `VerRestritos:AtendimentoChamado` (administração, por padrão) vê o pedido na lista, no link direto e nas filas da Minha Mesa. O aviso à
  equipe não traz o assunto nem a mensagem e **não vai ao e-mail geral do setor**.
- O pedido só nasce de um responsável com acesso (regra do Portal). A resposta é registrada na conversa do chamado; indeferir **exige justificativa**, e a família a recebe.
- O prazo vence na fila *Requerimentos para atender* da Minha Mesa.

**Fora desta entrega (e por quê):**

- **Exportação dos dados da pessoa** (acesso e portabilidade): é a parte de maior risco jurídico (omitir uma tabela é entregar dado incompleto). Precisa do mapa das tabelas que
  referenciam `pessoa`, revisado pelo encarregado, e de uma decisão sobre o formato. Hoje o encarregado atende o pedido manualmente e registra a resposta no chamado.
- Registro das operações de tratamento (ROPA, art. 37), cadastro do encarregado e sua exibição no site (art. 41), e as exceções de eliminação (guarda de documento escolar e fiscal),
  que dependem da tabela de temporalidade da [Onda 79](guarda_descarte_documentos_roadmap.md).

**Testes:** `AtendimentoSolicitacoesTest` (restrição, prazo e fila).
