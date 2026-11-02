# Pesquisa de Clima dos Funcionários (Onda 94) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

A escola mede a satisfação das famílias e dos leads, mas não a dos próprios funcionários. Uma pesquisa de clima anual ou semestral (liderança,
carga de trabalho, comunicação, segurança, reconhecimento) mostra onde está a rotatividade antes de ela acontecer. Só funciona se as pessoas
**acreditarem que não serão identificadas**.

## O que já existe (conferido no código)

- `Questionario`, `QuestionarioBloco`, `QuestionarioPergunta`, `QuestionarioAlvo`, `QuestionarioResposta`: período de aplicação, blocos, perguntas
  discursivas, objetivas, de múltipla escolha e **escala Likert**, alvos por registro (`alvo_type` e `alvo_id`; o usuário é um deles), notificação de questionário disponível
  (`QuestionarioDisponivelNotification`), importação e exportação CSV (`QuestionarioService`) e uma tela de **comparar respostas**.
- **Anonimato existe, mas é só metade do que o clima precisa.** Em questionário `is_anonimo`, a resposta é gravada com `user_id` nulo
  (`ResponderQuestionario`). Porém o mesmo campo também significa "pode ser respondido **sem login**". E, sem `user_id`, o sistema **não consegue impedir que
  a mesma pessoa responda várias vezes** nem dizer quem ainda não respondeu.
- **Não existe relatório agregado:** a tela atual compara respostas individuais. Também não há mínimo de respostas por grupo para proteger a identidade.

## Escopo proposto

### Separar quem participou do que foi respondido
- `QuestionarioParticipacao`: questionário e usuário, com a data. Sem ligação com a resposta. Serve para "uma resposta por pessoa" e para o "faltam 12 de 40".
- A resposta anônima guarda só o **grupo** (por exemplo, setor ou unidade), nunca o usuário.

### Relatório agregado
- Média e distribuição por pergunta e por bloco, por unidade e por setor. **Grupo com menos de N respostas (padrão 5) é fundido ou escondido**, e o relatório
  avisa. Comentários livres só aparecem sem ordem de envio e sem metadados.
- Comparativo entre aplicações (ano a ano) e índice por tema (liderança, carga, comunicação...).
- Modelo pronto de questionário de clima (editável), com as perguntas em escala Likert.

### Acompanhamento
- Plano de ação a partir dos piores temas: responsável, prazo e retorno aos funcionários ("você disse, nós fizemos"). O ciclo só se mantém se houver devolutiva.

## Base legal

- **LGPD:** a anonimização efetiva tira o dado do alcance da lei (art. 12); por isso o mínimo de respostas por grupo é parte do desenho. Respostas
  sobre saúde mental ou assédio podem ser dado sensível.
- **NR-1** (gerenciamento de riscos ocupacionais), que passou a tratar os riscos psicossociais: o clima pode ser uma das evidências do programa de riscos da escola.
  Confirmar o alcance e a vigência com a medicina e a segurança do trabalho.

## Dependências e riscos

- **Quebrar o anonimato por dedução:** grupo pequeno, comentário que cita o nome do diretor. O mínimo por grupo e a revisão dos comentários antes de divulgar mitigam.
- Não ligar a pesquisa ao `activitylog` nem ao `AuditLog` por usuário e horário: o horário da visita ao formulário poderia reidentificar a resposta.
- A direção precisa se comprometer com a devolutiva antes de aplicar.

## Esforço

Pequeno a médio (a separação participação × resposta é a parte delicada).

## Pacote

Independente; reaproveita o motor de questionários, como o [NPS das famílias (Onda 95)](nps_familias_roadmap.md). Ver a
[proposta de unificação](unificacao_de_ondas_proposta.md).
