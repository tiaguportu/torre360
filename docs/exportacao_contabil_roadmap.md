# Exportação para a Contabilidade (Onda 73)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda da tela e na seção 90 do `MANUAL_USUARIO.md`. Tela:
> `/admin/financeiro/exportacao-contabil` (grupo Financeiro, **Contabilidade**). Permissões: `View:ExportacaoContabil` (ver
> a conferência e o histórico) e `Exportar:ExportacaoContabil` (gerar, baixar e mexer nos parâmetros), dadas à
> administração.

## Contexto

O financeiro da escola já tem fatura, baixa, conciliação bancária, contas a pagar, plano de contas, centro de custo e DRE
(ver [financeiro_gateway_conciliacao_relatorios.md](financeiro_gateway_conciliacao_relatorios.md)). Mas quem fecha o
mês é o **escritório de contabilidade**, que usa o plano de contas dele, e a escola entregava planilhas montadas à mão.

Esta onda gera o **arquivo de lançamentos contábeis** do período, em partidas dobradas, já no plano de contas do
escritório, e aponta o que ainda falta classificar antes de entregar.

## O que já existia

- `PlanoConta` (código, tipo receita/despesa/ativo/passivo, hierarquia) e `CentroCusto`.
- `TransacaoBancaria` (entrada/saída, conta do plano e centro de custo opcionais), `Fatura`, `ContaPagar` (com
  `transacao_bancaria_id` quando paga) e `Banco`.
- `BaixaFaturaService` cria a transação da baixa **sem conta do plano**: hoje todo recebimento de mensalidade fica sem
  classificação, e por isso existe a conta de receita padrão.

## O que foi feito

### Cadastro: o "de-para" do escritório

| Onde | Campo | Para quê |
|---|---|---|
| Plano de Contas | *Código no escritório de contabilidade* (`plano_contas.codigo_contabil`) | A conta correspondente no plano do escritório |
| Bancos | *Conta no escritório de contabilidade* (`bancos.conta_contabil`) | A conta do disponível que o escritório usa para o banco |
| Contabilidade → Parâmetros contábeis (`parametros_contabeis`, uma linha) | Conta de clientes, conta de fornecedores, conta de receita padrão e conta de despesa padrão | Contas dos regimes e do que ainda não foi classificado |

### Os dois regimes

| Regime | Lançamentos |
|---|---|
| **Caixa** | Um por movimento do banco. Entrada: débito no banco, crédito na conta de receita. Saída: débito na conta de despesa, crédito no banco. |
| **Competência** | Fatura com vencimento no período: débito em clientes, crédito na receita padrão (faturas canceladas ficam de fora). Conta a pagar com vencimento no período: débito na despesa, crédito em fornecedores (canceladas ficam de fora). O movimento do banco baixa o que já foi lançado: recebimento de fatura vai a crédito de clientes; pagamento de conta a pagar vai a débito de fornecedores; o que não tem fatura nem conta a pagar segue a regra do caixa. |

O mês da fatura e da conta a pagar é o do **vencimento** (a fatura não guarda outra data de competência). O regime é decisão
do escritório: o sistema só entrega o que ele pedir.

Movimento com valor zero não entra. Cada lançamento leva data, conta a débito, conta a crédito, valor, histórico (até 120
caracteres), documento (`FAT-id`, `CP-id`, o identificador do extrato ou `MOV-id`), centro de custo e a origem.

### Pendências e avisos

A conferência da tela (e o `apurar()` do serviço) lista o que **impede** o arquivo:

- banco sem conta contábil;
- conta do plano usada no movimento **sem** código do escritório (nunca cai na conta padrão em silêncio);
- movimento sem conta do plano e conta de receita ou despesa padrão não definida (ou sem código);
- regime de competência sem conta de clientes ou de fornecedores.

Enquanto houver pendência, **o arquivo não sai**: o escritório não recebe lançamento com conta em branco. O que não impede
mas merece olhar vira **aviso**, por exemplo conta de despesa usada em movimento de entrada. A tela também avisa quando já
existe arquivo do mesmo regime para um período que se sobrepõe.

### Arquivos

| Formato | Conteúdo |
|---|---|
| Lançamentos contábeis (CSV) | `Data; Conta débito; Conta crédito; Valor; Histórico; Documento; Centro de custo; Origem`, ponto e vírgula, UTF-8 com BOM, valor com vírgula |
| Balancete de conferência (CSV) | Por conta: total a débito, total a crédito, saldo devedor e saldo credor (sem sinal negativo) |

Um leiaute proprietário (Domínio, Alterdata, Fortes, Questor...) entra como mais um valor de `FormatoExportacaoContabil` com
o seu gerador em `ExportacaoContabilService::gerarArquivo()`.

### Histórico e segurança

Cada arquivo gerado vira um `LoteContabil` (período, regime, formato, quantidade, total, **SHA-256**, quem gerou) e fica em
área privada (`exportacoes_contabeis/`), para baixar de novo sem refazer a conta. O download é por rota própria, só para
quem tem `Exportar:ExportacaoContabil`; antes de entregar, o controlador confere o hash (arquivo alterado: 409; ausente:
404). Gerar e baixar entram no log de atividades. Texto vindo do extrato bancário passa pelo `CsvSanitizer` (um histórico
que começa com `=` não vira fórmula na planilha).

### Privacidade

O arquivo leva **só** valores, datas, contas e a referência do documento. Não leva nome de aluno nem de responsável: o
escritório reconcilia pelo número da fatura (LGPD, art. 6º, III, necessidade).

### Mesa de pendências

Fila **Classificação contábil pendente** (permissão `View:ExportacaoContabil`): as pendências do mês anterior no regime de
caixa. A partir do dia 10 (`mesa_pendencias.contabilidade_dia_limite`) a fila inteira passa a atrasada.

## Decisões e limites

- **Não é o SPED nem a ECD.** O arquivo é insumo para o escritório. A escrituração contábil e as obrigações acessórias
  continuam sendo dele.
- **Sem conta do plano na fatura.** Toda receita de fatura vai para a conta de receita padrão. Separar mensalidade,
  matrícula e material pede a conta do plano por item de fatura (proposta abaixo).
- **Competência pelo vencimento.** Não há data de competência própria na fatura nem na conta a pagar.
- **Não há trava de período.** Exportar não impede alterar o movimento depois; o histórico mostra o que foi entregue e a
  sobreposição é avisada, não proibida.

## Testes

`tests/Feature/ExportacaoContabilTest.php` (26): caixa e competência, pendências e avisos, CSV e balancete (fechando débito
com crédito), neutralização de fórmula, ausência de nome de aluno, período inválido ou vazio, sobreposição, download
(permissão, arquivo alterado e ausente), tela, parâmetros e a fila da Mesa.

## Próximos passos (apenas documentados)

- **Leiautes por sistema contábil** e importação do plano de contas do escritório (para não digitar o de-para).
- **Conta do plano por item de fatura** e por tipo de cobrança, e data de competência própria.
- **Trava de período** depois de entregue ao escritório (com reabertura registrada).
- **Provisões** (13º, férias) e **folha**, quando a escola quiser que o RH alimente a contabilidade.
