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

## Melhorias aplicadas em 2026-08-23

- **Anti-spam no formulário de leads**: `POST /solicitar-acesso` não tinha nenhuma
  proteção contra bots. Adicionado:
  - `throttle:5,1` na rota (`routes/web.php`) — máximo 5 envios por IP a cada minuto.
  - reCAPTCHA v3, reaproveitando o mesmo padrão já usado em
    `CaptacaoInteressadoController` (`/quero-matricular`): `LandingPageController::verificarRecaptcha()`
    valida o token contra a API do Google e bloqueia envios com score baixo. Só ativa
    se `RECAPTCHA_SITE_KEY`/`RECAPTCHA_SECRET_KEY` estiverem no `.env` (já estão,
    então a proteção já está ativa em produção); sem as chaves, o form continua
    funcionando normalmente (fail-open, mesmo comportamento do form de captação).

- **Contatos clicáveis**: telefone e e-mail da seção de contato viraram links
  `tel:`/`mailto:`.

- **Imagens otimizadas**: `hero.png`/`mobile.png` (PNG grandes) agora têm `<picture>`
  com AVIF e WebP gerados via GD (`hero.avif` 32 KB / `hero.webp` 58 KB, vs. 541 KB do
  PNG original; mesma ordem de grandeza para `mobile.*`), com o PNG como fallback.
  A imagem do hero (candidata a LCP) ganhou `fetchpriority="high"`.

- **Acessibilidade do formulário**: `label for`/`input id` associados corretamente,
  e o bloco de erros de validação ganhou `role="alert"` + `aria-live="assertive"`
  para ser anunciado por leitores de tela.

- **SEO adicional**: `<link rel="icon">` (favicon já existia em `public/` mas não
  era referenciado) e `og:locale` = `pt_BR`.

- **Analytics opcional**: snippet do Google Analytics 4 (`gtag.js`) adicionado, mas
  só é renderizado se `GOOGLE_ANALYTICS_ID` estiver definido no `.env`
  (`config/services.php` → `services.google_analytics.measurement_id`). Sem a env,
  nada é carregado — não há ID de tracking real configurado ainda.

## Pendências / decisões que ficaram para o usuário

- Telefone `(11) 99999-9999` e e-mail `contato@escolatorredemarfim.com.br` na seção
  de contato parecem placeholders (domínio diferente da marca Torre360) — os links
  `tel:`/`mailto:` foram adicionados mas os dados em si não foram alterados por não
  termos os valores reais.
- Para ativar o Google Analytics, defina `GOOGLE_ANALYTICS_ID=G-XXXXXXX` no `.env`.
