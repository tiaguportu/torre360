# Conselho de Classe e Atas (Onda 27) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Ao fim de cada etapa avaliativa, professores e coordenação se reúnem por turma para decidir
sobre cada aluno: aprovação, recuperação, encaminhamentos pedagógicos, acionamento da família ou
de apoio especializado. Hoje essa discussão acontece fora do sistema (papel, planilha, ata em
documento avulso), e a decisão não fica ligada ao aluno nem ao fechamento do ciclo.

## O que já existe (ponto de partida)

- `Nota`, `NotaHabilidade`, `FrequenciaEscolar` e `OcorrenciaEscolar`: tudo o que o conselho
  precisa ver do aluno.
- `FechamentoCicloService` e a tela **Fechamento do Ciclo Letivo** (recuperação por etapa e exame
  final, `docs/recuperacao_etapa_exame_final.md`): onde a decisão final é aplicada.
- `RiscoEvasaoService` (risco de evasão por aluno), `ParecerDescritivo` (Onda 30) e
  `PlanoAulaAdaptacao` (PEI): contexto pedagógico por aluno.
- `Preceptoria` e `RelatorioPreceptoria`: acompanhamento individual, útil como fonte para a pauta.
- Nada que represente a **reunião** em si, a pauta, as deliberações ou a ata.

## Escopo

### Modelos novos
- `ConselhoClasse`: `turma_id`, `etapa_avaliativa_id`, data, status (agendado, realizado, ata
  assinada), presidente (user).
- `ConselhoClasseParticipante`: user/pessoa, presença.
- `ConselhoClasseAluno`: `matricula_id`, resumo automático (médias, faltas, ocorrências, risco),
  **deliberação** (aprovar, recuperação, encaminhar a AEE, acionar família...), observação.
- `ConselhoClasseAta`: texto da ata gerado a partir das deliberações, PDF e assinatura
  (reaproveitar o fluxo do Assinafy, `docs/assinafy_assinatura_contratos.md`).

### Telas
- Pauta do conselho: abre a lista de alunos da turma já com os indicadores, ordenada por risco.
- Deliberação aluno a aluno, em modo reunião (uma tela por aluno, teclado).
- Geração da ata em PDF e envio para assinatura dos presentes.

### Regras
- A deliberação **não altera notas**: ela gera encaminhamentos (tarefas para a coordenação) e pode
  alimentar a recuperação no fechamento do ciclo.
- Deliberação sobre aluno com necessidades especiais só mostra dados clínicos a quem tem permissão.
- Ata fechada fica imutável (correção por adendo).

## Dependências já satisfeitas

- Indicadores por aluno (notas, frequência, ocorrências, risco) e assinatura eletrônica.

## Risco e observação

- Dado sensível de menores na pauta: seguir `docs/lgpd_saude_policies.md` e o recorte do
  professor (`EscopoProfessor`, Onda 30).
- Procedimentos do conselho variam por regimento escolar; o modelo precisa ser configurável
  (itens de deliberação por escola).
