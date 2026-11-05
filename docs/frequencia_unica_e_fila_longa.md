# Frequência sem duplicata e fila dos jobs longos (2026-11-05)

> **Status: implementado.** Saiu da análise de 2026-11-05 (itens 1 e 2), registrada em
> [estabilizacao_antes_de_novas_funcionalidades.md](estabilizacao_antes_de_novas_funcionalidades.md). Duas correções
> independentes, publicadas juntas: uma de integridade de dados (frequência) e uma de confiabilidade da fila.

## 1. Frequência: um registro por aluno e por aula

### O problema

`frequencia_escolar` tinha só um índice comum em (`matricula_id`, `cronograma_aula_id`). A regra "um registro por aluno e
aula" vivia no código, como "ler e criar se não existir", que não é atômica. A tela *Lançamento Rápido no Diário*
(`LancarFrequencia::mount()`) fazia isso ao ser aberta: dois professores, duas abas ou um recarregamento criavam duas
linhas para o mesmo aluno e a mesma aula. Quem calcula frequência soma linhas (`count(*)` em `FrequenciaAlunoService`), então o
aluno passava a contar em dobro no percentual, no alerta dos 75% e nos relatórios. A tabela `nota` já tinha ganhado o
mesmo índice, em 2026-10-21, por um problema igual.

Dois defeitos menores estavam junto, na mesma tela:

- A chamada listava **todas** as matrículas da turma, inclusive quem saiu antes ou entrou depois da data da aula. As
  *Pendências de Frequência* e o widget do painel já usavam a janela de vigência da matrícula; o *Lançamento Rápido* e o
  *Lançar Frequência em Lote* (ação em massa da lista de aulas) não.
- O campo do aluno em cada linha da chamada relia a turma inteira (com três relações) **uma vez por aluno**: numa turma de 30,
  30 leituras de 30 matrículas a cada abertura.

### O que mudou

| Onde | Mudança |
|---|---|
| Banco | Índice único `frequencia_escolar_matricula_aula_unique` em (`matricula_id`, `cronograma_aula_id`). |
| Migration `2026_11_05_120100_add_unique_matricula_aula_to_frequencia_escolar_table` | Apaga as duplicatas existentes **antes** de criar o índice. Fica a linha mais informativa: a que tem situação lançada; no empate, a que tem justificativa, abono ou atestado; no empate, a mais recente (e, por fim, a de maior id). Os ids apagados vão para o log (`frequencia_escolar: registros duplicados removidos…`). |
| `Matricula::matriculadasEm($data)` | Escopo único da regra "estava matriculado na data": ativada até ela (ou sem data de ativação) e desativada depois dela (ou sem data de desativação). A desativação vale **a partir** do dia, igual a `Turma::matriculasAtivasCount()`. |
| `LancarFrequencia` | Só cria linha para quem estava matriculado na data da aula; usa `createOrFirst`, que devolve a linha existente se outra aba a criou no meio; lê os nomes da turma uma vez por requisição. |
| `PendenciasFrequencia`, `FrequenciaPendenteWidget` | Passam a usar o escopo (o filtro era idêntico, só estava copiado). |
| `CronogramaAulasTable` (lançamento em lote) | Passa a respeitar a vigência da matrícula; antes aceitava qualquer matrícula da turma. |
| Esquema dos testes | Migration `2026_11_05_120000_make_situacao_nullable_in_frequencia_escolar_for_sqlite` (só roda no SQLite): `situacao` aceita nulo, como na produção. Sem ela, a abertura da chamada para um aluno novo falhava no SQLite e nunca pôde ser testada. |

As outras três gravações (`updateOrCreate`) não mudaram: com o índice único, o `updateOrCreate` do Laravel já é seguro
contra corrida (ele tenta inserir e, se o banco recusar, busca a linha existente).

### Publicação

1. **Antes:** conferir se há duplicatas. Se não houver, a migration só cria o índice.
   ```sql
   SELECT matricula_id, cronograma_aula_id, COUNT(*) AS linhas
   FROM frequencia_escolar GROUP BY matricula_id, cronograma_aula_id HAVING COUNT(*) > 1;
   ```
2. Tirar um backup do banco: a migration **apaga linhas** e o `down()` só remove o índice, as linhas apagadas não voltam.
3. `php artisan migrate`. A ordem em relação ao código é indiferente: o código novo funciona sem o índice (só perde a
   proteção) e o índice não atrapalha o código antigo.
4. Depois: o percentual de frequência de alunos que estavam duplicados pode mudar (para o correto). O log de aplicação lista
   os ids removidos.

### Como foi validado

- SQLite (testes): `tests/Feature/FrequenciaEscolarUnicaTest` (unicidade, limpeza com os quatro critérios de desempate,
  chamada que não duplica ao abrir de novo, chamada com corrida simulada, vigência da matrícula, leitura única da turma,
  pontas do escopo). O teste da corrida foi conferido contra o código antigo: com `create()` ele falha.
- MariaDB 10.4 descartável (cópia do esquema local, nunca a produção): duplicatas reais apagadas como esperado, erro 1062 ao
  inserir duplicata, `migrate:rollback` remove o índice e reaplicar funciona.

### Limite conhecido

O índice comum de mesmas colunas (de 2026-08-23) ficou redundante com o único. Não foi removido: soltar índice que sustenta
chave estrangeira em MySQL exige cuidado e o ganho é pequeno. Pode sair numa limpeza futura.

## 2. Fila dos jobs longos

### O problema

A fila do sistema é a tabela `jobs`, com `retry_after` de **90 s**. Seis jobs declaram `timeout` bem acima disso, todos na
fila padrão:

| Job | `timeout` |
|---|---|
| `EnviarComunicacaoEmMassaJob` (`tries = 1`) | 600 s |
| `GerarBoletinsTurmaPdfJob`, `GerarCrachasTurmaPdfJob`, `GerarFolhasTurmaPdfJob`, `GerarInformeAnualLotePdfJob` | 300 s |
| `ProcessarGabaritoOmrJob` | 180 s |

Passados os 90 s, a fila entrega o job a outro worker **com o primeiro ainda rodando**. O resultado possível é o PDF gerado e
a notificação enviados em dobro, ou o envio em massa marcado como falho por tentativas enquanto ainda manda mensagens. E, como
o worker da fila padrão atende um job por vez, um PDF de 5 minutos atrasava e-mails e notificações. O job de IA tinha o mesmo
problema e já fora resolvido com uma fila e um worker próprios (`crm.fila_ia`), com `timeout` de 85 s *abaixo* do `retry_after`.
Os jobs longos não cabem nessa solução (precisam de minutos), então o `retry_after` é que muda, só para eles.

### O que mudou

- **Conexão `database_longa`** (`config/queue.php`): mesma tabela `jobs`, fila `longas`, `retry_after` de **720 s**
  (acima do maior `timeout`, 600 s). A conexão padrão não muda (90 s), então um job travado nela continua voltando à fila em
  90 s.
- **`App\Support\FilaLonga::direcionar($this)`** no construtor dos seis jobs manda o job para essa conexão e essa fila. Só
  vale com o driver `database`: com `sync` (testes) ou outro driver o job segue a conexão padrão, como antes.
- **Worker agendado** em `routes/console.php`: `queue:work database_longa --queue=longas --stop-when-empty --tries=3
  --timeout=600 --max-time=55`, a cada minuto, em segundo plano, com `withoutOverlapping(15)` (o mutex dura mais que o maior
  job, para uma segunda execução não entrar no meio). O worker da fila padrão e o da IA não mudam.
- **Botão *Processar Fila Agora*** (`QueueSupervisorWidget`) também inicia um worker da fila longa. Sem isso os jobs longos
  ficariam parados por ali, porque o worker da fila padrão não atende outra conexão.
- **`OMR_FILA`**: se alguém definiu outro nome que não `default`, `ProcessarGabaritoOmrJob` continua nele (na conexão padrão);
  sem escolha vai para a fila longa.
- **Teste de guarda** (`tests/Feature/FilaLongaTest`): todo job em `app/Jobs` com `timeout` precisa caber no `retry_after` da
  conexão em que roda. Job novo com `timeout` de 90 s ou mais que não chame `FilaLonga::direcionar()` quebra o teste.
- **Variáveis** (`.env.example`, todas opcionais): `DB_QUEUE_LONGA` (padrão `longas`) e `DB_QUEUE_LONGA_RETRY_AFTER`
  (padrão `720`; não baixar de 660).

### Publicação e operação

- **Com `schedule:run` a cada minuto no cron** (o desenho do sistema): nada a fazer; o agendador inicia o worker novo.
- **Com Supervisor ou serviço próprio** para a fila: subir também
  `php artisan queue:work database_longa --queue=longas --tries=3 --timeout=600`. Sem worker nessa fila, os jobs longos
  novos ficam na tabela `jobs` sem serem processados.
- Jobs que estavam na fila padrão no momento da publicação terminam normalmente pelo worker antigo.
- Jobs que falharam na fila longa ficam em `failed_jobs` com a conexão `database_longa`; `queue:retry` os reenvia para a fila
  certa.
- A fila longa tem **um worker**: os jobs longos rodam um de cada vez (um envio em massa de 10 minutos segura os PDFs
  atrás dele). É melhor que antes, quando seguravam também os e-mails. Se virar gargalo, suba um segundo worker: o
  `retry_after` já comporta.
- O `timeout` do job só é imposto onde há a extensão `pcntl` (Linux, a produção). No Windows de desenvolvimento ele não
  vale; é só um limite de projeto.

### Como foi validado

`tests/Feature/FilaLongaTest` (10 testes): roteamento dos seis jobs com a fila no banco e com `sync`; o job cai na fila `longas`
da tabela `jobs`, o worker da fila padrão **não** o enxerga e o da conexão longa enxerga; guarda de `timeout` contra
`retry_after`; o agendador lista o worker novo sem tirar os outros; o botão do widget.

### Limite conhecido

`GerarFolhasTurmaPdfJob` e `ProcessarGabaritoOmrJob` não são despachados por nenhuma tela hoje (só pelo teste
`OmrGabaritoPdfTest`); a documentação de OMR dizia o contrário e foi corrigida. Ficam na fila longa para quando forem ligados.
