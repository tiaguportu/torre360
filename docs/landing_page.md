# Landing Page (`/`)

View: `resources/views/landing.blade.php`
Controller: `App\Http\Controllers\LandingPageController`

## Melhorias aplicadas em 2026-08-22

- **CSS via build do Vite, não CDN**: a página carregava Tailwind em runtime pelo
  `unpkg.com/@tailwindcss/browser@4`. Isso causava flash de conteúdo sem estilo,
  dependência de um CDN externo e CSS não otimizado. Trocado por
  `@vite(['resources/css/app.css', 'resources/js/app.js'])`, mesmo padrão já usado em
  `welcome.blade.php`. `resources/css/app.css` já cobre `*.blade.php` via `@source`,
  então as classes (inclusive arbitrárias como `bg-[#ddaf00]`) continuam funcionando.

- **Bugs de classe corrigidos**: `bg-gold` (badge do hero) não existia como token
  Tailwind/CSS e não pintava nada — trocado por `bg-[#ddaf00]`. A classe `float` na
  imagem do hero não corresponde a nenhuma utility do Tailwind (as utilities de float
  são `float-left`/`float-right`/`float-none`) — removida por ser um no-op.

- **Menu mobile**: a navbar escondia os links (`Solução`, `Mobile`, `Contato`) em
  telas pequenas (`hidden md:flex`) sem nenhum substituto, deixando o menu
  inacessível no mobile. Adicionado botão hambúrguer + painel dropdown com JS
  vanilla (o projeto não usa Alpine.js, então evitou-se adicionar essa dependência
  só para isto).

- **Feedback de erro no formulário de contato**: `POST /solicitar-acesso` já
  validava os campos, mas a view só exibia `session('success')` — em caso de erro
  de validação o usuário não via nenhum retorno. Adicionado bloco `@if($errors->any())`
  listando as mensagens, e `old()` nos inputs para preservar o que foi digitado.

- **CLS/perf das imagens**: `hero.png` e `mobile.png` (1024×1024) não tinham
  `width`/`height`, causando layout shift ao carregar. Adicionados os atributos,
  e `loading="lazy"` na imagem `mobile.png` (abaixo da dobra).

- **Âncoras atrás da navbar fixa**: `scroll-margin-top` adicionado nas `section`
  para o scroll suave (`#solucao`, `#mobile`, `#contato`) não esconder o topo do
  conteúdo atrás da navbar fixa.

- **SEO**: `<link rel="canonical">` e JSON-LD `schema.org/SoftwareApplication`.
  Atenção: a diretiva Blade `@context` (Laravel 11+) colide com a chave JSON-LD
  `"@context"` — por isso o template usa `@@context`/`@@type` (escape do `@` do
  Blade) para emitir o `@` literal no JSON.

## Pendências / decisões que ficaram para o usuário

- Telefone `(11) 99999-9999` e e-mail `contato@escolatorredemarfim.com.br` na seção
  de contato parecem placeholders (domínio diferente da marca Torre360) — não foram
  alterados por não termos os dados reais.
- As imagens `hero.png` (541 KB) e `mobile.png` (634 KB) ainda não foram otimizadas/
  convertidas para WebP.
