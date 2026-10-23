# Assinatura Eletrônica de Documentos e Editor de Modelos (Onda 69)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 80 do
> `MANUAL_USUARIO.md`. Tela: `/admin/secretaria/documentos-assinatura` (grupo Secretaria, **Documentos para
> Assinatura**). Editor: **Modelos de Documentos**, no mesmo grupo.

## Contexto

O Assinafy só atendia o **contrato de matrícula** (`AssinafyService`, ver
[assinafy_assinatura_contratos.md](assinafy_assinatura_contratos.md)). Todo o resto que a escola precisa
colher assinado seguia no papel: o distrato de matrícula (Onda 61), o contrato de trabalho e o termo de
confidencialidade da admissão (Onda 65), declarações de ciência e autorizações. Papel significa imprimir, colher,
digitalizar, anexar e, com frequência, perder.

Esta onda leva **qualquer documento do sistema** para a assinatura eletrônica: o texto vem de um modelo, é
preenchido com os dados de um aluno, de um cancelamento ou de um funcionário, vira PDF, vai ao Assinafy com os
assinantes e volta com o certificado.

### Pré-requisito: editar os modelos
Um documento só pode ir para assinatura se o texto dele for editável pela escola. O editor de **Modelos de
Documentos** já existia, mas só para as declarações do aluno. Foi ampliado antes do envio:

- **Tipos e contextos.** Cada tipo de modelo tem o seu contexto de dados: *Aluno (matrícula)* para as declarações
  que já existiam, *Cancelamento de matrícula* para o **Distrato de Matrícula** e *Funcionário* para o
  **Contrato de Trabalho** e o **Termo de Confidencialidade e Proteção de Dados**.
- **Catálogo único de variáveis** (`MacrosDocumentoService`). O guia do editor, a validação e a prévia leem da mesma
  lista, e um teste confere que ela não se afasta do que o `DocumentoService` já preenchia nas declarações.
- **Recusa de variável inexistente.** Escrever `{{ALUNO_NOMEE}}`, `{{ CARGO }}` (com espaço) ou uma variável de outro
  contexto faz o editor recusar ao salvar, listando as erradas: elas sairiam impressas do jeito que foram escritas
  num documento que alguém vai assinar.
- **Guia dinâmico.** Ao trocar o tipo, o guia de variáveis muda.
- **Prévia** com os dados de um registro de verdade, usando o texto que está na tela (mesmo sem salvar).
- **Modelos padrão** (botão *Criar modelos padrão*): distrato, contrato de trabalho e termo de confidencialidade,
  como **rascunhos inativos**. O jurídico e o contador revisam e só então se ativa.
- **Histórico** (`log_name = modelos`): quem mexeu e quais campos; o texto não vai para o log.
- A emissão de declarações (secretaria e Portal) lista **só os modelos de aluno**: distrato e documentos de
  funcionário não saem por ali.

## O que já existia

- `AssinafyService` (contratos), webhook `/api/webhooks/assinafy`, chave `ASSINAFY_API_KEY`/`ASSINAFY_ACCOUNT_ID`.
- Editor de modelos com `TinyEditor` e guia de variáveis (só aluno).
- `HtmlSanitizer`, dompdf, disco privado e `VisualizarDocumentoController`.
- `CancelamentoMatricula` (distrato em papel) e `ProcessoFuncionario` (providências sem documento assinável).

O fluxo do contrato **não foi tocado**: o envio avulso é uma classe separada, e o webhook tenta primeiro o contrato e,
se o documento não for dele, o documento avulso.

## O que foi feito

### Fluxo
1. **Criar.** Escolhe-se o modelo (ativo), o registro (aluno, cancelamento ou funcionário) e os assinantes. O
   sistema sugere a lista: responsáveis financeiros (sem eles, os responsáveis do aluno) e o representante legal
   da unidade; ou o funcionário e o representante. Quem cria confere e ajusta (1 a 10 pessoas, e-mail válido e
   sem repetição). O texto é preenchido, o bloco `{{BLOCO_ASSINATURAS}}` lista os assinantes e o PDF é gerado e
   guardado com o **SHA-256**. Nada vai ao Assinafy ainda.
2. **Conferir e enviar.** Na página do documento, a secretaria lê o texto e clica em *Enviar para assinatura*. O
   sistema sobe o PDF, cadastra ou reaproveita cada assinante, espera o processamento e pede as assinaturas. Cada
   assinante recebe o e-mail do Assinafy; o endereço de assinatura de cada um fica guardado (criptografado) e
   aparece em *Links de assinatura* para quem enviou, caso o e-mail não chegue.
3. **Acompanhar.** A situação volta por três caminhos: webhook, botão *Atualizar situação* e a rotina
   `documentos:reconciliar-assinaturas` (de hora em hora). O envelope do webhook **não é assinado** pelo Assinafy,
   então ele só dispara uma **consulta à API**; a situação só muda pelo que a API responde.
4. **Concluir.** Com todos assinados, o sistema baixa o **PDF certificado** (a rotina repete se ele ainda não
   estiver pronto) e só então avisa o registro ligado ao documento. Quem criou recebe um aviso no sino.

### Situações
`Rascunho` → `Enviado` (aguardando assinaturas) → `Assinado`. Saídas: `Recusado` (alguém recusou), `Cancelado`,
`Expirado` (passou do prazo) e `Erro` (o Assinafy recusou o envio ou ficou sem conexão; dá para enviar de novo e **continuar de onde
parou**, sem subir o PDF nem cadastrar os assinantes duas vezes). Um documento assinado nunca volta a uma etapa
anterior; um documento cancelado aqui e assinado lá continua cancelado, com um alerta na página.

### O que cada documento faz ao ser assinado
- **Distrato de matrícula** (`CancelamentoMatricula`): a cópia certificada passa a ser a *cópia assinada* do
  pedido, e a conclusão deixa de esperar o anexo. Uma cópia em papel que já estivesse anexada não é apagada.
- **Providência de RH** (`ItemProcessoFuncionario`, Onda 65): a providência é concluída com a cópia certificada como
  comprovante. Processo já encerrado não muda.
- Os demais ficam só consultáveis.
A reação acontece **uma vez** (o log marca) e só depois de o certificado estar baixado.

### Telas
- **Documentos para Assinatura** (lista, criação e página do documento): situação, quantos assinaram, filtros,
  ações *Enviar*, *Atualizar situação*, *Links de assinatura*, *PDF enviado*, *PDF assinado* e *Cancelar*. As ações
  conferem a permissão **e o estado do documento**, porque o super admin passa por qualquer habilidade.
- **Cancelamento de Matrícula:** ação *Assinatura eletrônica do distrato* monta o distrato com o modelo ativo e leva
  ao documento; *Ver assinatura eletrônica* quando já existe um.
- **Portal da Família › Cancelar Matrícula:** botão *Assinar o distrato* com o link **do próprio usuário** (nunca o de
  outro assinante) e o andamento (*2 de 3 já assinaram*).
- **Admissão e Desligamento › Providências:** ação *Assinatura eletrônica* com os modelos de funcionário ativos.
- **Modelos de Documentos:** coluna *Dados de*, guia dinâmico, *Prévia* e *Criar modelos padrão*.
- **Distrato (PDF):** usa o modelo ativo de distrato quando existe; sem ele, mantém o texto fixo de antes.

### Permissões
`ViewAny`, `View`, `Create`, `Enviar` e `Cancelar:DocumentoAssinatura`, criadas por migration. Secretaria e
administração fazem tudo; a coordenação consulta. **Documentos de RH** (contrato de trabalho e termos de
funcionário) só aparecem, abrem e se enviam para quem também tem `View:ProcessoFuncionario`: a secretaria comum
não os vê, nem na lista, nem no PDF, nem no selo do menu. Sem edição nem exclusão: o texto enviado e o histórico
ficam.

### Dados e privacidade
- `documentos_assinatura`: modelo, registro ligado (polimórfico), título, **cópia do texto** (para provar o que foi
  assinado, mesmo se o modelo mudar depois), situação, caminhos e hash dos PDFs, id e situação no Assinafy, log, prazo.
- `assinantes_documento`: nome, e-mail, papel, ordem, situação, id no Assinafy e **link criptografado**.
- Os PDFs ficam em `documentos_assinatura/{id}/` no disco privado e abrem por `/visualizar-documento` para: a equipe
  com `View:DocumentoAssinatura` (e, no RH, `View:ProcessoFuncionario`), **quem foi chamado para assinar** (pelo
  e-mail) e a **família do aluno** nos documentos de matrícula e de cancelamento. O professor não entra só por ser
  da equipe. Arquivo sem documento correspondente não abre para ninguém.
- O histórico (`log_name = assinaturas`) guarda a situação e o id do modelo; **não** leva o texto nem e-mails.

### Configuração
`.env`: `ASSINAFY_API_KEY`, `ASSINAFY_ACCOUNT_ID`, `ASSINAFY_API_URL` (opcional) e `ASSINAFY_WEBHOOK_SECRET`
(opcional; a verificação só ocorre se o remetente enviar a assinatura). O mesmo endereço de webhook serve aos
contratos e aos documentos avulsos. O agendador precisa estar rodando para a rotina horária.

## Decisões e limites

- **Os modelos padrão são rascunhos.** Contrato de trabalho e distrato têm exigências legais (CLT, piso da categoria,
  jornada de professor, contrato de experiência, CDC art. 51 e Lei nº 9.870/1999). A escola deve revisar com o
  jurídico e o contador antes de ativar. Por isso nascem inativos.
- **Validade jurídica.** A assinatura é a do Assinafy; sua força (simples, avançada ou qualificada, Lei nº
  14.063/2020) depende do que a escola contratou lá e da natureza do documento. O sistema não afirma validade: guarda
  o PDF certificado e a prova do que foi enviado (texto e SHA-256).
- Um documento por vez em andamento para o mesmo modelo e registro.
- Sem ordem de assinatura (todos assinam ao mesmo tempo), sem lembrete automático ao assinante, sem assinatura
  presencial por tablet e sem carimbo de tempo próprio.
- O envio de **contratos de matrícula** continua no `AssinafyService`; unificar os dois fluxos é uma refatoração
  futura que não compensa o risco agora.
- O PDF usa o layout `pdfs.documento_assinatura` (timbre da escola, título, texto, rodapé do modelo, data de geração e
  código do documento).

## Variáveis por contexto
O catálogo completo aparece no guia do editor. Em resumo: **Aluno** (dados do estudante, acadêmicos, escola e, só na
declaração emitida, protocolo e verificação), **Cancelamento** (aluno e contrato, saída e valores, cláusulas de
devolução e de multa, tabela de faturas e escola) e **Funcionário** (dados pessoais, contratação, salário e vigência
do contrato de trabalho vigente, escola). `{{BLOCO_ASSINATURAS}}` vale em todos.

## Testes
- `tests/Feature/DocumentosAssinaturaTest.php` (51 testes): criação e validações, sugestão de assinantes, envio,
  falha e retomada, acompanhamento, certificado atrasado, recusa, webhook (incluindo o fluxo antigo do contrato),
  rotina e expiração, cancelamento, reação do distrato e da providência de RH, permissões e acesso aos PDFs.
- `tests/Feature/DocumentosAssinaturaTelasTest.php` (31 testes): lista, criação, página do documento, super admin,
  cancelamento de matrícula, Portal da Família e providência de RH.
- `tests/Feature/ModelosDocumentoEditorTest.php` (29 testes): contextos, catálogo, variáveis desconhecidas, editor,
  prévia, histórico, modelos padrão, isolamento da emissão e PDF do distrato.
