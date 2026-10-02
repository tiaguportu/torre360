# Risco de Evasão Escolar (Onda 13) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

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
