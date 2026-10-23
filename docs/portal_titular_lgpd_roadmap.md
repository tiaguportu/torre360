# Portal do Titular LGPD (Onda 34) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

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
