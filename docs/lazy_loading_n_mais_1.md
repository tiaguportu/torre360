# Lazy loading (N+1): como evitar e como auditar

## Por que isso importa
Ler uma relação que não foi carregada junto com a consulta (`$fatura->itens`, `$interessado->usuario`...) faz o Eloquent
executar **uma query por registro** (problema N+1). O Laravel pode transformar isso em erro com
`Model::preventLazyLoading()`, que lança `LazyLoadingViolationException` quando uma relação é carregada sob demanda em
um modelo vindo de uma coleção.

## Quando o modo estrito está ligado
Em `AppServiceProvider::boot()`:

```php
Model::preventLazyLoading(app()->environment('testing') || (bool) config('database.prevent_lazy_loading'));
```

- **Testes:** sempre ligado, então um N+1 novo quebra o teste em vez de passar despercebido.
- **Demais ambientes:** desligado por padrão. Para ligar em desenvolvimento, use `DB_PREVENT_LAZY_LOADING=true` no `.env`.
- **Por que não `! app()->isProduction()`:** o servidor pode rodar com `APP_ENV` diferente de `production` (o `.env` local
  tem `APP_ENV=local` e `APP_DEBUG=true`, e a produção também parecia estar em modo de depuração). Nesse caso, a regra
  ligaria o modo estrito para os usuários e qualquer N+1 viraria erro 500. Em produção, uma relação carregada sob demanda
  só custa queries, nunca deve derrubar a página.

Os pontos abaixo foram corrigidos e os testes dessas áreas rodam em modo estrito.

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
| `ContratosTable` (coluna de signatários) | `Contrato::getSignatarios()` lê `matricula.pessoa.responsaveis.users`, `matricula.turma.serie.curso.unidade.representantesLegais.users` e `responsaveisFinanceiros.pessoa.users` | `modifyQueryUsing(... ->with([...]))` com esse mesmo conjunto, que `AssinafyService::enviarContrato` já carrega |

Os demais usos de `label_exibicao` em seleções de matrícula (`PreceptoriaForm`, `Portal/Preceptoria`,
`AgendarPreceptoria`) já carregavam as relações.

## Pendente
- **Teste da lista de contratos ainda desliga o modo estrito.** O eager loading da `ContratosTable` já está feito, mas
  `AssinafyAssinaturaTest::lista_de_contratos_exibe_cada_etapa_com_seu_proprio_rotulo` mantém
  `Model::preventLazyLoading(false)`. Para a lista passar a ser protegida contra regressões, remova essa linha e rode o
  teste (com dados de mais de um contrato, como ele já cria); se ainda houver violação, o nome da relação aparece na
  exceção. A correção da tabela foi conferida só pela leitura do código, sem rodar esse teste.
- **`TipoVinculo` por linha.** `Contrato::getSignatarios()` consulta `TipoVinculo` (Pai/Mãe) a cada chamada, ou seja, uma
  query por contrato listado. Não é N+1 de relação (o modo estrito não acusa) e fica fora do eager loading; um cache
  desses ids resolveria.

## Como proteger uma área com teste
Como o modo estrito já é ligado em todos os testes, basta criar dados com **mais de um registro** (senão o N+1 não
aparece). O trait `Tests\Concerns\ProibeLazyLoading` continua disponível para garantir o modo estrito mesmo se a regra
global mudar: ele liga durante cada teste e restaura o padrão no final. Já é usado por `ConciliacaoCreditoFaturaTest`,
`FechamentoCicloServiceTest`, `InteressadoTest`, `InteressadoComunicacaoBulkActionTest`,
`PreceptoriaMatriculaFilterTest` e `SecurityHardeningTest`.

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
