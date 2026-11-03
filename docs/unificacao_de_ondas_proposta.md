# Unificação das ondas pendentes (proposta de 2026-11-02)

> **Status: proposta, sem código.** Revisão de todas as ondas ainda não implementadas, conferida contra o código
> (leitura e busca; nada foi executado nem verificado na produção). Substitui as tabelas de "Fusões sugeridas"
> espalhadas nos índices [68-71](ondas_68_a_71_indice.md), [72-79](ondas_72_a_79_indice.md) e
> [80-91](ondas_80_a_88_indice.md), que se sobrepõem entre si. Complementa a
> [análise de estabilização](estabilizacao_antes_de_novas_funcionalidades.md).
>
> Base conferida: `main` em `24f3849a` mais `origin/main` até `be285ea6` (atestados, média parcial, motivo da perda).
>
> **Atualização de 2026-11-02:** os pacotes **P2** (solicitações e casos) e **P10** (funil comercial, sem a Onda 85) foram implementados; veja o [índice dos pacotes P2 e P10](pacotes_p2_p10_indice.md). As ondas 92 a 106 estão no [índice próprio](ondas_92_a_106_indice.md).
> O primeiro passo do **P1** também foi dado: o resolvedor único de destinatários (`DestinatariosDaFamilia`), descrito na [Onda 70](central_avisos_familia_roadmap.md#implementação-2026-11-02-resolvedor-único-de-destinatários).

## 1. Onde estamos

Entre as ondas 27 e 91 (65 números):

| Situação | Quantas | Ondas |
|---|---|---|
| Entregues | 31 | 27, 30, 32, 38, 39, 40, 41, 43, 44, 45, 49, 50, 52, 57, 58, 59, 60, 61, 62, 64, 65, 69, 71, 72, 73, 74, 76, 77, 78, 84, 91 |
| Parciais | 3 | 42 (só o informe anual; falta a NFS-e), 46 (só falta o MFA), 81 (só a detecção de duplicados) |
| Propostas | 30 | 28, 29, 31, 33, 34, 35, 36, 37, 47, 48, 51, 53, 54, 55, 56, 66, 67, 68, 70, 75, 79, 80, 82, 83, 85, 86, 87, 88, 89, 90 |
| Mesclada | 1 | 63 (virou seção da 35) |

Mais seis propostas das ondas antigas (1 a 26) que continuam abertas: **8** provas online, **9** controle de saída,
**12** transporte, **15** excursões, **17** cantina e **22** AEE. Ao todo, **36 ondas inteiras e 3 partes de ondas**
esperam implementação.

## 2. O que o código mostra e os índices não dizem

Ondas "propostas" que já têm parte do trabalho feito por outra onda, ou documentos desatualizados.

| Onda | Documento diz | Código hoje | Efeito |
|---|---|---|---|
| **15** Excursões | "Não implementado; `EventoEscolar` cobre só confirmação simples" | `EventoEscolar` ganhou `limite_vagas`, `prazo_confirmacao`, `exige_autorizacao`, `termo_autorizacao` e `valor_por_pessoa`; `EventoConfirmacao` guarda `autorizado`, IP, data e acompanhantes; o Portal bloqueia a confirmação sem o aceite do termo (commit `71f0a5be`, 2026-09-28). **Falta:** gerar a fatura (o valor só aparece no formulário, nada cobra), lista de segurança (confirmou, autorizou, pagou), presença no dia e, se quiserem, assinatura pelo Assinafy. | A onda é uma **terminação** de ~40%, não um módulo novo. |
| **70** Central de avisos | "O Portal da Família não mostra o sino" | `PortalPanelProvider` liga `databaseNotifications()` com polling de 30 s desde 2026-10-28, e dez classes de notificação já gravam em `database`, várias dirigidas à família (Onda 91, ocorrência, questionário, vaga, preceptoria, documentos pendentes). **Falta:** catálogo de avisos, histórico próprio, deduplicação, preferências e os avisos de cancelamento, assinatura, lista de material, enfermaria e chamado. | Texto do documento errado; o trabalho é menor que o descrito. |
| **70 / 33 / 47** (destinatário) | "Resolver quem avisar" como item novo | O caminho `responsaveis → users` é **montado em 5 lugares**: `PreceptoriaAgendaService`, `ResumoFaltasDiarioService`, `AssinafyService`, `SignatariosSugeridosService` e `Contrato`, mais duas telas de preceptoria que chamam `->notify()` direto. | O primeiro passo da comunicação é um **resolvedor único**, antes de qualquer tela. |
| **33** Comunicados com ciência | "Não existe ciente por responsável nem enquete" | Certo, mas o material existe: `ComunicacaoEmMassa` (só e-mail; públicos *Interessados* e *Responsáveis da turma*), `Questionario` com `is_anonimo` e alvo por usuário (serve de enquete), `EventoConfirmacao` (ciência por evento). | Enquete = Questionário de uma pergunta; não criar segundo motor. |
| **47** Digest | "Criar interface `DigestBloco`" | A Mesa já tem `FontePendencia` com **28 filas**; o digest da **família** existe (Onda 91, com deduplicação). Falta a preferência por usuário, o comando agendado e a regra de não enviar vazio. | Pequena: é um consumidor da Mesa. |
| **53** Risco de inadimplência | "Não há código" | `RiscoEvasaoService` já tem o fator *Inadimplência* (binário, via `hasDebitosVencidos()`); a Onda 62 calcula dias de atraso e valor atualizado; `AcordoInadimplencia` registra o desfecho. Falta histórico de pagamento e previsão. | Cabe como segundo consumidor dos mesmos dados, depois da 68. |
| **55** Medicação | "Proposta" | `MedicamentoAluno` (nome, dosagem, horário, instruções, autorização dos responsáveis, receita) e `AtendimentoEnfermagem` existem. Falta só o **registro de dose**. | Pequena. |
| **56** Saúde do trabalhador | "Proposta" | O ASO admissional e o demissional já são itens da lista de providências da Onda 65 (`config/rh_processos.php`). Falta o periódico, treinamentos (NR), CIPA e Lei Lucas, com vencimento. | Reusa o padrão de vencimento da 60. |
| **28** Reforço | "Receberá os encaminhamentos do conselho" | `ReuniaoEncaminhamento` já guarda `matricula_id`, responsável e prazo, e tem duas filas na Mesa. O risco de evasão ainda é só número, sem ação. Falta o executor. | Hoje há **três origens de intervenção sem destino comum**. |
| **22** AEE | "Não implementado" | `NecessidadeEducacaoEspecial` (classificação), `RecursoAcessibilidade` e `PlanoAulaAdaptacao` (PEI do Copiloto). A decisão registrada é unificar com o PEI. | Não criar `PlanoEducacionalIndividualizado` paralelo. |
| **51** Tarefas | "Evolui `dever_casa`" | `dever_casa` (texto e anexo) existe e já aparece em *Horários* do Portal. Falta entrega, prazo e carga por dia. | Como descrito. |
| **80 / 83** Requerimentos e atendimento | "Cada um com seu fluxo" | Já há **quatro fluxos de pedido** com status próprio: `SolicitacaoDocumento` (protocolo, recusa), `AtendimentoChamado` (protocolo, setor, nota), `CancelamentoMatricula` e `TransferenciaEscolar`; o `AtestadoAluno` (2026-10-30) deixou o envio pelo Portal "fora do escopo". Implementar 80, 34 e 35 em separado cria o quinto, o sexto e o sétimo. | Principal motivo da unificação B. |
| **83** | "`email_notificacao` nunca é lido" | **Confirmado hoje:** aparece só no form, na tabela, no model e na migration; nenhuma notificação, listener ou observer toca no chamado. | Correção de uma linha que vale sozinha. |
| **87** Proposta com aceite | "`AceitaPelaFamilia`, `Convertida` e `Expirada` nunca são atribuídos" | **Confirmado:** o enum tem 9 estados; o código só os lê (`RevenueManagementService`, tabela). Já existem quatro links públicos por token (`acordo`, `pesquisa-visita`, convite de matrícula, admissão), cada um com o seu código. | O aceite reaproveita o padrão; não é novo. |
| **85** Distribuição | "Não há regra" | Há o escopo de carteira (Lote D7), a ação de **assumir** e a fila *Leads sem consultor* (Onda 84). Falta só a regra automática. | Pequena. |
| **68** Gateway | "Driver real" | `GatewayPagamento` + `GatewayPagamentoManager` + driver `fake` + webhook `/api/webhooks/pagamento` com HMAC + `PagamentoConfirmacaoService` + `BaixaFaturaService` + conciliação. | Falta **só o driver** (e a reemissão com valor atualizado). |
| **42** NFS-e | "Proposta" | `TributacaoCurso` (CNAE, ISS, PIS, COFINS, item de serviço por curso) e o informe anual existem. Falta a emissão. | Dados fiscais prontos. |
| **79** Guarda e descarte | "Não há rotina" | Existem rotinas de retenção de log (180 dias), e-mail (365), OMR, rascunhos de pré-matrícula e relatórios. Falta a tabela de temporalidade dos documentos do aluno. | Compartilha a tabela com a 34. |
| **54 / 90** | — | Só existe o `/up`. **Não há rotina de backup no sistema** (nenhuma referência em app, config ou agendador; pode existir no servidor, a confirmar). O **uso das telas já é gravado** (`AuditMiddleware` nos dois painéis, 180 dias), mas nenhuma tela lê (primeira versão deste documento errou aqui; ver [Onda 106](uso_das_telas_roadmap.md)). | Entra como primeira fatia da plataforma. |
| **9 / 37 / 12 / 17 / 31 / 36 / 48 / 8** | — | Nada além de bases genéricas (`QrCodeService`, crachás, `ItemEstoque`, `ItemFatura`, `ReservaEspaco`; a API tem 4 rotas). | Realmente do zero. |

## 3. Proposta: 11 pacotes no lugar de 36 ondas

Cada pacote tem **um núcleo que se constrói uma vez** e ondas que viram tipos ou consumidores dele. As três tabelas de
fusão dos índices tinham 21 conjuntos, com a mesma onda em mais de um (a 70 aparecia em três). Aqui cada onda pendente
está em **um** pacote.

| # | Pacote | Ondas | Núcleo compartilhado | Já existe | Esforço |
|---|---|---|---|---|---|
| **P1** | **Comunicação com a família** | 70, 33, 47, aviso da 83, 88 (canal) | `DestinatariosDaFamilia` (resolvedor único) + `AvisosFamiliaService` com catálogo de tipos, deduplicação e preferência; ciência por responsável | sino do Portal, 10 classes de notificação em `database`, `ComunicacaoEmMassa`, `CanalMensagemManager`, Onda 91, `Questionario`, Mesa | Médio, em fatias pequenas |
| **P2** | **Solicitações e casos** | 83, 80, 34, 35 (+63), ofício da 57 | protocolo, tipo, prazo, responsável, histórico, modelos de resposta e fila na Mesa, sobre `AtendimentoChamado` | `SolicitacaoDocumento`, `AtendimentoChamado`, `AtestadoAluno`, modelos de documento (69) | Médio |
| **P3** | **Obrigações e vencimentos** | 82, 56, ampliação da 60 (seguros e contratos) | o motor de `ProcessoFuncionario` com sujeito genérico (funcionário, matrícula, unidade) + tipos de vencimento sobre `DocumentoRegulatorio` | férias (10), documentos da escola (60), admissão e desligamento (65), fila de providências | Pequeno a médio |
| **P4** | **Cuidado com o aluno** | 28, 22, 55, ação sobre o risco de evasão (13) | `Intervencao` (aluno, origem, responsável, prazo, resultado) que recebe encaminhamento do conselho, alerta de risco e AEE | `ReuniaoEncaminhamento`, `RiscoEvasaoService`, `PlanoAulaAdaptacao`, `MedicamentoAluno`, `ReposicaoAula` | Médio |
| **P5** | **Planejamento do ensino** | 29, 51, 8 (só o banco de questões) | plano anual por série/disciplina entre a matriz e o plano de aula; banco de questões comum ao OMR e ao plano | `MatrizCurricular`, `PlanoAula`, `CargaLetivaService`, `GradeAssistidaService`, OMR, `dever_casa` | Grande |
| **P6** | **Serviços opcionais e vendas** | 15 (terminar), 31, 17, 67, 12 | catálogo de serviço/produto + inscrição ou pedido + cobrança + autorização + presença ou entrega | `EventoEscolar`/`EventoConfirmacao`, `ItemEstoque`, `ItemFatura`, `BolsaConcedida`, `ReservaEspaco`, `Consentimento` | Pequeno (15) a grande (resto) |
| **P7** | **Portaria e saída** | 9, 37, 101 (credencial) | `Autorizado` eventual (quem não é responsável vinculado) + registro de acesso + "quem está na escola agora" | `aluno_responsavel.permissao_retirada` (só responsável vinculado), `QrCodeService`, crachás, `ContatoEmergencia` | Médio |
| **P8** | **Cobrança real e risco** | 68, 53, reemissão com valor atualizado (62) | o driver real atrás da interface que já existe | interface, manager, webhook, baixa, conciliação, encargos, acordo | Médio, depende do provedor |
| **P9** | **Fechamento fiscal e contador** | 42 (NFS-e), 75, fechamento do mês | período fechado + entrega ao escritório com acesso restrito | exportação contábil (73), `TributacaoCurso`, `LoteContabil`, fila de classificação | Médio, 75 só depois do MFA |
| **P10** | **Funil comercial** | 87, 85, 86 | `LinkPublicoComToken` (um só para acordo, pesquisa, convite, aceite, agenda) + motor de horários | carteira, *assumir*, `VisitaInteressado`, `PropostaComercial`, régua de follow-up | Médio |
| **P11** | **Plataforma e conformidade** | 89, 90, 54, 46 (MFA), 34+79 (tabela de temporalidade), 48 | portão de qualidade (testes em MariaDB, guarda de policies) + publicação com backup e volta | testes de guarda, `/up`, agendador | Médio a grande |

Decisões de desenho:

1. **P2: núcleo compartilhado, tabelas separadas onde há regra própria.** Cancelamento (61) e transferência (24)
   têm regra legal e fluxo próprio e **ficam como estão**. Unificam-se 83 e 80 (chamado ganha `tipo`, prazo e modelo
   de resposta; "2ª via", "atestado enviado pelo Portal" e "atualização cadastral" viram tipos) e, por cima, 34. A 35
   e a 63 reusam protocolo, prazo e histórico, mas com **política e listagem próprias** (sigilo e denúncia anônima não
   podem aparecer na fila comum).
2. **P3: não migrar as férias.** O índice 68-71 sugeria "migrar as férias" para o cadastro genérico. Funciona, tem
   teste e aviso semanal; mexer não traz ganho. Os novos tipos usam o cadastro novo.
3. **P1: enquete é Questionário.** Um motor de pesquisa só; o comunicado com ciência é que é novo.
4. **P6: a Onda 15 vira o primeiro passo.** Já está em ~40%, dá cobrança de passeio em uma fatia pequena e prova o
   desenho do catálogo antes de cantina e loja.
5. **P10: o aceite da 87 e a agenda da 86 não esperam a 85.** A 87 não depende das outras; a 86 depende do motor de
   horários (o mesmo da agenda de atendimento da seção 5).

## 4. Ordem de entrega

Respeita a recomendação de pausar funções grandes até fechar a P11 mínima (89 e 90).

| Fase | O que entra | Por quê |
|---|---|---|
| **0. Portão** | P11 mínima: 89 (MariaDB nos testes e guarda de policies) e 90 (publicação com backup); migrar para o resolvedor único (P1.1); ler `email_notificacao`; garantir que existam as 4 permissões que o código usa e nenhuma migration ou seeder cria (`Aprovar:PropostaComercial`, `Homologar:Avaliacao`, `Homologar:PlanilhaLeiMensalidade`, `ReenviarNotificacao:OcorrenciaEscolar`). As duas primeiras com prefixo declarado no Shield (`aprovar`, `homologar`) só nascem se o `shield:generate` do roteiro de instalação rodar no deploy (conferir); `Homologar:Avaliacao` e `ReenviarNotificacao:OcorrenciaEscolar` não constam de prefixo nenhum | Cada onda nova aumenta o que uma publicação ruim quebra. |
| **1. Fatias pequenas que fecham coisas pela metade** | P1.2 (avisos de cancelamento, assinatura, material, enfermaria, chamado); P6 com a 15 (cobrança e lista de segurança); P10 com a 87 (aceite e expiração); 66 (conferência de cláusulas); P3 com a 82; 81 restante; 85 | Cada uma usa o que existe e entrega valor sozinha. |
| **2. Núcleos médios** | P2 (83 + 80), P4 (28 + ação sobre o risco), P1.3 (comunicado com ciência, 47), P3 com a 56, P8 (quando o provedor estiver escolhido), P9 (depois do MFA) | Dependem do núcleo de P1 ou de decisão externa. |
| **3. Grandes e opcionais** | P5, P6 (31, 17, 67), P7, 34/35, P10 (86), 88 | Pedem decisão de produto, contrato ou análise jurídica. |
| **Avaliar corte** | **48** (SSO, Classroom, webhooks), **12** (transporte), **36** (egressos), **8** completo (provas online) | Sem evidência de demanda. O relatório de uso da [Onda 106](uso_das_telas_roadmap.md) tiraria a decisão do palpite, e a coleta já roda. |

## 5. Outras funcionalidades que sistemas como este costumam ter

Filtradas contra o código: só entram as que **não existem** hoje e que **nenhuma onda cobre**. "Nada no código" quer
dizer busca sem resultado em `app`, `database/migrations`, `routes` e `config`.

> **Atualização:** estas ideias foram documentadas como as ondas 92 a 106 ([índice](ondas_92_a_106_indice.md)). A numeração: calendário iCal (92), aviso de enfermaria (93), clima (94), NPS (95),
> acessibilidade (96), folha (97), Pix Automático (98), mapa de habilidades (99), agenda de atendimento (100), credencial QR (101), carteira digital (102), vacinação (103), seleção de professores (104),
> fechamento do mês (105) e uso das telas (106).

### Pequenas e de alto reaproveitamento

| Ideia | Por que aqui | Reaproveita | Esforço |
|---|---|---|---|
| **Calendário assinável (iCal) por responsável** | Nada no código. A família já vê datas em três telas; um feed `.ics` leva eventos, provas (52), reuniões e dias não letivos ao celular. | `Calendario` do Portal, `EventoEscolar`, `CalendarioAvaliacaoService`, `DiaNaoLetivo` | Pequeno. Token revogável; sem nome do aluno no título. |
| **Aviso de atendimento na enfermaria** ([Onda 93](aviso_enfermaria_familia_roadmap.md)) | `AtendimentoEnfermagem` existe e **nenhuma notificação** parte dele; a caixa `notificado_responsaveis` é só uma marcação manual. É o aviso que toda família espera. | Tipo "essencial" da P1 | Pequeno. Corpo mínimo, sem diagnóstico. |
| **Pesquisa de clima dos funcionários** ([Onda 94](pesquisa_clima_funcionarios_roadmap.md)) | O `Questionario` grava resposta anônima (`user_id` nulo), mas isso também significa "sem login": não impede resposta repetida nem diz quem faltou. Falta separar participação de resposta e o relatório agregado com mínimo de respostas por grupo. | `Questionario*` | Pequeno a médio |
| **NPS recorrente das famílias** | O NPS só existe na pesquisa pós-visita do CRM. A resposta do detrator abriria um caso (P2) e alimentaria o painel e o risco de evasão. | `Questionario*`, `PainelDirecao` | Pequeno a médio |
| **Acessibilidade do Portal** (VLibras, contraste, tamanho de fonte) | Nada no código. A LBI (Lei nº 13.146/2015, art. 63) exige acessibilidade nos sítios de empresas (confirmar a leitura com o jurídico). | Tema do Portal | Pequeno |
| **Exportação do ponto e da hora-aula para a folha** | O ponto (41) e a hora-aula (50) estão prontos; falta o arquivo no leiaute do escritório. | Leiautes da 73 | Médio |

### Valor alto, esforço médio

| Ideia | Por que aqui | Reaproveita | Esforço |
|---|---|---|---|
| **Pix Automático / débito para a mensalidade** | Atacaria a inadimplência na origem. Em operação no Banco Central desde 2025; depende do driver da 68. | P8 | Médio |
| **Mapa de habilidades da turma** | `NotaHabilidade`, `OmrCorrecaoHabilidade` e `AvaliacaoHabilidade` já guardam o acerto por habilidade; **nenhuma tela cruza**. Daria a defasagem por turma e por aluno e agruparia quem precisa de reforço. | P4 (28), P5 (29), OMR | Médio |
| **Agenda de atendimento individual** (família com professor ou coordenação) | Hoje `reuniao_pais` é só um evento com confirmação. A preceptoria já tem agenda, recorrência e conflito. | Motor de horários da P10 e `PreceptoriaAgendaService` | Médio |
| **Credencial digital (QR) do aluno e do responsável** | Uma só para portaria, saída, biblioteca, cantina, loja e presença em evento. | `QrCodeService`, crachás | Médio |
| **Carteira digital do aluno** (saldo pré-pago único) | A cantina (17) previa saldo; a loja (67), a excursão e a multa da biblioteca precisariam do mesmo. Recarga por PIX depende da 68. | P6, P8 | Médio. Validar o enquadramento fiscal e jurídico. |
| **Carteira de vacinação e ficha de saúde vencida** | `FichaMedica` existe (criptografada); vacina não. O ECA (art. 14, §1º) trata a vacinação obrigatória; a exigência na matrícula varia por rede (confirmar). | `FichaMedica`, P3 (vencimento) | Pequeno a médio |
| **Vagas e seleção de professores** ([Onda 104](selecao_professores_roadmap.md)) | **Correção:** a primeira versão dizia "nada no código", e estava errada. O banco de talentos já existe (`Candidato`, currículo e entrevista por IA, 2026-10-23) e não tinha documento. Falta a vaga, a etapa por vaga, a aula-teste e a passagem à admissão. | `Candidato`, `CurriculoIaService`, `ProcessoFuncionario`, `DisponibilidadeProfessor` | Médio |

### Não recomendo agora

- **Chat professor-família:** cria obrigação de resposta fora do horário e risco de registro informal. A Central de
  Atendimento e os avisos da P1 cobrem o caso.
- **Mural ou álbum de fotos da turma:** o módulo de consentimento de imagem (Onda 25) permitiria, mas imagem de
  criança é o ponto de maior risco da LGPD e exige moderação.
- **IA nova com dado de aluno:** o rascunho de parecer por IA já é desligado por padrão por esse motivo.

## 6. Correções de texto sugeridas nos índices e roteiros

Nenhuma foi aplicada.

| Onde | O que corrigir |
|---|---|
| `excursoes_passeios_roadmap.md` | Status e "o que já existe": o evento já tem autorização com aceite, vagas, prazo e valor. |
| `central_avisos_familia_roadmap.md` | Trocar "O Portal da Família não mostra o sino" por o estado real (sino desde 2026-10-28; dez classes de notificação gravam em `database`). |
| `digest_diario_papel_roadmap.md` | Citar a Onda 91 como digest da família já entregue. |
| `ondas_27_a_48_indice.md` | "Ordem sugerida" ainda lista a 27 como pendente; apontar para esta proposta. |
| Índices 68-71, 72-79 e 80-91 | Em "Fusões sugeridas", apontar para esta proposta como a versão consolidada. |

## 7. Limites desta revisão

- A conferência foi por leitura e busca. Nenhum teste foi rodado e a produção não foi consultada.
- O checkout principal estava 3 commits atrás da `origin/main` e com alterações de outra sessão em preceptoria; os
  arquivos novos foram lidos direto da `origin/main`.
- "Nada no código" prova ausência no repositório, não no servidor (por exemplo, backup feito pela hospedagem).
- As referências legais citadas na seção 5 não foram reconferidas nesta sessão; confirmar antes de virar requisito.
- O esforço (pequeno, médio, grande) é estimativa por comparação com ondas já entregues, não medição.
