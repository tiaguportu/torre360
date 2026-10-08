# Agenda Diária / Rotina (Educação Infantil) (Onda 26)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda da tela (**Agenda Diária**, grupo
> **Acadêmico**, no admin).

## Contexto

Em creches e educação infantil, a família acompanha o dia da criança por um registro
diário (alimentação, sono, higiene, humor, atividades) — recurso de alto engajamento,
distinto de boletim/nota (que não se aplica a bebês) e de comunicado geral. Distinto de
`MaterialAula` (conteúdo do professor para o aluno estudar) e `EventoEscolar` (evento
pontual com confirmação de presença).

## Como foi implementado

- `RegistroRotinaDiaria`: `matricula_id`, `turma_id`, `data` (único por aluno+data — um
  registro por dia), `humor` (`HumorCrianca`: Feliz/Tranquilo/Agitado/Irritado/Sonolento/
  Choroso), `hora_inicio_soneca`/`hora_fim_soneca`, `atividades_dia`,
  `higiene_observacoes`, foto opcional (mesmo padrão de upload de `MaterialAula`).
- `RefeicaoRotina` (lista, não texto único): `registro_rotina_diaria_id`, `nome` da
  refeição (texto livre — "Café da Manhã", "Almoço" etc., já que escolas têm rotinas de
  refeição diferentes), `quantidade` (`QuantidadeRefeicao`: Comeu Tudo/Comeu Parcialmente/
  Não Quis Comer), observação. Gerenciada como `Repeater` com `->relationship()` dentro do
  próprio formulário do registro do dia — uma tela só, sem precisar salvar o registro
  antes de adicionar as refeições.
- Resource em `/admin/registro-rotina-diarias` (grupo **Acadêmico**), validação de
  unicidade (aluno + data) direto no formulário via `unique(modifyRuleUsing: ...)`.
- Portal da Família (`/portal/agenda-diaria`): lista os registros do aluno selecionado,
  com ação "Ver Detalhes" (Infolist) mostrando a lista completa de refeições e o horário
  da soneca.
- Permissões: `professor`, `coordenador`, `secretaria`, `admin`, `super_admin` — mesmo
  grupo que já publica conteúdo pedagógico (`MaterialAula`).

### Não incluído nesta entrega

- **Preenchimento em lote por turma** (a proposta original previa uma tela para o
  professor preencher a turma toda de uma vez, com valores padrão). Ficou de fora da v1:
  é uma UI própria fora do padrão Filament Resource usado no resto do projeto, que não foi
  pedida explicitamente nesta rodada — um registro por aluno via o Resource padrão já
  resolve o caso de uso principal. Boa candidata a melhoria futura se o volume de alunos
  por turma tornar o preenchimento individual lento na prática.
- **Notificação automática ao publicar o registro do dia**: a família acessa o registro
  pelo Portal sob demanda; não há envio automático de e-mail/push a cada registro criado,
  para não gerar uma notificação por aluno por dia (diferente de `OcorrenciaEscolar`, que é
  um evento raro). Se necessário, é um complemento simples sobre o que já existe.

## Dependências já satisfeitas

- Portal do Aluno (`InteractsWithMatriculaSelecionada`) já estabelecido.
- Padrão de upload de arquivo (`FileUpload` com `directory()`) já estabelecido em
  `MaterialAula`/`PlanoAula`.
