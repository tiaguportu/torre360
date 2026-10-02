# Biblioteca Escolar (Onda 11) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

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
