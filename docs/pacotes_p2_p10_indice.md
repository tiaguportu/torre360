# Pacotes P2 e P10 — Atendimento da família, ouvidoria e funil comercial (índice)

> **Status: implementados em 2026-11-02.** São os dois pacotes da [proposta de unificação](unificacao_de_ondas_proposta.md) que reúnem as
> ondas 83, 80, 34, 35 e 63 (P2, **Solicitações e casos**) e 87 e 86 (P10, **Funil comercial**). A **Onda 85** (distribuição automática de
> leads) ficou **de fora de propósito**, a pedido: a visita online escolhe o consultor por uma regra mínima, descrita abaixo.
> Cada onda tem o seu documento com o escopo original; este índice diz o que foi entregue, como as peças se ligam, o que mudou para quem já
> usava o sistema e o que continua de fora.

## O que foi entregue

| Onda | Tema | Onde fica | Documento |
|---|---|---|---|
| 83 | Atendimento unificado: prazo em horas úteis, aviso, assumir e reatribuir, respostas prontas, categorias, encerramento por inatividade | Comunicação Escolar → Central de Atendimento; Portal → Central de Atendimento | [atendimento_unificado_roadmap.md](atendimento_unificado_roadmap.md) |
| 80 | Requerimentos online da família (atestado, atualização cadastral, retirada, troca de turma ou turno, troca de responsável financeiro) | Portal → Requerimentos; Central de Atendimento → *Concluir pedido* | [requerimentos_online_familia_roadmap.md](requerimentos_online_familia_roadmap.md) |
| 34 | Pedido do titular da LGPD (como tipo restrito de requerimento) | Portal → Requerimentos → *Direitos sobre os meus dados* | [portal_titular_lgpd_roadmap.md](portal_titular_lgpd_roadmap.md) |
| 35 e 63 | Ouvidoria e convivência escolar (a 63 foi mesclada à 35) | Página pública `/ouvidoria`; Secretaria → Ouvidoria e Comitê da Ouvidoria | [ouvidoria_canal_denuncias_roadmap.md](ouvidoria_canal_denuncias_roadmap.md) |
| 87 | Proposta comercial com aceite da família | Página pública `/proposta/{token}`; CRM / Comercial → Propostas Comerciais | [proposta_comercial_aceite_roadmap.md](proposta_comercial_aceite_roadmap.md) |
| 86 | Agendamento online de visitas | Página pública `/visita`; CRM / Comercial → Agenda de Visitas | [agendamento_online_visitas_roadmap.md](agendamento_online_visitas_roadmap.md) |

## Como as peças se ligam

**P2.** O chamado da Central de Atendimento virou o motor das solicitações. Um **requerimento** é um chamado com `tipo_requerimento`, os campos do
formulário em `dados` e um prazo próprio; o **pedido do titular da LGPD** é um requerimento marcado como `restrito`. Tudo passa por
`AtendimentoService`: calcula o prazo, avisa a equipe (e o e-mail do setor, que era cadastrado e nunca lido) e a família, e conclui o
pedido. **Nenhuma alteração é aplicada sozinha:** a secretaria vê o dado atual ao lado do pedido e só então marca *Aplicar*, o que fica
registrado (quem e quando). A **ouvidoria** é um canal à parte (`Denuncia`), porque exige anonimato real e leitura restrita ao comitê, que não
cabem na mesma lista do chamado; reaproveita só o desenho (protocolo, prazo, histórico, fila na Minha Mesa).

| Pergunta | Tela | Quem vê |
|---|---|---|
| A família pediu algo? | Portal → Requerimentos / Central de Atendimento | A família, os próprios pedidos |
| Quem atende e dentro do prazo? | Central de Atendimento (colunas *Responder até* e *Atendente*) e as filas *Chamados com prazo estourado* e *Requerimentos para atender* da Minha Mesa | Quem tem `View:AtendimentoChamado` |
| E o pedido do titular? | A mesma lista, **só para quem tem `VerRestritos:AtendimentoChamado`** | Administração e quem a escola indicar |
| Alguém denunciou? | Secretaria → Ouvidoria | O comitê da categoria (e quem tem `VerTodas:Denuncia`) |

**P10 (sem a 85).** A proposta aprovada ganha um **link público** (token de 48 caracteres): a família lê as condições e aceita (data, IP e nome de quem
aceitou). Uma rotina diária expira as vencidas e avisa o consultor 3 e 1 dia antes. Ao matricular pelo Assistente de Matrícula com `?proposta_id=`, a proposta
vira *Convertida* e o **desconto negociado vira bolsa** "Desconto comercial", que o gerador de faturas já aplica. Em paralelo, o consultor publica os horários
em que recebe visitas (**Agenda de Visitas**) e a família escolhe um na página `/visita`: o lead entra pelas mesmas regras do formulário público (sem duplicar),
a visita nasce com token para trocar ou cancelar, e o consultor e a família são avisados.

**Consultor da visita sem a Onda 85.** Quando mais de um consultor atende no mesmo horário, a visita fica com quem tem **menos visitas marcadas naquele dia**
(empate: o de menor id). Lead **novo e sem dono** passa a ser do consultor da visita; lead que já tem dono continua com ele. Rodízio, capacidade, filtros por
série ou origem e reatribuição por prazo continuam sendo a Onda 85.

## O que mudou no que já existia

- **Central de Atendimento:** abrir ou responder um chamado agora **avisa** a equipe (sino, e-mail e o e-mail do setor) e a família (sino do Portal, e-mail e push,
  **só com o protocolo e um link**: o texto da resposta nunca vai na notificação). A lista do painel ganhou prazo, atendente, categoria e requerimento, mais as
  ações *Assumir*, *Reatribuir* e *Concluir pedido*. O formulário do setor ganhou os prazos (padrão: 24 h úteis para a primeira resposta, 72 h úteis para
  resolver e 7 dias de espera pela família), a marca *Recebe os requerimentos* e, na edição, **Categorias de assunto** e **Respostas prontas**.
- **Chamado com restrição:** o pedido do titular da LGPD não aparece na lista, no link direto nem nas filas da Minha Mesa para quem não tem `VerRestritos`.
- **Propostas Comerciais:** novas ações *Link para a família* e *Renovar*; o texto do WhatsApp passa a levar o link quando ele existe; a tabela carrega a série, o
  curso, a unidade e o consultor de uma vez (antes era uma consulta por linha). Os estados `AceitaPelaFamilia`, `Convertida` e `Expirada`, que nenhum código
  atribuía, passam a ser usados.
- **Assistente de Matrícula:** quando a matrícula nasce de uma proposta, o desconto vira bolsa. Se a proposta venceu ou não está aprovada, a matrícula **segue sem o
  desconto** e um aviso persistente diz por quê.
- **Lembrete de visita:** o lembrete à família (24 h antes) sai pela régua de follow-up quando há regra ativa para `visita_lembrete` (a migration da régua cria uma por
  padrão). Se a escola a desligar, as visitas marcadas online ainda recebem um lembrete direto.
- **Minha Mesa:** filas novas *Chamados com prazo estourado*, *Requerimentos para atender*, *Ouvidoria: manifestações para tratar*, *Propostas a vencer* e *Propostas para aprovar*.

## Permissões novas (migrations `2026_11_02_120000` e `2026_11_02_150000`)

| Permissão | Quem recebe por padrão |
|---|---|
| `Aplicar:AtendimentoChamado` (aplicar a alteração de um requerimento) | super admin, administração, secretaria |
| `VerRestritos:AtendimentoChamado` (ler o pedido do titular) | super admin, administração |
| `ViewAny`, `View`, `Tratar` e `VerTodas:Denuncia` (ouvidoria) | super admin, administração |
| `Gerenciar:DenunciaComite` (montar o comitê) | super admin, administração |
| `ViewAny`, `View`, `Create`, `Update` e `Delete:DisponibilidadeVisita` (agenda) | super admin, administração, secretaria, coordenação |

Quem lê uma manifestação precisa de **duas** coisas: a permissão `ViewAny/View:Denuncia` e estar no **Comitê da Ouvidoria** (por categoria ou todas), ou ter `VerTodas`.
Um papel criado à mão precisa receber as permissões em **Perfis de acesso**.

## Variáveis de ambiente (todas opcionais)

| Variável | Padrão | Para quê |
|---|---|---|
| `ATENDIMENTO_HORARIO_INICIO`, `ATENDIMENTO_HORARIO_FIM` | 08:00, 18:00 | Janela das horas úteis (segunda a sexta) |
| `ATENDIMENTO_PRAZO_PRIMEIRA_RESPOSTA_HORAS`, `ATENDIMENTO_PRAZO_RESOLUCAO_HORAS` | 24, 72 | Prazos do setor que não os preencheu |
| `ATENDIMENTO_FECHA_AGUARDANDO_DIAS` | 7 | Dias esperando a família até resolver sozinho |
| `ATENDIMENTO_PRAZO_PEDIDO_TITULAR_DIAS` | 15 | Prazo do pedido do titular (LGPD, art. 19, II), em dias corridos |
| `OUVIDORIA_PRAZO_DIAS`, `OUVIDORIA_PRAZO_SUSPEITA_VIOLENCIA_DIAS` | 10, 2 | Prazo da primeira providência do comitê |
| `OUVIDORIA_MAX_ENVIOS`, `OUVIDORIA_JANELA_MINUTOS`, `OUVIDORIA_MAX_CONSULTAS` | 5, 30, 10 | Limites do formulário e da consulta por código |
| `PROPOSTAS_RENOVACAO_DIAS` | 5 | Validade de uma proposta renovada |
| `VISITAS_MAX_AGENDAMENTOS`, `VISITAS_JANELA_MINUTOS` | 5, 60 | Limite de marcações por origem |
| `VISITAS_DIAS_A_EXIBIR` | 21 | Quantos dias à frente a página mostra |
| `VISITAS_AVISO_CANCELAMENTO_HORAS` | 4 | Abaixo disto o cancelamento chega ao consultor em destaque |
| `VISITAS_O_QUE_LEVAR` | texto padrão | O que a confirmação e o lembrete pedem à família |

## Agendador

| Comando | Quando | O que faz |
|---|---|---|
| `atendimento:resolver-aguardando` | diário, 07:20 | Resolve o chamado que espera a família há mais dias que o setor define; se ela responder, reabre |
| `crm:expirar-propostas` | diário, 07:25 | Avisa o vencimento (3 e 1 dia antes) e expira a proposta aprovada fora da validade |
| `crm:notificar-pendentes` (já existia) | diário, 08:00 | Passou a enviar também o lembrete de visita à família quando a régua não tem regra ativa |

## Migrations, em ordem

`2026_11_02_100000` (chamado: prazos, categoria, tipo de requerimento, restrição, resultado; categorias e respostas prontas), `110000` (ouvidoria),
`120000` (permissões), `130000` (aceite da proposta), `140000` (agenda de visitas e colunas da visita) e `150000` (permissões da agenda). Dicionário de dados em
`GEMINI_DB.md`, seções 53 a 56.

## Decisões de desenho (o que você pode querer mudar)

1. **Requerimento é chamado, mas a ouvidoria não.** O pedido do titular dividia protocolo, prazo e resposta com o chamado e entrou nele (como tipo restrito). A
   denúncia precisa de anonimato (nenhum usuário, IP ou navegador gravados) e de leitura restrita por categoria; separá-la evita que um erro de filtro exponha um relato.
2. **O aviso à família não leva conteúdo.** Só o protocolo e o link para o Portal autenticado. O molde da ocorrência escolar, que coloca o relato inteiro no e-mail,
   **não foi copiado**.
3. **A bolsa da proposta guarda percentual inteiro** (a coluna é `tinyint`). Desconto fixo ou fracionado é convertido e **arredondado** ao ponto percentual mais
   próximo (R$ 75 sobre R$ 1.000 = 7,5% vira 8%); a observação da bolsa e o aviso do Assistente dizem quando houve arredondamento. Se a escola preferir arredondar para baixo
   ou ampliar a coluna para decimal, a mudança fica em `PropostaAceiteService::converter()` e numa migration.
4. **Proposta aceita não expira sozinha.** A escola prometeu a condição; quem cobra a matrícula é o consultor, pela fila *Propostas a vencer*.
5. **Anonimato do formulário da ouvidoria sem reCAPTCHA.** O reCAPTCHA enviaria o endereço de quem denuncia a um terceiro. A defesa contra robôs é o limite por origem,
   o campo-armadilha e o limite de consultas por código. O servidor ainda mantém o registro técnico de sessão por algumas horas (ele não é ligado à manifestação).

## Achados da implementação

| Achado | Onde | O que foi feito |
|---|---|---|
| `AtendimentoSetor.email_notificacao` era cadastrado e nunca lido | Central de Atendimento | Passa a receber o aviso de chamado novo |
| A migration da régua já cria uma regra **ativa** de lembrete de visita | `regua_follow_ups` | O lembrete direto à família só vale se essa regra for desligada, para ela não receber dois |
| `PropostaComercialsTable` consultava série, curso, unidade e consultor por linha | Propostas Comerciais | Carga antecipada no recurso |
| O app proíbe carga preguiçosa fora de produção; `Denuncia` e `DenunciaMovimentacao` carregam o responsável e o autor sempre | Ouvidoria | `protected $with` nos dois models |
| `BolsaConcedida.percentual` é `tinyint` | Proposta → bolsa | Arredondamento declarado (decisão 3) |
| `TextEntry` com valor em array trata o array como lista de itens | Ouvidoria (envolvidos e identificação) | O texto vem pronto por `state()` |
| O pedido do titular exige **revisão humana** de qualquer exportação: a exportação automática dos dados da pessoa **não foi feita** | Onda 34 | Fica registrada como pendência (abaixo) |

## O que continua de fora

| Onda | Ficou para depois | Por quê |
|---|---|---|
| 83 | Perguntas frequentes no Portal; relatório de tempo de resposta (fonte da Onda 72); distribuição automática entre atendentes | Cada uma é uma entrega própria; o aviso e o prazo eram o que faltava de mais básico |
| 80 | Edição dos campos do formulário pelo administrador; aplicação automática de troca de turma, de responsável financeiro e de retirada | Mexem em contrato e em vaga: ficam como registro com atendimento manual |
| 34 | **Exportação dos dados da pessoa** (acesso e portabilidade), registro das operações de tratamento (ROPA) e o encarregado no site | É a parte de maior risco jurídico (exportação incompleta); precisa do mapa de tabelas que referenciam `pessoa` revisado pelo encarregado |
| 35 e 63 | Anexos da denúncia, plano de ação com prazo e responsável, impedimento de quem é citado, fluxo formal de comunicação ao Conselho Tutelar | O procedimento exato depende do jurídico da escola; o sistema só registra a comunicação |
| 87 | Simulador de mensalidade, indicadores de conversão por faixa de desconto, aceite com assinatura eletrônica, PDF da proposta | Não eram necessários para fechar o ciclo |
| 86 | Inscrição pública em eventos *portas abertas*, lista de presença, lembrete 2 h antes | A visita individual era o núcleo |
| 85 | Tudo (rodízio, capacidade, disponibilidade, reatribuição por prazo, entrada por webhook) | Fora do pedido |

## Como foi verificado

- **Suíte completa** (3.737 testes, em 7 processos paralelos): passou, com três exceções que a própria rodada achou e que foram resolvidas: duas telas novas sem o botão *Ajuda* (testes de
  cobertura da navegação e das páginas de recurso), quatro tabelas novas sem `stackedOnMobile` e a tela `/visita` sem o token do reCAPTCHA (esta, achada à parte). O único vermelho que sobra
  fora do código é `ValidarDocumentoIaTest`, que exige `GEMINI_API_KEY` no `.env` e passa com uma chave de teste; as quatro falhas antigas da `main` listadas em outros índices não apareceram.
- **Testes novos (103):** `HorarioComercialTest` (7), `AtendimentoSolicitacoesTest` (23), `OuvidoriaTest` (26), `PropostaAceiteTest` (25) e `AgendaVisitasOnlineTest` (22), mais
  as suítes existentes de Central de Atendimento, Mesa, Propostas, Visitas e do guarda de políticas, que continuaram passando.
- **MariaDB 10.4 (banco descartável copiado de `torre360_demo`, nunca a produção):** as seis migrations de estrutura e as duas de permissão aplicaram, fizeram
  `rollback` dos sete últimos passos e aplicaram de novo; um roteiro de fumaça rodou os serviços (chamado, requerimento aplicado, pedido restrito, ouvidoria, proposta até a bolsa
  de 8%, agenda e reserva) sem erro. A migration da Onda 87 usa `matricula` (singular) na chave estrangeira: o nome `matriculas` quebrava no SQLite e era antigo.
  O banco foi apagado depois.
- **Não foi verificado:** a aparência das telas novas no navegador (sem captura de tela), o envio real de e-mail e push, o comportamento com o volume da escola e as
  permissões em perfis feitos à mão.
