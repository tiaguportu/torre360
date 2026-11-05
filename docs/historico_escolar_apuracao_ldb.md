# Histórico Escolar: apuração automática do resultado (LDB, art. 24)

> **Status: implementado.** Uso pela secretaria na seção 40.3 do `MANUAL_USUARIO.md`; valores novos de `situacao_ano` e
> `situacao` em `GEMINI_DB.md` (`historico_escolar_anos` e `historico_escolar_disciplinas`).
> Código: `HistoricoEscolarService::sincronizarMatriculasInternas()` e `resources/views/pdfs/historico_escolar.blade.php`.

## O problema

A sincronização das matrículas internas com o histórico tinha quatro defeitos:

1. A nota de corte era fixa em 6,0, ignorando `periodo_letivo.nota_aprovacao` (padrão 7,00).
2. O exame final (`media_final_pos_exame`, `situacao_final_pos_exame`) era ignorado: a disciplina aparecia com a média antiga.
3. A situação do ano era decidida antes das disciplinas e caía em `Aprovado` por padrão, aprovando quem reprovou.
4. Não havia reprovação por infrequência nem progressão parcial (dependência).

## Regras aplicadas

**Disciplina** (`apurarDisciplina`):

| Caso | Nota gravada | Situação |
|------|--------------|----------|
| Exame final lançado, com `situacao_final_pos_exame` | `media_final_pos_exame` | `Aprovado` se a situação for `APROVADO`, senão `Reprovado` |
| Média pós-exame sem a situação gravada | `media_final_pos_exame` | compara com `nota_aprovacao_pos_exame` (padrão 5,0) |
| Sem exame | `media_final` (ou a calculada pelo fechamento) | `>= nota_aprovacao` do período: `Aprovado`; senão `Reprovado` |
| Sem nenhuma nota | nula | `Pendente` (não conta como reprovação) |

**Ano** (`apurarSituacaoAno`, calculado depois de gravar as disciplinas, na mesma transação):

| Matrícula | Condição | `situacao_ano` |
|-----------|----------|----------------|
| `concluido` | frequência < 75% | `Reprovado por Faltas` (art. 24, VI; independe das notas) |
| `concluido` | 0 disciplinas reprovadas | `Aprovado` |
| `concluido` | 1 ou 2 reprovadas | `Aprovado com Dependência` |
| `concluido` | 3 ou mais reprovadas | `Reprovado` |
| `ativa` | | `Cursando` |
| `cancelada`, `trancada`, `evasao` | | `Transferido` |

Exatamente 75% cumpre a frequência mínima. A frequência já desconta só as faltas que contam (`contaComoFalta`: falta
abonada vale presença).

## Decisões que merecem conferência

- **Limite de 2 disciplinas** para a dependência está na constante `MAX_DISCIPLINAS_DEPENDENCIA`. Na LDB a progressão
  parcial é matéria do regimento da escola; se o regimento for outro, é um número a trocar (ou, no futuro, uma coluna
  em `curso`/`serie`).
- **Matrícula `reserva` ou `pendente` não gera mais ano no histórico.** Antes caía no `default => 'Aprovado'`, e um aluno
  que ainda não cursou nada saía aprovado em documento oficial. Linhas já criadas por sincronizações antigas não são
  apagadas.
- **Disciplina `Pendente` não impede `Aprovado`.** Um ano concluído com disciplina sem nota fecha como `Aprovado` se não
  houver reprovação; a secretaria é avisada no manual para conferir antes de emitir.
- **Recuperação sem exame lançado** (período com exame final habilitado, mas exame ainda não registrado) conta como
  `Reprovado` no histórico, porque a média está abaixo do corte. Lance o exame antes de sincronizar.
- O `6.0` fixo também existia no PDF (nota em vermelho); agora o vermelho segue a `situacao` da disciplina. Em anos
  *Externo*, a cor passa a depender da situação que a secretaria escolheu, não da nota.

## PDF

A linha "Resultado final do ano letivo" pinta de vermelho `Reprovado` e `Reprovado por Faltas`, de âmbar
`Aprovado com Dependência` e de verde os demais. `montarMatrizTabular()` repassa `situacao_ano` sem alterá-lo.

## Reprocessar os históricos que já existem

O deploy não recalcula nada: os anos gravados antes da apuração (muitos como `Aprovado`) só mudam quando alguém
sincroniza o histórico. Para fazer isso de uma vez há o comando (para a equipe técnica; não é tela):

```
php artisan historico:reapurar-resultados                # simula e mostra o que mudaria, não grava
php artisan historico:reapurar-resultados --aplicar      # grava, depois de confirmar o banco
php artisan historico:reapurar-resultados --historico=12 --historico=40   # só estes históricos
```

- **Simula por padrão.** A apuração roda de verdade dentro de uma transação que é desfeita; a tabela mostrada
  (frequência, resultado do ano antes → depois, disciplinas alteradas) é o que `--aplicar` gravaria. Só entram na tabela
  os anos que mudam.
- **`--aplicar` mostra o banco** (`driver / nome`) e pede confirmação. `--force` pula a pergunta (uso em script). Como o
  `.env` local aponta para produção, leia essa linha antes de confirmar.
- **Cada histórico é atômico:** se um falha, nada dele é gravado, o erro aparece no fim e o código de saída é de falha;
  os demais seguem.
- **Só reapura anos internos que o histórico já tem** (`HistoricoEscolarService::reapurarAnosInternos()`): não cria ano
  novo (quem quiser importar anos que faltam usa **Sincronizar**), não mexe na `ordem` e não toca nos anos externos.
  Atualiza `frequencia_percentual`, as disciplinas da turma e `situacao_ano`.
- **Anos de matrícula em reserva ou pendente** (gravados antes de essas matrículas saírem da sincronização) não são
  alterados: aparecem no fim em "ignorados para revisão manual".
- Reexecutar é seguro: com os mesmos dados o resultado é o mesmo e a tabela sai vazia.

## Testes

`tests/Feature/HistoricoEscolarTest.php`: corte do período (8,0 reprova 7,5; 6,0 aprova 6,5), exame final aprovando e
reprovando, disciplina sem nota, 0/2/3 reprovações, 70% e exatos 75% de frequência, matrícula ativa/cancelada,
reserva/pendente fora do histórico, ressincronização sem duplicar, matriz tabular e PDF. Do comando: simulação sem
gravar, `--aplicar` (preservando a `ordem`), confirmação recusada, não criar ano nem alterar ano externo, ano de
matrícula pendente deixado para revisão e a opção `--historico`.
