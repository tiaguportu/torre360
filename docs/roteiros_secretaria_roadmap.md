# Roteiros da Secretaria (Onda 82) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 88](ondas_80_a_88_indice.md).

## Contexto

A secretaria tem um calendário que quase ninguém escreveu: abrir o ano letivo, rodar a rematrícula, preparar e enviar o Censo,
fechar o ciclo, emitir históricos de quem se forma. Cada etapa depende de dez outras (turmas criadas, grade pronta, lista de
material publicada, contratos gerados, notas fechadas) e de prazos que não dependem da escola. Hoje esse roteiro mora na cabeça de
quem trabalha lá há mais tempo. A Minha Mesa mostra **filas de dados** ("há 7 matrículas sem turma"), mas não a lista de coisas
**manuais** de rotina ("conferir o calendário com a mantenedora") que ninguém vê enquanto não esquece.

## O que já existe (conferido no código)

- **O motor de checklist com prazo e comprovante já existe**, feito para o RH (Onda 65): `ProcessoFuncionario` e
  `ItemProcessoFuncionario` (categoria, orientação, obrigatório, **prazo em dias a partir de uma data âncora**, quem fez e quando,
  "não se aplica" com justificativa, comprovante), o `ProcessoFuncionarioService` (`iniciar`, `adicionarItem`, `marcarItem`,
  `dispensarItem`, `reabrirItem`, `progresso`, `concluir`) e a lista-modelo em `config/rh_processos.php`. A providência vencida já
  vira fila da Mesa (*Providências de RH vencidas*).
- Datas âncora que a secretaria já cadastra: `PeriodoLetivo` (início e fim), `PeriodoRematricula` (campanha), `EtapaAvaliativa`,
  `DiaNaoLetivo` e `EventoEscolar`.
- Telas onde cada etapa de fato acontece: Rematrícula, Censo Escolar, Fechamento do Ciclo Letivo, Grade Horária Assistida, Lista de
  Material, Histórico Escolar.
- **Não existe** checklist de rotina da secretaria nem do ano letivo.

## Escopo proposto

### Roteiros e etapas
- `Roteiro` (um por ano letivo, ou por campanha) criado a partir de um **modelo** (*Abertura do ano letivo*, *Rematrícula*, *Censo
  Escolar*, *Fechamento do ano*, *Formatura*) e de uma **data âncora** (início do período, fim da campanha, data do Censo).
- `EtapaRoteiro`: título, orientação, responsável (usuário ou papel), prazo calculado a partir da âncora, obrigatória, "não se
  aplica" com justificativa, comprovante e **vínculo opcional com uma tela** (botão "Abrir Rematrícula").
- Etapas que **se marcam sozinhas** quando o dado existe (ex.: "turmas do período criadas" fica pronta quando há turmas ativas), no
  mesmo estilo das filas da Mesa. As demais são manuais.

### Telas
- **Roteiros** (Secretaria): lista com progresso (feitas de total, atrasadas) e tela do roteiro com as etapas agrupadas por semana.
- **Minha Mesa:** fila *Etapas de roteiro vencidas ou da semana*, filtrada pelo responsável.
- Modelos editáveis pela administração (como a lista de `config/rh_processos.php`, mas em tela), com um modelo de partida.

### Regras
- Concluir o roteiro exige as etapas obrigatórias feitas ou dispensadas com motivo.
- Mudar a data âncora **recalcula** os prazos das etapas que ninguém mexeu.
- Cada etapa guarda quem marcou e quando; o roteiro fechado vira o histórico do ano.

## Dependências e riscos

- **Reaproveitar, não copiar:** o desenho natural é **generalizar** `ProcessoFuncionario` num motor de "processo com etapas" cujo
  dono pode ser um funcionário, um ano letivo ou uma campanha. Copiar as duas tabelas e o serviço para a secretaria dobraria a
  manutenção. A generalização mexe numa tela já em uso (Onda 65) e pede teste de regressão do RH.
- Os **prazos legais** (Censo, divulgação de valores da anuidade, entrega de documentos ao sistema de ensino) mudam por ano e por
  sistema de ensino: o modelo traz o roteiro, **não** a data. Quem cadastra a âncora é a escola.
- Etapas demais viram ruído: começar com **um modelo curto por roteiro** (10 a 15 etapas) e crescer pelo uso.
- Não confundir com os **vencimentos** da [Onda 60](documentos_escola_validade_roadmap.md) (implementada) e da
  [56](saude_seguranca_trabalhador_roadmap.md) (proposta): aqui a etapa é uma **ação** da secretaria; lá é a **validade** de um papel.

## Fusão sugerida

**Obrigações com prazo (82 + 65 + 60 + 56).** Admissão e desligamento (65, feita), documentos da escola com validade (60, feita), a
saúde e segurança do trabalhador (56) e os roteiros da secretaria (esta) são o mesmo objeto: *algo a fazer, de alguém, até uma data,
com prova*. Um motor só, com o tipo do dono e a âncora, e uma fila só na Minha Mesa; o [índice das ondas 49 a 60](ondas_49_a_60_indice.md)
já apontava essa fusão para os vencimentos.
