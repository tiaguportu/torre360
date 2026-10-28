# Ondas 80 a 88 — Índice

Continuação do [índice das ondas 72 a 79](ondas_72_a_79_indice.md). Reúne as propostas para a **secretaria** (80 a 83) e para o **CRM**
(85 a 88), que a análise de produtividade da equipe levantou depois do conjunto Suprimentos e Resultado da escola. A **Onda 84**
(Mesa do consultor e próxima ação obrigatória) já foi implementada em 2026-10-27 e é a base da sequência do CRM; o documento dela está
em [mesa_consultor_proxima_acao_roadmap.md](mesa_consultor_proxima_acao_roadmap.md).

Cada documento diz o que já existe **conferido no código** (para não duplicar), o escopo, a base legal quando há, as dependências e os
riscos. Nenhuma das propostas abaixo tem código, migration nem teste.

- **Implementada (1):** 84.
- **Propostas (8):** 80, 81, 82, 83, 85, 86, 87 e 88.

## Visão geral

| Onda | Tema | Área | Esforço | Status | Documento |
|---|---|---|---|---|---|
| 80 | Requerimentos Online da Família | Secretaria / Portal | Médio | Proposta | [requerimentos_online_familia_roadmap.md](requerimentos_online_familia_roadmap.md) |
| 81 | Qualidade dos Cadastros | Secretaria / Conformidade | Pequeno | Proposta | [qualidade_cadastros_roadmap.md](qualidade_cadastros_roadmap.md) |
| 82 | Roteiros da Secretaria | Secretaria | Pequeno a médio | Proposta | [roteiros_secretaria_roadmap.md](roteiros_secretaria_roadmap.md) |
| 83 | Atendimento Unificado | Secretaria / Portal | Médio | Proposta | [atendimento_unificado_roadmap.md](atendimento_unificado_roadmap.md) |
| 84 | Mesa do Consultor e Próxima Ação Obrigatória | CRM | Pequeno | **Implementada** | [mesa_consultor_proxima_acao_roadmap.md](mesa_consultor_proxima_acao_roadmap.md) |
| 85 | Distribuição de Leads e Carteira | CRM | Médio | Proposta | [distribuicao_leads_carteira_roadmap.md](distribuicao_leads_carteira_roadmap.md) |
| 86 | Agendamento Online de Visitas e Eventos | CRM | Médio | Proposta | [agendamento_online_visitas_roadmap.md](agendamento_online_visitas_roadmap.md) |
| 87 | Proposta Comercial com Aceite da Família | CRM / Financeiro | Médio | Proposta (completa a proposta que já existe) | [proposta_comercial_aceite_roadmap.md](proposta_comercial_aceite_roadmap.md) |
| 88 | Canal WhatsApp Oficial | CRM / Comunicação | Grande, com contrato e custo | Proposta | [whatsapp_oficial_roadmap.md](whatsapp_oficial_roadmap.md) |

## O que a conferência no código mostrou

Ao escrever os documentos, cada afirmação sobre "o que já existe" foi conferida no código. Alguns achados mudam o desenho das ondas:

| Achado | Onde | Consequência |
|---|---|---|
| **O chamado não avisa ninguém.** Abrir ou responder não notifica a equipe nem a família, e o campo `AtendimentoSetor.email_notificacao` é cadastrado e **nunca lido**: a tela promete um e-mail que não sai. | Central de Atendimento | O primeiro item da Onda 83 é pequeno e vale sozinho: fazer o aviso existir. |
| **O Portal da família não tem sino** (o painel do Portal não liga `databaseNotifications`; o do admin liga). | Portal | As ondas 80 e 83 respondem para ninguém sem a Onda 70 (ou, no mínimo, e-mail por mudança de situação). |
| **A proposta comercial existe e o ciclo dela para na aprovação.** `AceitaPelaFamilia`, `Convertida` e `Expirada` estão no enum e **nenhum código muda a proposta para elas**; o Assistente de Matrícula pré-preenche os dados, mas o **desconto negociado não chega ao contrato**. | Proposta comercial | A Onda 87 não cria uma proposta: completa a existente (aceite, expiração e do desconto à bolsa). |
| **O conceito de "canal" existe três vezes** sem que um use o outro: `CanalMensagemManager` (e-mail e push), `CanalReguaFollowUp` e o `canal` da régua de cobrança (via `NotificationService`). | Comunicação | A Onda 88 começa unificando; senão o WhatsApp seria o quarto. |
| **Lembrete de visita só vai ao consultor.** A família só recebe se a régua tiver regra para `visita_lembrete`. | Visitas | A Onda 86 garante o lembrete à família. |
| **A detecção e a mesclagem de duplicados são só de lead** (`LeadDuplicadoDetectorService`, `LeadMesclagemService`). Para `Pessoa` não há nada; `Matricula::comCadastroIncompleto()` já existe e serve de fila pronta. | Cadastros | A Onda 81 reaproveita os dois: a fila de incompletos é quase de graça; a mescla de pessoas é a parte de risco. |
| **A *Landing Page* do menu do CRM é do produto** (escolas pedindo demonstração), não das famílias da escola. | CRM | Não é canal de entrada de lead de matrícula; a entrada automática (Onda 85) é nova. |
| O motor de checklist com prazo e comprovante **já existe**, feito só para o RH (`ProcessoFuncionario`, `config/rh_processos.php`). | RH | A Onda 82 deve generalizar esse motor, não copiá-lo. |

## Fusões sugeridas

| Conjunto | Ondas | O que compartilham | Ordem dentro do conjunto |
|---|---|---|---|
| **Sequência do CRM** | 84 → 85 → 86 → 87 | O lead entra e é distribuído (85), visita (86), recebe a proposta e aceita (87); tudo passa pela carteira, pelo SLA e pela Minha Mesa da 84. | 84 (feita), 85, 86 e 87 (a 87 não depende das outras e pode vir antes) |
| **Atendimento da família** | 80 + 83 + 70 | Pedido e chamado têm protocolo, responsável, prazo, resposta e aviso; a Central de Avisos é a saída dos dois. Um motor de "solicitação" com tipo evita duas filas. | 70 (aviso), 83 (SLA e modelos), 80 (tipos com resultado) |
| **Comunicação e canal** | 88 + 70 + 33 + 47 | Destinatário, consentimento, entrega e ciência são os mesmos para aviso, comunicado, cobrança e régua. O WhatsApp é um canal da escola inteira. | Unificar os canais existentes, 70 e 33, 88 por último |
| **Obrigações com prazo** | 82 + 65 + 60 + 56 | "Algo a fazer, de alguém, até uma data, com prova": admissão e desligamento (feita), documentos com validade (feita), saúde do trabalhador e os roteiros da secretaria. Um motor e uma fila na Mesa. | Generalizar o motor da 65, depois 82 |
| **Dado cadastral** | 81 + 80 + 34 + 58 | A ficha da pessoa tem três portas: a escola corrige (81), a família atualiza (80) e o titular exerce o direito (34). O Censo (58) cobra o resultado. | 81 (independente), 80 e 34 pelo mesmo canal |

## Ordem sugerida

1. **Aviso do chamado (parte da 83).** É o achado mais barato e mais concreto: o campo de e-mail do setor já está lá.
2. **81 (cadastros) e 82 (roteiros):** pequenas, com base pronta e sem dependência.
3. **85 (distribuição):** a fila *Leads sem consultor* da Onda 84 já pede.
4. **87 (completar a proposta)**, se a escola priorizar fechamento; **80** quando a Central de Avisos existir; **86** depois da 85.
5. **88 (WhatsApp)** por último: depende de contratar o provedor e de unificar os canais.

## Ampliações propostas em ondas já existentes

| Onda | Ampliação | Observação |
|---|---|---|
| 84 (mesa do consultor) | Prioridade calculada dentro de cada fila, o resumo do dia por WhatsApp ao consultor (depende da 88) e *Propostas a vencer* (depende da 87). | A 84 deixou isso explicitamente fora. |
| Régua de follow-up | **Acolhimento de novas famílias:** novo gatilho (matrícula concluída) com checklist de boas-vindas e **pesquisa 30 dias depois**; gatilho para proposta a vencer. | Cabe como regra nova da régua existente, sem onda própria. |
| 46 (segurança reforçada) | Corrigir o cabeçalho: a retenção de logs e a criptografia de saúde **já foram entregues** em 2026-10-23; só o **MFA** continua proposto. | Texto desatualizado, corrigido junto com este índice. |
| 72 (relatórios) | Fontes para *Chamados* (tempo de resposta por setor), *Propostas* e *Leads por consultor*. | Cada fonte é uma classe pequena. |
| 71 (mesa) | Filas de *Chamados sem resposta*, *Requerimentos para atender*, *Etapas de roteiro* e *Cadastros incompletos*. | Cada fila é uma classe pequena e traz sua permissão. |

## Fora deste índice

A proposta de **Fechamento do mês financeiro** (checklist calculado pelo próprio sistema, com conciliação, saídas sem classificação, contas a
revisar, apuração e exportação contábil) foi sugerida na análise anterior e **continua só em conversa**; não tem documento.
