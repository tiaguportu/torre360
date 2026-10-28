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
| Total de interações | 5 | Qualquer histórico de contato registrado **que não seja automático** |
| Recência do contato | 10 | Dias desde o último contato não automático (ou desde a criação, se nunca houve contato) — decai com o tempo |
| Completude do cadastro | 5 | Telefone, e-mail, profissão preenchidos + ao menos 1 dependente cadastrado |

> **Registros automáticos não pontuam.** `historico_contato.automatico = true` marca o que o próprio
> sistema gerou (e-mail da régua de follow-up, análise de documento por IA, dossiê da IA salvo no
> histórico). Eles aparecem na linha do tempo, mas não contam como interação: não somam em
> "Total de interações" nem renovam a "Recência". O mesmo vale para "Estagnado" e "Sem interação".
> Ações da família (reenvio do formulário, envio de documento, resposta à pesquisa) **contam**.

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

### Onde o detalhamento aparece (Resumo do Lead)

Na edição do interessado (`/admin/interessados/{id}/edit`, aba "Dados do Negócio"), o
bloco **Resumo do Lead** é a view
`resources/views/filament/resources/interessados/resumo-lead.blade.php`, alimentada por
`LeadScoreService::detalhar()` (uma linha por fator, com `fator`, `pontos` e `maximo`). O
"Detalhamento do score" é uma barra de progresso por fator, cor por % do máximo
(≥ 70% verde, ≥ 35% âmbar, abaixo disso vermelho). Para evitar poluição visual e rolagem excessiva,
o card de Detalhamento do score inicia **recolhido por padrão** via Alpine (`x-data="{ aberto: false }"` e
`wire:ignore.self`), podendo ser expandido pelo botão interativo "Ver como o score foi calculado" (com chevron rotacionável
e acessibilidade `aria-expanded`). O estado é protegido contra reset em re-renderizações Livewire.

Layout responsivo (CSS em `<style>` na própria view, classe `.lead-score-fatores`):

- **Até 767px (celular):** 1 coluna.
- **A partir de 768px (desktop/tablet):** 2 colunas fixas (`repeat(2, minmax(0, 1fr))`), 6 fatores
  em cada com os 12 fatores atuais.
- As colunas usam `minmax(0, 1fr)` e os itens `min-width: 0`: sem isso, o conteúdo "empurra" a
  coluna e a barra passa do limite da tela. O rótulo do fator quebra linha (`overflow-wrap`) e o
  valor "10 / 20" não quebra (`white-space: nowrap`).
- Os cartões do topo (anel do score e métricas) usam `minmax(min(100%, 260px), 1fr)` e
  `minmax(min(100%, 150px), 1fr)`: o mínimo da coluna nunca excede a largura disponível, o que
  antes causava estouro horizontal em celulares de ~360px.

**Recuos (margens internas) e isolamento do tema.** O tema do painel
(`resources/css/filament/admin/theme.css` e `public/css/filament/admin/theme.css`) aplica
`.fi-section { padding: 1.5rem !important; border-width: 0 !important; background: #fff !important }` em
**toda** seção. Por ser `!important`, um `padding` inline ou de classe simples **nunca vale**.
Essa especificidade do tema foi superada através de seletores escopados de maior especificidade com `!important`:
- No Resumo do Lead: `.lead-resumo-secao ...` com recuo compacto;
- Nas seções compactas do formulário (`InteressadoForm`): classe `crm-secao-compacta` com seletor
  `.fi-sc-section.crm-secao-compacta section.fi-section { padding: 1rem !important; }` no desktop e `0.75rem !important`
  em telas até 640px. Ambas as cópias de `theme.css` contêm exatamente as mesmas regras escopadas. somavam-se aba (24px) +
seção (24px) + conteúdo da seção (24px) + cartão (24px) por lado, e em 360px sobravam ~136px de
largura útil. A correção é local ao Resumo, sem mexer no tema:

- A `Section` recebe a classe `lead-resumo-secao` (`InteressadoForm`, `->extraAttributes()`), e a
  view usa seletores `.lead-resumo-secao …` (especificidade maior que `.fi-section`) com
  `!important`. É por isso que há `!important` no CSS da view: é a única forma de ganhar do tema.
- **Desktop:** a seção fica sem o padding extra do tema (o recuo vem do cabeçalho e do conteúdo,
  como no Filament padrão); cartões com 1.25rem (métricas 1rem).
- **Até 640px:** conteúdo da seção com .5rem, cartões com .75rem, espaços entre cartões .5rem e
  cabeçalho mais compacto. Em 360px a largura útil dos cartões passou de ~136px para ~240px.
- **Aba no celular:** o `p-6` das abas "contained" do Filament (24px de cada lado) também comia
  largura. O `Tabs::make('CRM')` recebe a classe `crm-abas-compactas` (`InteressadoForm`) e o
  `theme.css` reduz o padding da aba ativa para .75rem até 640px (seletor
  `.fi-sc-tabs.crm-abas-compactas.fi-contained .fi-sc-tabs-tab.fi-active`, mais específico que o do
  Filament, sem `!important`). Vale na criação e na edição do interessado. **O `theme.css` existe em
  duas cópias versionadas e idênticas** (`resources/css/filament/admin/` e `public/css/filament/admin/`,
  sem etapa de build): alterar sempre as duas. Em 360px a área útil dentro dos cartões passa de ~240px
  para ~264px (cartão de ~264px para ~288px: 360 − 32 da página − 24 da aba − 16 do conteúdo da seção).
- **Lead estagnado:** o cartão "Sem interação há" ganha a classe `lead-resumo-alerta`, com `outline`
  vermelho de 1px. O tema zera `border-width` de `.fi-section` com `!important`, então uma `border`
  vermelha nunca aparecia (só o texto ficava vermelho); `outline` não depende disso, não desloca o
  layout e acompanha os cantos arredondados.
- Se o tema mudar (ou a classe `fi-section` sair dos cartões), revisar esse bloco: o teste
  `InteressadoTest::test_pagina_de_edicao_renderiza_o_resumo_do_lead` garante apenas que os ganchos
  `lead-resumo-secao` e `crm-abas-compactas` chegam ao HTML.

## Quando o score é recalculado

O score é uma coluna persistida (`interessado.lead_score` +
`interessado.lead_score_atualizado_em`), não calculada em tempo real na tela,
para poder ser ordenado/filtrado na tabela do Filament. Ele é recalculado via
`LeadScoreService::recalcular()` nos seguintes pontos:

- Criar ou editar um lead pelo Filament (`CreateInteressado::afterCreate()` /
  `EditInteressado::afterSave()`).
- Registrar, editar ou excluir um histórico de contato
  (`HistoricosRelationManager`, ação rápida "Atendimento" na tabela).
- Mudar o status do lead (ações "Marcar matriculado"/"Perdido" na tabela, edição em lote, ou
  arrastar o card no Kanban) — todas passam por `LeadFunilService`; a conversão por matrícula,
  inclusive a matrícula online, vem de `InteressadoMatriculaService::registrarConversao()`.
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
