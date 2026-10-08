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
- Ao registrar empréstimo, `quantidade_disponivel` do livro é decrementada
  (`CreateEmprestimo::handleRecordCreation()`); só aparecem no seletor livros com
  exemplar disponível. Ao devolver (`Emprestimo::registrarDevolucao()`), o exemplar volta
  ao acervo e o status muda para Devolvido.
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
