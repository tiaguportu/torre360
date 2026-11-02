# Acessibilidade do Portal e das Páginas Públicas (Onda 96) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda. Faz parte do [índice das ondas 92 a 106](ondas_92_a_106_indice.md).

## Contexto

O Portal da Família e as páginas públicas (matrícula online, "quero matricular", pesquisa de visita, acordo, validação de documento) são a vitrine da escola e o
único contato de muitas famílias com o sistema. Uma escola atende, por definição, também famílias e alunos com deficiência; um portal que não funciona com leitor de
tela ou com zoom não atende.

## O que já existe (conferido no código)

- Portal em Filament (`PortalPanelProvider`), tema próprio (cor principal `#243468`, fonte Inter) e atributo `lang` derivado do idioma da aplicação
  na camada base. `aria-` e `role` aparecem em poucas telas (Painel da Direção, Simulador, visualização de contrato, editor de crachá).
- A escola cadastra **recursos de acessibilidade do aluno** (`RecursoAcessibilidade`, `CategoriaRecursoAcessibilidade`) e necessidades educacionais especiais para o Censo,
  mas isso é sobre o **aluno**, não sobre a **interface**.
- Política de segurança de conteúdo com relatório (`/csp-report`).
- **Não existe** o plugin de Libras (VLibras), controle de tamanho de fonte ou de contraste, link de pular para o conteúdo nem declaração de acessibilidade (busca em `app`, `resources` e `config`).

## Escopo proposto

### Fatia 1 — rápida
- **VLibras** (tradutor em Libras do governo federal) no Portal e nas páginas públicas. Exige liberar o domínio dele na política de segurança de conteúdo e carregar o
  script só onde ligado.
- Barra de acessibilidade: **aumentar e diminuir a fonte**, **alto contraste** e **reduzir animações**, guardados por usuário (e no navegador para quem não está logado).
- **Link "ir para o conteúdo"**, foco visível e ordem de tabulação nas telas públicas.

### Fatia 2 — auditoria
- Auditoria **WCAG 2.1 nível AA** com ferramenta automática (axe ou Lighthouse) e conferência manual com leitor de tela (NVDA) nas telas que a família mais usa:
  login, Financeiro, Notas, Frequência, Documentos, Matrícula online. Conferir primeiro o contraste das cores do tema (a cor de alerta `#DCA814` sobre fundo claro é
  candidata a falhar).
- Campos com rótulo, erros de formulário anunciados, tabelas com cabeçalho, imagens com texto alternativo e botões só de ícone com nome.
- **Declaração de acessibilidade** no rodapé, com um canal para relatar barreiras.

### Fatia 3 — documentos
- PDFs que a família baixa (boletim, contrato, informe) não são acessíveis a leitor de tela (geração por DomPDF). Registrar o limite na declaração e oferecer
  a versão em página quando possível.

## Base legal

- **Lei Brasileira de Inclusão (Lei nº 13.146/2015), art. 63:** acessibilidade nos sítios de empresas com sede ou representação comercial no País (confirmar a
  leitura com o jurídico). **WCAG 2.1 AA** é a referência técnica usual.

## Dependências e riscos

- Script de terceiro (VLibras) é superfície nova: aceitar só a origem oficial e conferir o comportamento sem JavaScript.
- Filament traz boa base de acessibilidade, mas telas próprias (Blade) e gráficos podem falhar: a auditoria diz onde.
- Acessibilidade não termina: incluir a verificação no portão de qualidade da [Onda 89](paridade_esquema_testes_mariadb_roadmap.md) para as telas públicas.

## Esforço

Pequeno (fatia 1) a médio (fatia 2).

## Pacote

Independente. Ver a [proposta de unificação](unificacao_de_ondas_proposta.md).
