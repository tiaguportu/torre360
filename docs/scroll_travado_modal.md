# Scroll vertical que some no painel admin

## Sintoma
A barra de rolagem vertical da página desaparece às vezes e só volta com F5.

## Causa
Modais e slide-overs do Filament (`resources/views/vendor/filament/components/modal/index.blade.php`)
usam `x-trap.noscroll`, que aplica `overflow: hidden` no `<html>` enquanto o modal está aberto.
Se o modal for removido durante um re-render/navegação do Livewire, o Alpine não libera o
travamento e o scroll fica bloqueado.

## Solução
Script de segurança no render hook `HEAD_END` em `app/Providers/Filament/AdminPanelProvider.php`:
quando `<html>` ou `<body>` está com `overflow: hidden` e não existe nenhum `.fi-modal-open`,
o estilo é limpo. Dispara em `livewire:navigated` e após qualquer mudança de `style` no `<html>`.

## Diagnóstico se voltar a ocorrer
No DevTools, inspecionar o atributo `style` de `<html>` e `<body>`; se estiverem limpos,
procurar um container interno com `overflow-hidden` cortando a página.
