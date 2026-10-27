# Painel da Direção (Onda 45)

> **Status: implementada** (2026-10-27). As seções *Contexto*, *O que já existe* e *Escopo* abaixo são o planejamento original;
> o que foi entregue, com as decisões e o que ficou de fora, está em **Como foi implementado**, no fim do documento.

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

---

## Como foi implementado (2026-10-27)

Menu **Relatórios → Painel da Direção** (`/admin/painel-da-direcao`) e **Relatórios → Metas dos Indicadores**.

### Indicadores (13, em quatro seções)

Cada indicador é uma classe com **uma definição só** (`App\Contracts\IndicadorDoPainel`, registrada em `config/painel_direcao.php`). Quando já existia um serviço ou relatório com o mesmo número, o indicador chama esse código, para a escola não ter dois valores para a mesma coisa; os testes comparam o painel com o relatório de Inadimplência e com o DRE.

| Seção | Indicador | Melhor | Permissão | Por unidade |
|---|---|---|---|---|
| Alunos e vagas | Alunos matriculados | maior | `ViewAny:Matricula` | sim |
| | Ocupação das turmas | maior | `ViewAny:Turma` | sim |
| | Vagas livres | menor | `ViewAny:Turma` | sim |
| | Alunos em risco alto de evasão | menor | `ViewAny:Matricula` | sim |
| Financeiro | Inadimplência em aberto (o "total devido" do relatório) | menor | `View:RelatorioInadimplencia` | não |
| | Taxa de inadimplência (90 dias) | menor | `View:RelatorioInadimplencia` | não |
| | Resultado do mês anterior (o do DRE) | maior | `View:RelatorioDRE` | não |
| | Caixa projetado em 30 dias (fluxo projetado) | maior | `View:FluxoProjetado` | não |
| | Margem das turmas (resultado realizado) | maior | `View:RentabilidadeRealizada` | sim |
| | Despesas sobre o orçamento | menor | `View:OrcadoRealizado` | não |
| Captação | Leads novos (30 dias) | maior | `ViewAny:Interessado` | não |
| | Conversão de leads (90 dias) | maior | `ViewAny:Interessado` | não |
| Pedagógico | Frequência média (30 dias) | maior | `ViewAny:FrequenciaEscolar` | não |

**Permissão por indicador:** cada pessoa vê só os cartões cuja permissão (a da tela onde o número nasce) ela tem; financeiro não aparece para quem não vê financeiro. A página em si exige `View:PainelDirecao`.

### Cartão

Valor atual, **variação contra 30 dias atrás** (seta verde é melhora, vermelha é piora, conforme o sentido de cada indicador), variação contra o **ano anterior**, **série** dos últimos 90 dias, **meta do ano** e **semáforo**. A fórmula do número aparece no "ⓘ" e o clique abre a tela de detalhe. Indicador que não existe por unidade avisa que mostra a escola toda quando a unidade é filtrada. Indicador que falha vira "—" e **não derruba o painel**.

### Metas e semáforo

`indicador_metas`: indicador, ano, unidade (em branco = escola toda), meta e **tolerância %**. A meta da unidade vence a da escola toda. **Verde** atingiu a meta; **amarelo** está pior que a meta, mas dentro da tolerância (em % da meta); **vermelho** passou dela. Para "maior é melhor" a comparação é `>=`; para "menor é melhor", `<=`. Meta repetida para o mesmo indicador, ano e unidade é recusada.

### Fotografias e comparação

`painel_snapshots`: o valor de cada indicador por dia (e por unidade, nos que suportam). Rotina **`painel:fotografar`** (todo dia, 06:55) grava e apaga as com mais de 800 dias; o botão *Guardar fotografia de hoje* (`Fotografar:PainelDirecao`) refaz a de hoje. A comparação usa a fotografia mais recente que não seja posterior a 30 dias atrás (janela de 60 dias) e a do ano anterior (janela de 15 dias); sem fotografia, o cartão diz que ainda não há o que comparar. O valor de cada indicador fica em cache por 10 minutos (`PAINEL_CACHE_SEGUNDOS`); o botão *Atualizar* esquece tudo.

### Atenção hoje

Lista as filas da **Minha Mesa da própria pessoa** que têm algo atrasado, as mais atrasadas primeiro (o painel não reimplementa pendência nenhuma).

### Permissões

`View:PainelDirecao` (administração, financeiro, coordenação), `Fotografar:PainelDirecao` (administração), `ViewAny` e `View:IndicadorMeta` (administração, financeiro) e `Create`, `Update` e `Delete:IndicadorMeta` (administração).

### Testes

`tests/Feature/PainelDirecaoTest.php` (27: cada indicador, permissão por indicador, semáforo, variação, metas, fotografias, falha isolada, cache) e `tests/Feature/PainelDirecaoTelasTest.php` (14: página, filtro de unidade, ações e cadastro de metas).

### Fora desta entrega

Indicadores de operação (OS vencidas, manutenção preventiva, pareceres aguardando revisão) e de evasão consumada, que dependem de fontes que ainda não têm uma definição única; indicadores por perfil de decisor (a permissão já separa); alertas por e-mail quando um cartão fica vermelho.
