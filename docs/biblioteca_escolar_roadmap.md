# Biblioteca Escolar (Onda 11)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda das telas (**Livros** e **Empréstimos**, grupo
> **Biblioteca** do menu).

## Contexto

Módulo clássico de sistemas de gestão escolar completos (acervo de livros e controle de
empréstimo/devolução), não coberto pela lista de marketing do Sponte nem pelas Ondas 1-8.
Confirmado: não existe hoje nenhum model, migration ou tela relacionada a livros/acervo no
Torre360.

## Escopo

### Modelos novos
- `Livro`: título, autor, ISBN, categoria/assunto, editora, quantidade de exemplares
  (total e disponível).
- `Emprestimo`: `livro_id`, `matricula_id` (aluno que pegou emprestado), data do
  empréstimo, data prevista de devolução, data real de devolução (nullable), status
  (emprestado/devolvido/atrasado).

### Telas
- Resource de catálogo (`Livro`) na secretaria/biblioteca.
- Ação "Registrar Empréstimo" / "Registrar Devolução".
- Relatório de atrasos (livros não devolvidos após a data prevista).
- Opcional: página no Portal do Aluno/Família mostrando os empréstimos ativos do aluno.

## Dependências já satisfeitas

- Nenhuma onda anterior é pré-requisito técnico — pode ser implementada de forma
  independente a qualquer momento. `Matricula` já existe como referência para "quem pegou
  emprestado".

## Como foi implementado

- `Livro` (`app/Models/Livro.php`) e `Emprestimo` (`app/Models/Emprestimo.php`), Resources
  em `/admin/livros` e `/admin/emprestimos` (grupo **Biblioteca**).
- Ao registrar empréstimo (`Emprestimo::emprestar()`, usado pela tela de Empréstimos e pelo
  Balcão de Circulação), o exemplar é reservado de forma atômica (decremento condicional
  `quantidade_disponivel > 0` na mesma transação que cria o empréstimo); só aparecem no
  seletor livros com exemplar disponível. Ao devolver (`Emprestimo::registrarDevolucao()`,
  idempotente), o exemplar volta ao acervo sem passar do total e o status muda para Devolvido.
  `quantidade_disponivel` é derivada (total − empréstimos em aberto) e não é digitada;
  `biblioteca:reconciliar-disponibilidade` confere/corrige o saldo.
- Comando `biblioteca:atualizar-emprestimos-atrasados` (agendado diariamente às 07h, mesmo
  princípio do `financeiro:atualizar-contas-pagar-atrasadas`) marca como Atrasado qualquer
  empréstimo ainda não devolvido com devolução prevista vencida.
- Não é dado sensível: permissões liberadas para `secretaria`, `coordenador`, `professor`,
  além de `admin`/`super_admin`.
- Listagem de livros em **lista ou grade** (`LivrosTable::configure()`): a grade usa
  `Stack`/`Split` com `contentGrid()` e paginação de 12 em 12; a lista mantém as colunas
  originais. A preferência fica na sessão (`LivrosTable::SESSION_VISUALIZACAO`, valores
  `lista`/`grade`). O Filament monta a tabela no início da requisição, antes de qualquer
  ação rodar, então os botões do cabeçalho (`ListLivros::visualizacaoAction()`) gravam a
  sessão e redirecionam para a URL anterior (que preserva busca, filtros e ordenação); não
  dá para trocar as colunas "no lugar".
- Testes em `tests/Feature/BibliotecaTest.php` (inclui a alternância lista/grade).
- Página do portal do aluno para ver os próprios empréstimos (item opcional do escopo
  original) não foi incluída nesta entrega — pode ser um incremento futuro.

## Busca por ISBN e capas (`LivroLookupService`, `LivroCapaService`)

Cadastro de livro com busca por ISBN (botão do cabeçalho e lupa do campo ISBN), no PHP do
painel (`max_execution_time` de 30 s).

- **Fontes e prioridade:** Open Library, BrasilAPI e Google Books são consultadas **em
  paralelo** (`Http::pool`, 5 s cada); para cada campo vale o primeiro valor preenchido nessa
  ordem. Se o Google não achar pelo ISBN-13, tenta o ISBN-10. A chave do Google fica em
  `config('services.google_books.key')` (`GOOGLE_BOOKS_API_KEY`) — nunca `env()` direto,
  que devolve `null` com a configuração em cache.
- **Orçamento de tempo:** ~20 s no total; fases opcionais (segundo pedido ao Google, Amazon,
  novas tentativas de capa) são puladas quando o orçamento acaba. Medido: 1–4 s por busca.
- **Cache por ISBN (`Cache`, chave `livros:isbn:v1:{isbn}`):** encontrado 7 dias; não encontrado
  1 h; resultado parcial (alguma fonte deu 5xx/429/timeout) 10 min; "sem capa" 15 min. Falha de
  rede **nunca** é cacheada como "não existe" e a mensagem ao usuário muda para "serviço
  indisponível".
- **Amazon desligada por padrão** (`services.livros.amazon_habilitado`,
  `LIVROS_AMAZON_HABILITADO=true` para ligar): a raspagem do HTML (`consultarAmazon`) e o CDN
  de capas são não oficiais e contrários aos termos de uso. Ligar é decisão da escola.
- **Capa:** candidatas em ordem — URL da fonte, Open Library Covers
  (`/b/isbn/{isbn}-L.jpg?default=false`) e, se habilitada, Amazon. A primeira que passar na
  validação vence: tipo detectado pelo **conteúdo** (`getimagesizefromstring`; JPEG/PNG/WebP, a
  extensão vem daí e não do `Content-Type`), até 3 MB (`on_headers` aborta por `Content-Length`
  antes do corpo), 60–8000 px por lado (recusa placeholders de 1 pixel).
- **SSRF:** a URL da capa vem de dados de terceiros. `urlPublica()` aceita só http(s), portas
  80/443 e host cujos IPs resolvidos sejam públicos (`FILTER_FLAG_NO_PRIV_RANGE |
  NO_RES_RANGE`); redirecionamentos são revalidados (`on_redirect`, máx. 3). O DNS pode ser
  desligado em testes (`services.livros.validar_dns`).
- **Ciclo de vida do arquivo (`LivroCapaService`):** a capa baixada vai para
  `livros/capas/pendentes/{isbn}.{ext}` (disco `public`), reaproveitada em cliques repetidos
  (a data é renovada se tiver mais de 24 h). No `saving` do `Livro`, uma capa pendente é
  **copiada** para `livros/capas/{isbn}_{rand}.{ext}` (cada livro tem o seu arquivo; o pendente
  fica para outros formulários abertos). Pendente que já foi limpo zera a referência. No
  `updated` (capa trocada) e no `deleted`, o arquivo antigo é apagado se for local, estiver em
  `livros/capas/`, não for pendente e nenhum outro livro o referenciar.
  `biblioteca:limpar-capas-pendentes {--horas=48}` (diário, 03:30) apaga pendentes antigos.
- **Testes:** `tests/Feature/LivroIsbnLookupTest.php` (fluxo e formulário) e
  `tests/Feature/LivroLookupCapaTest.php` (cache, paralelismo, Amazon opt-in, validação de
  imagem, SSRF, ciclo de vida da capa e comando de limpeza). Sem rede: `Http::preventStrayRequests()`.
- **Órfãs antigas (`biblioteca:limpar-capas-orfas`):** arquivos gravados antes desta mudança
  (um por clique) são limpos manualmente por quem opera o servidor. Padrão = só lista; `--apagar`
  pede confirmação (`--force` dispensa). `LivroCapaService::diagnosticarOrfas()` compara os arquivos
  diretos de `livros/capas/` com `livros.capa` (normalizando barra inicial; URL remota não conta) e só
  considera órfão o que não é referenciado **e** tem mais de `--dias` (padrão 7, mínimo 1). Nunca
  entram `pendentes/`, subpastas e ocultos; `apagarOrfas()` reconfere cada caminho no momento de
  apagar. **Salvaguarda de ambiente:** se nenhuma capa em uso no banco existe no disco, recusa (disco
  e banco de máquinas diferentes — o `.env` local aponta para o banco de produção); sem capas locais
  no banco, exige `--force`. O cabeçalho imprime disco e banco comparados. Não é agendado.
  Testes: `tests/Feature/LivroCapasOrfasTest.php`.
- **Por que novas órfãs não surgem mais:** a busca grava só em `pendentes/` (1 arquivo por ISBN); o
  livro só ganha arquivo próprio ao ser salvo; trocar a capa/excluir o livro apaga o arquivo antigo;
  pendentes viram lixo só se o formulário for abandonado e são removidos em 48 h (exige o
  `schedule:run` ativo). Exclusão em massa por SQL ou `Livro::query()->delete()` não dispara os
  eventos do modelo e pode deixar arquivo — é para esse caso (e para o legado) que o comando existe.
