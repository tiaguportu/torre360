# Qualificação de Leads no CRM: Temperatura x Lead Score

Model: `App\Models\Interessado` (tabela `interessado`)
Serviço: `App\Services\LeadScoreService`
Config: `config/lead_score.php`

O CRM usa **dois indicadores complementares e independentes** para qualificar um
lead. Eles não se substituem — cada um responde uma pergunta diferente:

| | `temperatura` | `lead_score` |
|---|---|---|
| O que responde | "Como o consultor **sente** esse lead?" | "O que os **dados** dizem sobre esse lead?" |
| Quem define | 100% manual, no formulário do Interessado | 100% automático, recalculado pelo sistema |
| Valores | `quente` / `morno` / `frio` (ou vazio) | Número de 0 a 100 |
| Onde fica | Coluna "Temp. (consultor)" na tabela, campo no formulário, card do Kanban | Coluna "Score" na tabela, placeholder no formulário, badge no card do Kanban |

Antes desta implementação (2026-08), existiam **duas lógicas automáticas
diferentes e não documentadas** tentando calcular a temperatura sozinhas:
`Interessado::temperaturaCalculada()` (dias sem contato) e
`CaptacaoInteressadoController::inferirTemperatura()` (completude do
formulário público). Ambas foram removidas. A temperatura agora é
exclusivamente a percepção do consultor; o papel de "cálculo automático" passou
a ser do Lead Score, que é multifator e documentado nesta página.

## Como o Lead Score é calculado

`LeadScoreService::calcular(Interessado $interessado): int` soma pontos de 11
fatores, agrupados em 3 blocos. A soma dos pesos máximos é 100. Todos os pesos,
faixas e mapeamentos ficam em `config/lead_score.php` — **ajustar a fórmula não
exige mexer no código**, só no config.

### Perfil / Fit (até 55 pontos)

| Fator | Peso máx. | Como é calculado |
|---|---|---|
| Nº de filhos em idade escolar | 15 | `dependentes()->count()`, por faixas (`config('lead_score.filhos')`) |
| Distância até a escola | 15 | Campo manual `faixa_distancia_escola` (não há geocoding no sistema) |
| Meio de transporte | 5 | Campo manual `meio_transporte` |
| Profissão | 10 | Palavra-chave sobre `pessoa.profissao` (texto livre), ver `config('lead_score.profissoes')` |
| Valor estimado | 10 | `valor_estimado`, por faixas (`config('lead_score.valor_estimado')`) |

### Engajamento (até 35 pontos)

| Fator | Peso máx. | Como é calculado |
|---|---|---|
| Interações bem-sucedidas | 15 | Histórico de contato com `resultado` em `agendou_visita`/`matriculou` |
| Total de interações | 5 | Qualquer histórico de contato registrado |
| Recência do contato | 10 | Dias desde o último histórico (ou desde a criação, se nunca houve contato) — decai com o tempo |
| Completude do cadastro | 5 | Telefone, e-mail, profissão preenchidos + ao menos 1 dependente cadastrado |

### Intenção comercial (até 10 pontos)

| Fator | Peso máx. | Como é calculado |
|---|---|---|
| Origem do lead | 5 | Peso por nome da origem (`config('lead_score.origem')`), ex.: indicação > anúncio > orgânico |
| Estágio no funil | 5 | Proporcional à posição (`ordem`) do status atual entre os status ativos; leads matriculados recebem o máximo, leads perdidos recebem 0 |

### Faixas de cor exibidas na interface

Definidas em `config('lead_score.faixas_cor')`:

- **Verde (`success`)**: score ≥ 70
- **Amarelo (`warning`)**: score entre 40 e 69
- **Vermelho (`danger`)**: score < 40

## Quando o score é recalculado

O score é uma coluna persistida (`interessado.lead_score` +
`interessado.lead_score_atualizado_em`), não calculada em tempo real na tela,
para poder ser ordenado/filtrado na tabela do Filament. Ele é recalculado via
`LeadScoreService::recalcular()` nos seguintes pontos:

- Criar ou editar um lead pelo Filament (`CreateInteressado::afterCreate()` /
  `EditInteressado::afterSave()`).
- Registrar, editar ou excluir um histórico de contato
  (`HistoricosRelationManager`, ação rápida "Atendimento" na tabela).
- Mudar o status do lead (ações "Matricular"/"Perdido" na tabela, ou arrastar o
  card no Kanban).
- Novo lead capturado pela landing page pública
  (`CaptacaoInteressadoController::store()`).

Além disso, um comando agendado roda **todo dia às 6h**
(`php artisan crm:recalcular-lead-score`, registrado em `routes/console.php`)
recalculando todos os leads ativos. Isso é necessário porque o fator de
recência (dias sem contato) muda sozinho com a passagem do tempo, mesmo sem
nenhuma ação manual no lead.

## Ajustando os pesos

Toda a fórmula fica em `config/lead_score.php`. Para mudar o peso de um fator,
edite o valor correspondente em `pesos` (lembrando de manter a soma em 100 para
o score continuar variando de 0 a 100) e, se necessário, as faixas/mapeamentos
específicas daquele fator no mesmo arquivo. Depois de mudar o config, rode
`php artisan crm:recalcular-lead-score` para atualizar os leads já existentes.
