# Linha do Tempo 360° (CRM) — arquitetura e layout

Aba **Linha do Tempo 360°** da edição do Interessado (`/admin/interessados/{id}/edit`).
Guia de uso para a equipe: `MANUAL_USUARIO.md`, seção 3.21.

## Peças
| Arquivo | Papel |
|---|---|
| `app/Filament/Resources/Interessados/RelationManagers/TimelineRelationManager.php` | Componente Livewire: filtros (`filtroCategoria`, `termoBusca`), registro rápido de interação, Action de Ajuda. |
| `app/Services/Customer360TimelineService.php` | Agrega contatos, visitas (NPS), documentos (parecer de IA) e auditoria do funil em uma lista única e normalizada (`obterTimeline`) e calcula os indicadores do cabeçalho (`obterResumoMetricas`). |
| `resources/views/filament/resources/interessados/relation-managers/timeline-feed.blade.php` | Toda a interface: cabeçalho com indicadores, registro rápido, filtros/busca e feed agrupado por dia. |

## Permissões
O bloco **Registrar nova interação** só é renderizado quando `podeRegistrar` (propriedade `#[Computed]` do
`TimelineRelationManager`) é verdadeiro: `super_admin`, ou permissão `Update:Interessado`, ou `Create:HistoricoContato`.
O método `registrarContatoRapido()` repete a checagem no servidor (`abort_unless(..., 403)`), então esconder o bloco na
view não é a única proteção. O restante da aba (indicadores, filtros e feed) segue a visibilidade do próprio relation manager.

## Contrato de cada evento do feed
Cada item devolvido por `obterTimeline()` é um array com:

`id`, `registro_id`, `tipo` (`contato`/`visita`/`documento`/`etapa`), `categoria`, `data_hora` (Carbon),
`data_formatada`, `data_relativa`, `titulo`, `subtitulo`, `autor`, `conteudo`, `icone` (nome Heroicon),
`badge`, `badge_cor`, `detalhes` (campos específicos do tipo) e **`tom`**.

`tom` é a cor semântica do evento (`emerald`, `sky`, `purple`, `amber`, `indigo`, `teal`, `violet`, `blue`, `rose`, `gray`).
A view traduz o `tom` em cores (claro/escuro) com a classe `tl360-tone-{tom}`. Os campos `cor_icone` e `bg_icone`
(classes Tailwind) continuam no array por compatibilidade, mas **não são usados na renderização**.

Para criar um novo tipo de evento: devolva o array com o contrato acima em um novo `coletar*()` do serviço, informe
`icone` (Heroicon) e `tom`, e, se precisar de um bloco próprio dentro do card, trate o `tipo` na view.

## Convenções de layout (importante)
O painel admin **não compila Tailwind próprio**: `public/css/filament/admin/theme.css` é um CSS estático e o
`app.css` do Filament só contém as classes usadas pelas views do próprio Filament. Classes utilitárias como
`rounded-2xl`, `bg-indigo-50`, `space-y-6` ou `w-12` **não existem** no CSS servido e a tela fica sem estilo
(o primeiro layout desta aba quebrou exatamente por isso). Por isso a view segue o padrão do Kanban:

- CSS escopado em um bloco `<style>` dentro da própria view, com prefixo `tl360-` e uma única raiz `.tl360`.
- Cores via variáveis do Filament (`--gray-*`, `--danger-*`) e variáveis locais `--tl-*`; modo escuro com `.dark .tl360`.
- Tons: `.tl360-tone-{nome}` define `--tl-rgb` (claro e escuro) e daí saem `--tl-c` (cor), `--tl-bg` (fundo suave) e
  `--tl-bd` (borda).
- Componentes nativos do Filament para o que precisa parecer nativo: `x-filament::input.wrapper`, `input`, `input.select`,
  `badge`, `button`, `icon` (ícones sempre pelo `x-filament::icon`; o tamanho é definido no CSS da view).
- Responsividade por **container queries** (`@container`) nos wrappers `.tl360-cq` e no card do evento, porque a largura
  útil muda com a sidebar aberta/fechada; `@media` só onde a viewport importa (rolagem dos filtros no celular).
- Não aplicar `container-type` na raiz: ele cria um bloco de contenção para elementos `position: fixed` e quebraria o modal
  de Ajuda.
- Cuidado com a ordem das regras: um `@container` precisa vir **depois** da regra base do mesmo seletor (mesma
  especificidade, vence a última).

## Feed
- Eventos ordenados do mais recente ao mais antigo e agrupados por dia (`Hoje`, `Ontem`, `Amanhã` ou data por extenso em
  pt-BR); dias futuros (visitas agendadas) recebem a marca **Agendado**.
- Uma linha vertical contínua (`.tl360-feed::before`) liga os nós; os nós e as etiquetas de dia têm fundo opaco para
  esconder a linha por trás.
- `wire:key` em dias, eventos, canais e filtros para o Livewire fazer o morph da lista corretamente.

### Paginação progressiva ("Carregar mais")
- O feed mostra `TimelineRelationManager::EVENTOS_POR_LOTE` (20) eventos por vez. A propriedade pública `limiteEventos`
  guarda quantos estão visíveis e `carregarMais()` soma outro lote; a view faz `take($livewire->limiteEventos)` sobre a
  lista já filtrada/ordenada e agrupa por dia só o que vai aparecer.
- O limite volta ao primeiro lote em `filtrar()`, `limparFiltros()` e `updatedTermoBusca()`, para que uma nova busca ou
  categoria nunca comece "no meio" da lista.
- O rodapé mostra `Mostrando X de Y eventos` com o botão `Carregar mais N` enquanto houver eventos ocultos e, ao chegar
  ao fim, `N eventos • início do histórico`. O resumo "N eventos encontrados" (com filtro ativo) continua usando o total.
- O serviço ainda agrega todos os eventos do lead (a busca textual e a ordenação por data precisam do conjunto completo);
  a paginação limita o que é renderizado e enviado ao navegador, não as consultas. Se algum lead passar de alguns milhares
  de eventos, o próximo passo é paginar dentro do serviço.

## Texto da Ajuda
`getHelpContent()` (modal do botão **Ajuda**) acompanha o layout: indicadores do cabeçalho, filtros com contagens,
feed por dia, "Carregar mais" e o botão **Retornar no WhatsApp** no cabeçalho. Os itens **Registrar nova interação** e
**Pontuação do Lead** só aparecem para quem `podeRegistrar()`, igual ao bloco na tela. Ao mudar a interface, atualize o
texto junto — o teste `test_ajuda_descreve_o_layout_atual_e_respeita_a_permissao_do_registro_rapido` falha se a Ajuda
voltar a citar o botão "direto na timeline" ou perder esses itens.

## Testes
- `tests/Feature/Customer360TimelineTest.php`: serviço (agregação, filtro, busca, métricas) e registro rápido.
- `tests/Feature/Customer360TimelineLayoutTest.php`: contrato do `tom`, agrupamento por dia, estados vazios, visibilidade
  do registro rápido por permissão, paginação do feed (lote, "Carregar mais" e reinício ao filtrar/buscar), texto da Ajuda
  e a proteção contra a volta de classes Tailwind não compiladas.
