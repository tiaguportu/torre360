# NPS e Satisfação Recorrente das Famílias (Onda 95) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

A escola só ouve a família quando ela reclama. Uma pergunta curta e periódica ("de 0 a 10, o quanto você recomendaria a escola?") mostra a
satisfação **antes** da rematrícula e aponta o detrator enquanto ainda há tempo de falar com ele. Hoje o NPS existe só para quem visitou a escola como lead.

## O que já existe (conferido no código)

- **NPS de visita:** `PesquisaSatisfacaoVisita` (`nota_nps` de 0 a 10, notas de atendimento e infraestrutura, link por token em `/pesquisa-visita/{token}`),
  com `classificacaoNps()` (promotor, neutro, detrator) e cor. É do CRM e só alcança o lead.
- **Avaliação do chamado:** `AtendimentoChamado` recebe nota de 1 a 5 e comentário no fechamento.
- **Motor de pesquisa:** `Questionario*` (alvos, período, Likert, discursiva), com `QuestionarioDisponivelNotification`. **Não tem tipo "NPS 0–10"**, nem
  recorrência, nem amostragem, nem relatório agregado.
- **Painel da Direção** (Onda 45): 14 indicadores, nenhum de satisfação. O **risco de evasão** (Onda 13) não usa satisfação.
- Fila **Minha Mesa** (Onda 71) para onde o detrator iria.

## Escopo proposto

### Campanha recorrente
- Tipo de pergunta **NPS (0–10) + "o que motivou a nota?"** no Questionário, e uma **campanha** que o aplica a cada fim de etapa ou trimestre.
- **Amostragem e descanso:** cada responsável é consultado no máximo a cada N meses (padrão 6) e uma vez por família, não por filho. A campanha pode ser limitada
  a uma série, turma ou unidade.
- Resposta **identificada por padrão**, com a opção "prefiro não me identificar". A identificação é o que permite contatar o detrator.

### O que acontece com a resposta
- Nota até 6 (detrator) abre um **caso de retorno** (a fila *Detratores para contato* na Minha Mesa, ou um chamado do [Atendimento Unificado](atendimento_unificado_roadmap.md))
  com prazo, responsável e registro do contato. Promotor recebe um agradecimento (opcional).
- **Indicador no Painel da Direção:** NPS do período, por série e por unidade, e evolução. Fonte de relatório agendado (Onda 72).
- Fator opcional do **risco de evasão**: família detratora pesa na pontuação, com peso configurável.

### Regras
- O NPS **prioriza contato; não decide nada sobre o aluno** (rematrícula, bolsa, atendimento). Na dúvida, aplicar a mesma cautela do
  [risco de inadimplência](risco_inadimplencia_roadmap.md).
- Convite pelo canal da [Onda 70](central_avisos_familia_roadmap.md), com link único, sem exigir login.

## Base legal

LGPD, art. 6º (finalidade e transparência) e art. 20 (revisão humana de decisões automatizadas): o NPS é perfilamento leve. A política de privacidade deve dizer para
que a nota é usada.

## Dependências e riscos

- **Fadiga de pesquisa:** sem descanso e amostragem a taxa de resposta cai. Medir a taxa de resposta junto com o NPS.
- **Viés:** NPS de quem responde não é o da escola inteira; mostrar sempre o número de respostas.
- Resposta com crítica a um professor citado pelo nome: o comentário entra no caso, e não em relatório aberto.
- Depende de um canal de aviso razoável (Onda 70); no começo pode sair pelo e-mail da `ComunicacaoEmMassa`.

## Esforço

Pequeno a médio.

## Pacote

**P1 (Comunicação com a família)** pelo convite e **P2 (Solicitações e casos)** pelo retorno ao detrator. Ver a
[proposta de unificação](unificacao_de_ondas_proposta.md).
