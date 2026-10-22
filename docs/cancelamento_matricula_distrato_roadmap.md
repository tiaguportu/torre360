# Cancelamento de Matrícula e Distrato (Onda 61)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 74 do
> `MANUAL_USUARIO.md`. Tela da secretaria: `/admin/cancelamentos-matricula` (grupo Secretaria). Portal da
> Família: **Cancelar Matrícula**, em Meus Dados.

## Contexto

Cancelar uma matrícula no meio do ano é uma das situações que mais geram disputa entre escola e família:
quem deve o quê, o que acontece com as parcelas futuras, se há multa, quando o aluno sai, e o que fazer com o
que a família pagou adiantado. Antes, o cancelamento era um clique em `situacao = cancelada`, sem pedido por
escrito, sem conta e sem papel assinado. As faturas seguiam abertas, e a família continuava recebendo cobrança
de parcelas de um aluno que já tinha saído.

Base legal:

- **Lei nº 9.870/1999, art. 6º, § 2º**: o estabelecimento deve expedir, a qualquer tempo, os documentos de
  transferência do aluno, **independentemente de adimplência**. Por isso a Declaração de Transferência sai
  mesmo com débito.
- **CDC, art. 51, IV**, e **Código Civil, art. 413**: multa por rescisão abusiva ou desproporcional pode ser
  anulada ou reduzida. O sistema não calcula multa: só a registra se a secretaria a informar.

## O que já existia

- `Matricula` (situação `CANCELADA` e `data_desativacao`), `Fatura`/`Contrato`, `GatewayPagamento::cancelar()`
  (que ninguém chamava) e a `TransferenciaEscolar` (saída com declaração).
- `RematriculaService::aplicarCancelamento`, que cancelava a matrícula e as faturas da **rematrícula** (outro
  fluxo), serviu de modelo.
- O Assinafy só atende o `Contrato`; o distrato não passa por ele.

## O que foi feito

### Fluxo
1. **Pedido.** A família pede pelo Portal (responsável pelo aluno, responsável financeiro do contrato ou o
   próprio aluno **maior de idade**; o menor não vê o botão), ou a secretaria registra (pedido por telefone,
   presencial, retroativo). Um pedido em andamento por matrícula.
2. **Análise.** A secretaria vê a **situação financeira** (com multa e juros até hoje, Onda 62) e **aprova**
   decidindo o destino de cada fatura. Ou recusa, com o motivo (a família lê).
3. **Distrato.** PDF do termo (partes, objeto, faturas e destino de cada uma, valor devido, valor a devolver,
   multa se houver, quitação e a nota da Lei nº 9.870, art. 6º, § 2º). Assinado, a cópia (PDF ou foto) é
   anexada.
4. **Conclusão**, a partir da data de saída, com o distrato assinado anexado.
5. **Devolução.** Se há dinheiro a devolver, a secretaria registra a data em que devolveu.

A família pode **retirar** o pedido enquanto ele aguarda a secretaria. Depois de aprovado, só a secretaria
encerra o pedido.

### A proposta para cada fatura
- **Manter:** a entrada e tudo o que vence **até o último dia do mês da saída**. O mês da saída é cobrado
  inteiro, sem proporcional.
- **Cancelar:** o que vence depois e **não tem pagamento**.
- **Devolver:** o que vence depois e **já tem pagamento** (inclusive parcial): a fatura é cancelada e o pago
  vai para o valor a devolver.
- A secretaria troca qualquer decisão. O sistema só aceita o que cabe: não dá para cancelar fatura paga nem
  devolver fatura sem pagamento.

### O que a conclusão faz
- Matrícula `CANCELADA`, `data_desativacao` na data de saída (a fila de espera é avisada pelo observador que já
  existia).
- Faturas marcadas como cancelar ou devolver viram `Cancelado`, e a **cobrança sai do gateway** (se falhar, fica
  no log e o cancelamento segue). Fatura marcada para cancelar que **recebeu pagamento depois da aprovação** não
  é cancelada: vira aviso para a secretaria.
- **Multa rescisória** (se informada): uma fatura própria, "Multa rescisória — cancelamento de matrícula", com
  vencimento em 10 dias (`config/cancelamento.php`).
- **Rematrícula do próximo ano** que ainda não virou turma nem contrato é cancelada junto. Se já foi efetivada, o
  sistema só **avisa**: o contrato dela é outro e a decisão é da família.
- **Declaração de Transferência** (opcional): abre o processo de saída em Transferências Escolares e emite a
  declaração. Sem modelo de declaração ativo, o processo fica em andamento para concluir depois, e o
  cancelamento conclui mesmo assim.

### Dados e privacidade
- `cancelamentos_matricula`: origem, motivo, `motivo_detalhe` (**criptografado**), data de saída, situação,
  decisões por fatura (JSON), valores do distrato, transferência, quem e quando em cada etapa.
- O texto livre do motivo **não vai para o log** nem para o aviso da equipe (que leva só o nome do aluno e a
  data). A cópia assinada fica em `cancelamentos_matricula/{id}/` no disco privado, com o tipo detectado pelo
  conteúdo (PDF ou imagem; HTML e SVG são recusados) e abre para a equipe com permissão e para a família do
  aluno.
- Permissões `ViewAny/View/Create/Aprovar/Concluir:CancelamentoMatricula`: secretaria e administração decidem; a
  coordenação consulta. Sem edição nem exclusão: o histórico fica. A política também confere o estado do pedido,
  porque o super admin passa por qualquer permissão.

### Configuração (`config/cancelamento.php`)
`exige_distrato_assinado` (padrão sim), `antecedencia_minima_dias` (padrão 0; vale só para o pedido da família) e
`multa_vencimento_dias` (padrão 10).

## Decisões e limites

- **O distrato é um modelo.** A escola deve revisar o texto com o jurídico antes de usar.
- **Devolver o dinheiro é manual:** o sistema controla a data e o valor; a saída se lança em Movimentação
  Bancária. A fatura devolvida fica `Cancelado` e o recebimento original continua no extrato.
- Sem proporcional do mês da saída, sem cálculo automático de multa e sem aviso à família por e-mail ou WhatsApp
  (ela acompanha pelo Portal). O Portal não mostra o sino de avisos.
- A assinatura é no papel (ou foto). O Assinafy não assina o distrato.

## Testes

`tests/Feature/CancelamentoMatriculaTest.php` (50 testes: pedido e regras, proposta por fatura, aprovação,
recusa e retirada, cópia assinada, conclusão e seus efeitos, gateway, rematrícula, transferência com e sem modelo,
devolução, telas da secretaria, PDF e Portal com as autorizações).
