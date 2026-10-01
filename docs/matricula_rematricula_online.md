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
   chamando o serviço em vez de ter a lógica duplicada).
2. **Envio automático para assinatura:** logo depois, `AssinafyService::enviarContrato()`
   é chamado automaticamente. Se o envio for bem-sucedido, `Rematricula.status` vira
   `AguardandoAssinatura` (não `Confirmada`). Se falhar (ex.: Assinafy não configurado,
   ou alguma falha de API), a rematrícula fica em `DadosConfirmados` — a secretaria
   resolve manualmente pela tela de Contrato, sem travar o processo da família.
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
- **A página do convite** (`/quero-matricular/convite/{token}`): mostra só os dados do
  próprio interessado — nome, e cada dependente já cadastrado com os campos **Série de
  Interesse** e **Turno de Preferência**. A validação do formulário (`confirmarConvite()`)
  rejeita explicitamente qualquer `id` de dependente que não pertença ao interessado do
  token — é isso que impede o link de um lead de alterar o cadastro de outro.
- **Ao confirmar:** atualiza telefone/e-mail do responsável e a série de cada dependente,
  marca o token como usado (`token_convite_usado_em`), registra um
  `HistoricoContato` ("Confirmação via Convite Online") e recalcula o lead score
  (`LeadScoreService`) — o preenchimento é, em si, um sinal de engajamento.
- **O convite não efetiva a matrícula sozinho.** A secretaria continua usando a ação
  **"Matricular"** já existente (abre o `EnrollmentWizard` pré-preenchido via
  `InteressadoMatriculaService::dadosParaWizard()`) — o convite só resolve a coleta de
  dados, reduzindo a ida e volta por telefone antes desse passo.
- **Link expirado/usado/inexistente:** mostra uma página de aviso (`convite-invalido`)
  em vez de erro técnico, com um atalho para o formulário público completo.

## 3. Migrations desta onda

| Migration | O que faz |
|---|---|
| `add_token_convite_to_interessado_table` | Colunas `token_convite` (único), `token_convite_expira_em`, `token_convite_usado_em`. |
| `add_parcelamento_padrao_to_periodo_rematriculas_table` | Colunas `quantidade_parcelas_padrao` (padrão 12), `valor_entrada_padrao` (padrão 0). |

## 4. Testes

`GeracaoFaturasContratoServiceTest`, `EditContratoGerarFaturasTest` (regressão da ação
manual após a extração), `RematriculaAssinaturaTest` (envio automático + confirmação via
webhook + idempotência), `ConviteMatriculaTest` (serviço), `ConviteMatriculaHttpTest`
(rotas públicas + ação admin). `RematriculaTest` (já existente) foi atualizado para
refletir que a rematrícula não fica mais `Confirmada` até a assinatura.
