# Painel da Direção (Onda 45) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

A direção quer abrir uma única tela e ver a saúde da escola: matrículas e vagas, inadimplência,
evasão, funil de captação, frequência, ocorrências e pendências críticas, com comparação com o
período anterior e metas. Hoje esses números estão espalhados em relatórios e widgets por área.

## O que já existe (ponto de partida)

- `Dashboard` e widgets (`StatsOverview`, `AlunosPorTurmaChart`, `MatriculasPendentesWidget`,
  `FrequenciaPendenteWidget`, `QueueSupervisorWidget`, widgets de CRM).
- Serviços de indicadores: `RiscoEvasaoService`, `TermometroVagasService`, `FunilAnaliticoService`,
  `CrmRelatoriosService`, `DREService`, `ControladoriaTurmaService`.
- Relatórios: **Inadimplência**, **DRE**, **Fluxo de Caixa**, **Controladoria de Turmas**.
- Não existe painel único com metas, comparação de períodos e visão por perfil de decisor.

## Escopo

### Modelos novos
- `IndicadorMeta`: indicador (chave), unidade, período, meta e tolerância (para semáforo).
- `PainelSnapshot` (opcional): fotografia diária dos indicadores para séries históricas baratas.

### Telas
- **Painel da Direção** com cartões (valor atual, variação, meta, semáforo) e detalhamento por
  clique, agrupados em: Alunos e vagas, Financeiro, Captação, Pedagógico, Operação.
- Filtro por unidade e período; comparação com o mesmo período do ano anterior.
- Lista de **atenção hoje**: itens que exigem ação (contratos pendentes, SLA estourado, OS vencidas,
  planos de manutenção vencidos, pareceres aguardando revisão).

### Regras
- Cada indicador tem **uma definição única** em código (fórmula documentada), reutilizada pelos
  relatórios existentes para não haver dois números diferentes na escola.
- Permissão por indicador: financeiro não aparece para quem não vê financeiro.

## Dependências já satisfeitas

- Serviços de indicadores já existem; falta o agregador e as metas.

## Risco e observação

- É a onda que mais depende de dados confiáveis das outras: publicar só os indicadores cuja fonte
  esteja bem preenchida.
- Custo de consulta: usar snapshot diário para séries históricas em vez de recalcular tudo a cada
  abertura.
