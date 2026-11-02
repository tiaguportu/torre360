# Aviso de Atendimento na Enfermaria à Família (Onda 93) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

Quando a criança é atendida na enfermaria, a família espera saber **na hora**: caiu no recreio, está com febre, tomou um medicamento. Hoje a
enfermagem registra o atendimento e, se quiser avisar, liga ou escreve por conta própria. O sistema tem até uma caixa **"Notificado
responsáveis"**, mas ela é uma marcação manual: nada é enviado quando alguém a ativa.

## O que já existe (conferido no código)

- `AtendimentoEnfermagem` (`AtendimentoEnfermagemResource`): pessoa, quem atendeu, data e hora, e os campos clínicos **criptografados**
  (`sintomas_queixa`, `procedimento_realizado`, `medicamento_ministrado`, `observacoes`), mais `notificado_responsaveis` (booleano).
- **Nenhum código lê esse booleano nem notifica a partir do atendimento.** Ele só aparece no formulário e na coluna da tabela. Nenhuma notificação, listener
  ou observer cita enfermagem.
- `FichaMedica` (alergias, plano, hospital de preferência, tudo criptografado), `MedicamentoAluno` e `ContatoEmergencia`.
- O molde de aviso à família já existe para a ocorrência disciplinar: `OcorrenciaRegistradaNotification` (e-mail, banco, push), com a permissão
  `ReenviarNotificacao:OcorrenciaEscolar`. **Atenção:** esse molde coloca o **relato inteiro** no e-mail. Para saúde isso não pode ser copiado.
- Resolução dos responsáveis com acesso (`pessoa.responsaveis.users`), refeita em vários serviços (ver a
  [proposta de unificação](unificacao_de_ondas_proposta.md), P1).

## Escopo proposto

### O aviso
- Ação **Avisar responsáveis** no atendimento (e opção de enviar ao salvar). Marca `notificado_responsaveis` **sozinho**, grava data e quem enviou, e registra
  se o responsável **abriu** (a leitura da notificação no banco).
- Três níveis, escolhidos pela enfermagem:
  1. **Informativo:** "foi atendido(a) na enfermaria às 10h20; sem necessidade de ação".
  2. **Pedir contato:** a família deve ligar para a escola.
  3. **Urgente:** exige ligação. A tela guarda as **tentativas de contato** (quem ligou, a que horas, para qual número, atendeu ou não), começando pelos
     responsáveis e depois pelos `ContatoEmergencia`.
- **Resumo para a família** escrito pela enfermagem num campo próprio, separado dos campos clínicos criptografados. O que vai ao e-mail e ao Portal é só esse resumo,
  e só se a enfermagem o preencher.

### Regras de conteúdo
- E-mail e push levam: nome do aluno, hora, nível e um link para o Portal autenticado. **Nunca** sintoma, diagnóstico, medicamento nem observação.
- Push em tela bloqueada: texto genérico, sem nome do aluno.
- Aviso de nível urgente que ninguém abriu em N minutos entra na **Minha Mesa** da enfermagem e da direção (fila nova, com a permissão do módulo).

### Portal
- O aviso aparece no sino e em uma lista simples ("atendimentos na enfermaria": data, hora e resumo, quando houver). O histórico clínico não aparece.

## Base legal

LGPD, art. 11 (dado sensível de saúde) e art. 14 (criança): tratamento para proteção da vida e da saúde do titular (art. 11, II, "e") e informação ao
responsável legal. Conteúdo mínimo por princípio da necessidade (art. 6º, III).

## Dependências e riscos

- **Quem recebe:** o responsável com acesso ao aluno, e não só o financeiro. A regra de destinatário deve ser a do resolvedor único da P1.
- Aviso sem retorno é pior que sem aviso: o nível urgente depende da trilha de tentativas de contato.
- Falha de entrega (e-mail inválido, push desligado) precisa aparecer para a enfermagem, que então liga.
- Permissão própria para avisar e para reenviar, no modelo da ocorrência.

## Esforço

Pequeno.

## Pacote

**P1 (Comunicação com a família)** como tipo de aviso **essencial** (não pode ser silenciado) e **P4 (Cuidado com o aluno)**, junto com o registro de doses da
[Onda 55](medicacao_registro_doses_roadmap.md). Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
