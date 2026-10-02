# Risco de Evasão Escolar (Onda 13)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda da tela **Pesos do Risco de Evasão**
> (`/admin/secretaria/pesos-risco-evasao`).

## Contexto

O CRM já pontua **interessados** (leads) por probabilidade de conversão (`LeadScoreService`
+ `LeadScoreConfiguracao`, Onda 1). Não existe o equivalente para **alunos já
matriculados**: um score de risco de evasão/abandono, calculado a partir de frequência,
notas e inadimplência, que ajude a escola a agir antes da evasão acontecer (hoje
`SituacaoMatricula::EVASAO` só existe como status manual, lançado depois que o aluno já
saiu — não é preditivo).

## Padrão a espelhar (já existe e funciona bem)

`app/Services/LeadScoreService.php` + `app/Models/LeadScoreConfiguracao.php` +
`config/lead_score.php` + `app/Filament/Pages/ConfiguracaoLeadScore.php`:

- Config em arquivo PHP (pesos por fator, somando 100, e faixas de cor) com override
  persistido em banco (`LeadScoreConfiguracao`, aplicado no boot via
  `AppServiceProvider`).
- Serviço estático de cálculo (`calcular()`, `detalhar()` por fator, `recalcular()` via
  `DB::table()->update()` direto, sem disparar observers).
- Página de configuração dos pesos (abas, validação de soma = 100), com ações "Salvar",
  "Restaurar padrão" e "Recalcular todos".
- Recálculo automático nos eventos relevantes (criar/editar/mudar status) **e** comando
  agendado diário para fatores que decaem só com o tempo.

## Escopo

### Fatores propostos (a validar/ajustar quando a onda for autorizada)
- **Frequência:** faltas recentes (ex.: últimos 30 dias) via `FrequenciaEscolar`.
- **Desempenho:** médias abaixo da aprovação / situação de recuperação via
  `SituacaoFinalDisciplina`.
- **Inadimplência:** `Matricula::hasDebitosVencidos()` (já existe, criado na Onda 7) —
  reaproveitar diretamente, sem reimplementar a lógica de atraso.
- Opcional: tempo desde o último contato da secretaria/ocorrência registrada.

### Modelo/campos novos
- `risco_evasao_score` e `risco_evasao_atualizado_em` em `Matricula` (mesmo padrão de
  `lead_score`/`lead_score_atualizado_em` em `Interessado`).
- `RiscoEvasaoConfiguracao` (espelhando `LeadScoreConfiguracao`) + `config/risco_evasao.php`.
- Página de configuração de pesos (espelhando `ConfiguracaoLeadScore`).
- Comando agendado de recálculo (espelhando `crm:recalcular-lead-score`).

## Dependências já satisfeitas

- `FrequenciaEscolar`, `SituacaoFinalDisciplina` (Onda 5) e
  `Matricula::hasDebitosVencidos()` (Onda 7) já existem e cobrem os 3 fatores principais.
- O padrão inteiro de configuração/cálculo/recálculo já está implementado e testado para
  o Lead Score — é reaproveitar a receita, não desenhar algo novo do zero.

## Como foi implementado

- `RiscoEvasaoService` (`app/Services/RiscoEvasaoService.php`): espelha o
  `LeadScoreService` método a método (`calcular()`, `detalhar()`, `recalcular()` via
  `DB::table()->update()` direto, `cor()`). Única inversão de semântica: aqui pontuação
  **alta** é **ruim** (mais risco), então `cor()` mapeia para danger/warning/success em vez
  de success/warning/danger.
- **3 fatores, 100 pontos no total** (ver `config/risco_evasao.php`):
  - **Frequência (40 pts):** % de faltas nos últimos 30 dias sobre as aulas com
    frequência lançada no período; sem aulas registradas, 0 pontos. Faixas configuráveis.
  - **Desempenho (35 pts):** pior situação final (`SituacaoFinalDisciplina`) entre as
    disciplinas do período letivo mais recente que já teve fechamento de ciclo
    registrado para a matrícula — reprovado > recuperação > aprovado.
  - **Inadimplência (25 pts):** `Matricula::hasDebitosVencidos()` (Onda 7), reaproveitado
    diretamente — sem pontos parciais, é tudo ou nada.
  - O quarto fator opcional do escopo original (tempo desde o último contato) **não foi
    implementado** nesta entrega, para manter o escopo no mesmo tamanho do que o Lead
    Score original antes de crescer com fatores adicionais.
- `RiscoEvasaoConfiguracao` + `ConfiguracaoRiscoEvasao`
  (`/admin/secretaria/pesos-risco-evasao`): mesma mecânica de override persistido em
  banco, aplicado no boot via `AppServiceProvider`. Ações "Salvar configuração",
  "Restaurar padrão" e "Recalcular todas as matrículas".
- **Recálculo:** só o comando agendado diário (`academico:recalcular-risco-evasao`,
  06h30) e a ação manual "Recalcular todas as matrículas" — ao contrário do Lead Score,
  não há recálculo automático em ~10 pontos de código diferentes (ações do CRM); isso
  pode ser adicionado depois caso o recálculo diário não seja granular o suficiente.
- **Exibição:** nova coluna "Risco de Evasão" (badge colorido, mesmo padrão da coluna
  "Qualificação" do Lead Score) na listagem de Matrículas já existente
  (`/admin/matriculas`), `toggleable` para não forçar quem não usa o recurso.
- Permissão de visualização da página de configuração (`View:ConfiguracaoRiscoEvasao`)
  restrita a `admin`/`super_admin`, mesmo padrão do Lead Score.
- Testes em `tests/Feature/RiscoEvasaoServiceTest.php` e
  `tests/Feature/ConfiguracaoRiscoEvasaoTest.php`.
