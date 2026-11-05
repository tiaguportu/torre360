# Barra lateral do Responsável e do Aluno

A barra lateral de quem atua como `responsavel` ou `aluno` (e não é `super_admin`) não é a navegação padrão do Filament: ela é montada a cada requisição ao painel `/admin` em `app/Http/Middleware/EnsureActiveRole.php`.

## Estrutura

| Grupo | Conteúdo |
|---|---|
| Principal | Dashboard |
| Preceptoria | Agendar preceptoria |
| Um grupo por aluno | Avatar e nome do aluno; **Dados Cadastrais** (uma vez) e, para cada matrícula, **Boletim Escolar**, **Preceptorias** e **Documentos** |

O responsável vê os alunos vinculados a ele (`Pessoa::alunos()`); o aluno vê só a si mesmo.

## Quais matrículas aparecem

Só as de situação **Ativa**, **Pendente** ou **Reserva** (`EnsureActiveRole::SITUACOES_NA_BARRA`). Trancada, cancelada, concluída e evasão ficam de fora, e o aluno que não tem nenhuma das três não ganha grupo.

Antes, o grupo mostrava apenas a primeira matrícula Ativa do aluno; Pendente e Reserva não apareciam.

## Mais de uma matrícula do mesmo aluno

Os atalhos continuam num único grupo do aluno. Quando ele tem duas ou mais matrículas, o nome de cada atalho recebe o período letivo da matrícula (`Documentos · 2026`):

- situação diferente de Ativa vem entre parênteses: `Preceptorias · 2027 (Reserva)`;
- duas matrículas do mesmo período e da mesma situação (cursos diferentes) se distinguem pela turma: `Documentos · 2026 – Turma Extra`;
- com uma única matrícula os nomes não mudam (`Boletim Escolar`, `Preceptorias`, `Documentos`).

**Ordem:** a matrícula Ativa primeiro; depois, do período letivo mais recente (`data_inicio`) para o mais antigo.

**Por que rótulos e não submenus:** itens filhos (`NavigationItem::childItems`) só são desenhados quando o item pai está ativo, então os atalhos ficariam escondidos. Além disso, o Filament proíbe ícone no grupo e nos itens ao mesmo tempo; o ícone do grupo é o avatar do aluno, por isso os itens não têm ícone.

## Cuidados

- **Eager load.** `Matricula::getMissingMandatoryDocuments()` (selo vermelho de documentos pendentes) lê série, curso, documentos do curso, tipos de documento da turma e da matrícula e os documentos inseridos. A consulta da barra carrega tudo isso de uma vez (`matriculasDaBarra`). Sem isso, com 2 ou mais matrículas o lazy loading estrito (ligado nos testes ou com `DB_PREVENT_LAZY_LOADING=true`) derruba a página com `LazyLoadingViolationException`, e em produção viram N consultas por matrícula.
- **Item ativo.** Os itens da barra não marcam "ativo" (o padrão do `NavigationItem` é falso), então os atalhos de Preceptorias de matrículas diferentes, que só mudam pelo filtro da URL, não se destacam juntos.
- **Ajuda.** Os destinos (Dados Cadastrais, Boletim, Documentos, Preceptorias, Agendar) precisam ter o botão de Ajuda: ver [ajuda_botoes_sidebar.md](ajuda_botoes_sidebar.md).

## Testes

`tests/Feature/BarraLateralResponsavelMatriculasTest.php` (6 testes): situações que entram e que ficam de fora, aluno com uma matrícula (rótulos sem período), aluno com várias (rótulos, situação e ordem, um só *Dados Cadastrais*), desempate pela turma e um grupo por aluno. O teste renderiza a página inteira do painel e usa `withoutVite()` porque `public/build` não existe em todos os ambientes.

Manual do usuário: seção 2.1 do `MANUAL_USUARIO.md`.
