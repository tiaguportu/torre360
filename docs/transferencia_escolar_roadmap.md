# Transferência Escolar — Saída e Entrada (Onda 24)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda da tela (**Transferências Escolares**, grupo
> **Secretaria**, no admin).

## Contexto

A proposta inicial via de um gap simples: só existia a declaração impressa
(`TipoTemplateDocumento::DeclaracaoTransferencia`), sem nenhum processo administrativo de
saída (para outra escola) ou entrada (vinda de outra escola).

## Descoberta durante a implementação — escopo reduzido para não duplicar

Ao aprofundar para implementar, ficou claro que o sistema **já tinha muito mais pronto**
do que a declaração solta:

- `HistoricoEscolar`/`HistoricoEscolarAno`/`HistoricoEscolarDisciplina`
  (`HistoricoEscolarService`) já é um sistema completo de histórico escolar multi-ano, com
  PDF oficial (QR Code de autenticidade, assinatura de diretor/secretário). Cada ano pode
  ser `tipo: 'interno'` (sincronizado automaticamente das matrículas) ou `tipo: 'externo'`
  — o `HistoricoEscolarForm` já tem um `Repeater` com a opção "Externo (Outra Escola)"
  (`escola_nome`/`escola_cidade`/`escola_uf` manuais). **Importar o histórico de quem está
  chegando de outra escola já era possível antes desta onda** — só não estava "batizado"
  como fluxo de transferência.
- `DocumentoService::emitirDocumento(Matricula, TemplateDocumento, ...)` já gera qualquer
  declaração oficialmente (PDF com carimbo e QR Code) — reaproveitado, não reimplementado.
- `DocumentoService::validarEmissao()` já tinha a regra de negócio correta: bloqueia
  `DeclaracaoQuitacao` por débito, mas **não bloqueia `DeclaracaoTransferencia`** — alinhado
  com a legislação (a escola não pode reter documentação de transferência por
  inadimplência). Esta onda segue a mesma regra: pendências aparecem só como informação.
- `HistoricoEscolarService` já derivava `situacao_ano = 'Transferido'` a partir de
  `Matricula::situacao` em `cancelada`/`trancada`/`evasao`. Ou seja, a convenção já
  estabelecida no sistema é reaproveitar `SituacaoMatricula::CANCELADA` para uma saída por
  transferência — por isso esta onda **não criou um novo caso no enum** (o que exigiria
  revisar todo `match()` exaustivo sobre ele, risco desnecessário para o ganho).

Com isso, o escopo ficou bem mais enxuto do que a proposta original: uma camada fina de
**processo administrativo** sobre o que já existia.

## Como foi implementado

- `TransferenciaEscolar` (`app/Models/TransferenciaEscolar.php`): `matricula_id`, `tipo`
  (`TipoTransferencia`: Saída/Entrada), escola externa (nome/cidade/UF), `data`, `motivo`,
  `status` (`StatusTransferencia`: Em Andamento/Concluída/Cancelada), `historico_recebido`
  (relevante para Entrada), `solicitacao_documento_id` (liga à declaração emitida, Saída),
  `historico_escolar_ano_id` (liga ao ano externo já lançado, Entrada).
- `TransferenciaEscolar::concluirSaida()`: busca o `TemplateDocumento` ativo de
  `DeclaracaoTransferencia`, chama `DocumentoService::emitirDocumento()` e atualiza a
  matrícula para `CANCELADA`. Lança `\DomainException` se não houver template ativo
  cadastrado (situação que a secretaria resolve em Modelos de Documento, não um bloqueio
  de regra de negócio).
- `TransferenciaEscolar::marcarHistoricoRecebido()`: fecha o processo de Entrada depois que
  a secretaria lança o(s) ano(s) externo(s) na tela de Histórico Escolar já existente.
- Resource em `/admin/transferencia-escolars` (grupo **Secretaria**), ações "Concluir
  Saída" e "Marcar Histórico Recebido" na tabela, cada uma visível só para o tipo/status
  correspondente.
- Permissões: `secretaria`, `admin`, `super_admin` — mesmo grupo que já cuida do Histórico
  Escolar.

## Dependências já satisfeitas

- `HistoricoEscolar`/`HistoricoEscolarAno` (já existente) resolve a importação do
  histórico de quem chega — esta onda só adiciona o rastreio do processo em si.
- `DocumentoService` (já existente, Onda 5) resolve a emissão da declaração oficial.
- `Matricula::hasDebitosVencidos()`/`getDebitosVencidosCount()` (já existente, Onda 7) —
  disponíveis para exibição informativa de pendências, sem bloquear a emissão.
