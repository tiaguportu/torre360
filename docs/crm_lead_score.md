# Qualificação de Leads no CRM: Temperatura x Lead Score

Model: `App\Models\Interessado` (tabela `interessado`)
Serviço: `App\Services\LeadScoreService`
Config: `config/lead_score.php`

O CRM usa **dois indicadores complementares** para qualificar um lead. Cada um
responde uma pergunta diferente, mas a `temperatura` (percepção do consultor)
também **entra no cálculo do score**, como o fator de maior peso (20 de 100):

| | `temperatura` | `lead_score` |
|---|---|---|
| O que responde | "Como o consultor **sente** esse lead?" | "O que os **dados** dizem sobre esse lead?" |
| Quem define | 100% manual, no formulário do Interessado | Automático, recalculado pelo sistema (inclui a temperatura como um dos fatores) |
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

`LeadScoreService::calcular(Interessado $interessado): int` soma pontos de 12
fatores, agrupados em 4 blocos. A soma dos pesos máximos é 100. Todos os pesos,
faixas e mapeamentos ficam em `config/lead_score.php` — **ajustar a fórmula não
exige mexer no código**, só no config.

### Percepção do consultor (até 20 pontos)

| Fator | Peso máx. | Como é calculado |
|---|---|---|
| Percepção do consultor | 20 | Campo manual `temperatura` do lead: quente = 20, morno = 10, frio = 0, não informada = 0 (`config('lead_score.percepcao_consultor')`). É o fator de maior peso |

Como a temperatura é um campo do formulário, o score é recalculado ao salvar o
lead (já faz parte do fluxo de edição).

### Perfil / Fit (até 40 pontos)

| Fator | Peso máx. | Como é calculado |
|---|---|---|
| Nº de filhos em idade escolar | 10 | `dependentes()->count()`, por faixas (`config('lead_score.filhos')`) |
| Distância até a escola | 10 | Campo manual `faixa_distancia_escola` (não há geocoding no sistema) |
| Meio de transporte | 5 | Campo manual `meio_transporte` |
| Profissão | 5 | Palavra-chave sobre `pessoa.profissao` (texto livre), ver `config('lead_score.profissoes')` |
| Valor estimado | 10 | `valor_estimado`, por faixas (`config('lead_score.valor_estimado')`) |

### Engajamento (até 30 pontos)

| Fator | Peso máx. | Como é calculado |
|---|---|---|
| Interações bem-sucedidas | 10 | Histórico de contato com `resultado` em `agendou_visita`/`matriculou` |
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

**Pelo painel (recomendado):** menu CRM / Comercial → **Pesos do Lead Score**
(`App\Filament\Pages\ConfiguracaoLeadScore`, permissão Shield
`View:ConfiguracaoLeadScore`, concedida a `super_admin` e `admin`). A tela tem 4
abas (Pesos e Cores, Perfil / Fit, Engajamento, Origem) e três ações:
**Salvar configuração**, **Restaurar padrão** e **Recalcular todos os leads**.

Regras validadas ao salvar: a soma dos 12 pesos deve ser 100; o corte "Morno"
não pode superar o "Quente"; os pontos de cada faixa/opção não podem passar do
peso do fator. As faixas (filhos, valor estimado, recência) são reordenadas
automaticamente.

**Como é guardado:** tabela `lead_score_configuracao` (uma linha por salvamento,
vale a mais recente; `valores` em JSON). Em cada requisição,
`AppServiceProvider::boot()` chama `LeadScoreConfiguracao::aplicar()`, que
sobrescreve chave a chave a config em memória (`array_replace`) com o valor do
cache `lead_score_configuracao`. Chaves ausentes continuam valendo
`config/lead_score.php`, que permanece como padrão do sistema ("Restaurar
padrão" apaga as sobrescritas). Salvar não altera scores já gravados: use
"Recalcular todos os leads" ou `php artisan crm:recalcular-lead-score`.

**Por arquivo:** ainda é possível editar `config/lead_score.php` (vira o novo
padrão, mas só vale enquanto não houver personalização salva no painel).
