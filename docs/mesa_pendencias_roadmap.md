# Mesa de Pendências por Papel (Onda 71)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda da tela e na seção 81 do `MANUAL_USUARIO.md`.
> Tela: `/admin/minha-mesa` (**Minha Mesa**, no topo do menu, logo depois do Início).

## Contexto

As filas de trabalho da escola estão espalhadas: cada módulo tem a sua tela e, quando muito, um selo no menu
(Rematrículas, Admissão e Desligamento, Painel de Férias). Quem trabalha na secretaria precisa abrir sete telas para
saber se há alguma coisa vencendo, e o que está parado há uma semana só aparece quando alguém lembra de olhar. O
cancelamento que a família pediu, o documento que ficou em rascunho, a rematrícula sem turma e a providência de RH
vencida competem pela atenção sem que nada diga por onde começar.

A **Mesa** reúne essas filas numa tela só, **por papel**: cada pessoa vê o que o papel ativo dela pode resolver, com
quantos itens esperam, quantos já passaram do prazo e os mais antigos de cada fila, com atalho para a tela onde se
resolve.

## O que já existia

- Selos de menu com contagem própria: Rematrículas (aguardando turma), Admissão e Desligamento (providências vencidas)
  e Painel de Férias. A Mesa **usa as mesmas regras** (`Rematricula::aguardandoTurma()`,
  `ItemProcessoFuncionario::atrasados()`), então nunca contradiz o selo.
- Widgets do início (matrículas pendentes, contratos, frequência pendente), que continuam como estão.
- O padrão de papel ativo (`session('active_role')`) que `User::can()` já respeita.
- Nenhum catálogo das filas: cada módulo contava por conta própria.

## O que foi feito

### Estrutura
- **`App\Contracts\FontePendencia`:** uma fila. Informa chave, título, área, ícone, orientação (o que fazer), a
  **permissão** necessária, a tela onde se resolve, `contar()`, `atrasados()` e `itens()` (os mais antigos primeiro).
- **`config/mesa_pendencias.php`:** a lista de filas e os prazos das que não têm prazo próprio. Um módulo novo
  acrescenta a sua classe aqui, sem tocar na tela.
- **`App\Services\Pendencias\MesaDePendencias`:** monta as filas do usuário (só as de que o papel ativo tem
  permissão), isola a que falha (a Mesa continua com as outras e avisa qual não carregou) e guarda o resumo do selo
  por 5 minutos, por usuário e papel.
- **Nenhuma tabela nova.** A Mesa só lê. Nada é copiado, atribuído nem resolvido por ela.

### As nove filas

| Fila | Área | Permissão | Entra | Atrasado quando |
|---|---|---|---|---|
| Pedidos de cancelamento para analisar | Secretaria | `Aprovar:CancelamentoMatricula` | pedido da família `Solicitado` | sem resposta há mais de 3 dias |
| Cancelamentos prontos para concluir | Secretaria | `Concluir:CancelamentoMatricula` | `Aprovado` com a data de saída chegada | a data de saída já passou |
| Devoluções a fazer | Secretaria | `Concluir:CancelamentoMatricula` | `Concluído` com valor a devolver e sem a data da devolução | concluído há mais de 10 dias |
| Documentos para enviar à assinatura | Secretaria | `Enviar:DocumentoAssinatura` | documento em `Rascunho` ou com `Erro` no envio | erro, ou rascunho há mais de 2 dias |
| Assinaturas atrasadas | Secretaria | `View:DocumentoAssinatura` | `Enviado` há mais de 7 dias, ou com o prazo acabando em até 3 dias | toda a fila |
| Rematrículas aguardando a turma | Secretaria | `Update:Rematricula` | dados confirmados pela família e sem turma | parada há mais de 5 dias |
| Transferências em andamento | Secretaria | `Update:TransferenciaEscolar` | entrada ou saída `Em andamento` | a data prevista passou |
| Providências de RH vencidas | RH | `ViewAny:ProcessoFuncionario` | providência não resolvida, com prazo vencido, em processo em andamento | toda a fila |
| Grades horárias incompletas | Calendário e Horários | `GradeAssistida:Turma` | turma do período em curso com aulas faltando ou turno sem tempos | o período já começou |

Documento de RH (contrato de trabalho, termos do funcionário) só entra nas duas filas de assinatura para quem também
tem `View:ProcessoFuncionario`. Os prazos de dias vêm de `config/mesa_pendencias.php` e podem ser trocados no `.env`
(`MESA_CANCELAMENTO_RESPOSTA_DIAS`, `MESA_DEVOLUCAO_DIAS`, `MESA_RASCUNHO_DOCUMENTO_DIAS`,
`MESA_ASSINATURA_ATRASO_DIAS`, `MESA_ASSINATURA_PRAZO_DIAS`, `MESA_REMATRICULA_TURMA_DIAS` e
`MESA_SELO_CACHE_SEGUNDOS`).

### Tela
- Dois totais no alto (esperando ação e já passaram do prazo) e as filas **agrupadas por área**.
- Cada fila mostra o título, a quantidade, os atrasados em vermelho, a orientação, os cinco primeiros itens (com o
  detalhe que ajuda a decidir por onde começar) e "e mais N", e o link *Abrir a fila*. Cada item leva à tela onde se
  resolve (com o filtro já aplicado, quando a lista permite).
- As filas em dia ficam numa linha só ("Em dia: ..."), para a pessoa saber que foram olhadas.
- Fila que não carregou vira um aviso discreto no rodapé; as outras seguem corretas.
- Botão **Atualizar** (esquece o resumo guardado) e **Ajuda**.

### Selo do menu
*Minha Mesa* mostra o número de itens **atrasados** em vermelho; se não houver atrasado, o total esperando, em amarelo.
O número fica guardado por 5 minutos por usuário e papel. A página nem aparece para o papel que não enxerga nenhuma
fila (o professor, por exemplo, vê só as ferramentas dele).

## Como acrescentar uma fila

1. Crie uma classe em `app/Services/Pendencias/Fontes/` que implemente `FontePendencia`. Use a mesma consulta que a
   tela do módulo usa (ou um escopo do model compartilhado com ela), para a Mesa nunca contradizer a tela.
2. Escolha a **permissão** que o papel precisa ter para resolver o item, no formato `Acao:Entidade`.
3. Eager-load o que `itens()` usa: o projeto proíbe lazy loading em teste.
4. Acrescente a classe em `config/mesa_pendencias.php` e um teste em `tests/Feature/MesaDePendenciasTest.php`.

Filas acrescentadas depois da Onda 71:

| Onda | Fila | Área | Permissão |
|---|---|---|---|
| 73 | Classificação contábil pendente (cacheia a apuração do mês anterior pelo mesmo tempo do selo) | Financeiro | `View:ExportacaoContabil` |
| 84 | Contatos do dia | CRM / Comercial | `ViewAny:Interessado` |
| 84 | Visitas de hoje e sem resultado | CRM / Comercial | `ViewAny:Interessado` |
| 84 | Primeira resposta fora do prazo | CRM / Comercial | `ViewAny:Interessado` |
| 84 | Leads sem próxima ação | CRM / Comercial | `ViewAny:Interessado` |
| 84 | Leads quentes sem contato | CRM / Comercial | `ViewAny:Interessado` |
| 84 | Leads sem consultor | CRM / Comercial | `Update:Interessado` |
| 38 | Compras para cotar | Suprimentos | `Cotar:RequisicaoCompra` |
| 38 | Pedidos para aprovar (só os da alçada da pessoa) | Suprimentos | `ViewAny:RequisicaoCompra` |
| 38 | Compras para receber (sem os que a pessoa aprovou) | Suprimentos | `Receber:PedidoCompra` |
| 16 | Estoque abaixo do mínimo | Suprimentos | `ViewAny:ItemEstoque` |
| 43 | Orçamento estourado (cache de alguns minutos para todos) | Financeiro | `View:OrcadoRealizado` |
| 78 | Contas recorrentes a revisar | Financeiro | `Update:ContaPagar` |
| 78 | Reajustes de contrato | Financeiro | `Update:ContaRecorrente` |
| 78 | Caixa projetado negativo | Financeiro | `View:FluxoProjetado` |
| 77 | Turmas com resultado negativo (cache de 6 horas) | Financeiro | `View:RentabilidadeRealizada` |
| 76 | Projeção do próximo ano | Secretaria | `View:SimuladorProximoAno` |
| 27/32 | Meus encaminhamentos de reunião | Acadêmico | `ViewAny:Reuniao` |
| 27/32 | Atas de reunião por fechar (quem organiza e a coordenação, sem as sigilosas dos outros) | Acadêmico | `Update:Reuniao` |
| 27/32 | Encaminhamentos vencidos da equipe (sem os da própria pessoa e sem os de reunião sigilosa) | Acadêmico | `VerTodas:Reuniao` |

Uma fila pode filtrar o que mostra **pela pessoa** (a Mesa continua sem atribuição própria): a do CRM usa a carteira do consultor, e
*Pedidos para aprovar* e *Compras para receber* respeitam a alçada e a segregação de funções de quem olha.

## Decisões e limites

- **Só leitura e sem estado.** Não há atribuição, fila "minha", prioridade manual, adiamento nem comentário. É uma
  vista, não um sistema de chamados: a Central de Atendimento continua sendo o lugar das conversas.
- **Por papel, não por pessoa.** Todos os usuários de um papel veem as mesmas filas. Filtrar por responsável (ex.:
  "só as minhas") exigiria atribuir itens, o que nenhum módulo faz hoje.
- Faltam filas de outros módulos (contratos a assinar, frequência pendente do professor, ajustes de ponto, pareceres
  para revisar, reservas, manutenção). Cada uma é uma classe pequena; ficam para quando o módulo for ajustado.
- A fila de grades usa a visão geral da Grade Horária Assistida, que consulta turma a turma. Por isso o resultado fica
  guardado por 5 minutos **para todos os usuários** (a contagem não depende de quem olha) e o botão *Atualizar* o refaz.
  Uma fila nova que tenha custo parecido implementa `GuardaPendenciasEmCache` para a Mesa saber esquecê-la.

## Fusão com a Onda 47 (Digest diário por papel)
A [Onda 47](digest_diario_papel_roadmap.md) propõe um resumo diário por e-mail ou push com o que exige ação. Ela precisa
do mesmo catálogo de filas por papel que a Mesa criou: o digest passa a ser **um segundo consumidor de
`FontePendencia`** (sem `DigestBloco` próprio), enviando contagem e link, sem dado sensível no corpo. O que falta nela é
a preferência por usuário, o agendamento e o envio.

## Testes
`tests/Feature/MesaDePendenciasTest.php` (29 testes): cadastro das filas, papel ativo, cada uma das nove filas com
seus prazos, documentos de RH, isolamento de fila que falha, resumo guardado e botão Atualizar, tela e selo do menu.
