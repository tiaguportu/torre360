# Atestados com abono de faltas e média parcial na grade de notas

Duas melhorias do eixo acadêmico (sugeridas em revisão externa e conferidas contra o código antes de implementar).
Manual do usuário: seção 109 (atestados) e 5.9 (grade de notas) do `MANUAL_USUARIO.md`.

## O que já existia

- `frequencia_escolar.justificada` e `motivo_justificativa` já existiam, mas **nenhuma tela os preenchia**. Só os
  consumidores estavam prontos: o resumo diário de faltas ignora falta justificada e a apuração do Conselho Tutelar
  separa justificadas de injustificadas.
- `BoletimService::calcularMediaFinal()` (média ponderada da etapa, com categorias de substituição) e
  `FechamentoCicloService::classificarSituacao()` (aprovado / recuperação / reprovado pelas notas de corte do Período
  Letivo, padrão 7,0 e 5,0) já tinham a regra de média e de faixa. A grade só não as usava.

## Atestados (`/admin/atestados-aluno`)

- Tabela `atestados_aluno` (matrícula, período, `abona_faltas`, comprovante em `atestados_aluno/{matrícula}/`,
  observação criptografada, situação vigente/cancelado) e, em `frequencia_escolar`, `atestado_aluno_id` e `abonada`.
- `AtestadoAlunoService`: `registrar`, `corrigirPeriodo`, `cancelar` e `justificarFaltas`. Justificar é `justificada = true`
  + `motivo_justificativa = "Atestado médico (dd/mm/aaaa a dd/mm/aaaa)"` + vínculo com o atestado; se o atestado foi
  registrado como abono (`abona_faltas`), a falta também fica `abonada = true`.
- **Só o que o atestado fez é desfeito.** O vínculo existe para cancelar ou corrigir sem tocar na justificativa (ou no
  abono) lançada à mão. Falta já justificada por outra pessoa nunca é sobrescrita nem promovida a abono. Ao liberar uma
  falta, os demais atestados vigentes do aluno são reaplicados (atestados sobrepostos).
- `FrequenciaFaltaObserver`: falta lançada **depois** do atestado, dentro do período, já nasce justificada (e abonada, se
  o atestado é abono); a secretaria pode registrar o atestado antes dos dias passarem. Falta corrigida para presente
  perde a justificativa e o abono do atestado. Regra do cadastro: `abonada` sem `justificada` é zerada (abonar exige
  justificar).
- Teto de 180 dias no período, contra data digitada errada (ano de 2062) justificar anos de faltas.
- Privacidade: o comprovante só abre com `View:AtestadoAluno` (contexto próprio no `VisualizarDocumentoController`;
  professor e família não abrem); o log não registra a observação; o motivo gravado na falta não a cita.
- Permissões (migration): `ViewAny`, `View`, `Create`, `Update`, `Cancelar` de `AtestadoAluno`. Secretaria e admin
  fazem tudo; coordenação consulta. Sem exclusão.

### Justificar não é abonar

Duas marcas independentes em `frequencia_escolar` (a segunda implica a primeira):

| | `justificada` | `abonada` | Conta na frequência | Aviso à família | Conselho Tutelar |
|---|---|---|---|---|---|
| Injustificada | não | não | sim, como falta | sim | injustificada |
| Só justificada | sim | não | **sim, como falta** | não | justificada |
| Justificada e abonada | sim | sim | **não, como presença** | não | fora dos números |

- O abono é uma escolha registrada: no atestado (`abona_faltas`, padrão desmarcado) ou, para uma falta isolada, na
  tela Frequência Escolar (seção "Justificativa e abono da falta", com motivo obrigatório). Não há abono implícito.
- **Onde o abono vale** (o escopo `FrequenciaEscolar::contaComoFalta()` e `contaComoPresenca()` concentram a regra):
  `FrequenciaAlunoService` (Portal), `BoletimService::getFrequenciaDisciplinaEtapa` (percentual e datas de falta),
  `SituacaoAlunoCensoService`, `HistoricoEscolarService`, `RiscoEvasaoService`, indicador `FrequenciaMedia30Dias` e
  `FaltasConselhoTutelarService` (a abonada sai de totais, justificadas e injustificadas; o resultado traz
  `faltas_abonadas`). `ResumoFaltasDiarioService` já ignorava falta justificada, e abonada implica justificada.
- **Decisão de modelo:** a falta abonada entra como **presença** (o denominador, as aulas registradas, não muda). A
  alternativa seria tirá-la do denominador; se a escola preferir, a mudança fica concentrada nesses cálculos.
- O Portal mostra "Falta abonada" (azul), "Falta justificada" (amarelo, ainda conta) e "Falta" (vermelho), e avisa
  quantas abonadas ficaram fora da conta.
- As faltas que já existiam ficam sem abono (migration sem backfill): nada muda nos números até alguém abonar.

### Fora do escopo

- Envio do atestado pelo Portal da Família (hoje é a secretaria que registra).
- Atestado de funcionário: o ponto já tem o seu fluxo (`PontoTratamento`, abono de minutos).

## Média parcial na grade (`LancamentoNotasGrade`)

- `MediaParcialService` monta o contexto uma vez por requisição (duas consultas) e calcula a média de cada linha com a
  nota **digitada** no lugar da salva. A cor vem de `classificarSituacao()`: verde = aprovado, âmbar = recuperação,
  vermelho = reprovado, comparando a média arredondada a uma casa (como o boletim exibe: 6,96 aparece 7,0 e fica verde).
- A coluna é só leitura e não vai para o banco. Campo em branco, "Faltou" e "Não se aplica" tiram a avaliação da conta;
  valor acima da nota máxima ou negativo é ignorado.
- O campo de nota passou a `live(debounce: 400)`; a conta roda no servidor (precisa das categorias de substituição),
  então há uma ida e volta curta por digitação.

### Correções feitas junto

- **Bug anterior:** escolher "Faltou" / "Não se aplica" na grade e salvar quebrava com `Object of class SituacaoNota
  could not be converted to string`. A grade salva com `getState()`, que devolve o Select como enum; a outra tela usa
  `$this->data` cru. `NotaLancamentoService::salvarNotas` agora aceita enum ou texto.
- A lista de avaliações da grade lia `turma` e `disciplina` sem carregá-las (uma consulta por opção). Passaram para o
  `with()`.

## Implantação

- Rodar as migrations (`atestados_aluno`, colunas `abonada` e `atestado_aluno_id` em `frequencia_escolar` e as
  permissões). Validadas em MariaDB: `up`, chaves estrangeiras, `down` com dado e `up` de novo.
- Os cálculos de frequência passam a ler `frequencia_escolar.abonada`: sem a migration, Portal, boletim, Censo, risco de
  evasão e painel quebram (coluna inexistente). A migration vem antes do código.
- Não há comando agendado nem fila novos.
