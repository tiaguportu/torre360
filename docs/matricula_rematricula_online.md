# Matrícula e Rematrícula Online

Onda 7 do roadmap de funcionalidades.

## 0. Reescopo desta onda

O plano original tinha 3 itens: (1) rematrícula administrativa, (2) rematrícula pelo
Portal com confirmação de dados + upload de documentos + assinatura via Assinafy +
geração de faturas, (3) matrícula nova online por convite. Os itens 1 e a maior parte do
2 **já existiam**, implementados pela "Secretaria Digital"
(`PeriodoRematricula`/`Rematricula`, Portal `Rematricula`): a família já conseguia
confirmar os dados e isso já criava a nova matrícula e um contrato — mas o contrato
nunca era assinado (o status `AguardandoAssinatura` existia no enum e nunca era usado) e
nenhuma fatura era gerada. Esta onda cobre:

1. Completar o fluxo existente: assinatura do contrato via Assinafy e geração
   automática das faturas.
2. Matrícula nova online por convite (não existia nada disso).

**Não construída nesta onda:** upload de documentos pela família pelo Portal. É um
padrão que não existe em nenhum lugar do sistema hoje — mesmo a matrícula original
(`EnrollmentWizard`) tem o envio de documentos como uma etapa feita pela secretaria
(`DocumentosMatricula`, dentro do admin), não um self-service da família. Introduzir o
primeiro upload self-service do sistema é uma mudança de padrão maior do que completar
um fluxo já existente, então ficou fora do escopo — a família ainda entrega os
documentos à secretaria pelos canais já existentes (Central de Atendimento, presencial).

## 0. Rematrícula com turma obrigatória (secretaria escolhe a turma)

Toda matrícula nasce numa turma (`matricula.turma_id` passa a ser obrigatório na fase
seguinte). Por isso o fluxo da rematrícula foi dividido em duas etapas:

1. **Família (Portal):** a ação "Realizar Rematrícula" só **registra a intenção** —
   `serie_destino_id`, `turno_pretendido_id`, `observacoes` — e deixa a rematrícula em
   `DadosConfirmados`. Não cria matrícula, contrato, faturas nem chama o Assinafy.
2. **Secretaria (admin):** `RematriculaService::efetivar(Rematricula, ?int $turmaId)`.
   A turma é **obrigatória** (argumento ou `turma_destino_id`); sem ela lança
   `DomainException`. O antigo "tenta achar uma turma pela série+turno" e o "cria
   matrícula sem turma" foram removidos.

Validações em `efetivar()` (dentro da transação, na ordem de locks Rematricula → Turma):
- turma no `periodo_letivo_destino_id` da campanha (`TurmaIndisponivelException::periodoDiferente`);
- turma da série pretendida, quando a família informou (`serieDiferente`);
- turma **aberta para matrícula** — status Planejada ou Ativa (`fechada`);
- **vaga disponível**, com `lockForUpdate` na turma (`lotada`).

A turma escolhida passa a ser a fonte da verdade: `turma_destino_id`, `serie_destino_id` e
(se vazio) `turno_pretendido_id` da rematrícula são gravados a partir dela, e a nova
matrícula recebe `turma_id`, `serie_id` e `periodo_letivo_id` coerentes.

### Regra única de vagas — `App\Services\TurmaVagasService`
- Ocupa vaga a matrícula **Ativa, Pendente ou Reserva** sem `data_desativacao` vencida
  (mesma regra do Termômetro de Vagas). `vagas_maximas` nulo/0 = turma sem limite.
- `garantirVaga($turma, $quantidade = 1)` exige estar **dentro de `DB::transaction()`**
  (`LogicException` caso contrário), trava a linha da turma e lança
  `TurmaIndisponivelException` (extends `DomainException`; `turmaSemVaga()` indica que
  lotes devem parar). No SQLite dos testes o lock não tem efeito.
- Usado por: `RematriculaService`, `MatriculaOnlineService`, `EnrollmentWizard::save()` e
  `EnsalamentoService::alocarAlunosEmTurma()` (este continua lançando
  `InvalidArgumentException`, com a mensagem da regra nova; mover alguém para a turma onde
  já está não conta vaga em dobro).
- `RematriculaService::opcoesDeTurma()`/`turmaSugerida()` alimentam o Select de turma da
  ação **Efetivar** (individual e em lote, lotadas desabilitadas) e o formulário de edição.

### Outras mudanças
- Campanha (`PeriodoRematriculaForm`): destino ≠ origem; ativar exige ao menos uma turma
  Planejada/Ativa no período de destino.
- Migration `add_unique_index_to_rematriculas_table`: índice único
  `(periodo_rematricula_id, matricula_origem_id)`. Se já houver duplicatas a migration
  **não derruba o deploy**: grava um aviso no log (`Log::warning`) e deixa o índice para
  depois de limpar as linhas repetidas. Com o índice, o `firstOrCreate` de
  `iniciarOuObter()` passa a ser seguro contra cliques duplos.
- Ação **Efetivar** do admin passou a exigir a permissão `Update:Rematricula`.
- Testes: `RematriculaTurmaObrigatoriaTest` (regras da turma, vagas, ações da secretaria,
  campanha, índice único) e `RematriculaAssinaturaTest` (Portal só registra a intenção).

## 1. Rematrícula: Assinatura e Cobrança Automáticas

### Antes desta onda
`RematriculaService::efetivar()` criava a nova `Matricula` e um `Contrato` (quando a
campanha tinha um `template_contrato_id`), e já marcava `Rematricula.status` como
`Confirmada` imediatamente — sem passar pela assinatura nem gerar nenhuma fatura.

### Depois desta onda
1. **Faturas automáticas:** ao criar o contrato, `GeracaoFaturasContratoService::gerar()`
   já gera a entrada (se configurada) e as parcelas, usando os novos campos da campanha
   (`PeriodoRematricula.quantidade_parcelas_padrao`, `valor_entrada_padrao`). Esse
   serviço foi extraído da ação manual "Gerar Faturas Automaticamente" de
   `Contratos > Editar` (que continua existindo e funcionando do mesmo jeito, agora só
   chamando o serviço em vez de ter a lógica duplicada). Os vencimentos partem do **dia
   da rematrícula** (data-base passada a `gerar()`): o contrato nasce sem `data_aceite`,
   que só é gravada quando ele é assinado, então a assinatura não desalinha as faturas
   já geradas.
2. **Envio automático para assinatura:** logo depois, `AssinafyService::enviarContrato()`
   é chamado automaticamente. Se o envio for bem-sucedido, `Rematricula.status` vira
   `AguardandoAssinatura` (não `Confirmada`). Se falhar (ex.: Assinafy não configurado,
   ou alguma falha de API), a rematrícula fica em `DadosConfirmados` — a secretaria
   resolve manualmente pela tela de Contrato, sem travar o processo da família.
   **Idempotência:** `efetivar()` roda numa transação com lock da linha da `Rematricula`
   e, se `nova_matricula_id` já existe, devolve a matrícula criada sem gerar matrícula,
   contrato, faturas nem novo documento no Assinafy. Falhas no meio (ex.: entrada maior
   que o valor do contrato) desfazem tudo, então uma nova tentativa não duplica nada. O
   envio ao Assinafy acontece depois da transação, para não segurar o lock durante as
   chamadas HTTP. As ações "Realizar Rematrícula" (Portal) e "Efetivar Rematrícula"
   (admin) só aparecem enquanto não há nova matrícula.
   **Aviso à família:** o Portal informa o estado real após efetivar — "Rematrícula
   Confirmada!" só quando não há contrato a assinar; com o contrato enviado, "Falta
   assinar o contrato"; se o envio falhou, "Dados registrados — contrato ainda não
   enviado". As duas últimas ficam fixas na tela e levam a Documentos e Contratos.
3. **Confirmação pelo webhook:** `AssinafyService::handleWebhook()` agora também verifica
   se o contrato assinado pertence a uma `Rematricula` (`Contrato::rematricula()`, nova
   relação) — se sim, e o evento é de assinatura concluída, marca
   `Rematricula.status = Confirmada` e grava `data_confirmacao`. É esse evento, não mais
   a confirmação de dados da família, que efetivamente fecha o processo.

## 2. Matrícula Nova Online por Convite

Um link único e temporário (`App\Services\ConviteMatriculaService`) enviado a um lead já
qualificado pelo CRM, para que a própria família confirme/complete os dados — sem expor
nem permitir navegar por nenhum outro registro do sistema.

- **Gerar o convite:** botão **"Gerar Link de Convite"** na listagem de Interessados
  (só aparece para leads com ao menos um dependente cadastrado). Gera um token aleatório
  de 48 caracteres, válido por 7 dias e de uso único.
- **A página do convite** (`/quero-matricular/convite/{token}`): formulário de **pré-matrícula**
  restrito ao próprio interessado. Coleta responsável (nome, CPF, nascimento, contato, vínculo,
  financeiro), endereço (CEP com ViaCEP no navegador; a cidade é resolvida no servidor por
  `cidade.codigo_ibge`), segundo responsável opcional (com divisão de percentual), e, por aluno,
  nascimento, CPF opcional, sexo, série e turno. Exige aceite LGPD. A validação
  (`confirmarConvite()`, regra `App\Rules\Cpf` para CPF) rejeita qualquer `id` de dependente que
  não pertença ao interessado do token — é isso que impede o link de um lead de alterar o cadastro
  de outro.
- **Ao confirmar:** atualiza telefone/e-mail do responsável e série/nascimento de cada dependente,
  grava o payload completo em `interessado.dados_pre_matricula` (JSON, montado por
  `ConviteMatriculaService::montarPreMatricula()`, com `lgpd_aceite_em` e `lgpd_ip`), marca o token
  como usado (`token_convite_usado_em`), registra um `HistoricoContato` ("Confirmação via Convite
  Online") e recalcula o lead score (`LeadScoreService`).
- **O convite não efetiva a matrícula sozinho.** A secretaria usa a ação **"Matricular"**, que abre o
  `EnrollmentWizard` pré-preenchido via `InteressadoMatriculaService::dadosParaWizard()`: essa função
  mescla `dados_pre_matricula` (responsáveis com endereço e percentual, alunos com CPF/nascimento/
  sexo/endereço; `pessoa_id_existente` resolvido por CPF). Em `registrarConversao()` o campo é
  zerado (minimização de dados, LGPD). Fora do escopo: upload de documentos e endereço por aluno.
- **Link expirado/usado/inexistente:** mostra uma página de aviso (`convite-invalido`)
  em vez de erro técnico, com um atalho para o formulário público completo.

## 3. Migrations desta onda

| Migration | O que faz |
|---|---|
| `add_token_convite_to_interessado_table` | Colunas `token_convite` (único), `token_convite_expira_em`, `token_convite_usado_em`. |
| `add_dados_pre_matricula_to_interessado_table` | Coluna JSON `dados_pre_matricula` (rascunho da pré-matrícula preenchida pela família). |
| `add_parcelamento_padrao_to_periodo_rematriculas_table` | Colunas `quantidade_parcelas_padrao` (padrão 12), `valor_entrada_padrao` (padrão 0). |

## 4. Testes

`GeracaoFaturasContratoServiceTest` (inclui a data-base explícita), `EditContratoGerarFaturasTest`
(regressão da ação manual após a extração), `RematriculaAssinaturaTest`, `ConviteMatriculaTest`
(serviço), `ConviteMatriculaHttpTest` (rotas públicas + ação admin). `RematriculaTest` (já
existente) foi atualizado para refletir que a rematrícula não fica mais `Confirmada` até a
assinatura.

`RematriculaAssinaturaTest` cobre: envio automático, confirmação via webhook, `efetivar()`
idempotente (chamadas repetidas não duplicam matrícula/contrato/faturas/envio), rollback
quando a geração de faturas falha, vencimentos estáveis após a assinatura e, no Portal, a
visibilidade da ação "Realizar Rematrícula" e as três mensagens de resultado.

**Atenção ao rodar testes que passam por `efetivar()`:** o `.env` local pode trazer
credenciais reais do Assinafy (inclusive de produção), e o `phpunit.xml` não as zera. Todo
teste que efetiva uma rematrícula com template de contrato deve bloquear HTTP real
(`Http::preventStrayRequests()`) e esvaziar `services.assinafy.key` no `setUp`, como fazem
`RematriculaTest` e `RematriculaAssinaturaTest`; senão `enviarContrato()` chama a API de verdade.

## 5. Limitações conhecidas

- O lock de linha de `efetivar()` (`lockForUpdate`) não é exercitado pela suíte: ela roda em
  SQLite em memória, onde o lock não tem efeito. Os testes cobrem a idempotência sequencial e o rollback.
- `AguardandoAssinatura` só é gravado no primeiro envio, dentro de `efetivar()`. Se esse envio
  falhar e o contrato for enviado depois (tela de Contratos ou Documentos e Contratos), a
  rematrícula continua em `DadosConfirmados` até a assinatura, quando vai para `Confirmada`.
- O Portal grava `data_confirmacao` já na confirmação dos dados pela família, então a coluna
  aparece preenchida enquanto a rematrícula ainda aguarda a assinatura.
