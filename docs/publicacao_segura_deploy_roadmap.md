# Publicação Segura (Onda 90) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Faz parte do [índice das ondas 80 a 90](ondas_80_a_88_indice.md) e usa como portão
> a [Onda 89](paridade_esquema_testes_mariadb_roadmap.md) (paridade de esquema e testes em MariaDB).

## Contexto

Hoje publicar é **apertar um botão**: a página *Atualizar Sistema* roda `git pull origin main` e `migrate --force` no servidor. Nada
separa o que acabou de ser escrito do que a escola usa: toda mudança que entra na `main` **é** a próxima publicação, e as migrations
rodam sem backup, sem aviso e sem volta. O sistema guarda dado de crianças, contratos e financeiro; o custo de um erro de publicação é
maior que o de qualquer onda de produtividade.

## O que já existe (conferido no código)

- **A página `GitPull`** (`/admin/git-pull`, fora do menu): executa `git pull origin main`, depois `optimize:clear` e `migrate --force`, e
  mostra a saída. Só o botão confere `hasRole('super_admin')`; a **página não tem `canAccess`** e abre para qualquer conta ativa (a
  família entra no painel admin).
- **A `main` é a produção:** não há tag, release, branch de homologação nem pipeline (nenhum `.github`). Várias pessoas e sessões
  publicam direto na `main`; um clique no botão leva tudo.
- **O build dos assets não faz parte da publicação:** `public/build` é ignorado pelo Git e a página não roda `npm run build`. O CSS
  compilado pode ficar velho e sem as classes das telas novas (aconteceu: o CSS da máquina de verificação era de 1º/10).
- **O ambiente de desenvolvimento aponta para a produção:** o `.env` do checkout de desenvolvimento tem `APP_ENV=local` e
  `APP_DEBUG=true` e, segundo o registro das sessões anteriores, usa o banco e as credenciais reais. Rodar `tinker`, um teste mal
  configurado ou uma migration ali **é** rodar na produção.
- **Existe** a rota de saúde `/up` do Laravel (`bootstrap/app.php`), o agendador (`routes/console.php`, dezenas de comandos), fila e cache em
  banco, e as propostas de [Saúde do Sistema (54)](saude_do_sistema_roadmap.md) (inclui o aviso de `APP_DEBUG` ligado e a data do último
  backup) e de [Segurança Reforçada (46)](seguranca_reforcada_roadmap.md) (perder a `APP_KEY` perde os dados criptografados).
- **Não existe** documento de publicação, de backup, de restauração nem de volta atrás no repositório.

## Escopo proposto

### 1. Ambientes separados
Desenvolvimento **nunca** aponta para o banco de produção. Um ambiente de **homologação** (banco próprio, de preferência uma cópia
sem dado pessoal) onde cada versão roda antes. `APP_DEBUG=false` e `APP_ENV=production` na produção, com a verificação da Onda 54.

### 2. Publicar uma versão, não a ponta da `main`
- Versões com **tag** (`v2026.10.28`), criadas só depois do portão (Pint, testes de guarda, suíte, subconjunto em MariaDB da Onda 89).
- A publicação aplica **uma tag**; a página mostra a versão atual, as versões disponíveis e o que mudou (as mensagens dos commits).
- Proteção da `main` (revisão e verificação obrigatórias) é decisão de processo: hoje as sessões automatizadas publicam direto.

### 3. Um comando de publicação com passos fixos (`sistema:publicar`)
1. confere o portão e o espaço em disco; 2. **backup do banco** (com a data e a versão, antes de tudo); 3. entra em **manutenção** com
a página de aviso; 4. busca a tag; 5. `composer install --no-dev` e **build dos assets**; 6. lista as **migrations pendentes** e para se alguma
é destrutiva (`dropColumn`, `drop`, renomeação) sem confirmação explícita; 7. `migrate --force`; 8. `optimize`, `queue:restart` e
reinício do agendador; 9. **verificação** (`/up` e um roteiro curto: login, boletim, fatura, Minha Mesa); 10. sai da manutenção e avisa o
`super_admin`. Qualquer falha dispara a **volta**: reaplica a versão anterior e restaura o banco do passo 2 quando a migration já rodou.

### 4. A página *Atualizar Sistema* reescrita
`canAccess` só para `super_admin` (e, quando existir, com o segundo fator da Onda 46); mostra versão atual, versão nova, migrations
pendentes com o aviso de destrutivas e o **resumo do que muda**; confirmação digitada; o resultado e quem publicou ficam no log de
atividades. Substitui o `git pull origin main`.

### 5. Backup e restauração de verdade
Backup antes de cada publicação e diário, com **retenção** definida, cópia **fora do servidor** e `APP_KEY` guardada à parte (cofre). **Teste
de restauração** num banco descartável, a cada trimestre, com o resultado registrado. O comando de backup grava a data para a Onda 54 exibir.
O tempo que o backup guarda dado eliminado entra na política da [Onda 79](guarda_descarte_documentos_roadmap.md).

### 6. Migrations que não derrubam o sistema
Regra de **expandir e contrair** em duas publicações para mudanças que removem ou renomeiam coluna: primeiro o código passa a funcionar com
as duas formas, depois a coluna sai. O guarda da Onda 89 impede coluna prometida e inexistente; este item impede coluna removida enquanto o
código ainda a usa.

### 7. Janela e aviso
Evitar publicar em horário de uso intenso (início da aula, vencimentos, período de matrícula); aviso prévio à equipe; página de manutenção
com a previsão.

## Dependências e riscos

- **Descobrir como o servidor é operado.** A página existe, provavelmente, porque o acesso ao servidor é limitado (sem SSH, ou
  hospedagem compartilhada, ou Windows com XAMPP). O desenho precisa funcionar **por onde a escola consegue operar**: se só há o
  navegador, o comando de publicação roda por ele (com limite de tempo e em fila), nunca como hoje.
- **A volta tem limite:** restaurar o banco apaga o que entrou desde o backup (matrícula online, pagamento). Por isso a janela e o
  backup imediatamente antes; e por isso a preferência por migrations reversíveis.
- **Mudar o fluxo de publicação muda o dia a dia da equipe e das sessões automatizadas:** a `main` deixa de ser produção, e quem hoje
  publica direto passa a abrir uma versão. É o ponto de maior atrito e precisa de decisão do dono do produto.
- **Homologação com dado real é risco de LGPD:** usar dado fictício ou anonimizado.
- O custo de manter dois ambientes e o CI é pequeno perto de uma publicação que derruba a matrícula online.

## Fusão sugerida

**Engenharia de entrega (90 + 89 + 54).** [Esta onda](publicacao_segura_deploy_roadmap.md) usa o portão da [89](paridade_esquema_testes_mariadb_roadmap.md)
e depende do aviso de saúde da [54](saude_do_sistema_roadmap.md). A ordem que reduz risco mais rápido: separar o `.env` de desenvolvimento
da produção, fechar o `canAccess` da página, fazer o backup antes de migrar e só depois tags e ambiente de homologação.
