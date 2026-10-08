# Autorização de Uso de Imagem e Consentimentos (Onda 25) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Escolas publicam fotos/vídeos de alunos (redes sociais, mural, material de divulgação) e
precisam do consentimento formal dos responsáveis — obrigatório para menores (ECA) e boa
prática de LGPD. Hoje isso normalmente é só um papel assinado na matrícula, sem registro
consultável no sistema (a secretaria não tem como checar rapidamente "posso postar a foto
deste aluno?").

## O que já existe (ponto de partida)

- `docs/lgpd_saude_policies.md` existente é sobre **controle de acesso** (Shield/Policies
  para dados de saúde) — não é sobre termo de consentimento da família. Não confundir os
  dois: um é "quem no sistema pode ver o dado", o outro é "o que a família autorizou sobre
  o próprio filho".
- `AssinafyService`/`TemplateContrato` (Onda 7) já cobrem assinatura eletrônica com
  validade jurídica — é reaproveitar para o termo de consentimento, não reimplementar.
- `Matricula`/`Pessoa` já são o ponto de ancoragem natural para qualquer consentimento por
  aluno.

## Escopo

### Modelo novo
- `TipoConsentimento`: nome (ex.: "Uso de Imagem — Redes Sociais", "Uso de Imagem —
  Material Impresso", "Compartilhamento de Dados com Parceiros"), texto padrão, se exige
  renovação periódica (ex.: anual, na rematrícula).
- `ConsentimentoMatricula`: `matricula_id`, `tipo_consentimento_id`, status
  (autorizado/não autorizado/pendente), `documento_assinatura_id` (quando assinado via
  Assinafy), vigência (data início/fim), observação.

### Telas
- Portal da Família: tela para visualizar e assinar/recusar cada tipo de consentimento
  vigente.
- Admin/Secretaria: indicador rápido por aluno ("pode usar imagem? sim/não/pendente"),
  consultável antes de qualquer publicação — o uso real (aprovar a postagem em si) fica
  fora do sistema, aqui só mora a autorização.
- Renovação: na rematrícula (Onda 7), consentimentos com vigência anual voltam a
  "pendente" automaticamente.

## Dependências já satisfeitas

- `AssinafyService` (Onda 7) já cobre assinatura eletrônica — o trabalho real é só o
  model `ConsentimentoMatricula` e a tela de consulta/assinatura, orquestrando um serviço
  que já existe e já é testado.
