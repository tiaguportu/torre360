# Geração de PDFs em lote (boletins e crachás da turma)

Os PDFs em lote rodam em fila, nos jobs `GerarBoletinsTurmaPdfJob` e `GerarCrachasTurmaPdfJob`. O usuário que pediu a
geração recebe uma notificação do sistema com o resultado.

## Resultado para o usuário
| Situação | Notificação |
|---|---|
| PDF gerado | `success`, com o link de download |
| Sem dados (boletins) ou sem alunos ativos (crachás) | `warning` |
| Erro depois de esgotar as tentativas | `danger`: "Ocorreu um erro inesperado... tente novamente" |

## Tentativas, tempo limite e falha
- `tries = 3`, com `backoff` de 30s, 120s e 300s.
- `timeout = 300` (5 minutos) por tentativa. O padrão do `queue:work` do Laravel é 60 segundos, pouco para renderizar o
  PDF de uma turma grande.
- `failed(Throwable)` é chamado quando as tentativas acabam: registra o erro no log (com `turma_ids`, `user_id` e o
  `etapa_id` ou `template_cracha_id`) e avisa o usuário. Antes, a falha era silenciosa e o usuário ficava esperando.

## Crachás: uma consulta só
`GerarCrachasTurmaPdfJob` busca as matrículas ativas de todas as turmas de uma vez
(`Matricula::whereIn('turma_id', ...)->with(['pessoa', 'turma'])`), em vez de uma consulta por turma. Matrículas sem
pessoa são ignoradas. Ver também [lazy_loading_n_mais_1.md](lazy_loading_n_mais_1.md).

## Testes
`tests/Feature/Jobs/GerarBoletinsTurmaPdfJobTest.php` e `GerarCrachasTurmaPdfJobTest.php` cobrem a notificação de erro
chamando `failed()` diretamente.
