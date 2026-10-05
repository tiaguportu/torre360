# Lista de Espera por Turma Lotada (Onda 20)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda da tela (**Lista de Espera**, grupo
> **Secretaria**, no admin).

## Contexto

`Turma::vagas_maximas` já bloqueia a matrícula de novos alunos quando a turma está
lotada (`EnrollmentWizard::save()`), mas até esta onda não havia nada a fazer além de
tentar outra turma: nenhuma fila de espera, nenhuma notificação automática quando um
cancelamento ou transferência libera uma vaga.

## Como foi implementado

- `ListaEsperaMatricula` (`app/Models/ListaEsperaMatricula.php`): `turma_id`,
  `periodo_letivo_id`, `pessoa_id` (obrigatório — o pretendente precisa já ter um cadastro
  de `Pessoa`, criado antes pela tela de Cadastros se for um lead novo), `interessado_id`/
  `interessado_dependente_id` (opcionais, só para rastrear a origem no CRM quando
  aplicável), `status` (`StatusListaEspera`: Aguardando/Notificado/Convertido/Desistiu),
  `observacoes`, `notificado_em`, `criado_por_user_id`. Fila FIFO por `created_at` — sem
  campo de posição para não precisar reordenar nada.
- Resource em `/admin/lista-espera-matriculas` (grupo **Secretaria**), com ação "Marcar
  Desistência" na tabela. `EnrollmentWizard` ganhou uma frase na notificação de "turma sem
  vagas" apontando para esta tela.
- `MatriculaVagaObserver` (`#[ObservedBy]` em `Matricula`) reage a `created`/`updated`/
  `deleted`:
  - Quando uma matrícula é criada para a mesma pessoa+turma de uma entrada
    Aguardando/Notificado, marca a entrada como **Convertida** automaticamente.
  - Quando `situacao` ou `turma_id` muda, ou a matrícula é excluída, reavalia a ocupação
    da turma; se abriu vaga, notifica (e-mail + push + sininho, mesmo canal usado em
    `OcorrenciaEscolar`/`Preceptoria`) o **primeiro** da fila (`Aguardando`, mais antigo),
    marcando-o como **Notificado**.

### Nota técnica (para quem for mexer neste código)

A ocupação da vaga, para decidir se "abriu vaga", é contada apenas entre as situações que
de fato ocupam vaga (`ativa`, `pendente`, `reserva`) — **diferente** da contagem usada hoje
em `EnrollmentWizard::save()` (`$turma->matriculas()->count()`, sem filtro de situação, que
trata uma matrícula cancelada como vaga ainda ocupada). Isso é intencional: do contrário, a
lista de espera nunca notificaria ninguém num cancelamento simples, o caso mais comum. A
consequência é que, em um caso raro, a lista de espera pode avisar uma vaga que o
assistente de matrícula ainda bloquearia por essa contagem mais simples dele — ajustar a
contagem do assistente é uma correção separada, fora do escopo desta onda.

## Dependências já satisfeitas

- `Turma::vagas_maximas` e a validação de lotação já existiam no `EnrollmentWizard`
  (ponto de partida desta onda).
- Padrão de notificação por e-mail/push/sininho (`FcmChannel`, `Filament\Notifications`)
  já estabelecido em `OcorrenciaRegistradaNotification`/`PreceptoriaNotification`.
- Padrão `#[ObservedBy]` em vez de lógica em `booted()` já estabelecido em
  `FrequenciaEscolar`/`FrequenciaFaltaObserver` (Onda 3, alerta de falta).
