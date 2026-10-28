# Paridade de Esquema e Testes em MariaDB (Onda 89) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 90](ondas_80_a_88_indice.md) e é a
> base da [publicação segura (Onda 90)](publicacao_segura_deploy_roadmap.md): é o portão que impede um erro de MySQL de chegar à produção.

## Contexto

O sistema é testado em **SQLite em memória** (`phpunit.xml`) e roda em **MySQL/MariaDB** em produção. Os dois bancos se comportam de
forma diferente em pontos que importam: colunas que existem em um e não no outro, agrupamento (`ONLY_FULL_GROUP_BY`), data em coluna
com cast, tamanho de índice, maiúsculas e minúsculas em nome de tabela e de busca. Um teste verde em SQLite não prova nada sobre o
MySQL. Isso já custou caro: nas últimas semanas o roteiro de fumaça **à mão** em MariaDB achou, em ondas diferentes, código que passa
em toda a suíte (cerca de 3.300 testes) e quebraria em produção.

## O que já existe (conferido no código e rodando)

- **Testes só em SQLite.** `phpunit.xml` fixa `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:`. **Não há integração contínua** (não existe
  `.github`, nem outro arquivo de pipeline) e **nenhuma tag** no repositório. A suíte completa leva de uma a duas horas na máquina de
  desenvolvimento, e a execução paralela com `paratest` entrou há pouco.
- **26 migrations têm ramo por driver** (`getDriverName()` ou `'sqlite'`): renomeações de tabela e remoção de coluna que só rodam fora
  do SQLite. É a origem da divergência abaixo.
- **A cadeia de migrations não roda do zero em MariaDB.** Verificado em 2026-10-28 num banco vazio (MariaDB 10.4.32): as 77 primeiras
  passam e a 78ª, `2026_04_06_164854_create_anotacao_os_table`, falha (`errno 150`, chave estrangeira mal formada). Os bancos reais nasceram
  de forma incremental (existe o comando `db:migrate-to-mysql`, da passagem do SQLite para o MySQL). Para validar uma migration, hoje se
  copia o esquema de um banco existente (`torre360_demo`) para um banco descartável e aplica só as pendentes.
- **Divergência medida** (217 models comparados com um banco MariaDB copiado do demo e migrado até a `main`): **cinco colunas existem só
  no SQLite**, e nenhuma outra diverge no `$fillable`.

| Coluna só no SQLite | Quem ainda a usa | Efeito em MySQL |
|---|---|---|
| `dia_nao_letivo.curso_id` | `Portal/Calendario`, `HorarioAlunoService`, `GradeHorarioService`, `ReservaEspacoConflitoService` (filtros `whereNull('curso_id')`) | Erro `Unknown column` ao montar calendário, horário, grade ou reserva **com turma** (confirmado no banco copiado) |
| `etapa_avaliativa.turma_id` | `ParecerDescritivoFactory`; a Onda 27 já contorna (`EtapaAvaliativa::daTurma`) | Só quebra o que a consulta; a factory só existe nos testes |
| `pessoa.endereco_id` | `PessoaExporter`, `PessoaImporter` e o `$fillable` de `Pessoa` | Exportar ou importar Pessoa pode falhar |
| `matricula.status` | só o `$fillable` (o código grava `situacao`) | Nenhum (coluna morta) |
| `tipo_documento.curso_id` | só o `$fillable` (o vínculo é a tabela `tipo_documento_curso`) | Nenhum (coluna morta) |

**Produção não foi verificada**: os números acima são do banco copiado. Se a produção tiver as colunas, os erros não acontecem; se não
tiver, as quatro telas da primeira linha já falham hoje.
- Já existem **testes de guarda** que travam um tipo de erro inteiro: cobertura de ajuda por tela, permissão das filas da Minha Mesa,
  lista de tipos de arquivo dos uploads e, desde 2026-10-28, política para todo recurso do painel.

## Escopo proposto

### 1. Um esquema de referência que sobe do zero
- Gerar `database/schema/mysql-schema.sql` com `php artisan schema:dump` a partir de um banco MariaDB fiel à produção, e
  **consertar ou neutralizar** a migration 78. A partir daí o banco novo nasce do dump e das migrations posteriores.
- Podar (`--prune`) as migrations antigas só depois de conferir o dump num banco limpo; a poda não tem volta.

### 2. Tirar a divergência
- Decidir **coluna por coluna**: `dia_nao_letivo.curso_id` é calendário por curso (recurso que o formulário nem oferece): ou se recria no
  MySQL, se o produto quer, ou se **tiram os quatro filtros**. As outras quatro saem do código, do `$fillable` e da factory.
- Uma migration "alinha esquema" que remove as colunas fantasma do SQLite, para os testes passarem a ver o que a produção vê.

### 3. Integração contínua com MariaDB
- Pipeline (o repositório está no GitHub) com serviço MariaDB **na versão da produção** (a confirmar) que roda, a cada mudança:
  Pint, os testes de guarda, a suíte em SQLite em paralelo (rápida) e um **subconjunto marcado como `mariadb`** rodando no MariaDB
  (financeiro, matrícula, grade e horários, relatórios, Minha Mesa e as migrations novas).
- O resultado vira **portão**: sem verde, não há publicação (ver Onda 90).

### 4. Teste de paridade de esquema
Comando `schema:comparar` (e teste no CI) que compara `$fillable` e `$casts` de cada model com as colunas reais e **falha** quando um
model promete uma coluna que o banco não tem. É o que achou as cinco acima, transformado em regra permanente.

### 5. Armadilhas do MySQL como lista de conferência
`ONLY_FULL_GROUP_BY` (o MariaDB do desenvolvimento o liga e o SQLite não); coluna com cast `date` que grava `Y-m-d 00:00:00` (consultar com `whereDate`); busca com
acento e maiúscula por collation; `utf8mb4` e tamanho de índice; nome de tabela com maiúscula (**o MySQL em Linux diferencia, o XAMPP em
Windows não**: se o desenvolvimento é em Windows e a produção em Linux, isso é uma classe de erro à parte); `JSON` e `decimal`.

### 6. Procedimento escrito
O roteiro de validar migration em banco descartável (copiar o esquema, apontar as variáveis de ambiente, conferir o nome do banco antes,
apagar depois) vive hoje só na memória de quem o fez. Ele vai para `docs/` e vira script.

## Dependências e riscos

- **Sem a decisão sobre `dia_nao_letivo.curso_id` e sem confirmar o banco da produção, o resto é teoria.** O primeiro passo útil é
  barato: `SHOW COLUMNS` nas cinco tabelas, em produção.
- **Custo e tempo do CI:** a suíte em MariaDB inteira seria lenta (algumas classes semeiam as permissões a cada teste). Por isso o
  subconjunto marcado, e a suíte inteira continua em SQLite.
- **Versão:** MariaDB 10.4 (o do desenvolvimento) e MySQL 8 (se for o da produção) divergem em detalhes; testar na versão real.
- **`schema:dump` e `--prune` são irreversíveis** no histórico: fazer num branch, conferir num banco limpo e só então publicar.
- O dump não pode carregar dado: só esquema e a tabela de migrations.

## Fusão sugerida

**Engenharia de entrega (89 + 90 + 54).** Esta onda dá o **portão de qualidade**; a [Onda 90](publicacao_segura_deploy_roadmap.md) usa o
portão para publicar sem risco; a [Saúde do Sistema (54)](saude_do_sistema_roadmap.md) olha o que acontece depois. Juntas, "publicou" passa
a significar "passou nos testes, tem backup, e dá para voltar".
