# Modal de Ajuda — Design com emojis e imagens

O botão **Ajuda** (ícone ❓ cinza no cabeçalho das telas) abre um modal redesenhado:
hero com emoji + título + resumo, vídeo tutorial opcional, seções em cards com emoji,
passos numerados, dicas 💡 e alertas ⚠️, e imagens ilustrativas opcionais.

## Peças
| Arquivo | Papel |
|---|---|
| `resources/views/filament/components/help-content.blade.php` | Visual do modal (CSS escopado `.help-modal`, claro/escuro). Também estiliza o HTML legado (`h3`, `ul`, `strong`, `blockquote`) — as telas antigas ganham o novo visual sem edição. |
| `app/Support/HelpContent.php` | Builder fluente que gera o HTML (texto sempre escapado). |
| `app/Filament/Concerns/HasAjudaAction.php` | Trait que devolve a `Action` `ajuda` pronta (modal `3xl`, vídeo por `chave_pagina`). |

## Uso
```php
use HasAjudaAction;

protected function getHeaderActions(): array
{
    return [CreateAction::make(), $this->ajudaAction('Gestão de Cursos', $this->getHelpContent(), 'chave-video-opcional')];
}

private function getHelpContent(): HelpContent
{
    $user = auth()->user();

    return HelpContent::make('🎓', 'Gestão de Cursos', 'Resumo curto da tela.')
        ->secao('🎯 O que você pode fazer?', [
            ['📋', 'Listagem', 'Descrição.'],
            $user->can('Create:Curso') ? ['🆕', 'Novo Curso', 'Descrição.'] : null, // nulo = omitido
        ])
        ->passos('🚀 Passo a passo', ['Clique em Novo', 'Preencha', 'Salve'])
        ->dica('Texto da dica')->alerta('Texto do alerta')
        ->imagem('images/ajuda/cursos.png', 'Legenda'); // opcional, relativo a public/
}
```
Métodos: `secao`, `passos`, `dica`, `alerta`, `imagem`, `texto`. Itens `[emoji, título, texto]` ou `[título, texto]`.

## Convenção de emojis por grupo
CRM 📣 · Secretaria 🗂️ · Acadêmico 🎓 · Avaliações 📝 · Currículo 📚 · Preceptoria 🤝 · Calendário 📅 · Financeiro 💰 · Operacional 🛠️ · Configurações ⚙️ · Sistema 🔐 · Portal 👨‍👩‍👧.

Seções: 🎯 o que fazer · 🚀 passo a passo · 🔒 permissões · 💡 dica · ⚠️ atenção.

Testes: `tests/Feature/AjudaModalTest.php`. Checklist de cobertura: `docs/ajuda_botoes_sidebar.md`.

## Atalho para cadastros simples
`ajudaCadastro()` (no mesmo trait) monta a ajuda padrão de telas de cadastro: Listagem + Novo registro + Editar, estes dois só aparecem com `Create:<Modelo>` / `Update:<Modelo>`.
```php
$this->ajudaCadastro('🏦', 'Bancos', 'Resumo.', 'Banco', 'Texto da listagem.', 'Texto do novo.', 'Texto do editar.',
    extras: [['🌳', 'Item extra', 'Texto']], dica: 'Dica opcional');
```
Usado nas tabelas auxiliares do grupo Configurações (fase 6).

## Telas legadas migradas
Telas antigas (HTML montado à mão) ganham o visual novo automaticamente, mas podem ser migradas para `HelpContent` para ter hero com emoji e cartões. Já migradas: Cursos e Matrículas (`ListMatriculas`, que também passou a explicar as abas de situação).
