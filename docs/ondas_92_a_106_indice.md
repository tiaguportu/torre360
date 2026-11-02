# Ondas 92 a 106 — Índice

Continuação do [índice das ondas 80 a 91](ondas_80_a_88_indice.md). Reúne as **funcionalidades que a [proposta de unificação](unificacao_de_ondas_proposta.md)
(2026-11-02) apontou e que nenhuma onda cobria**, agora documentadas como ondas. Cada documento diz o que já existe **conferido no código**, o escopo, a base legal quando há, as
dependências e os riscos.

- **Propostas (14):** 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 105 e 106 (a coleta da 106 já existe; falta o relatório).
- **Parcial (1):** 104 (o banco de talentos de candidatos já existe e não tinha documento).

## Visão geral

| Onda | Tema | Área | Esforço | Status | Pacote | Documento |
|---|---|---|---|---|---|---|
| 92 | Calendário Assinável (iCal) para a Família | Portal | Pequeno | Proposta | independente | [calendario_assinavel_ical_roadmap.md](calendario_assinavel_ical_roadmap.md) |
| 93 | Aviso de Atendimento na Enfermaria à Família | Saúde / Comunicação | Pequeno | Proposta | P1, P4 | [aviso_enfermaria_familia_roadmap.md](aviso_enfermaria_familia_roadmap.md) |
| 94 | Pesquisa de Clima dos Funcionários | RH | Pequeno a médio | Proposta | independente | [pesquisa_clima_funcionarios_roadmap.md](pesquisa_clima_funcionarios_roadmap.md) |
| 95 | NPS e Satisfação Recorrente das Famílias | Relacionamento | Pequeno a médio | Proposta | P1, P2 | [nps_familias_roadmap.md](nps_familias_roadmap.md) |
| 96 | Acessibilidade do Portal e das Páginas Públicas | Portal / Conformidade | Pequeno a médio | Proposta | independente | [acessibilidade_portal_roadmap.md](acessibilidade_portal_roadmap.md) |
| 97 | Exportação para a Folha de Pagamento | RH / Financeiro | Médio | Proposta | P9 | [exportacao_folha_pagamento_roadmap.md](exportacao_folha_pagamento_roadmap.md) |
| 98 | Pix Automático e Cobrança Recorrente | Financeiro | Médio (depende da 68) | Proposta | P8 | [pix_automatico_roadmap.md](pix_automatico_roadmap.md) |
| 99 | Mapa de Habilidades da Turma | Pedagógico | Médio | Proposta | P4, P5 | [mapa_habilidades_turma_roadmap.md](mapa_habilidades_turma_roadmap.md) |
| 100 | Agenda de Atendimento Individual (Família com Professor ou Coordenação) | Pedagógico / Portal | Médio | Proposta | P10, P2 | [agenda_atendimento_individual_roadmap.md](agenda_atendimento_individual_roadmap.md) |
| 101 | Credencial Digital (QR) do Aluno e do Responsável | Operação | Médio | Proposta | P7 | [credencial_digital_qr_roadmap.md](credencial_digital_qr_roadmap.md) |
| 102 | Carteira Digital do Aluno (Saldo Pré-pago Único) | Financeiro / Operação | Médio a grande | Proposta | P6, P8 | [carteira_digital_aluno_roadmap.md](carteira_digital_aluno_roadmap.md) |
| 103 | Carteira de Vacinação e Ficha de Saúde Atualizada | Saúde | Pequeno a médio | Proposta | P4, P3 | [vacinacao_ficha_saude_roadmap.md](vacinacao_ficha_saude_roadmap.md) |
| 104 | Vagas e Seleção de Professores | RH | Médio | **Parcial** (banco de talentos feito) | P3, P10 | [selecao_professores_roadmap.md](selecao_professores_roadmap.md) |
| 105 | Fechamento do Mês Financeiro | Financeiro | Médio | Proposta | P9 | [fechamento_mes_financeiro_roadmap.md](fechamento_mes_financeiro_roadmap.md) |
| 106 | Uso das Telas e Adoção do Sistema | Plataforma | Pequeno | Proposta (coleta já existe) | P11 | [uso_das_telas_roadmap.md](uso_das_telas_roadmap.md) |

## O que a conferência no código mostrou

| Achado | Onde | Consequência |
|---|---|---|
| **O banco de talentos de candidatos já existe** (2026-10-23, `6c8cc728`) e não tinha documento: currículo lido por IA, entrevista por áudio, status até "banco de talentos". Falta a vaga, a etapa por vaga e a passagem à admissão. | `Candidato`, `CurriculoIaService` | A Onda 104 completa, não cria. A proposta de unificação dizia "nada no código": estava errada. |
| **O sistema já grava cada visualização de tela** (`AuditMiddleware` nos dois painéis, `audit_logs` e `activitylog`), por 180 dias, **e nada lê**. A análise de estabilização dizia que não havia registro de uso. | `AuditMiddleware`, `AuditLog` | A Onda 106 é um relatório e uma agregação, não uma nova coleta. |
| `AtendimentoEnfermagem.notificado_responsaveis` é uma **caixa manual**: nada é enviado quando ela é marcada. | Enfermagem | A Onda 93 faz a caixa significar alguma coisa. |
| O **anonimato do Questionário** grava `user_id` nulo, mas também significa "responder sem login": não impede resposta repetida nem dá lista de quem faltou. | `Questionario`, `ResponderQuestionario` | A Onda 94 separa participação e resposta. |
| `OmrCorrecaoHabilidade` (acerto por habilidade na prova) é **gravada e nunca lida**. | `OmrService` | A Onda 99 dá uso a um dado que já se acumula. |
| O **Calendário do Portal não mostra os eventos da escola** (só provas, dias não letivos e aulas). | `Portal/Pages/Calendario` | A Onda 92 também corrige isso. |
| `aluno_responsavel.permissao_retirada` existe, mas só cobre **responsável vinculado**. | Relação de pessoa | A Onda 101 completa com o autorizado eventual. |
| O molde de aviso à família da ocorrência põe o **relato inteiro no e-mail**. | `OcorrenciaRegistradaNotification` | Não copiar para saúde (Onda 93); rever como parte da P1. |

## Fora deste índice (de propósito)

Três ideias da proposta de unificação **não** viraram onda, pelos riscos explicados na seção 5 dela: **chat professor-família** (obrigação de resposta fora do horário), **mural ou álbum de fotos
da turma** (imagem de criança) e **IA nova com dado de aluno**. Se a escola pedir, entram com a análise jurídica antes do roteiro.

## Ordem sugerida

Não há ordem própria: cada onda está no pacote da [proposta de unificação](unificacao_de_ondas_proposta.md) e segue a ordem dele. As que valem fazer cedo por serem pequenas e usarem o que existe são
**106** (a coleta já está rodando e o histórico expira em 180 dias), **93**, **92** e a primeira fatia da **96**.
