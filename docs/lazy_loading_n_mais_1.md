# Lazy loading (N+1): como evitar e como auditar

## Por que isso importa
Ler uma relação que não foi carregada junto com a consulta (`$fatura->itens`, `$interessado->usuario`...) faz o Eloquent
executar **uma query por registro** (problema N+1). O Laravel pode transformar isso em erro com
`Model::preventLazyLoading()`, que lança `LazyLoadingViolationException` quando uma relação é carregada sob demanda em
um modelo vindo de uma coleção. Esse modo estrito fica **ligado em todos os ambientes, exceto produção**
(`AppServiceProvider::boot()`: `Model::preventLazyLoading(! app()->isProduction())`), então um N+1 novo quebra no
desenvolvimento e nos testes em vez de passar despercebido. Em produção continua desligado: uma relação carregada sob
demanda só custa queries, nunca derruba a página. Os pontos abaixo foram corrigidos e os testes dessas áreas passaram a
rodar em modo estrito.

> Só há violação em modelos vindos de **mais de um resultado** (coleção/paginação). Um `find()` ou o primeiro de um
> relacionamento isolado não dispara o erro, o que esconde N+1 quando os dados de teste têm um único registro.

## Pontos corrigidos
| Onde | Relação | Correção |
|---|---|---|
| `ConciliacaoBancariaService` (faturas candidatas) | `Fatura::itens`, `Fatura::transacoes` (usadas por `valor_restante`) | `->with(['itens', 'transacoes'])` |
| `BoletimService` / `FechamentoCicloService` | `CategoriaAvaliacao::substituidas` | `with('categoria.substituidas')` nas consultas e `categoriasDasAvaliacoes()` (`loadMissing`) para qualquer chamador |
| `InteressadosTable` | `Interessado::usuario`, `ultimoHistorico` | `modifyQueryUsing(... ->with(['usuario', 'ultimoHistorico']))` |
| `PreceptoriasTable` (filtro de matrícula) | `Matricula::periodoLetivo`, `turma`, `pessoa` (`label_exibicao`) | `$query->with([...])` na relação do filtro |
| `User::pessoasAcessiveis()` | `Pessoa::alunos` | `$pessoas->loadMissing('alunos')` |

Os demais usos de `label_exibicao` em seleções de matrícula (`PreceptoriaForm`, `Portal/Preceptoria`,
`AgendarPreceptoria`) já carregavam as relações.

## Pendente: lista de contratos (`/admin/contratos`)
> **Atenção:** enquanto isto não for corrigido, em ambientes fora de produção (local, testes) a lista de contratos pode
> lançar `LazyLoadingViolationException` quando houver mais de um contrato na página.

A coluna de signatários (`ContratosTable`) chama `Contrato::getStatusSignatarios()` → `getSignatarios()`, que carrega sob
demanda `responsaveisFinanceiros`, `Pessoa::responsaveis` e `Matricula::turma` (e, com mais de um responsável por
contrato, também `pessoa`/`users`). Correção proposta: eager loading na tabela, com o mesmo conjunto que
`AssinafyService::enviarContrato` já carrega:
`with(['matricula.turma.serie.curso.unidade.representantesLegais', 'matricula.pessoa.responsaveis.users', 'responsaveisFinanceiros.pessoa.users'])`.
`getSignatarios()` também consulta `TipoVinculo` a cada chamada (N+1 por query, não por relação), que merece cache.
Até lá, `AssinafyAssinaturaTest::lista_de_contratos_exibe_cada_etapa...` desliga o modo estrito de propósito.

## Como proteger uma área com teste
Use o trait `Tests\Concerns\ProibeLazyLoading` na classe de teste: ele liga o modo estrito durante cada teste e
restaura o padrão no final. Já é usado por `ConciliacaoCreditoFaturaTest`, `FechamentoCicloServiceTest`,
`InteressadoTest`, `InteressadoComunicacaoBulkActionTest`, `PreceptoriaMatriculaFilterTest` e `SecurityHardeningTest`.
Lembre de criar dados com **mais de um registro**, senão o N+1 não aparece.

## Como auditar (listar todas as violações sem parar na primeira)
Um bootstrap temporário registra cada violação em arquivo em vez de lançar exceção:

```php
// bootstrap-auditoria.php
require __DIR__.'/vendor/autoload.php';

Illuminate\Database\Eloquent\Model::handleLazyLoadingViolationUsing(function ($model, string $relation) {
    file_put_contents('lazy.log', class_basename($model).'->'.$relation.PHP_EOL, FILE_APPEND);
});
```

Rode com `vendor/bin/phpunit --bootstrap bootstrap-auditoria.php --filter <teste>` (com o banco de testes: SQLite em
memória) e agrupe `lazy.log` com `sort | uniq -c`. Em cada linha, o primeiro frame de `debug_backtrace()` dentro de
`app/` indica onde adicionar o `with()`. Nunca rode isso contra o banco de produção.
