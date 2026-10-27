# Reuniões e atas (Ondas 27 e 32) — Entrega unificada

> **Status: implementadas** em 2026-10-28, como uma entrega só. Os documentos originais continuam como registro da proposta:
> [Conselho de Classe e Atas (27)](conselho_classe_atas_roadmap.md) e [Reuniões com Professores (32)](reunioes_professores_roadmap.md).
> Continua no [índice das ondas 27 a 48](ondas_27_a_48_indice.md).

## Por que uma entrega só

O conselho de classe é uma reunião com deliberação por aluno; a reunião com professores é uma reunião com tarefas combinadas. As duas
propostas pediam agendamento, convite, reserva de sala, presença, ata, encaminhamentos com responsável e prazo, e lembrete de vencimento.
Em vez de duas famílias de tabelas, há **uma reunião** com um **tipo**; o conselho de classe é o tipo que ganha a turma, a etapa e a pauta
aluno por aluno.

## O que foi entregue

| Peça | Onde fica | O que faz |
|---|---|---|
| Reuniões e atas | Acadêmico → **Reuniões e atas** | Lista, marca, remarca, cancela e acompanha a reunião até a ata fechada |
| Meus encaminhamentos | Acadêmico → **Meus encaminhamentos** | O que ficou combinado, com responsável e prazo; a coordenação enxerga também os da equipe |
| Pauta do conselho | Aba **Alunos** da reunião (só no conselho de classe) | Cada aluno da turma com a fotografia dos indicadores e a deliberação |
| Ata em PDF | Botão **Baixar PDF da ata** | Participantes, pauta, texto, deliberações por aluno, encaminhamentos, adendos e linhas de assinatura |
| Assinatura da ata | Botão **Enviar para assinatura** | A ata fechada segue pelo fluxo de assinatura eletrônica (Onda 69/74) e a cópia assinada volta para a reunião |
| Fila da Minha Mesa | Minha Mesa | *Meus encaminhamentos de reunião*, *Atas de reunião por fechar* e *Encaminhamentos vencidos da equipe* |
| Fonte de relatório | Meus Relatórios | *Encaminhamentos de reuniões* (Onda 72), para a coordenação |
| Lembrete de prazo | Agendador, 07:15 | Avisa uma vez o responsável e a coordenação de cada encaminhamento vencido |

## Como a reunião anda

1. **Agendada.** Título, tipo, data, hora de início e de fim (se não vier, usa a duração padrão do tipo), espaço ou local, participantes e
   pauta. Quem é convidado recebe um aviso e responde **Vou participar** ou **Não posso ir**; a recusa avisa quem organiza. Escolher um espaço
   **reserva o horário** nele (Onda 21): se estiver ocupado, nada é gravado e a tela diz o motivo. Mudar o horário pede nova resposta de todos e
   avisa; mudar só o título ou a pauta, não.
2. **Realizada, ata em aberto.** Depois do horário de início, quem organiza marca a reunião como realizada e **marca quem esteve presente**. A
   partir daí escreve-se a ata (rascunho, quantas vezes for preciso) e, no conselho, delibera-se sobre cada aluno.
3. **Ata fechada.** Para fechar é preciso ter registrado a presença, ter escrito a ata (no conselho a pauta por aluno faz as vezes do texto) e, no
   conselho, ter **deliberado sobre todos os alunos**. Fechada, a ata **não muda mais**: correção ou complemento entram como **adendo**, com o nome
   de quem escreveu e a data. Os participantes recebem um aviso. Novo combinado depois do fechamento também vai no adendo.
4. **Cancelada.** Só a reunião ainda agendada, com motivo; libera o espaço e avisa quem foi convidado.

## Tipos de reunião e sigilo

| Tipo | O que tem de especial |
|---|---|
| Conselho de classe | Turma e etapa avaliativa; pauta aluno por aluno; presidente (em geral o professor conselheiro); uma por turma e etapa |
| Reunião pedagógica, formação, outra | Só o que é comum |
| Devolutiva individual | Entre quem organiza e **uma única pessoa**; é **sempre sigilosa** |

**Reunião sigilosa** (a devolutiva e qualquer outra marcada como confidencial) só aparece para quem **organiza** e para quem **participa**. Nem
a coordenação com *Ver todas* a vê, nem o super administrador: o filtro é da consulta, não da política (o `Gate::before` daria `true` ao super
administrador). Além disso, a reunião sigilosa:

- aparece na **reserva do espaço** só como "Reunião" (as reservas são visíveis a todos os papéis com acesso);
- não leva o assunto nos **avisos** nem nas filas da Minha Mesa (aparece o tipo, "Devolutiva individual");
- não leva o lembrete de prazo à coordenação e fica de fora da fonte de relatório;
- **não segue para a assinatura eletrônica**: o documento ficaria visível a quem tem `View:DocumentoAssinatura`. O PDF da ata baixa normalmente.

A reunião aberta é vista por quem organiza, por quem participa e, com `VerTodas:Reuniao`, pela coordenação.

## A pauta do conselho de classe

Ao marcar o conselho, o sistema monta uma linha para cada **aluno com matrícula ativa** na turma, em ordem alfabética, e **fotografa** os
indicadores do dia (a ata registra o que o conselho viu, mesmo que a nota mude depois):

| Indicador | De onde vem |
|---|---|
| Média geral e disciplinas abaixo da média | Boletim da etapa (`BoletimService`) contra a **nota de aprovação do período letivo** |
| Frequência e número de faltas | Presenças e faltas das aulas do boletim, acumuladas desde o início do período |
| Ocorrências (quantidade e tipos) | Ocorrências disciplinares dos últimos 120 dias |
| Parecer descritivo | Há parecer **publicado** na etapa? |
| Risco de evasão | Pontuação da Onda 13 |

A aba traz esses números com destaque para o que preocupa (média abaixo da nota de aprovação, frequência abaixo de 75%). **Atualizar indicadores**
refaz a fotografia de quem ainda não foi deliberado e inclui quem entrou na turma depois; quem já foi deliberado mantém o que viu.

### Deliberação

Cada aluno recebe uma decisão do conselho, escolhida na lista de `config/reunioes.php`: *Segue sem pendências*, *Destaque a reconhecer*,
*Acompanhamento pedagógico*, *Indicar recuperação*, *Chamar a família* e *Encaminhar a apoio especializado*. As quatro últimas **pedem um
encaminhamento** (o que será feito, responsável e prazo; sem prazo, 15 dias). Rever a deliberação troca a decisão e apaga o encaminhamento que
ela havia gerado, se ainda estiver aberto.

**A deliberação não altera nota, frequência nem a matrícula.** Ela só registra a decisão e gera o encaminhamento; há teste que confere as
notas e a situação da matrícula antes e depois.

Quem delibera: quem organiza o conselho, a coordenação (com *Ver todas*) e o **presidente**, desde que tenha `Deliberar:Reuniao`, e só
**enquanto a reunião está em andamento** (começou e a ata não foi fechada).

## Encaminhamentos

O que ficou combinado, com responsável e prazo. **Vencido** não é uma situação: é um encaminhamento aberto cujo prazo já passou. Quem organiza
ou coordena a reunião registra, conclui e cancela (com motivo); o **responsável** conclui o que é seu, mesmo sem ter participado. Prazo no
passado é recusado. O responsável recebe um aviso quando o encaminhamento nasce.

Todo dia às 07:15 o agendador (`reunioes:lembrar-encaminhamentos`) avisa, **uma vez só**, o responsável, quem organizou e a coordenação sobre
cada encaminhamento vencido. Reunião cancelada não gera lembrete, e a sigilosa não leva o aviso à coordenação.

## Minha Mesa

| Fila | Quem vê | Atrasado quando |
|---|---|---|
| Meus encaminhamentos de reunião | `ViewAny:Reuniao` (cada um vê os seus) | O prazo passou |
| Atas de reunião por fechar | `Update:Reuniao` (quem organiza e a coordenação, menos as sigilosas dos outros) | A reunião foi realizada há mais de `ata_prazo_dias` (5) |
| Encaminhamentos vencidos da equipe | `VerTodas:Reuniao` (sem os da própria pessoa e sem os de reunião sigilosa) | Toda a fila está atrasada |

## Permissões

Criadas pela migration `2026_10_28_100100_create_reunioes_permissions`. Papéis que a escola não criou são ignorados; um papel feito à mão
precisa recebê-las em **Perfis de acesso**.

| Permissão | Quem recebe |
|---|---|
| `ViewAny`, `View:Reuniao` | administração, secretaria, coordenação, professor (cada um só vê o que organiza ou em que participa) |
| `Create`, `Update:Reuniao` | administração, secretaria, coordenação |
| `VerTodas:Reuniao` | administração e coordenação |
| `Deliberar:Reuniao` | administração, coordenação e professor |

A reunião **não se exclui**: cancela-se (com motivo) ou fecha-se a ata, para o histórico e a reserva do espaço não se perderem.

Os botões de cada passo conferem a permissão **e** a situação da reunião (o super administrador passa por qualquer habilidade da política);
o serviço recusa o que a regra não permite.

## Parâmetros

Em `config/reunioes.php` (sem variável de ambiente):

| Chave | Padrão | Para quê |
|---|---|---|
| `deliberacoes` | seis itens (acima) | Decisões que o conselho pode tomar sobre o aluno |
| `deliberacoes_com_encaminhamento` | quatro itens | Quais pedem encaminhamento |
| `ata_prazo_dias` | 5 | Dias, depois de realizada, até a ata em aberto ser cobrada na Minha Mesa |
| `encaminhamento_prazo_dias` | 15 | Prazo sugerido do encaminhamento criado pela deliberação |
| `duracao_padrao_minutos` | 60 (conselho 90, devolutiva 30, formação 120) | Fim da reunião quando não é informado |
| `janela_ocorrencias_dias` | 120 | Quanto tempo atrás as ocorrências entram na pauta |

## Tabelas

`reunioes` (inclui as colunas da cópia assinada), `reuniao_participantes`, `reuniao_alunos` (pauta do conselho, com os indicadores em JSON),
`reuniao_encaminhamentos` e `reuniao_adendos`, criadas pela migration `2026_10_28_100000_create_reunioes_tables`. Dicionário de dados em
`GEMINI_DB.md`, seção 51. O log de atividades registra o fluxo da reunião, **nunca a pauta nem o texto da ata**.

## Decisões em relação às propostas

| Proposta | O que foi feito | Por quê |
|---|---|---|
| Tabelas separadas (`ConselhoClasse*`, `ReuniaoEquipe*`) | Uma só família, com `tipo` | O ciclo de vida é o mesmo; duplicar dobraria telas, avisos e filas |
| `ReuniaoAta` / `ConselhoClasseAta` como tabela | Texto na própria reunião, `ata_fechada_em` e a tabela de adendos | A ata é um texto por reunião; o que muda depois é o adendo |
| Status "vencido" no encaminhamento | Derivado (aberto + prazo passado) | Evita o estado desatualizado quando ninguém abre a tela |
| Pauta ordenada por risco, deliberação em "modo reunião" (uma tela por aluno, teclado) | Tabela em ordem alfabética com um botão **Deliberar** por aluno | Entrega o essencial; o modo reunião é refinamento de tela (ver adiante) |
| Lembrete pelo digest da Onda 47 | Comando próprio diário + fila da Minha Mesa | A Onda 47 não existe; quando existir, as filas já são a fonte dela |
| Painel da coordenação "por pessoa" | Fila da Mesa e **Meus encaminhamentos** sem o filtro "Somente os meus" | Reaproveita as telas existentes |
| Dados clínicos só a quem tem permissão | A pauta **não traz** dado clínico | Só nota, frequência, ocorrência, parecer publicado e risco |
| Deliberação "pode alimentar a recuperação no fechamento do ciclo" | O encaminhamento fica na reunião; **não** alimenta o fechamento | A Onda 28 é quem recebe os encaminhamentos |

## Achados da implementação

| Achado | Onde | O que foi feito / o que fazer |
|---|---|---|
| **`etapa_avaliativa.turma_id` existe no SQLite dos testes (obrigatório) e não existe em MySQL**: a migration `2026_03_27_224946` a remove fora do SQLite, e lá a etapa é do período letivo, igual para todas as turmas dele. A primeira versão filtrava por `turma_id` e **quebraria em produção**; só o roteiro no MariaDB mostrou. É a mesma divergência de `dia_nao_letivo.curso_id` (índice das ondas 72 a 79). | Pauta do conselho | `EtapaAvaliativa::daTurma()` usa a coluna quando ela existe e o período letivo quando não; o formulário só oferece as etapas da turma e o serviço recusa a etapa que não é dela. Teste cobre os dois SQL. **Conferir a produção** com `SHOW COLUMNS FROM etapa_avaliativa`. |
| **Comparação de colunas SQLite × MariaDB** (banco descartável copiado de `torre360_demo` e migrado), feita por causa do achado acima. Cinco colunas existem só no esquema de testes: `dia_nao_letivo.curso_id`, `etapa_avaliativa.turma_id`, `matricula.status`, `pessoa.endereco_id` e `tipo_documento.curso_id` (as migrations as removem fora do SQLite). `PessoaExporter` e `PessoaImporter` ainda citam `endereco_id`, e os models `Pessoa`, `Matricula`, `TipoDocumento` e `DiaNaoLetivo` o têm no `$fillable`; `ParecerDescritivoFactory` grava `etapa_avaliativa.turma_id`. | Esquema | **Não verificado em produção** (só no banco copiado). Conferir com `SHOW COLUMNS` e, se as colunas realmente não existem, tirar os usos (importador e exportador de Pessoa são os que podem falhar) e rodar a suíte também em MariaDB. |
| O `Gate::before` dá `true` ao super administrador para **qualquer** habilidade, até em método de política como `gerenciar`. | Sigilo | O sigilo é filtro da consulta (`Reuniao::visiveisPara`), e as telas conferem a situação da reunião; há teste com super administrador. |
| A relação carregada no `mount` do Livewire **não sobrevive inteira** às chamadas seguintes (a aninhada `participantes.user` volta sem carga e o modo estrito de lazy loading acusa dentro do modal). | Botões da reunião | Os botões consultam os participantes direto. |
| As **reservas de espaço** são visíveis a todos os papéis com acesso. | Reunião sigilosa | A reserva da reunião sigilosa leva só "Reunião", sem descrição nem turma. |
| `View:DocumentoAssinatura` abre **todos** os documentos de assinatura. | Ata sigilosa | Ata sigilosa não vai para a assinatura eletrônica. |
| O PDF da ata do conselho leva **nome e deliberação de menores** e, ao ser enviado para a assinatura, sai para o provedor de assinatura (Assinafy). | LGPD | Mesma situação dos contratos de matrícula já enviados; a escola deve ter o provedor no registro de operadores. |

## Limites desta entrega

- **A reserva do espaço e a reunião não se sincronizam depois de criadas** pela tela de *Reservas de espaços*: se a reserva for cancelada ou
  alterada lá, a reunião continua com o espaço que tinha. Remarcar ou cancelar pela reunião acerta as duas.
- **Sem calendário.** A reunião não aparece no calendário escolar nem no Portal; só na lista, na Minha Mesa e nos avisos.
- **Sem conflito de agenda das pessoas:** o sistema confere o espaço, não se o professor já está em aula ou em outra reunião.
- **Itens de deliberação** ficam em arquivo de configuração, não numa tela de cadastro; mudar a lista exige o deploy.
- **Pauta fotografada, não ao vivo.** Quem entra na turma depois precisa de **Atualizar indicadores**.
- **Sem ligação com a recuperação paralela (Onda 28) nem com o fechamento do ciclo.** O encaminhamento é a ponte, mas a ligação não existe.
- **Desempenho da pauta:** o conselho de uma turma chama o boletim aluno por aluno. Em turma grande, marcar o conselho leva alguns segundos.

## Como foi verificado

- **Testes novos (75):** `ReuniaoServiceTest` (33: agendar, convite, remarcar, realizar, ata e adendo, cancelar, encaminhamentos, lembrete,
  PDF e assinatura), `ConselhoClasseTest` (17: pauta, indicadores, deliberação, sugestão de participantes, esquema sem `turma_id`) e
  `ReunioesTelasTest` (25: quem enxerga o quê, sigilo, formulário, botões de cada passo, abas, *Meus encaminhamentos*, filas da Minha Mesa,
  relatório e rotina diária).
- **Suíte completa** (cerca de 3.300 testes, em 6 processos paralelos): sem falha além das de duas edições que fiz **durante** a execução
  (um processo já tinha carregado `EtapaAvaliativa` antiga quando carregou o serviço novo; 19 erros `daTurma()` indefinido, todos das três
  classes novas). Rodadas de novo no código final, as três classes passam (75 testes). **Não rodei a suíte inteira de novo depois disso**:
  as mudanças entre a execução e o fim foram o `scopeDaTurma`, o `stackedOnMobile` das abas e a lista de participantes mantida na remarcação,
  cobertas pelas três classes. Os testes de proteção (ajuda por tela, Minha Mesa, relatórios, agendador, uploads) passaram; o de uploads
  que falhava na `main` (`CandidatoForm`) foi corrigido por outro commit que entrou antes do rebase.
- **MariaDB 10.4 (banco descartável copiado de `torre360_demo`, nunca a produção):** as duas migrations aplicaram e um roteiro de fumaça
  rodou ponta a ponta: reunião com sala, remarcação (a reserva acompanha), cancelamento (a reserva é liberada), conselho com a turma de um aluno
  real (9 alunos, indicadores, deliberação com encaminhamento, ata fechada, adendo, PDF), lembrete de prazo (uma vez só), sigilo, as três filas
  da Minha Mesa e a fonte de relatório. **Foi esse roteiro que mostrou a ausência de `etapa_avaliativa.turma_id`.** O banco foi apagado depois.
- **Conferência visual** (Chrome headless sobre o HTML renderizado, desktop e 360 px): lista, tela da reunião com as seções e as duas abas
  (renderizadas à parte, porque sem o JavaScript do Livewire a aba fica vazia). Corrigiu a tabela de alunos, que no celular escondia a ação
  *Deliberar* (agora empilha) e o ícone vermelho de "sem parecer", que parecia alarme. **Limite:** o CSS compilado da máquina é antigo e a captura
  é do HTML sem scripts.
- **Não foi verificado:** o envio real da ata ao Assinafy (só a criação do documento, com o provedor simulado); a aparência com o CSS
  recompilado e no tema escuro; o tempo de montar a pauta numa turma de 30 a 40 alunos com dados reais; o comportamento com o JavaScript
  em funcionamento (modais de presença, ata e deliberação foram testados pelo Livewire, não no navegador); e as permissões em perfis feitos à mão.

## O que continua proposto

- **Onda 28 (Reforço e Recuperação Paralela):** passa a ter de onde receber os encaminhamentos do conselho.
- **Modo reunião** do conselho (uma tela por aluno, teclado, pauta ordenada por risco) e **cadastro dos itens de deliberação**.
- **Sincronizar** a reserva de espaço com a reunião e mostrar a reunião no **calendário** de quem participa.
- Entrega pela **Central de Avisos** (Onda 70) e pelo **digest** (Onda 47) quando existirem.
