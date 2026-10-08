# Autorização de Uso de Imagem e Consentimentos (Onda 25)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda das telas (**Tipos de Consentimento** e
> **Consentimentos** no admin, **Consentimentos** no Portal da Família).

## Contexto

`docs/lgpd_saude_policies.md` existente é sobre controle de acesso (Shield/Policies para
dados de saúde) — não sobre consentimento da família. Esta onda cobre o segundo: o que a
família autoriza sobre o próprio filho (ex.: uso de imagem em redes sociais), consultável
rapidamente pela secretaria.

## Decisão de implementação — aceite digital simples em vez de Assinafy

A proposta original previa reaproveitar o `AssinafyService` (Onda 7) para a assinatura do
consentimento. Ao implementar, ficou claro que `AssinafyService::enviarContrato()` é
fortemente acoplado ao model `Contrato` (contrato de matrícula) — generalizá-lo para
qualquer tipo de documento seria um refactor arriscado de um serviço crítico e já testado,
desproporcional para um consentimento de baixo risco jurídico (bem diferente de um
contrato financeiro).

Em vez disso, o consentimento é registrado como **aceite digital simples no Portal**:
usuário autenticado, resposta com timestamp e IP (`respondido_em`, `ip_resposta`,
`respondido_por_user_id`) — mesmo padrão já usado em `EventoConfirmacao` (RSVP de eventos
escolares, Onda 2), que também já registra `data_resposta` e `ip_resposta` da mesma forma.
Suficiente para este caso de uso e sem tocar em `AssinafyService`.

## Como foi implementado

- `TipoConsentimento`: catálogo (nome, texto padrão, se exige renovação periódica e a
  cada quantos meses, ativo). Resource em Configurações.
- `ConsentimentoMatricula`: `matricula_id`, `tipo_consentimento_id`, status (Pendente/
  Autorizado/Não Autorizado), vigência (início/fim), dados do respondente. Resource em
  Secretaria, com indicador rápido por aluno.
- `ConsentimentoMatricula::statusEfetivo()`: um consentimento Autorizado cuja vigência já
  venceu é tratado como Pendente **na leitura**, sem precisar de job/observer para
  "rebaixar" o registro — mais simples e sem risco de ficar desatualizado.
- Portal da Família (`/portal/consentimentos`): lista todo tipo de consentimento ativo
  para o aluno selecionado (criando a entrada Pendente na hora, se ainda não existir) com
  botões Autorizar / Não Autorizar.
- Permissões: `secretaria`, `admin`, `super_admin` — mesmo grupo que já cuida da matrícula.

## Dependências já satisfeitas

- Portal do Aluno (`InteractsWithMatriculaSelecionada`) e padrão de registro de aceite com
  IP/timestamp já estabelecidos (`EventoConfirmacao`, Onda 2) — reaproveitados diretamente.
