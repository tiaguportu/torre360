# Gerar e regenerar faturas do contrato com IA (texto livre)

> **Status: implementada** em 2026-10-28. Manual: §7.1.1.1. Ajuda da tela: `help-edit.blade.php` do contrato.

## O pedido

Em `admin/contratos/{id}/edit`, além de "Gerar/Regenerar Faturas" (quantidade de parcelas + entrada), poder
**descrever o parcelamento em texto** e deixar o Gemini (mesma chave `GEMINI_API_KEY` do resto do sistema)
interpretar. Exemplo do pedido: *"Divida o valor do contrato em 2 parcelas pagas semestralmente no final da
primeira quinzena do primeiro mês de cada semestre"* → 2 faturas de metade do valor, vencendo em 15/01 e 15/07
do ano subsequente.

## Princípio de desenho

**A IA entende, o sistema calcula.** O modelo devolve só quantas faturas existem, quando vencem e (se o texto
disser) o valor ou o percentual de cada uma. Os valores em reais saem de `GeracaoFaturasContratoService::resolverPlano()`,
em centavos inteiros, e a soma é conferida contra `contratos.valor_total`. Regenerar apaga as faturas atuais,
então **nada é gravado antes de o operador ver a prévia** e confirmar.

## O que foi feito

### 1. `PlanoFaturasIaService::interpretar(Contrato, string $instrucao)`
- Monta a instrução de sistema com o contexto mínimo: hoje, valor total, data de aceite e período letivo da
  matrícula (nome e datas). **Não envia** nome de aluno, responsável, e-mail nem telefone.
- O texto do operador entra delimitado por `GeminiAgentService::delimitarEntradaSegura($t, 'instrucao_operador')`
  e o prompt o declara dado não confiável (mesma defesa contra prompt injection dos demais serviços de IA).
- Chama `GeminiAgentService::callGeminiApi()` (cascata de modelos, orçamento de tempo e sanitização da chave já
  existentes) com `responseMimeType: application/json`.
- Contrato do JSON devolvido pelo modelo:

| Campo | Significado |
|---|---|
| `entendeu` / `motivo` | `false` + motivo quando a instrução não é um parcelamento ou faltam datas |
| `resumo` | uma frase com o que foi entendido (aparece na prévia) |
| `avisos` | suposições feitas diante de ambiguidade, ex.: "considerei *ano subsequente* como 2027" (até 5) |
| `parcelas[]` | `descricao`, `vencimento` (AAAA-MM-DD), `valor` (reais) **ou** `percentual` (do contrato), ambos nulos = parte igual do saldo |

- Textos da IA (`resumo`, `avisos`, `motivo`) passam por `strip_tags` + limite de tamanho antes de ir à tela.
- Falha de rede/API vira `RuntimeException` com mensagem genérica ("Tente novamente em instantes", sem detalhe
  técnico nem chave; a exceção original é só reportada). Sem `GEMINI_API_KEY`, avisa sem chamar a API.

### 2. `GeracaoFaturasContratoService` (refatorado, comportamento do `gerar()` preservado)
- `resolverPlano(Contrato, array $parcelas, ?Carbon $referencia)` — **função pura**, sem gravar:
  - parcela com `valor` ou `percentual` fica com esse valor (se vierem os dois, vale `valor`);
  - parcelas sem nenhum dos dois dividem **igualmente** o saldo; o resto de centavos vai para a última;
  - sem parcelas livres, a soma precisa fechar com o contrato (tolerância de 1 centavo por parcela, para
    percentuais redondos como 33,33% × 3, absorvida pela última); senão `InvalidArgumentException` com os dois valores;
  - recusa data inexistente (30/02), data fora de [hoje − 2 anos, hoje + 6 anos], menos de 1 ou mais de
    `MAX_PARCELAS_PLANO` (60) parcelas, valor/percentual inválido, saldo que não chega a 1 centavo por parcela livre;
  - descrição sem HTML, espaços colapsados, máximo de 120 caracteres; resultado ordenado por vencimento.
- `gerarPlano(Contrato, array $plano)` — **ponto único de gravação**: checa faturas protegidas
  (`scopeProtegida`), aplica o percentual da bolsa ativa em cada `ItemFatura` e substitui as faturas numa
  transação. `gerar()` (entrada + parcelas mensais, usado também pela Rematrícula Online e pelo comando de
  auditoria) agora monta o plano e chama `gerarPlano()`, então as duas rotas compartilham as mesmas salvaguardas.
- `percentualBolsaAtiva()` passou de `private` a `public` (a prévia mostra a bolsa).

### 3. Ações do Filament (`GerarFaturasIaAction`, registrada em `EditContrato`)
- **`gerarFaturasIa`** (cabeçalho, ícone sparkles, cor `purple`): formulário com um `Textarea` (até 1000
  caracteres). Verifica o limite por minuto (`CrmIaVendasService::verificarIncrementarRateLimit()`, chave
  `crm_gemini_user_{id}`, compartilhada com o CRM), chama o serviço e, se der certo, abre a segunda ação com
  `replaceMountedAction`. Se a IA não entende ou falha, notifica e dá `Halt`: **o modal continua aberto com o
  texto** para o operador ajustar.
- **`confirmarFaturasIa`** (método `confirmarFaturasIaAction()` na página, não é botão do cabeçalho): mostra
  a prévia (`resources/views/filament/resources/contratos/previa-faturas-ia.blade.php`) com instrução, resumo,
  suposições, tabela de faturas, total × valor do contrato, bolsa aplicada, faturas que serão substituídas,
  faturas protegidas (impedem a regeneração) e reset do Assinafy. Só ao confirmar grava.
- Na confirmação o plano que veio pelos argumentos da ação (que trafegam pelo navegador) é **recalculado e
  revalidado** por `resolverPlano()`; só `descricao`, `vencimento` e `valor` passam, e a soma precisa fechar com
  o contrato. Plano adulterado é recusado.
- Autorização: `PermissaoAcao::qualquer('Update:Contrato')` nas duas ações (ações customizadas do Filament
  não herdam a policy).
- Pós-gravação, igual à geração manual: `activity('contrato')` com `tipo = geracao_faturas_ia`, a instrução
  e o plano gravado; se o contrato já foi ao Assinafy, `resetAssinafyState()` + aviso.

## Decisões

- **Prévia obrigatória** (duas ações encadeadas em vez de uma): regenerar apaga faturas e a leitura de
  "ano subsequente" ou "semestre" pode divergir da intenção. A prévia mostra as suposições da IA.
- **Soma que não fecha bloqueia** em vez de gerar com aviso: a divergência contrato × faturas é o defeito
  que `EditContrato::afterSave` já alerta; criá-la de propósito por erro do modelo seria pior. O operador
  reescreve a instrução (ex.: citando a parcela que recebe o restante).
- **Valores nunca vêm da IA como número final**: só `valor`/`percentual` que o operador escreveu. A IA não
  faz contas.
- **Ação separada da "Gerar Faturas Automaticamente"** (que segue igual) em vez de um modo dentro do mesmo
  modal: o fluxo de duas etapas e a prévia não cabem no formulário de campos fixos.
- **Teto de 60 faturas e janela de datas** como rede de segurança contra resposta fora de controle.

## Pontos de atenção

- **Não há persistência do plano entre prévia e confirmação** (ele viaja nos argumentos da ação). Se uma
  fatura passar a ter pagamento nesse intervalo, `gerarPlano()` recusa na confirmação.
- A prévia **avisa** de fatura protegida, mas o botão de confirmar continua ativo; o bloqueio efetivo é do serviço
  (notificação de erro ao clicar).
- O caminho antigo (`gerar()`) continua dividindo com `round(saldo / parcelas, 2)` sem ajustar o resto de
  centavos (as parcelas podem somar alguns centavos a menos que o contrato). O caminho por IA fecha o total
  exatamente. Unificar exigiria mudar valores de faturas já esperados por testes e relatórios; ficou fora do escopo.
- O limite por minuto é o mesmo do CRM (`services.gemini.limite_por_minuto`, padrão 10 por usuário).
- Verificado por testes automatizados; a tela não foi aberta em navegador porque o `.env` local aponta para o
  banco de produção.

## Testes

`tests/Feature/GerarFaturasContratoIaTest.php` (26 testes, Gemini simulado com `Http::fake`):
`resolverPlano` (divisão igual, resto de centavos, percentual + fixo + livres, ordenação, soma que não fecha,
saldo esgotado, data inexistente, data absurda, teto, HTML na descrição), `gerarPlano` (substituição, fatura
paga intocada), o serviço de IA (exemplo do pedido, contexto do prompt sem dados do aluno, tag de dados não
fechável pela instrução, conta errada da IA, "não entendi", resposta não-JSON, sem chave, falha de rede sem
vazar a chave) e as ações (prévia antes de gravar, cancelar não altera, falha mantém o modal, plano
adulterado recusado, reset do Assinafy) mais a renderização da prévia (HTML escapado).
Regressão: `GeracaoFaturasContratoServiceTest`, `GeracaoFaturasComBolsaTest`, `EditContratoGerarFaturasTest`,
`ContratoGerarFaturasAntesDoAceiteTest`, `FaturaImportExportRoundTripTest` e os testes de Rematrícula seguem verdes.
