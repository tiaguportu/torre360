# Cartões de resumo no topo das listagens (Matrículas e Ordens de Serviço)

## Sintoma
Ao interagir com a lista (ex.: clicar na coluna **Pendências** em `/admin/matriculas`), a requisição
`POST /livewire-.../update` falhava com:

```
TypeError: Cannot assign null to property App\Filament\Resources\Matriculas\Widgets\MatriculasResumoStats::$tableColumnSearches of type array
```

## Causa
Os cartões eram um widget (`StatsOverviewWidget`) com o trait `InteractsWithPageTable`, registrado em
`getHeaderWidgets()`. Esse trait cria um **componente Livewire filho** com várias propriedades
`#[Reactive]` (`tableColumnSearches`, `tableFilters`, `activeTab`...), preenchidas pela página a cada
requisição. Em `/livewire/update`, o Livewire (`BaseReactive::hydrate`) tentou gravar `null` na
propriedade tipada `array $tableColumnSearches` do filho e estourou o `TypeError`. O motivo de o pai
entregar `null` não foi determinado (Filament 5.9 + Livewire 4.4.7); não reproduz em teste porque a
atualização do filho é disparada pelo navegador.

## Solução
Nada de componente filho: os cartões são renderizados **dentro da própria página**, reaproveitando os
mesmos componentes `Stat` do Filament, e leem o estado da lista direto de
`$this->getFilteredTableQuery()` (aba, busca e filtros em uso). Não há props reativas entre componentes.

- `app/Filament/Resources/Matriculas/Pages/ListMatriculas.php`
- `app/Filament/Resources/OrdemServicoResource/Pages/ListOrdemServicos.php`

Em cada página:

```php
public function content(Schema $schema): Schema
{
    return $schema->components([
        $this->getResumoContentComponent(),   // Section com os Stat, contained(false) + gridContainer()
        $this->getTabsContentComponent(),
        RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
        EmbeddedTable::make(),
        RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
    ]);
}

public function getResumoStats(): array   // público para ser testável
{
    $query = $this->getFilteredTableQuery()->reorder();
    // ... (clone $query)->count(), etc.
    return [Stat::make('Título', $valor)->description('...')->color('...')];
}
```

Se a página já define `content()`, basta inserir o componente de resumo antes dos demais.

## Ao criar cartões em outra listagem
- Preferir este padrão a `getHeaderWidgets()` + `InteractsWithPageTable`.
- Cada cartão é uma query a mais por requisição Livewire da página; manter as contagens simples ou usar
  scopes do model (ex.: `Matricula::comPendencias()`).
- Testar com `Livewire::test(MinhaLista::class)->instance()->getResumoStats()` e checar que o HTML não
  contém a classe de um widget filho (`MatriculaListagemTest`, `OrdemServicoListagemTest`).

## Diagnóstico se voltar a ocorrer
- Procurar por outros widgets com `InteractsWithPageTable` (`grep -R "InteractsWithPageTable" app`).
- Conferir as versões do Filament e do Livewire em uso; o problema apareceu com Filament 5.9 e Livewire 4.4.7
  e pode se comportar de forma diferente após atualizações.
