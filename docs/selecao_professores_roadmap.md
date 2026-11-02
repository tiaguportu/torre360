# Vagas e Seleção de Professores (Onda 104) — Parcialmente Implementada (Banco de Talentos)

> **Status: parcial.** O **banco de talentos** (cadastro de candidatos, análise de currículo e de entrevista por IA) já existe e **não tinha documento**; este registra o que há e o que falta
> (vagas, etapas, aula-teste e a passagem para a admissão). Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

O RH da escola recebe currículos o ano inteiro e contrata em picos (antes do ano letivo, na substituição). O cadastro de candidatos já poupa a digitação, mas não responde às perguntas de
quem contrata: **para qual vaga** esse candidato foi, **em que etapa** está, **como foi na aula-teste**, **por que** foi recusado, e como ele **vira funcionário** sem digitar tudo outra vez.

## O que já existe (conferido no código)

Entregue em 2026-10-23 (commit `6c8cc728`, "banco de talentos com IA multimodal para currículos e áudios de WhatsApp"):

- `Candidato` (grupo **RH** do menu, com `CandidatoPolicy`): nome, contato, data de nascimento e idade, **cargo pretendido**, escola atual, local de moradia, **pretensão salarial**,
  formação, experiências, habilidades, resumo do perfil, **currículo** e foto em armazenamento, **impressões do entrevistador**, áudio da entrevista com resumo da transcrição, saída da IA
  (`dados_ia`), quem cadastrou e o entrevistador. Exclusão lógica.
- `StatusCandidato`: novo, em triagem, entrevista agendada, entrevistado, **banco de talentos**, aprovado, reprovado.
- `CurriculoIaService` lê PDF, imagem, Word e LibreOffice com a IA e preenche o cadastro (*Cadastrar com IA*); a ação *Entrevista de RH / áudio de WhatsApp* transcreve e resume a conversa.
- Admissão (`ProcessoFuncionario`, `config/rh_processos.php`, Onda 65) e `DisponibilidadeProfessor`, que são o **destino** de um candidato aprovado.
- **Falta:** vaga, ligação entre candidato e vaga, etapas por vaga, avaliação estruturada, aula-teste, retorno ao candidato, motivo de recusa, política de guarda do banco e a conversão em
  funcionário.

## Escopo proposto

### Vaga e candidatura
- `Vaga`: cargo, unidade, disciplina e segmento (para professor), regime e carga, quem pediu, data de abertura e meta, situação (aberta, em seleção, preenchida, cancelada). **A vaga nasce de uma
  necessidade real**: substituição ou ampliação de turma pode abri-la a partir da grade incompleta (Onda 64) ou do pedido do coordenador.
- `Candidatura` (candidato × vaga): etapa **por vaga**, e o mesmo candidato pode concorrer a mais de uma. O banco de talentos vira "candidatos sem vaga aberta", com busca por cargo, disciplina e
  disponibilidade.

### Etapas e avaliação
- Etapas configuráveis (triagem, entrevista, **aula-teste ou prova didática**, referências, proposta). Cada etapa tem **avaliador, critérios e nota** num formulário estruturado (o mesmo desenho
  dos critérios da avaliação docente da Onda 19), e não só texto livre.
- A **agenda de entrevista e da aula-teste** usa o motor de horários compartilhado com as [Ondas 86 e 100](agenda_atendimento_individual_roadmap.md).
- **Retorno ao candidato** por modelo de e-mail (recebemos, avançou, não seguiu), com o motivo de recusa de uma lista, para o relatório.

### Contratação
- Candidato **aprovado** abre o `ProcessoFuncionario` de admissão e **pré-preenche** `Funcionario` e `DisponibilidadeProfessor`, sem redigitar. A lista de providências da Onda 65 assume dali.

### Indicadores
- Tempo para preencher a vaga, candidatos por etapa, fonte do candidato (indicação, site, rede), taxa de aprovação na aula-teste.

## Regras que valem desde já

- **A IA apoia a triagem e não decide.** `dados_ia` fica visível e corrigível pela pessoa do RH, e nenhuma recusa sai só da IA (LGPD, art. 20).
- **Triagem às cegas:** o cadastro guarda **idade, foto e local de moradia**, que não devem pesar na análise. A tela de triagem esconde esses campos por padrão; só o RH vê na entrevista.
- Currículo e áudio da entrevista são dado pessoal: **consentimento para o banco de talentos**, prazo de guarda e descarte (hoje não há; entra na tabela da [Onda 79](guarda_descarte_documentos_roadmap.md)).
- Pretensão salarial e impressões do entrevistador têm acesso restrito ao RH e à direção.

## Base legal

- **Lei nº 9.029/1995:** proíbe prática discriminatória no acesso à relação de trabalho (idade, sexo, origem, situação familiar e outros). O desenho às cegas é a defesa prática.
- **LGPD:** art. 7º (base legal: procedimentos preliminares a contrato, ou consentimento para o banco de talentos), art. 20 (revisão de decisão automatizada) e art. 16 (término do tratamento).

## Dependências e riscos

- O áudio de entrevista gravado e enviado por mensagem pede aviso e consentimento do candidato: conferir o texto do aceite.
- Nada neste desenho precisa de portal público de vagas, mas ele é a evolução natural (formulário público com vaga e consentimento, como o de captação).

## Esforço

Médio.

## Pacote

**P3 (Obrigações e vencimentos)**, pela passagem à admissão, e o motor de horários da **P10**. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
