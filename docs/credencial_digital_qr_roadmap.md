# Credencial Digital (QR) do Aluno e do Responsável (Onda 101) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

Portaria, saída de alunos, biblioteca, cantina, loja e presença em evento precisam todos de uma coisa: **saber, na hora, quem é a pessoa à frente**. Hoje cada um
resolveria isso do seu jeito (digitar nome, carteirinha impressa, código do livro). Uma credencial digital, uma só, no celular do responsável e no crachá do aluno,
serve a todos e é a peça que falta para a [portaria e saída (Ondas 9 e 37)](portaria_digital_roadmap.md).

## O que já existe (conferido no código)

- `QrCodeService` (SVG e data URI) já usado nos documentos com verificação, no histórico escolar e nas folhas de gabarito do OMR.
- `BarcodeService` (código de barras em SVG), usado nas etiquetas e fichas da biblioteca.
- **Crachás:** `TemplateCracha` e `TemplateCrachaV3` (tipo de entidade, medidas, desenho em JSON), páginas de geração e geração em lote por turma (`GerarCrachasTurmaPdfJob`).
- **Quem pode retirar o aluno:** `aluno_responsavel.permissao_retirada`, editável pela secretaria nas relações de pessoa. Cobre só quem é **responsável vinculado**; vizinho, tio e
  transporte alternativo não têm onde constar. `ContatoEmergencia` guarda outras pessoas, mas sem a permissão de retirada.
- Página pública de validação de documento por código (`/validar-documento`).
- **Não existe** credencial: nenhum token por pessoa, nenhum endpoint que o resolva, nenhuma conferência do operador (busca de `Credencial` e de leitura de QR sem resultado).

## Escopo proposto

### A credencial
- `Credencial`: pessoa (e matrícula, quando aluno), tipo (aluno, responsável, funcionário, **autorizado eventual**), **token opaco**, validade, situação e revogada em. O QR traz **só o
  token**; nenhum nome, número de matrícula ou documento dentro do código.
- **Autorizado eventual:** o responsável cadastra no Portal quem pode buscar naquele dia (nome, documento, parentesco, validade, foto opcional), e a escola aprova ou recusa.
  Isso completa o `permissao_retirada` para quem não é responsável.
- **QR dinâmico do responsável** no Portal (o token muda a cada poucos minutos, para a foto do QR não servir) e **QR fixo, revogável**, no crachá do aluno.

### O operador
- Endpoint de validação que o operador abre autenticado (portaria, biblioteca, cantina): mostra **foto, nome, vínculo e se está autorizado a retirar o aluno X agora**,
  e registra quem validou, onde e quando. Nunca devolve dado financeiro nem de saúde.
- Credencial revogada ou vencida responde com um motivo curto, e perda do crachá revoga e emite outro.

### Consumidores (cada um é uma onda própria)
Controle de saída e portaria (P7), empréstimo na biblioteca por crachá, presença em evento ([P6](unificacao_de_ondas_proposta.md)), cantina e loja pela
[carteira (Onda 102)](carteira_digital_aluno_roadmap.md). A primeira entrega é a credencial e **um** consumidor.

## Base legal

LGPD, art. 6º, III (necessidade) e art. 14: foto e vínculo de criança só aparecem para operador autenticado; o QR não carrega dado pessoal. ECA e as normas da rede definem quem pode
retirar a criança: a regra de quem é autorizado é da escola e do responsável, e o sistema só a executa e a registra.

## Dependências e riscos

- **Foto do aluno e do responsável:** a credencial só ajuda o operador se mostrar a foto. Conferir onde a foto da pessoa é guardada hoje e usar o consentimento de imagem (Onda 25) para o uso na portaria.
- **Operação offline** (portaria sem internet): decidir se há lista de contingência impressa. Sem decisão, a credencial vira ponto único de falha.
- QR compartilhado por mensagem: por isso o código dinâmico no celular do responsável.
- Dado de quem busca (terceiro, adulto sem vínculo): finalidade e prazo de guarda curtos.

## Esforço

Médio.

## Pacote

**P7 (Portaria e saída)**, com a [carteira (Onda 102)](carteira_digital_aluno_roadmap.md) como segundo consumidor. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
