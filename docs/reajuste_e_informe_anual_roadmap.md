# Reajuste de Mensalidades e Informe Anual (Ondas 44 e 42, entrega unificada)

> **Status: implementado** (reajuste da Onda 44 e informe anual da Onda 42). A **NFS-e** (outra
> parte da Onda 42) continua proposta: veja `nfse_informe_mensalidades_roadmap.md`. Detalhes de uso
> ficam no texto de Ajuda das telas (**Reajuste de Mensalidades** e **Informe Anual de
> Mensalidades**, grupo **Financeiro**; **Informe Anual** no Portal da Família) e nas seções 70 e 71
> do `MANUAL_USUARIO.md`.

## Por que juntas

As duas fecham o ciclo financeiro anual da família: no fim do ano a escola define quanto custará o
próximo ano (reajuste, comunicado dentro do prazo legal) e, no começo do ano seguinte, entrega o que
foi pago no ano anterior para o imposto de renda (informe). Usam a mesma base (contratos, faturas,
pagamentos, responsáveis financeiros) e a mesma equipe.

## O que existia

- O preço do novo contrato de rematrícula era **um valor único por campanha**
  (`PeriodoRematricula::valor_taxa`, rotulado "Valor total do novo contrato"). Não havia preço por
  série.
- `PlanilhaLeiMensalidade`: planilha de custos da Lei nº 9.870/1999 com o percentual sugerido e o
  adotado, e o status (rascunho, em análise, homologada, publicada).
- `Contrato` (valor anual), `Fatura`/`ItemFatura`, `TransacaoBancaria` (pagamentos recebidos) e
  `ResponsavelFinanceiro` (percentual de cada pagante).

## Reajuste de mensalidades (Onda 44)

### Dados
- `cenarios_reajuste`: campanha de rematrícula, planilha da lei (opcional), nome, percentual padrão,
  arredondamento (centavo, real, R$ 10), status (simulação, aprovado, aplicado), quem criou e aprovou,
  datas de aprovação, aplicação e comunicado.
- `cenario_reajuste_itens`: uma linha por série, com valor atual, percentual próprio (opcional),
  valor novo, alunos e bolsa média.
- `periodo_rematricula_precos`: preço do novo contrato por série na campanha. A efetivação da
  rematrícula (`RematriculaService::efetivar`) passa a usar `PeriodoRematricula::valorTotalParaSerie()`:
  o preço da série, se houver; senão, o valor único da campanha (comportamento anterior).

### Regras (`ReajusteMensalidadeService`)
- **Base:** valor atual da série = média do contrato mais recente de cada matrícula **ativa** do
  período letivo de **origem** da campanha (contratos com valor zero ficam de fora). Editável por
  série, para quando a média não representa o preço de tabela.
- **Cálculo:** valor novo = valor atual × (1 + percentual), em centavos, com o arredondamento
  escolhido. Percentual entre −50% e 100%.
- **Fluxo:** simulação (editável) → aprovado (congelado) → aplicado (preços gravados na campanha).
  Desfazer devolve a "aprovado" e apaga os preços.
- **Travas:** não aplica nem desfaz se alguma rematrícula da campanha já foi efetivada (as famílias
  teriam preços diferentes para a mesma série); só um cenário aplicado por campanha. Contratos que
  já existem nunca são alterados.
- **Projeção:** receita anual atual e nova (alunos × valor), diferença, variação e receita líquida
  estimada de bolsas. Não considera inadimplência, evasão nem alunos novos.
- **Pontos de atenção** (não bloqueiam): sem planilha vinculada; planilha não homologada; percentual
  acima do da planilha; divulgação a menos de **45 dias** do fim da campanha (Lei nº 9.870/1999,
  art. 2º, usando a data final da campanha como data final de matrícula); série sem aluno com
  contrato; rematrículas já efetivadas; séries do cenário sem **lista de material publicada** no período
  de destino da campanha (Onda 59).
- **Comunicação:** lista em CSV (responsável, e-mail, telefone, aluno, série, valor atual e novo,
  variação), com BOM, ponto e vírgula e proteção contra injeção de fórmulas (`CsvSanitizer`). A
  exportação fica no log. "Registrar comunicado" grava a data, que passa a valer no alerta de prazo.
  O envio em si é feito pelos canais da escola.
- **Log:** aprovar, aplicar, desfazer e comunicar registram quem fez.

### Telas e permissões
- **Reajuste de Mensalidades** (Financeiro): nova simulação, edição (percentual, arredondamento,
  planilha), aba **Séries** com "Ajustar", projeção e pontos de atenção, ações Aprovar, Aplicar na
  campanha, Desfazer, Registrar comunicado e Baixar lista.
- `ViewAny/View/Create/Update/Delete/Aprovar/Aplicar/Comunicar:CenarioReajuste`, todas para
  `super_admin` e `admin`.

### Diferenças em relação à proposta original
- O reajuste não altera contratos um a um (`ReajusteAplicado` não foi criado): ele define o **preço
  da série na campanha**, porque o novo valor só passa a existir no contrato da rematrícula.
- A projeção não usa inadimplência histórica.
- O comunicado não é disparado pelo sistema (lista + registro da data).

## Informe anual de mensalidades (Onda 42, parte do informe)

### Regras (`InformeAnualMensalidadesService`)
- **Regime de caixa:** conta o que **entrou** de 1º/1 a 31/12 (data da `TransacaoBancaria` de
  entrada ligada a uma fatura), não o vencimento. Pagamento parcial entra pelo valor recebido;
  saídas (estornos) e entradas sem fatura não entram.
- **Parte de instrução (estimativa):** cada pagamento é repartido entre os itens da fatura pela
  proporção do valor líquido (com desconto); itens cuja descrição contém uma palavra de
  `config('financeiro.informe_anual.palavras_nao_dedutiveis')` (material, uniforme, alimentação,
  lanche, refeição, transporte, passeio, excursão, apostila) ficam fora. Base: Lei nº 9.250/1995,
  art. 8º, II, b; limites e regras do ano devem ser conferidos com a contabilidade.
- **Responsáveis:** o valor de cada responsável financeiro segue o percentual cadastrado no
  contrato. CPF e CNPJ formatados (`App\Support\Formatacao`).
- **Acesso:** a família vê os alunos de que é responsável (pessoa acessível) **ou** em cujo contrato
  consta como responsável financeiro; a equipe com `GerarInformeAnual:Fatura` vê todos. A emissão
  pela equipe fica no log (ano e filtros, sem valores).

### Telas e PDFs
- **Informe Anual de Mensalidades** (Financeiro): ano, turma e busca por nome; totais e PDF por aluno;
  PDF em lote dos filtrados (até 300 alunos; acima disso, pede para filtrar).
- **Informe Anual** (Portal da Família, Meus Dados): ano e um cartão por aluno com o PDF.
- Rotas `informe-anual.aluno` (`/informe-anual/{ano}/matricula/{matricula}`) e `informe-anual.lote`;
  ano futuro ou anterior a 2000 dá 404.
- O PDF traz a escola e o CNPJ, aluno e CPF, responsáveis com participação, pagamentos mês a mês,
  total, parte estimada de instrução e o detalhe de cada pagamento, com a ressalva de que não
  substitui recibo nem nota fiscal.

### Permissão
`GerarInformeAnual:Fatura` para `super_admin`, `admin` e `secretaria`.

## Limitações e próximos passos
- NFS-e (continua na proposta da Onda 42).
- Envio do comunicado do reajuste por e-mail/push direto do cenário (hoje: lista CSV).
- Reajuste por curso ou por turno; preço diferente para alunos novos.
- Categoria explícita no item de fatura (dedutível ou não), em vez da classificação por palavra.
- Envio do informe às famílias por e-mail em lote.

## Testes
`tests/Feature/ReajusteMensalidadeTest.php` (base, cálculo, arredondamento, bolsas, fluxo, efeito na
rematrícula, travas, projeção, alertas, CSV e telas) e
`tests/Feature/InformeAnualMensalidadesTest.php` (ano-calendário, parte de instrução, responsáveis,
acesso, PDFs, log, limite do lote e telas).
